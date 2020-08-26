<?php


namespace App\Service;


use App\Component\Log\Log;
use App\Constants\ErrorCode;
use App\Constants\MemoryTable;
use App\Exception\BusinessException;
use App\Model\FriendChatHistory;
use App\Model\Group;
use App\Model\GroupChatHistory;
use App\Model\GroupRelation;
use App\Model\User;
use App\Model\UserApplication;
use App\Task\GroupTask;
use App\Task\UserTask;
use Carbon\Carbon;
use Hyperf\DbConnection\Db;
use Hyperf\Memory\TableManager;
use Hyperf\Utils\ApplicationContext;

/**
 * Class GroupService
 * @package App\Service
 */
class GroupService
{

    /**
     * @param int    $userId
     * @param string $groupName
     * @param string $avatar
     * @param int    $size
     * @param string $introduction
     * @param int    $validation
     */
    public static function createGroup (int $userId, string $groupName, string $avatar, int $size, string $introduction, int $validation)
    {
        $groupId = Group::query()->insertGetId([
            'uid' => $userId,
            'group_name' => $groupName,
            'avatar' => $avatar,
            'size' => $size,
            'introduction' => $introduction,
            'validation' => $validation
        ]);
        if (!$groupId) {
            throw new BusinessException(ErrorCode::GROUP_CREATE_FAIL);
        }
        $groupRelationId = GroupRelation::query()->insertGetId([
            'uid' => $userId,
            'group_id' => $groupId
        ]);
        if (!$groupRelationId) {
            throw new BusinessException(ErrorCode::GROUP_RELATION_CREATE_FAIL);
        }
        return self::findGroupById($groupId);
    }

    /**
     * @param int $groupId
     */
    public static function findGroupById (int $groupId)
    {
        $groupInfo = Group::query()->where(['id' => $groupId])->first();
        if (!$groupInfo) {
            throw new BusinessException(ErrorCode::GROUP_NOT_FOUND);
        }
        return $groupInfo;
    }


    /**
     * @param int $groupId
     * @return array
     */
    public static function getGroupRelationById (int $groupId)
    {
        self::findGroupById($groupId);

        $groupRelations = GroupRelation::query()
            ->with(['user'])
            ->whereNull('deleted_at')
            ->where(['group_id' => $groupId])
            ->get()
            ->toArray();

        $data['list'] = collect($groupRelations)->map(function ($item) {
            return [
                'id' => $item['id'],
                'username' => $item['user']['username'] ?? '',
                'avatar' => $item['user']['avatar'] ?? '',
                'sign' => $item['user']['content'] ?? '',
            ];
        })->toArray();

        return $data;
    }

    /**
     * @param int $limit
     * @return array
     */
    public static function getRecommendedGroup ($uid, int $limit)
    {
        $hadGroup = GroupRelation::query()->where('uid', $uid)->pluck('group_id');

        return Group::query()->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->whereNotIn('id', $hadGroup)
            ->limit($limit)
            ->get()
            ->toArray();
    }


    /**
     * @param string $keyword
     * @param int    $page
     * @param int    $size
     * @return array
     */
    public static function searchGroup (string $keyword, int $page, int $size)
    {
        return Group::query()->whereNull('deleted_at')
            ->where(['id' => $keyword])
            ->orWhere('group_name', 'like', "%$keyword%")
            ->limit($size)
            ->forPage($page, $size)
            ->get()
            ->toArray();
    }

    /**
     * @param int    $userId
     * @param int    $groupId
     * @param string $applicationReason
     * @return \App\Model\Group|\Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object|string
     */
    public static function apply (int $userId, int $groupId, string $applicationReason)
    {
        self::checkIsGroupRelation($userId, $groupId);

        $groupInfo = self::findGroupById($groupId);

        self::checkGroupSize($groupId, $groupInfo->size);

        $applicationStatus = (($groupInfo->validation) == Group::VALIDATION_NOT) ? UserApplication::APPLICATION_STATUS_ACCEPT : UserApplication::APPLICATION_STATUS_CREATE;

        $result = UserService::createUserApplication($userId, $groupInfo->uid, $groupId, UserApplication::APPLICATION_TYPE_GROUP, $applicationReason, $applicationStatus, UserApplication::UN_READ);

        if (!$result) {
            throw new BusinessException(ErrorCode::USER_CREATE_APPLICATION_FAIL);
        }


        $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$groupInfo->uid, 'fd') ?? '';
        if ($fd) {
            app()->get(UserTask::class)->unReadApplicationCount($fd, '新');
        }

