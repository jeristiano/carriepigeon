<?php


namespace App\Task;

use App\Component\Server;
use App\Constants\Atomic;
use App\Constants\MemoryTable;
use Hyperf\Memory\AtomicManager;
use Hyperf\Memory\TableManager;
use Hyperf\Task\Annotation\Task;
use App\Constants\WsMessage;
use Hyperf\WebSocketServer\Sender;

/**
 * Class UserTask
 * @package App\Task
 */
class UserTask
{
    /**
     * @Task()
     * @param int $fd
     * @param     $data
     */
    public function unReadApplicationCount (int $fd, $data)
    {
        $text = wsSuccess(WsMessage::WS_MESSAGE_CMD_EVENT,
            WsMessage::EVENT_GET_UNREAD_APPLICATION_COUNT, $data);
        make(Sender::class)->push($fd, $text);
    }

    /**
     * @Task()
     */
    public function onlineNumber()
    {
        $atomic = AtomicManager::get(Atomic::NAME);

        $userToFdTable = TableManager::get(MemoryTable::USER_TO_FD);

        $fds = [];
        foreach ($userToFdTable as $item) {
            array_push($fds, $item['fd']);
        }

        $data = wsSuccess(
            WsMessage::WS_MESSAGE_CMD_EVENT,
            'onlineNumber',
            "<span>当前在线人数：<b>{$atomic->get()}</b></span>"
        );

        Server::sendToAll($data, $fds);
    }

    /**
     * @Task()
     * @param array $fds
     * @param array $data
     *
     * @return bool
     */
    public function setUserStatus(array $fds, array $data)
    {
        if (empty($fds)) {
            return false;
        }
        $result = wsSuccess(WsMessage::WS_MESSAGE_CMD_EVENT, WsMessage::EVENT_USER_STATUS, $data);
        Server::sendToAll($result, $fds);
        return true;
    }

}