<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\Cache\Annotation\Cacheable;
use Hyperf\Cache\Annotation\CacheEvict;
use Hyperf\Database\Model\Events\Created;
use Hyperf\DbConnection\Model\Model;

/**
 */
class FriendRelation extends Model
{
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'friend_relation';
    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = ['id', 'uid', 'friend_id', 'friend_group_id', 'created_at', 'updated_at', 'deleted_at'];
    /**
     * The attributes that should be cast to native types.
     * @var array
     */
    protected $casts = ['id' => 'integer', 'uid' => 'integer', 'friend_id' => 'integer', 'friend_group_id' => 'integer', 'created_at' => 'datetime', 'updated_at' => 'datetime'];


    const STATUS_TEXT = [
        'offline',
        'online'
    ];

    /**
     * @return \Hyperf\Database\Model\Relations\BelongsTo
     */
    public function user ()
    {
        return $this->belongsTo(User::class, 'friend_id', 'id');
    }


    /**
     * @Cacheable(prefix="getFriendIds", value="_#{uid}", ttl=9000)
     * @param int $uid
     * @return \Hyperf\Utils\Collection
     */
    public function getFriendIds (int $uid)
    {
        return FriendRelation::query()->where('uid', $uid)->pluck('friend_id');
    }

    /**
     * @CacheEvict(prefix="getFriendIds", value="_#{uid}")
     * @param int $uid
     */
    public function clearFriendIds (int $uid)
    {
        return true;
    }

    /**
     * 监听模型新增
     * @param \Hyperf\Database\Model\Events\Created $event
     * @return bool
     */
    public function created (Created $event)
    {
        return $this->clearFriendIds($event->getModel()->uid);
    }


}