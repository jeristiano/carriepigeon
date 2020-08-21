<?php


namespace App\Service;


use App\Model\FriendGroup;
use App\Model\FriendRelation;
use App\Model\GroupRelation;

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

}