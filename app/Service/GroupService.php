<?php


namespace App\Service;


use App\Constants\ErrorCode;
use App\Exception\ApiException;
use App\Exception\BusinessException;
use App\Model\Group;
use App\Model\GroupRelation;

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
            throw new ApiException(ErrorCode::GROUP_CREATE_FAIL);
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

}