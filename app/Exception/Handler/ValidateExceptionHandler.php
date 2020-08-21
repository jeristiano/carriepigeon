<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */
namespace App\Exception\Handler;

use App\Constants\ErrorCode;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Utils\Codec\Json;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class ValidateExceptionHandler.
 */
class ValidateExceptionHandler extends ExceptionHandler
{
    /**
     * @var StdoutLoggerInterface
     */
    protected $logger;

    protected $request;

    /**
     * DryExceptionHandler constructor.
     */
    public function __construct(StdoutLoggerInterface $logger, RequestInterface $request)
    {
        $this->logger = $logger;
        $this->request = $request;
    }

    /**
     * Handle the exception, and return the specified result.
     */
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        // 判断被捕获到的异常是希望被捕获的异常
        if ($throwable instanceof ValidationException) {

            $data=Json::encode([
                'code' => ErrorCode::INVALID_PARAMETER,
                'msg' => $throwable->validator->errors()->first(),
            ], JSON_UNESCAPED_UNICODE);
            $this->logger->debug('返回给客户端数据');
            $this->logger->debug($data);
            // 阻止异常冒泡
            $this->stopPropagation();
            return $response->withStatus(422)
                ->withAddedHeader('content-type', 'application/json; charset=utf-8')
                ->withBody(new SwooleStream($data));
        }

        return $response;
        // 或者不做处理直接屏蔽异常
    }

    /**
     * 判断该异常处理器是否要对该异常进行处理.
     */
    public function isValid(Throwable $throwable): bool
    {
        if ($throwable instanceof ValidationException) {
            return true;
        }
        return false;
    }
}
