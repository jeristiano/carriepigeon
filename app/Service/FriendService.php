<?php


namespace App\Service;


use App\Component\Log\Log;
use App\Constants\ErrorCode;
use App\Constants\MemoryTable;
use App\Exception\BusinessException;
use App\Model\FriendChatHistory;
use App\Model\FriendGroup;
use App\Model\FriendRelation;
use App\Model\GroupRelation;
use App\Model\User;
use App\Model\UserApplication;
use App\Task\FriendTask;
use App\Task\UserTask;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\Memory\TableManager;

/**
 * Class FriendService
 * @package App\Service
 */
class FriendService
{
    /**
     * 获得用户的朋友列表
     * @param int $uid
     * @return array
     */
    public static function getFriend (int $uid): array
    {
        $result = FriendGroup::query()
            ->where('uid', $uid)
            ->with(['group.user' => function ($query) {
                $query->whereNull('deleted_at');
            }])->get()
            ->toArray();

        if (!$result) return [];

        return collect($result)->map(function ($item, $k) {
            $friend['list'] = self::getGroupArrayMap($item['group']);
            $friend['groupname'] = $item['friend_group_name'];
            $friend['id'] = $item['id'];
            return $friend;
        })->toArray();

    }

    /**
     * @param $groups
     * @return array
     */
    private static function getGroupArrayMap ($groups): array
    {
        return collect($groups)->map(function ($item) {
            $resp['id'] = $item['id'];
            $resp['username'] = $item['user']['username'];
            $resp['avatar'] = $item['user']['avatar'];
            $resp['sign'] = $item['user']['sign'];
            $resp['status'] = FriendRelation::STATUS_TEXT[$item['user']['status']];
            return $resp;
        })->toArray();

    }

    /**
     * 获取用户加入的群组
     * @param int $uid
     * @return array
     */
    public static function getGroup (int $uid): array
    {
        $groups = GroupRelation::query()
            ->with(['group' => function ($query) {
                $query->whereNull('deleted_at');
            }])->where('uid', $uid)
            ->get()
            ->toArray();
        if (!$groups) return [];

        return collect($groups)->map(function ($item, $key) {
            return [
                'groupname' => $item['group']['group_name'],
                'id' => $item['group']['id'],
                'avatar' => $item['group']['avatar']
            ];
        })->toArray();
    }


    /**
     * 朋友推荐
     * @param int $uid
     * @param int $size
     */
    public static function getRecommendedFriend (int $uid, $size = 20)
    {
        $friendIds = make(FriendRelation::class)->getFriendIds($uid);
        $friendIds[] = $uid;
        return User::query()
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->whereNotIn('id', $friendIds)
            ->limit($size)
            ->select(['id', 'status', 'avatar', 'username'])
            ->get();

    }

    /**
     * @param $uid
     * @param $groupName
     * @return \App\Model\FriendGroup|\Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object
     */
    public static function createFriendGroup ($uid, $groupName)
    {
        $frGroupId = FriendGroup::query()->insertGetId([
            'uid' => $uid,
            'friend_group_name' => $groupName
        ]);
        return self::findFriendGroupById($frGroupId);

    }

    /**
     * @param int $friendGroupId
     * @return \Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object|FriendGroup
     */
    public static function findFriendGroupById (int $friendGroupId)
    {
        $result = FriendGroup::query()->where(['id' => $friendGroupId])->first();
        if (!$result) {
            exception(BusinessException::class, ErrorCode::FRIEND_GROUP_NOT_FOUND);
        }
        return $result;
    }

    /**
     * 搜索朋友
     * @param string $keyword
     * @param int    $page
     * @param int    $size
     * @return array
     */
    public static function searchFriend (string $keyword, int $page, int $size)
    {
        $model = User::query()
            ->whereNull('deleted_at')
            ->where('id', '=', $keyword)
            ->orWhere('username', 'like', "%{$keyword}%")
            ->orWhere('email', 'like', "%{$keyword}%");
        $list = $model
            ->limit($size)
            ->offset(($page - 1) * $size)
            ->get()
            ->toArray();

        $count = $model->count('id');
        return compact('list', 'count');
    }


