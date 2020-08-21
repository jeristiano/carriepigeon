<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

use App\Component\MonologHourlyHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

// config/autoload/logger.php
$appEnv = env('APP_ENV', 'dev');
if ($appEnv == 'dev') {
    $stream = 'php://stdout';
    return [
        'default' => [
            'handler' => [
                'class' => StreamHandler::class,
                'constructor' => [
                    'stream' => 'php://stdout',
                    'level' => Logger::DEBUG,
                ],
            ],
            'formatter' => [
                'class' => LineFormatter::class,
                'constructor' => [
                    'format' => "||%datetime%||%channel%||%level_name%||%message%||%context%||%extra%\n",
                    'allowInlineLineBreaks' => true,
                    'includeStacktraces' => true,
                ],
            ],
        ],
    ];
}
return [
    'default' => [
        'handlers' => [
            [
                'class' =>MonologHourlyHandler::class,
                'constructor' => [
                    'filename' => BASE_PATH . '/runtime/logs/hyperf.log',
                    'level' => Logger::INFO,
                ],
                'formatter' => [
                    'class' => LineFormatter::class,
                    'constructor' => [
                        'format' => "||%datetime%||%channel%||%level_name%||%message%||%context%||%extra%\n",
                        'dateFormat' => 'Y-m-d H:i:s',
                        'allowInlineLineBreaks' => true,
                    ],
                ],
            ],
            [
                'class' => RotatingFileHandler::class,
                'constructor' => [
                    'filename' => BASE_PATH . '/runtime/logs/hyperf-debug.log',
                    'level' => Logger::DEBUG,
                ],
                'formatter' => [
                    'class' => JsonFormatter::class,
                    'constructor' => [
                        'format' => "||%datetime%||%channel%||%message%||%context%||%extra%\n",
                        'dateFormat' => 'Y-m-d H:i:s',
                        'batchMode' => JsonFormatter::BATCH_MODE_JSON,
                        'appendNewline' => true,
                    ],
                ],
            ],
        ],
    ],
];



