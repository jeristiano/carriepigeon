<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Exception\Handler;

use App\Exception\BusinessException;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Utils\Codec\Json;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class AppExceptionHandler
 * @package App\Exception\Handler
 */
class AppExceptionHandler extends ExceptionHandler
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    public function __construct (StdoutLoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Handle the exception, and return the specified result.
     */
    public function handle (Throwable $throwable, ResponseInterface $response)
    {
        // 判断被捕获到的异常是希望被捕获的异常
        if ($throwable instanceof BusinessException) {
            // 格式化输出

            $data = Json::encode([
                'code' => $throwable->getCode(),
                'msg' => $throwable->getMessage(),
            ], JSON_UNESCAPED_UNICODE);


            // 阻止异常冒泡
            $this->stopPropagation();
            $this->logger->info('返回给客户端数据');
            $this->logger->info($data);
            return $response->withStatus($throwable->getCode())->withBody(new SwooleStream($data));
        }

        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        $this->logger->error($throwable->getTraceAsString());
        $data = Json::encode([
            'code' => -1,
            'msg' => '服务端异常!'
        ], JSON_UNESCAPED_UNICODE);

        $this->stopPropagation();
        return $response->withStatus(200)
            ->withAddedHeader('content-type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($data));
    }

    public function isValid (Throwable $throwable): bool
    {
        return true;
    }
}
