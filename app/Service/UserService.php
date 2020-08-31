<?php


namespace App\Service;


use App\Constants\ErrorCode;
use App\Constants\MemoryTable;
use App\Exception\BusinessException;
use App\Model\FriendRelation;
use App\Model\Group;
use App\Model\User;
use App\Model\UserApplication;
use App\Task\UserTask;
use Hyperf\Memory\TableManager;

/**
 * Class UserService
 * @package App\Service
 */
class UserService

{

    /**
     * 查询用户不存在抛出异常
     * @param int $uid
     */
    public function findUserInfoById (int $uid)
    {
        return User::query()->whereNull('deleted_at')->where(['id' => $uid])->first() ?: [];

    }


    /**
     * 注册用户
     * @param string $email
     * @param string $password
     * @return bool
     */
    public function register (string $email, string $password): bool
    {
        $insert = [
            'email' => $email,
            'username' => $email,
            'password' => password_hash($password, CRYPT_BLOWFISH),
            'sign' => '',
            'status' => User::STATUS_OFFLINE,
            'avatar' => 'https://s.gravatar.com/avatar/' . md5(strtolower(trim($email))),
        ];
        return User::query()->insert($insert);

    }

    /**
     * 登录
     * @param $email
     * @param $password
     * @return \Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object
     */
    public function attempt ($email, $password)
    {
        $user = User::query()->where('email', '=', $email)->first();

        if (!$user) {
            throw new BusinessException(ErrorCode::USER_NOT_FOUND);
        }

        if (!password_verify($password, $user['password'])) {
            throw new BusinessException(ErrorCode::USER_PASSWORD_ERROR);
        }
        return $user;

    }

    /**
     * 获取用户资料
     * @param $uid
     * @return array
     */
    public function getUserProfile ($uid)
    {
        $user = User::query()->where('id', $uid)->first();
        if (!$user) return [];
        return [
            'username' => $user->username,
            'id' => $user->id,
            'status' => User::STATUS_TEXT[User::STATUS_ONLINE],
            'sign' => $user->sign,
            'avatar' => $user->avatar,
        ];
    }

    /**
     * 未通过的好友申请
     * @param int $uid
     * @return int
     */
    public function getUnreadApplyCount (int $uid)
    {

        return UserApplication::query()
            ->whereNull('deleted_at')
            ->where('read_state', '=', UserApplication::UN_READ)
            ->where('receiver_id', '=', $uid)
            ->count("id");
    }


    /**
     * @param int    $uid
     * @param string $username
     * @param string $avatar
     */
    public function updateUserProfile (int $uid, ?string $username, ?string $avatar)
    {
        $update = [];
        if ($username) $update['username'] = $username;
        if ($avatar) $update['avatar'] = $avatar;
        if (!$update) return false;
        $this->changeUserInfoById($uid, $update);
        return true;
    }


    /**
     * @param int   $userId
     * @param array $data
     * @return int
     */
    public function changeUserInfoById (int $userId, array $data)
    {
        return User::query()
            ->whereNull('deleted_at')
            ->where(['id' => $userId])
            ->update($data);
    }

    /**
     * @param int $uid
     * @param int $page
     * @param int $size
     * @return mixed
     */
    public function getApplication (int $uid, int $page = 1, int $size = 10)
    {
        $messages = UserApplication::query()
            ->where('uid', $uid)
            ->whereNull('deleted_at')
            ->orWhere('receiver_id', $uid)
            ->forPage($page, $size)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();

        foreach ($messages as $key => $value) {
            if ($uid != $value['uid']) {
                $appIds[] = $value['id'];
            }
            if ($value['application_type'] == UserApplication::APPLICATION_TYPE_GROUP) {
                $groupIds[] = $value['group_id'];
            }

            $userIds[] = $value['uid'];
            $userIds[] = $value['receiver_id'];

        }

        [$appIds, $groupIds, $userIds] = $this->getQueryIds($uid, $messages);
        $groupInfos = $this->getGroupMappingInfo($groupIds);
        $userInfos = $this->getUserMappingInfo($userIds);
        $this->changeAppReadStateByIds($appIds, $uid, UserApplication::ALREADY_READ);
        $application['list'] = $this->filterMessages($uid, $messages, $groupInfos, $userInfos);
        $application['count'] = count($messages);

        return $application;
    }