    /**
     * 申请
     * @param int    $userId
     * @param int    $receiverId
     * @param int    $friendGroupId
     * @param string $appReason
     * @return int
     */
    public static function apply (int $userId, int $receiverId, int $friendGroupId, string $appReason)
    {
        if ($userId == $receiverId) {
            exception(BusinessException::class, ErrorCode::FRIEND_NOT_ADD_SELF);
        }

        self::checkFriendGroup($friendGroupId);
        self::checkFriendRelation($userId, $receiverId);

        $result = UserService::createUserApplication($userId, $receiverId, $friendGroupId,
            UserApplication::APPLICATION_TYPE_FRIEND,
            $appReason,
            UserApplication::APPLICATION_STATUS_CREATE,
            UserApplication::UN_READ);
        if (!$result) {
            exception(BusinessException::class, ErrorCode::USER_CREATE_APPLICATION_FAIL);
        }

        self::notifyUser($receiverId);


        return $result;

    }

    /**
     * 验证朋友关系
     * @param int $userId
     * @param int $receiverId
     */
    private static function checkFriendRelation (int $userId, int $receiverId)
    {
        $check = FriendRelation::query()
            ->whereNull('deleted_at')
            ->where(['uid' => $userId])
            ->where(['friend_id' => $receiverId])
            ->first();
        if ($check) {
            exception(BusinessException::class, ErrorCode::FRIEND_RELATION_ALREADY);
        }

    }

    /**
     * 验证分组关系
     * @param int $userId
     * @param int $receiverId
     */
    private static function checkFriendGroup (int $friendGroupId)
    {
        $friendGroupInfo = self::findFriendGroupById($friendGroupId);
        if (!$friendGroupInfo) {
            exception(BusinessException::class, ErrorCode::FRIEND_GROUP_NOT_FOUND);
        }


    }

