<?php


namespace App\Service;


use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use App\Model\User;
use Phper666\JWTAuth\JWT;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Auth
 * @package App\Service
 */
class Auth
{


    /**
     * 获得登录的用户信息
     * @throws \Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     */
    public static function user ()
    {
        $request = app()->get(ServerRequestInterface::class);
        $token = $request->getCookieParams()['IM_TOKEN'] ?? '';

        if (!$token) return null;

        $jwt = make(JWT::class);

        if ($jwt->checkToken($token)) {
            $jwtData = $jwt->getParserData($token);

            $user = User::query()->where(['id' => $jwtData['uid']])->first();
            if (!$user) {
                throw new BusinessException(ErrorCode::AUTH_ERROR);
            }
            return $user;
        }
        return null;
    }

}