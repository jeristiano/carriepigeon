<?php

declare(strict_types=1);

namespace App\Controller\Ws;

use App\Component\WsProtocol;
use App\Constants\MemoryTable;
use App\Controller\AbstractController;
use App\Model\FriendChatHistory;
use App\Model\UserApplication;
use App\Task\FriendTask;
use Carbon\Carbon;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Memory\TableManager;
use Hyperf\Utils\Context;

/**
 * Class FriendController
 * @package App\Controller\Ws
 * @Controller(prefix="friend",server="ws")
 */
class FriendController extends AbstractController
{

    /**
     * @Inject()
     * @var \App\Service\FriendService
     */
    protected $friendService;

    /**
     * @Inject()
     * @var \App\Service\UserService
     */
    protected $userService;

    /**
     * @RequestMapping(path="read",methods="GET")
     */
    public function read ()
    {
        /**
         * @var WsProtocol $request
         */
        $request = Context::get('request');
        $data = $request->getData();

        $this->friendService->setFriendChatHistoryByMessageId($data['message_id'], FriendChatHistory::RECEIVED);
        return ['message_id' => $data['message_id'] ?? ''];
    }

    /**
     * @RequestMapping(path="getUnreadMessage",methods="GET")
     */
    public function getUnreadMessages ()
    {
        /**
         * @var WsProtocol $request
         */
        $request = Context::get('request');
        $fd = $request->getFd();

        $userId = TableManager::get(MemoryTable::FD_TO_USER)->get((string)$fd, 'userId') ?? '';
        $messages = $this->friendService->getUnreadMessageByToUserId((int)$userId);


        collect($messages)->map(function ($message) use ($fd) {
            app()->get(FriendTask::class)->sendMessage(
                $fd,
                $message['username'],
                $message['avatar'],
                $message['from_uid'],
                UserApplication::APPLICATION_TYPE_FRIEND,
                $message['content'],
                $message['message_id'],
                false,
                $message['from_uid'],
                $message['timestamp']);
        });
    }

    /**
     * @RequestMapping(path="send",methods="GET")
     */
    public function sendMessage ()
    {
        /**
         * @var WsProtocol $protocol
         */
        $protocol = Context::get('request');
        $data = $protocol->getData();

        $friendChatHistory = $this->friendService->createFriendChatHistory($data['message_id'], $data['from_user_id'], $data['to_id'], $data['content']);


        $userInfo = $this->userService->findUserInfoById($data['from_user_id']);
        $fd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$data['to_id'], 'fd') ?? '';

        app()->get(FriendTask::class)->sendMessage(
            $fd,
            $userInfo->username,
            $userInfo->avatar,
            $data['from_user_id'],
            UserApplication::APPLICATION_TYPE_FRIEND,
            $data['content'],
            $data['message_id'],
            false,
            $data['from_user_id'],
            Carbon::parse($friendChatHistory->created_at)->timestamp * 1000
        );

        return ['message_id' => $data['message_id'] ?? ''];
    }


}
