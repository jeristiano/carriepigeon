<?php
declare(strict_types=1);

/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace App\Listener;

use App\Component\Log;
use App\Constants\Atomic;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BeforeMainServerStart;
use Hyperf\Memory\AtomicManager;
use Hyperf\Memory\TableManager;
use Hyperf\Utils\Coroutine;

/**
 * Class BeforeMainServerStartListener
 * @package App\Listener
 */
class BeforeMainServerStartListener implements ListenerInterface
{
    public function listen (): array
    {
        return [
            BeforeMainServerStart::class
        ];
    }

    /**
     * Handle the Event when the event is triggered, all listeners will
     * complete before the event is returned to the EventDispatcher.
     */
    public function process (object $event)
    {
        $tableConfig = config('table');
        foreach ($tableConfig as $key => $item) {
            TableManager::initialize($key, $item['size']);
            Log::debug(sprintf('TableManager [%s] initialize...', $key));
            foreach ($item['columns'] as $columnKey => $column) {
                TableManager::get($key)->column($columnKey, $column['type'], $column['size']);
            }
            TableManager::get($key)->create();
        }
        AtomicManager::initialize(Atomic::NAME);
        Log::debug(sprintf('AtomicManager [%s] initialize...', Atomic::NAME));
    }
}