    /**
     * @param $messages
     * @param $groupInfos
     * @param $userInfos
     */
    private function filterMessages ($uid, $messages, $groupInfos, $userInfos)
    {
        foreach ($messages as &$message) {
            $message['application_role'] = $this->getApplicationRole($uid, $message);
            $message['user_application_id'] = $message['id'];
            $message['user_id'] = $message['uid'];
            if ($message['application_type'] == UserApplication::APPLICATION_TYPE_GROUP) {
                $message['group_name'] = $groupInfos[$message['group_id']]['group_name'] ?? '';
                $message['group_avatar'] = $groupInfos[$message['group_id']]['avatar'] ?? '';
            }
            $message['user_name'] = $userInfos[$message['uid']]['username'] ?? '';
            $message['user_avatar'] = $userInfos[$message['uid']]['avatar'] ?? '';
            $message['receiver_name'] = $userInfos[$message['receiver_id']]['username'] ?? '';
            $message['receiver_avatar'] = $userInfos[$message['receiver_id']]['avatar'] ?? '';
            unset($message['id']);
            unset($message['uid']);
        }
        return $messages;
    }

    /**
     * @param $uid
     * @param $message
     */
    private function getApplicationRole ($uid, $message): string
    {
        //如果不是本人操作，是接收者
        if ($uid != $message['uid']) {
            return UserApplication::APPLICATION_RECEIVER_USER;
        }

        //应用状态不是新创建，是系统消息
        if ($message['application_status'] != UserApplication::APPLICATION_STATUS_CREATE) {
            return UserApplication::APPLICATION_SYSTEM;
        }

        return UserApplication::APPLICATION_CREATE_USER;

    }

    /**
     * @param $uid
     * @param $messages
     * @return array
     */
    private function getQueryIds ($uid, $messages): array
    {
        foreach ($messages as $key => $value) {
            if ($uid != $value['uid']) {
                $appIds[] = $value['id'];
            }
            if ($value['application_type'] == UserApplication::APPLICATION_TYPE_GROUP) {
                $groupIds[] = $value['group_id'];
            }

            $userIds[] = $value['uid'];
            $userIds[] = $value['receiver_id'];

        }
        $userIds = collect($userIds ?? [])->unique()->all();
        return [$appIds ?? [], $groupIds ?? [], $userIds];
    }

    /**
     * 获得用户的信息（k-v）
     * @param array $userIds
     * @return array
     */
    private function getUserMappingInfo (array $userIds)
    {
        return User::query()->whereNull('deleted_at')
            ->whereIn('id', $userIds)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item['id'] => $item];
            })
            ->toArray();
    }

    /**
     * 获得分组的信息（k-v）
     * @param array $userIds
     * @return array
     */
    private function getGroupMappingInfo (array $groupIds)
    {
        return Group::query()->whereNull('deleted_at')
            ->whereIn('id', $groupIds)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item['id'] => $item];
            })
            ->toArray();
    }

    /**
     * @param array $ids
     * @param int   $receiver_id
     * @param int   $readState
     * @return void
     */
    public function changeAppReadStateByIds (array $ids, int $receiver_id, int $readState)
    {
        if (!$ids) return;
        UserApplication::query()->whereNull('deleted_at')
            ->where('receiver_id', '=', $receiver_id)
            ->whereIn('id', $ids)
            ->update([
                'read_state' => $readState
            ]);
    }

    /**
     * 创建通知消息
     * @param int    $userId
     * @param int    $receiverId
     * @param int    $groupId
     * @param string $applicationType
     * @param string $applicationReason
     * @param int    $applicationStatus
     * @param int    $readState
     */
    public function createUserApplication (
        int $userId,
        int $receiverId,
        int $groupId,
        string $applicationType,
        string $applicationReason,
        int $applicationStatus = UserApplication::APPLICATION_STATUS_CREATE,
        int $readState = UserApplication::UN_READ
    )
    {
        return UserApplication::query()->insertGetId([
            'uid' => $userId,
            'receiver_id' => $receiverId,
            'group_id' => $groupId,
            'application_type' => $applicationType,
            'application_status' => $applicationStatus,
            'application_reason' => $applicationReason,
            'read_state' => $readState
        ]);
    }


    /**
     * 上线修改用户状态
     * @param int $userId
     * @param int $status
     * @return array
     */
    public function setUserStatus (int $userId, int $status = User::STATUS_ONLINE)
    {
        $this->changeUserInfoById($userId, [
            'status' => $status
        ]);

        $result = [
            'user_id' => $userId,
            'status' => FriendRelation::STATUS_TEXT[$status]
        ];
        $task = app()->get(UserTask::class);
        $task->setUserStatus($this->getOnlineFriends($userId), $result);

        return $result;
    }

    /**
     * @param int $userId
     * @return array
     */
    private function getOnlineFriends (int $userId)
    {
        $friendIds = make(FriendRelation::class)->getFriendIds($userId);

        $onlineFds = collect([]);
        collect($friendIds)->map(function ($item, $key) use ($onlineFds) {
            $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$item, 'fd');
            if ($fd) return $onlineFds->push($item);

        });
        return $onlineFds->toArray();
    }

}