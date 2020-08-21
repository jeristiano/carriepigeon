<?php


namespace App\Component;


use Hyperf\Logger\Logger;
use Hyperf\Utils\ApplicationContext;

/**
 * Class Log
 * @package App\Component
 */
class Log
{
    /**
     * @method static Logger get($name)
     * @method static void log($level, $message, array $context = [])
     * @method static void emergency($message, array $context = [])
     * @method static void alert($message, array $context = [])
     * @method static void critical($message, array $context = [])
     * @method static void error($message, array $context = [])
     * @method static void warning($message, array $context = [])
     * @method static void notice($message, array $context = [])
     * @method static void info($message, array $context = [])
     * @method static void debug($message, array $context = [])
     */
    /**
     * @param $name
     * @param $arguments
     * @return \Psr\Log\LoggerInterface
     */
    public static function __callStatic ($name, $arguments)
    {
        $container = ApplicationContext::getContainer();
        $factory = $container->get(\Hyperf\Logger\LoggerFactory::class);
        if ($name === 'get') {
            return $factory->get(...$arguments);
        }
        $log = $factory->get('default');
        $log->$name(...$arguments);
    }
}