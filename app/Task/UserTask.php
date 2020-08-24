<?php


namespace App\Task;

use Hyperf\Task\Annotation\Task;
use App\Constants\WsMessage;
use Hyperf\WebSocketServer\Sender;
use function App\Helper\wsSuccess;

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
}