<?php


namespace App\Controller\Http;


use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Middleware\JwtAuthMiddleware;
use App\Request\GroupApplyRequest;
use App\Request\GroupCreateRequest;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;

/**
 * Class GroupController
 * @package App\Controller\Http
 * @Controller(prefix="group")
 */
class GroupController extends AbstractController
{

    /**
     * @Inject()
     * @var \App\Service\GroupService
     */
    protected $groupService;


    /**
     * @param \App\Request\GroupCreateRequest $request
     */
    public function createGroup (GroupCreateRequest $request)
    {
        $user = $this->request->getAttribute('user');

        $resp = $this->groupService->createGroup($user['uid'],
            $request->input('group_name'),
            $request->input('avatar'),
            (int)$request->input('size'),
            $request->input('introduction'),
            (int)$request->input('validation'));
        return $this->response->success($resp);
    }

    /**
     * @RequestMapping(path="getGroupRelation",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function getGroupRelation ()
    {
        $validated = $this->validator->make(
            $this->request->all(), [
                'id' => 'required'
            ]
        );
        if ($validated->fails()) {
            exception(BusinessException::class, ErrorCode::INVALID_PARAMETER, $validated->errors()->first());
        }
        $input = $validated->validated();
        return $this->response->success($this->groupService->getGroupRelationById((int)$input['id']));
    }

    /**
     * @RequestMapping(path="getRecommendedGroup",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function getRecommendedGroup ()
    {
        $user = $this->request->getAttribute('user');
        return $this->response->success($this->groupService->getRecommendedGroup($user['uid'], 20));
    }

    /**
     * @RequestMapping(path="search",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function searchGroup ()
    {

        $keyword = $this->request->input('keyword');
        $page = $this->request->input('page');
        $size = $this->request->input('size');
        return $this->response->success($this->groupService->searchGroup($keyword, $page, $size));
    }

    /**
     * @RequestMapping(path="apply",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function apply (GroupApplyRequest $request)
    {

        $user = $this->request->getAttribute('user');

        $result = $this->groupService->apply((int)$user['uid'], (int)$request->input('group_id'),
            $request->input('application_reason'));
        $msg = empty($result) ? '等待管理员验证 !' : '你已成功加入此群 !';
        return $this->response->success($result, 0, $msg);
    }

    /**
     * @RequestMapping(path="info",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function groupInfo ()
    {
        $validated = $this->validator->make(
            $this->request->all(), [
                'group_id' => 'required'
            ]
        );
        if ($validated->fails()) {
            exception(BusinessException::class, ErrorCode::INVALID_PARAMETER, $validated->errors()->first());
        }
        $input = $validated->validated();
        return $this->response->success($this->groupService->findGroupById((int)$input['group_id']));
    }

    /**
     * @RequestMapping(path="agreeApply",methods="GET")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function agreeApply ()
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

        $result = $this->groupService->agreeApply($user['uid'], (int)$input['user_application_id']);
        return $this->response->success($result);
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

        $this->groupService->refuseApply($user['uid'], (int)$input['user_application_id']);
        return $this->response->success($input['user_application_id']);
    }

    /**
     * @RequestMapping(path="getChatHistory",methods="POST")
     * @Middleware(JwtAuthMiddleware::class)
     */
    public function getChatHistory ()
    {
        $toGroupId = $this->request->input('to_group_id');
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 10);
        return $this->response->success($this->groupService->getChatHistory((int)$toGroupId, (int)$page, (int)$size));
    }
}