        if ($groupInfo->validation == Group::VALIDATION_NOT) {
            GroupRelation::query()->insertGetId([
                'uid' => $userId,
                'group_id' => $groupId
            ]);
            return $groupInfo;
        }
        return '';
    }

    /**
     * @param int $userId
     * @param int $groupId
     * @return null|\Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Model|object
     */
    public static function checkIsGroupRelation (int $userId, int $groupId)
    {
        $check = GroupRelation::query()->whereNull('deleted_at')
            ->where(['uid' => $userId])
            ->where(['group_id' => $groupId])->first();
        if ($check) {
            throw new BusinessException(ErrorCode::GROUP_RELATION_ALREADY);
        }
        return $check;
    }

    /**
     * @param int $groupId
     * @param int $size
     * @return int
     */
    public static function checkGroupSize (int $groupId, int $size)
    {
        $count = GroupRelation::query()->whereNull('deleted_at')->where(['group_id' => $groupId])->count();
        if ($count >= $size) {
            throw new BusinessException(ErrorCode::GROUP_FULL);
        }
        return $count;
    }


    /**
     * @param int $userApplicationId
     * @return int
     */
    public static function agreeApply ($uid, int $userApplicationId)
    {

        try {
            Db::beginTransaction();
            $userApp = self::beforeApply($uid, $userApplicationId, UserApplication::APPLICATION_TYPE_GROUP);
            self::checkIsGroupRelation($userApp->uid, $userApp->group_id);
            $groupInfo = self::findGroupById($userApp->group_id);
            self::checkGroupSize($groupInfo->id, $groupInfo->size);
            self::pushMess($userApp, $groupInfo);
            Db::commit();
            return GroupRelation::query()->insertGetId([
                'uid' => $userApp->uid,
                'group_id' => $userApp->group_id
            ]);
        } catch (\Exception $e) {
            Db::rollBack();
            Log::warning('朋友邀请出现更新错误', [$e->getMessage(), $e->getFile(),
                $e->getLine()]);
            throw new BusinessException(ErrorCode::GROUP_CREATE_FAIL);
        }

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
     * @param $userApp
     * @param $groupInfo
     */
    private static function pushMess ($userApp, $groupInfo)
    {
        $pushGroupInfo = [
            'type' => UserApplication::APPLICATION_TYPE_GROUP,
            'avatar' => $groupInfo->avatar,
            'groupName' => $groupInfo->group_name,
            'groupId' => $groupInfo->id,
        ];
        go(function () use ($pushGroupInfo, $userApp) {
            $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$userApp->uid, 'fd') ?? '';

            if ($fd) {
                app()->get(GroupTask::class)->agreeApply($fd, $pushGroupInfo);
                app()->get(UserTask::class)->unReadApplicationCount($fd, '新');
            }
        });

    }

    /**
     * @param int $userApplicationId
     *
     * @return \App\Model\UserApplication|\Hyperf\Database\Model\Builder|\Hyperf\Database\Model\Collection|\Hyperf\Database\Model\Model
     */
    public static function refuseApply($uid,int $userApplicationId)
    {
        $userApplicationInfo = self::beforeApply($uid,$userApplicationId, UserApplication::APPLICATION_TYPE_GROUP);
        FriendService::changeApplicationStatusById($userApplicationId, UserApplication::APPLICATION_STATUS_REFUSE);

        $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$userApplicationInfo->uid, 'fd') ?? '';
        if ($fd) {
           app()->get(UserTask::class)->unReadApplicationCount($fd, '新');
        }
        return $userApplicationInfo;
    }


    /**
     * @param int $toGroupId
     * @param int $page
     * @param int $size
     * @return array
     */
    public static function getChatHistory(int $toGroupId, int $page, int $size){
        $history = GroupChatHistory::query()
            ->whereNull('deleted_at')
            ->where(['to_group_id' => $toGroupId])
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

}