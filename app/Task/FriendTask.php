<?php


namespace App\Task;


use App\Constants\WsMessage;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Task\Annotation\Task;

/**
 * Class FriendTask
 * @package App\Task
 */
class FriendTask
{
    /**
     * @Inject()
     * @var \Hyperf\WebSocketServer\Sender
     */
    private $sender;

    /**
     * @Task()
     * @param $fd
     * @param $username
     * @param $avatar
     * @param $userId
     * @param $type
     * @param $content
     * @param $cid
     * @param $mine
     * @param $fromId
     * @param $timestamp
     *
     */
    public function sendMessage(
        $fd,
        $username,
        $avatar,
        $userId,
        $type,
        $content,
        $cid,
        $mine,
        $fromId,
        $timestamp
    ) {
        if (!$fd) {
            return false;
        }
        $data   = [
            'username'  => $username,
            'avatar'    => $avatar,
            'id'        => $userId,
            'type'      => $type,
            'content'   => $content,
            'cid'       => $cid,
            'mine'      => $mine,
            'fromid'    => $fromId,
            'timestamp' => $timestamp,
        ];
        $result = wsSuccess(WsMessage::WS_MESSAGE_CMD_EVENT, WsMessage::EVENT_GET_MESSAGE, $data);
        $this->sender->push($fd, $result);
    }
}