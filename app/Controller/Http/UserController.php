<?php

declare(strict_types=1);

namespace App\Controller\Http;

use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Event\UserLogged;
use App\Exception\BusinessException;
use App\Middleware\JwtAuthMiddleware;
use App\Model\User;
use App\Request\LoginRequest;
use App\Request\RegisterRequest;
use App\Request\UserUpdateRequest;
use App\Service\Auth;
use App\Service\UserService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * @Controller(prefix="user")
 * Class UserController
 * @package App\Controller\Http
 */
class UserController extends AbstractController
{

    /**
     * @Inject()
     * @var \App\Service\UserService
     */
    protected $userService;

    /**
     * @Inject()
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @Inject()
     * @var \Phper666\JWTAuth\JWT
     */
    protected $auth;

    /**
     * 主頁
     * @RequestMapping(path="home",methods="GET")
     */
    public function home ()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->response->redirect(env('APP_URL') . '/index/login');
        }
        $menus = config('menu');

        return $this->view->render('user/home', [
            'menus' => $menus,
            'user' => $user,
            'wsUrl' => env('WS_URL'),
            'webRtcUrl' => env('WEB_RTC_URL'),
            'stunServer' => 'stunServer'
        ]);

    }

    /**
     * 注册
     * @RequestMapping(path="register",methods="POST")
     */
    public function register (RegisterRequest $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        return $this->response->success($this->userService->register($email, $password));
    }


    /**
     * 登录
     * @RequestMapping(path="login",methods="POST")
     */
    public function login (LoginRequest $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $user = $this->userService->attempt($email, $password);

        //登录事件
        $param = $user->toArray();
        $param['user_login_ip'] = get_client_ip();
        $this->eventDispatcher->dispatch(new UserLogged($param));

        $auth = [
            'uid' => $user->id,
            'username' => $user->email
        ];
        $token = $this->auth->setScene('default')->getToken($auth);


        return $this->response
            ->withCookie(new Cookie('IM_TOKEN', $token, time() + $this->auth->getTTL(), '/', '', false, false)
            )->json([
                'data' => $user,
                'code' => 0,
                'msg' => '登录成功',
            ]);
    }

    /**
     * @Inject()
     * @var \App\Service\FriendService
     */
    protected $friendService;

    /**
     * @RequestMapping(path="init", method="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function feed ()
    {
        $user = $this->request->getAttribute('user');
        if (!$user) {
            throw new BusinessException(ErrorCode::INVALID_PARAMETER, '未获取到参数user');
        }

        //开启协程
        $goRoutines = parallel([
            //自己
            function () use ($user) {
                return $this->userService->getUserProfile($user['uid']);
            },
            //朋友
            function () use ($user) {

                return $this->friendService->getFriend($user['uid']);
            },
            //群组
            function () use ($user) {
                return $this->friendService->getGroup($user['uid']);
            },

        ]);

        return $this->response->success([
            'mine' => $goRoutines[0] ?? [],
            'friend' => $goRoutines[1] ?? [],
            'group' => $goRoutines[2] ?? []
        ]);
    }

    /**
     * @RequestMapping(path="signout",method="GET")
     */
    public function signOut ()
    {
        $this->response->withCookie(new Cookie('IM_TOKEN', ''))->redirect(env('APP_URL') . '/index/login');
    }


    /**
     * 未通过申请
     * @RequestMapping(path="getUnreadApplicationCount",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function getUnreadApplicationCount ()
    {
        $user = $this->request->getAttribute('user');

        //未读消息
        return $this->response->success($this->userService->getUnreadApplicationCount($user['uid']));
    }

    /**
     * 用户信息
     * @RequestMapping(path="info",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function userInfo ()
    {
        $user = $this->request->getAttribute('user');

        $resp = User::query()
            ->where('id', $user['uid'])
            ->first()
            ->makeHidden(['password', 'deleted_at']);

        return $this->response->success($resp);
    }

    /**
     * 更新用户资料
     * @RequestMapping(path="changeUserNameAndAvatar",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function updateUser (UserUpdateRequest $request)
    {
        $user = $this->request->getAttribute('user');

        $result = $this->userService->updateUserProfile($user['uid'],
            $request->input('username'),
            $request->input('avatar'));
        if (!$result) {
            return $this->response->success(null, 0, '未更新任何资料');
        }
        return $this->response->success(null, 0, '更新成功');

    }

    /**
     * @RequestMapping(path="setSign",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function setSign ()
    {

        $user = $this->request->getAttribute('user');

        $validated = $this->validator->make(
            $this->request->all(),
            ['sign' => "required"]);

        if ($validated->fails()) {
            $errorMessage = $validated->errors()->first();
            throw new BusinessException(
                ErrorCode::INVALID_PARAMETER,
                $errorMessage
            );
        }
        $input = $validated->validated();

        $this->userService->changeUserInfoById($user['uid'], ['sign' => $input['sign']]);
        return $this->response->success();
    }

    /**
     * @RequestMapping(path="setStatus",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function setStatus ()
    {

        $user = $this->request->getAttribute('user');

        $validated = $this->validator->make(
            $this->request->all(),
            ['status' => "required"]);

        if ($validated->fails()) {
            $errorMessage = $validated->errors()->first();
            throw new BusinessException(
                ErrorCode::INVALID_PARAMETER,
                $errorMessage
            );
        }
        $input = $validated->validated();
        $this->userService->changeUserInfoById($user['uid'], ['status' => (int)$input['status']]);
        return $this->response->success();
    }

    /**
     * 獲取
     * @RequestMapping(path="getApplication",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function getAppMessages ()
    {
        $user = $this->request->getAttribute('user');
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 10);
        $result = $this->userService->getApplication($user['uid'], (int)$page, (int)$size);
        return $this->response->success($result);
    }


}
