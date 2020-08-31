<?php

declare(strict_types=1);

namespace App\Controller\Ws;

use App\Component\WsProtocol;
use App\Constants\MemoryTable;
use App\Controller\AbstractController;
use App\Task\UserTask;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Memory\TableManager;
use Hyperf\Utils\Context;

/**
 * @Controller(prefix="user",server="ws")
 * Class UserController
 * @package App\Controller\Ws
 */
class UserController extends AbstractController
{
    /**
     * @RequestMapping(path="ping",methods="GET")
     * @return int
     */
    public function index ()
    {
        return WEBSOCKET_OPCODE_PONG;
    }

    /**
     * @Inject()
     * @var \App\Service\UserService
     */
    protected $userService;

    /**
     * @RequestMapping(path="getUnreadApplicationCount",methods="GET")
     */
    public function getUnreadApplicationCount ()
    {
        /**
         * @var WsProtocol $protocol
         */
        $protocol = Context::get('request');
        $userId = TableManager::get(MemoryTable::FD_TO_USER)->get((string)$protocol->getFd(), 'userId') ?? '';
        $count = $this->userService->getUnreadApplyCount($userId);
        //推送
        app()->get(UserTask::class)->unReadApplicationCount($protocol->getFd(), $count);
    }
}
