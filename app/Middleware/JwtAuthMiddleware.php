<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace App\Middleware;

use App\Component\Response;
use App\Constants\ErrorCode;
use App\Exception\BusinessException;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Utils\Context;
use Phper666\JWTAuth\Exception\TokenValidException;
use Phper666\JWTAuth\JWT;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class JwtAuthMiddleware
 * @package App\Middleware
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * @var Response
     */
    protected $response;

    protected $prefix = 'Bearer';

    protected $jwt;

    public function __construct (HttpResponse $response, JWT $jwt)
    {
        $this->response = $response;
        $this->jwt = $jwt;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request
     * @param \Psr\Http\Server\RequestHandlerInterface $handler
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function process (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {

        $token = $this->getToken($request);
        try {
            $this->jwt->checkToken($token);
        } catch (TokenValidException $e) {
            throw new BusinessException(ErrorCode::AUTH_ERROR, $e->getMessage());
        }

        $jwtData = $this->jwt->getParserData($token);


        //复写上下文
        $request = Context::override(ServerRequestInterface::class, function (ServerRequestInterface $request)
        use ($jwtData) {
            return $request->withAttribute('user', $jwtData);
        });
        return $handler->handle($request);

    }

    /**
     * @param $request
     * @return string
     */
    private function getToken ($request): string
    {
        $token = $request->getHeader('Authorization')[0] ?? '';

        if (empty($token)) {
            if (!$request->getQueryParams()['token']) {
                throw new BusinessException(ErrorCode::AUTH_ERROR, 'token not passed');
            }
            $token = $this->prefix . ' ' . ($request->getQueryParams()['token'] ?? '');
        }
        $token = ucfirst($token);
        $arr = explode($this->prefix . ' ', $token);
        return $arr[1] ?? '';
    }
}
