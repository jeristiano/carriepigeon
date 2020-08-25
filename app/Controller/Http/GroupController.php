<?php


namespace App\Controller\Http;


use App\Constants\ErrorCode;
use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Request\GroupCreateRequest;
use App\Service\GroupService;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use App\Middleware\JwtAuthMiddleware;
/**
 * Class GroupController
 * @package App\Controller\Http
 */
class GroupController extends AbstractController
{

    /**
     * @param \App\Request\GroupCreateRequest $request
     */
    public function createGroup (GroupCreateRequest $request)
    {
        $user = $this->request->getAttribute('user');

        $resp = GroupService::createGroup($user['uid'],
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
    public function getGroupRelation()
    {
        $validated = $this->validator->make(
            $this->request->all(), [
                'id' => 'required'
            ]
        );
        if ($validated->fails()) {
            exception(BusinessException::class, ErrorCode::INVALID_PARAMETER, $validated->errors()->first());
        }
        $input=$validated->validated();
        return $this->response->success(GroupService::getGroupRelationById((int)$input['id']));
    }
}