    /**
     * @param $receiverId
     */
    private static function notifyUser ($receiverId)
    {
        go(function () use ($receiverId) {
            $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$receiverId, 'fd') ?? '';
            if ($fd) {
                $task = app()->get(UserTask::class);
                $task->unReadApplicationCount($fd, '新');
            }
        });
    }


    /**
     * 申请同意
     * @param int $uid
     * @param int $userApplicationId
     * @param int $friendGroupId
     */
    public static function agreeApply (int $uid, int $userApplicationId, int $friendGroupId)
    {

        Db::beginTransaction();
        try {

            $userApp = self::beforeApply($uid, $userApplicationId, $friendGroupId);
            self::findFriendGroupById($userApp->group_id);
            self::findFriendGroupById($friendGroupId);
            self::changeApplicationStatusById($userApplicationId, UserApplication::APPLICATION_STATUS_ACCEPT);
            $fromCheck = self::checkIsFriendRelation($userApp->receiver_id, $userApp->uid);
            $toCheck = self::checkIsFriendRelation($userApp->uid, $userApp->receiver_id);
            if (!$fromCheck) {
                self::createFriendRelation($userApp->receiver_id, $userApp->uid, $friendGroupId);
                self::createFriendRelation($userApp->uid, $userApp->receiver_id, $userApp->group_id);
            }
            if ($fromCheck && $toCheck) {
                throw new BusinessException(ErrorCode::FRIEND_RELATION_ALREADY);
            }
            $friendInfo = UserService::findUserInfoById($userApp->uid);
            if (!$friendInfo) {
                throw new BusinessException(ErrorCode::USER_NOT_FOUND);
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollBack();
            Log::warning('朋友邀请出现更新错误', [$exception->getMessage(), $exception->getFile(),
                $exception->getLine()]);
            throw new BusinessException(ErrorCode::FRIEND_GROUP_CREATE_FAIL);
        }

        self::pushMess($userApp, $friendInfo);

        return [
            'type' => UserApplication::APPLICATION_TYPE_FRIEND,
            'avatar' => $friendInfo->avatar,
            'username' => $friendInfo->username,
            'id' => $friendInfo->id,
            'sign' => $friendInfo->sign,
            'groupid' => $friendGroupId,
            'status' => FriendRelation::STATUS_TEXT[$friendInfo->status]
        ];
    }

    /**
     * 验证申请
     * @param int    $userApplicationId
     * @param string $userApplicationType
     */
    public static function beforeApply (int $uid, int $userApplicationId, string $userApplicationType)
    {
        $userApplicationInfo = self::findUserApplicationById($userApplicationId);
        self::checkApplicationProcessed($uid, $userApplicationInfo);

        if ($userApplicationInfo->application_type !== $userApplicationType) {
            throw new BusinessException(ErrorCode::USER_APPLICATION_TYPE_WRONG);
        }
        return $userApplicationInfo;
    }

    /**
     * 查询用户申请
     * @param int $id
     */
    public static function findUserApplicationById (int $id)
    {
        $userApplication = UserApplication::query()
            ->whereNull('deleted_at')
            ->find($id);

        if (!$userApplication) {
            throw new BusinessException(ErrorCode::USER_APPLICATION_NOT_FOUND);
        }
        return $userApplication;
    }

    /**
     * 验证申请进度
     */
    public static function checkApplicationProcessed ($uid, $userApplication)
    {
        if ($userApplication->application_status !== UserApplication::APPLICATION_STATUS_CREATE) {
            throw new BusinessException(ErrorCode::USER_APPLICATION_PROCESSED);
        }

        if ($userApplication->receiver_id !== $uid) {
            throw new BusinessException(ErrorCode::NO_PERMISSION_PROCESS);
        }
    }

    /**
     * @param int $id
     * @param int $applicationStatus
     * @return int
     */
    public static function changeApplicationStatusById (int $id, int $applicationStatus)
    {
        return UserApplication::query()->whereNull('deleted_at')->where(['id' => $id])->update([
            'application_status' => $applicationStatus
        ]);
    }

    /**
     * @param int $userId
     * @param int $friendId
     */
    public static function checkIsFriendRelation (int $userId, int $friendId)
    {
        return FriendRelation::query()
            ->whereNull('deleted_at')
            ->where(['uid' => $userId])
            ->where(['friend_id' => $friendId])
            ->first();
    }


    /**
     * 创建用户关系
     * @param int $userId
     * @param int $friendId
     * @param int $groupId
     * @return int
     */
    public static function createFriendRelation (int $userId, int $friendId, int $groupId)
    {
        return FriendRelation::query()->insertGetId([
            'uid' => $userId,
            'friend_id' => $friendId,
            'friend_group_id' => $groupId,
        ]);
    }


    /**
     * 推送到socket两端
     * @param $userApplicationInfo
     * @param $selfInfo
     * @param $friendInfo
     */
    private static function pushMess ($userApplicationInfo, $friendInfo)
    {
        $selfInfo = UserService::findUserInfoById($userApplicationInfo->receiver_id);

        if (!$selfInfo) {
            throw new BusinessException(ErrorCode::USER_NOT_FOUND, '用户id' . $userApplicationInfo->receiver_id . '不存在');
        }

        $pushUserInfo = [
            'type' => UserApplication::APPLICATION_TYPE_FRIEND,
            'avatar' => $selfInfo->avatar,
            'username' => $selfInfo->username,
            'groupid' => $userApplicationInfo->group_id,
            'id' => $selfInfo->id,
            'sign' => $selfInfo->sign,
            'status' => $selfInfo->status
        ];

        go(function () use ($pushUserInfo, $friendInfo) {
            $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$friendInfo->id, 'fd') ?? '';
            if ($fd) {
                app()->get(FriendTask::class)->agreeApply($fd, $pushUserInfo);
                app()->get(UserTask::class)->unReadApplicationCount($fd, '新');
            }
        });

    }


    /**
     * @param int $fromUserId
     * @param int $userId
     * @param int $page
     * @param int $size
     * @return array
     */
    public static function getChatHistory (int $fromUserId, int $userId, $page = 1, $size = 10)
    {
        $history = FriendChatHistory::query()
            ->whereNull('deleted_at')
            ->where('from_uid', '=', $fromUserId)
            ->where('to_uid', $userId)
            ->orWhere('from_uid', '=', $userId)
            ->where('to_uid', $fromUserId)
            ->orderBy('created_at', 'ASC')
            ->forPage($page, $size)
            ->get()
            ->toArray();


        $response = collect($history)->map(function ($item) {
            $id = $item['from_uid'];
            $user = User::findFromCache($id);
            return [
                'id' => $id,
                'username' => $user['username'] ?? '',
                'avatar' => $user['avatar'] ?? '',
                'content' => $item['content'],
                'timestamp' => Carbon::parse($item['created_at'])->timestamp * 1000,
            ];
        })->toArray();
        return [
            'list' => $response,
            'count' => count($response),
        ];
    }

    /**
     * 拒绝申请
     * @param     $uid
     * @param int $apply_id
     */
    public static function refuseApply (int $uid, int $apply_id)
    {

        $userApplicationInfo = self::beforeApply($uid, $apply_id, UserApplication::APPLICATION_TYPE_FRIEND);
        self::changeApplicationStatusById($apply_id, UserApplication::APPLICATION_STATUS_REFUSE);


        go(function () use ($userApplicationInfo) {
            $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$userApplicationInfo->id, 'fd') ?? '';
            if ($fd) {
                app()->get(UserTask::class)->unReadApplicationCount($fd, '新');
            }
        });

        return $userApplicationInfo;
    }
}