<?php


namespace App\Controller\Ws;


use App\Component\MessageParser;
use App\Component\Server;
use App\Component\WsProtocol;
use App\Constants\Atomic;
use App\Constants\MemoryTable;
use App\Controller\AbstractController;
use App\Model\User;
use App\Service\UserService;
use App\Task\UserTask;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Base\Response;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Memory\AtomicManager;
use Hyperf\Memory\TableManager;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;
use Hyperf\WebSocketServer\Sender;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;

/**
 * Class WebSocketController
 * @package App\Controller\Ws
 */
class WebSocketController extends AbstractController implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{

    /**
     * @Inject()
     * @var \App\Service\UserService
     */
    protected $userService;
    /**
     * @param Response|Server $server
     */
    public function onClose ($server, int $fd, int $reactorId): void
    {
        $userId = TableManager::get(MemoryTable::FD_TO_USER)->get((string)$fd, 'userId');
        $selfFd = TableManager::get(MemoryTable::USER_TO_FD)->get((string)$userId, 'fd');
        if ($fd == $selfFd) {
            TableManager::get(MemoryTable::USER_TO_FD)->del((string)$userId);
            TableManager::get(MemoryTable::FD_TO_USER)->del((string)$fd);
        }
       $this->userService->setUserStatus($userId, User::STATUS_OFFLINE);

        $atomic = AtomicManager::get(Atomic::NAME);
        $atomic->sub(1);

        WsContext::destroy('user');
        app()->get(UserTask::class)->onlineNumber();

    }

    /**
     * @Inject()
     * @var Sender
     */
    private $sender;


    /**
     * @param Response|Server $server
     */
    public function onMessage ($server, Frame $frame): void
    {
        //处理消息
        $message = MessageParser::decode($frame->data);
        $this->setRequestContext($server, $frame, $message);
        $dispatched = $this->dispatch($message);
        if ($dispatched->isFound()) {
            //路由处理
            $result = call_user_func([
                make($dispatched->handler->callback[0]),
                $dispatched->handler->callback[1],
            ]);
            if ($result !== NULL) {
                $receive = [
                    'cmd' => $message['cmd'],
                    'data' => $result,
                    'ext' => []
                ];
                $this->sender->push($frame->fd, MessageParser::encode($receive));
            }
        }
    }

    /**
     * @param $server
     * @param $frame
     * @param $message
     */
    private function setRequestContext ($server, $frame, $message)
    {
        Context::set('request', new WsProtocol(
            $message['data'],
            $message['ext'],
            $frame->fd,
            $server->getClientInfo($frame->fd)['last_time'] ?? 0
        ));
    }


    /**
     * @param $message
     * @return \Hyperf\HttpServer\Router\Dispatched|mixed
     */
    private function dispatch ($message)
    {
        $dispatcher = app()
            ->get(DispatcherFactory::class)
            ->getDispatcher('ws');

        [$controller, $method] = $this->getRoutePath($message);
        return make(Dispatched::class, [
            $dispatcher->dispatch('GET', sprintf('/%s/%s', $controller, $method))
        ]);
    }

    /**
     * @param $message
     * @return array
     */
    private function getRoutePath ($message)
    {
        $controller = explode('.', $message['cmd'])[0] ?? '';
        $method = explode('.', $message['cmd'])[1] ?? '';
        return [$controller, $method];
    }


    /**
     * 开启
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
        $this->userService->setUserStatus($user['uid'], User::STATUS_ONLINE);
        $atomic = AtomicManager::get(Atomic::NAME);
        $atomic->add(1);

        $task = app()->get(UserTask::class);
        $task->onlineNumber();
    }
}