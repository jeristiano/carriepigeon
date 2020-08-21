<?php


namespace App\Service;


use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\User;
use App\Model\UserApplication;

/**
 * Class UserService
 * @package App\Service
 */
class UserService

{


    /**
     * 注册用户
     * @param string $email
     * @param string $password
     * @return bool
     */
    public static function register (string $email, string $password): bool
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
    public static function getUserProfile ($uid)
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
    public static function getUnreadApplyCount (int $uid)
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
    public static function updateUserProfile (int $uid, ?string $username, ?string $avatar)
    {
        $update = [];
        if ($username) $update['username'] = $username;
        if ($avatar) $update['avatar'] = $avatar;
        if (!$update) return false;
        self::changeUserInfoById($uid, $update);
        return true;
    }


    /**
     * @param int   $userId
     * @param array $data
     * @return int
     */
    public static function changeUserInfoById (int $userId, array $data)
    {
        return User::query()
            ->whereNull('deleted_at')
            ->where(['id' => $userId])
            ->update($data);
    }

}