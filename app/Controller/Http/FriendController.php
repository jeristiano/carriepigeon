<?php

declare(strict_types=1);

namespace App\Controller\Http;

use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Middleware\JwtAuthMiddleware;
use App\Request\FriendApplyRequest;
use App\Request\FriendGroupRequst;
use App\Service\FriendService;
use App\Service\UserService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;

/**
 * @Controller(prefix="friend")
 * Class FriendController
 * @package App\Controller\Http
 */
class FriendController extends AbstractController
{
    /**
     * @RequestMapping(path="getRecommendedFriend",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function getRecommendedFriend ()
    {
        $user = $this->request->getAttribute('user');
        $size = $this->request->input('size', 20);
        return $this->response->success(FriendService::getRecommendedFriend($user['uid'], $size));
    }

    /**
     * @RequestMapping(path="createFriendGroup",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function createFriendGroup (FriendGroupRequst $request)
    {

        $user = $this->request->getAttribute('user');
        $frGroupName = $request->input('friend_group_name');

        $friendGroup = FriendService::createFriendGroup($user['uid'], $frGroupName);

        return $this->response->success([
            'id' => $friendGroup->id,
            'groupname' => $friendGroup->friend_group_name
        ]);
    }

    /**
     * @RequestMapping(path="search",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function searchFriend ()
    {
        $keyword = $this->request->input('keyword');
        $page = $this->request->input('page', 10);
        $size = $this->request->input('size', 10);
        return $this->response->success(FriendService::searchFriend($keyword, (int)$page, (int)$size));
    }

    /**
     * 申请
     * @RequestMapping(path="apply",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function apply (FriendApplyRequest $request)
    {
        $user = $this->request->getAttribute('user');
        $receiverId = $request->input('receiver_id');
        $friendGroupId = $request->input('friend_group_id');
        $applicationReason = $request->input('application_reason');

        return $this->response->success(FriendService::apply($user['uid'], (int)$receiverId, (int)
        $friendGroupId, (string)$applicationReason));
    }

    /**
     * 同意申请
     * @RequestMapping(path="agreeApply",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function agreeApply ()
    {
        $user = $this->request->getAttribute('user');
        $userAppId = $this->request->input('user_application_id');
        $friendGroupId = $this->request->input('friend_group_id');
        $result = FriendService::agreeApply($user['uid'], (int)$userAppId, (int)$friendGroupId);
        return $this->response->success($result);
    }

    /**
     * @RequestMapping(path="getChatHistory",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function getChatHistory ()
    {

        $user = $this->request->getAttribute('user');
        $fromUserId = $this->request->input('from_user_id');
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 10);
        return $this->response->success(FriendService::getChatHistory((int)$fromUserId, $user->id, (int)$page, (int)$size));
    }

    /**
     * @RequestMapping(path="info",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function friendInfo ()
    {

        $validated = $this->validator->make(
            $this->request->all(), [
                'user_id' => 'required'
            ]
        );
        if ($validated->fails()) {
            exception(BusinessException::class, ErrorCode::INVALID_PARAMETER, $validated->errors()->first());
        }
        $input = $validated->validated();
        $userInfo = UserService::findUserInfoById((int)$input['user_id']);
        return $this->response->success($userInfo);
    }

    /**
     * @RequestMapping(path="refuseApply",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function refuseApply ()
    {
        $user = $this->request->getAttribute('user');
        $validated = $this->validator->make(
            $this->request->all(), [
                'user_application_id' => 'required'
            ]
        );
        if ($validated->fails()) {
            exception(BusinessException::class, ErrorCode::INVALID_PARAMETER, $validated->errors()->first());
        }

        $input = $validated->validated();
        FriendService::refuseApply($user['uid'],(int)$input['user_application_id']);
        return $this->response->success($input['user_application_id']);
    }


}
