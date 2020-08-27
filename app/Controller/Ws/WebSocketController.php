<?php


namespace App\Controller\Ws;


use App\Component\MessageParser;
use App\Component\Server;
use App\Constants\Atomic;
use App\Constants\MemoryTable;
use App\Controller\AbstractController;
use App\Model\User;
use App\Service\UserService;
use App\Task\UserTask;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\HttpMessage\Base\Response;
use Hyperf\Memory\AtomicManager;
use Hyperf\Memory\TableManager;
use Hyperf\WebSocketServer\Context as WsContext;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;

/**
 * Class WebSocketController
 * @package App\Controller\Ws
 */
class WebSocketController extends AbstractController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{

    public function onClose ($server, int $fd, int $reactorId): void
    {

    }

    public function onMessage ($server, Frame $frame): void
    {
        $message = MessageParser::decode($frame->data);
    }

    /**
     * @param Response|Server $server
     */
    public function onOpen ($server, Request $request): void
    {
        $user = WsContext::get('user');
        $checkOnline = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$user['uid'], 'fd');

        if ($checkOnline) {
            Server::disconnect($checkOnline, 0, '你的帐号在别的地方登录!');
        }

        TableManager::get(MemoryTable::FD_TO_USER)->set((string)$request->fd, ['userId' => $user['uid']]);
        TableManager::get(MemoryTable::USER_TO_FD)->set((string)$user['uid'], ['fd' => $request->fd]);

        //保存用户状态
        UserService::setUserStatus($user['uid'], User::STATUS_ONLINE);
        $atomic = AtomicManager::get(Atomic::NAME);
        $atomic->add(1);

        $task = app()->get(UserTask::class);
        $task->onlineNumber();
    }
}