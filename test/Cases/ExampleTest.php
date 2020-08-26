<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace HyperfTest\Cases;

use App\Model\FriendChatHistory;
use App\Model\FriendRelation;
use App\Model\GroupRelation;
use App\Model\User;
use App\Model\UserApplication;
use App\Service\FriendService;
use App\Service\UserService;
use Carbon\Carbon;
use Hyperf\Cache\Driver\RedisDriver;
use Hyperf\Redis\Redis;
use HyperfTest\HttpTestCase;

/**
 * @internal
 * @coversNothing
 */
class ExampleTest extends HttpTestCase
{


    public function testExample ()
    {
        $this->assertTrue(true);
        $this->assertTrue(is_array($this->get('/')));

//        $result = make(FriendGroup::class)
//            ->query()
//            ->where('uid', 39)
//            ->with(['group.user' => function ($query) {
//                $query->whereNull('deleted_at');
//            }])
//            ->get()
//            ->toArray();
//
//        $friendList = collect($result)->map(function ($item, $k) {
//
//            $friend['list'] = $this->getGroupMap($item['group']);
//            $friend['groupname'] = $item['friend_group_name'];
//            $friend['id'] = $item['id'];
//            return $friend;
//        })->toArray();

//        $friendList = FriendService::getGroup(39);
        $goRoutines = parallel([

            function () {
                return UserService::getUserProfile(39);
            },

            function () {

                //朋友
                return FriendService::getGroup(39);
            },
            //群组
            function () {

                return FriendService::getFriend(39);
            }
        ]);


        var_dump(json_encode($goRoutines));
    }

    /**
     * @param $groups
     * @return array
     */
    private function getGroupMap ($groups)
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

    public function testGetApplication ()
    {
        $uid = 39;
        $page = 1;
        $size = 10;

        $messages = UserApplication::query()
            ->where('uid', $uid)
            ->whereNull('deleted_at')
            ->orWhere('receiver_id', $uid)
            ->forPage($page, $size)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
        $this->assertIsArray($messages, 'res is an array');

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
        $resu = [$appIds ?? [], $groupIds ?? [], $userIds];

        $userInfos = User::query()->whereNull('deleted_at')
            ->whereIn('id', $userIds)
            ->get()->mapWithKeys(function ($item) {
                return [$item['id'] => $item];
            })->toJson();
        var_dump($userInfos);
    }

    public function testGetRecommendedFriend ()
    {
        $uid = 39;
        $friendIds = FriendRelation::query()->where('uid', $uid)->pluck('friend_id');
        $friendIds[] = $uid;

        $userInfos= User::query()
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->whereNotIn('id', $friendIds)
            ->limit(20)
            ->get()
            ->toArray();

//        $friendIds=  make(FriendRelation::class)->getFriendIds(39);
        $insert = [
            'uid' => 39,
            'friend_id' => 41,
            'friend_group_id' => 7
        ];
        $friendIds = FriendRelation::query()
//            ->where('id',20)
            ->create($insert);
        var_dump($friendIds);
    }

    /**
     * @return int
     */
    public function testGetHistory(){

        $history = FriendChatHistory::query()
            ->whereNull('deleted_at')
            ->where('from_uid', '=', 39)
            ->where('to_uid', 40)
            ->orWhere('from_uid', '=', 40)
            ->where('to_uid', '=', 39)
            ->orderBy('created_at', 'ASC')
            ->forPage(1, 20)
            ->get()
            ->toArray();

        $result= collect($history)->map(function ($item) {
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
        debug_print(json_encode($result));
        $this->assertIsArray($result,'array');
    }


    public function testGetGroupRelation(){
        $groupRelations = GroupRelation::query()
            ->with(['user'])
            ->whereNull('deleted_at')
            ->where(['group_id' => 7])
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
        $this->assertIsArray($data);
        debug_print($data);
    }


    public function testGetRecommendedGroup(){
      $group=  GroupRelation::query()->where('uid',39)->pluck('group_id');

    }


}
