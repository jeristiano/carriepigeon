<?php

declare(strict_types=1);

namespace App\Listener;

use App\Event\UserLogged;
use App\Model\UserLoginLog;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\Container\ContainerInterface;

/**
 * @Listener
 */
class LoggedListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct (ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function listen (): array
    {
        return [
            UserLogged::class
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process (object $event)
    {
        //记录日志
        $this->loggedIn($event->user);

    }

    /**
     * @param $user
     */
    private function loggedIn ($user)
    {
        UserLoginLog::query()->insert([
            'uid' => $user['id'],
            'user_login_ip' => $user['user_login_ip']
        ]);
    }
}
