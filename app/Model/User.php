<?php

declare (strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 */
class User extends Model
{
    const STATUS_ONLINE = 1;
    const STATUS_OFFLINE = 0;
    const STATUS_TEXT = ['hide', 'online'];
    /**
     * The table associated with the model.
     * @var string
     */
    protected $table = 'user';
    /**
     * The attributes that are mass assignable.
     * @var array
     */
    protected $fillable = ['id', 'created_at', 'updated_at', 'email', 'password', 'status', 'sign', 'avatar', 'deleted_at', 'username'];
    /**
     * The attributes that should be cast to native types.
     * @var array
     */
    protected $casts = [];

    /**
     * @return \Hyperf\Database\Model\Relations\HasMany
     */
    public function relation ()
    {
        return $this->hasMany(FriendRelation::class, 'uid', 'id');
    }
}