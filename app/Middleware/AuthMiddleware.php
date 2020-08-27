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

use Hyperf\Contract\ContainerInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\HttpServer\Router\DispatcherFactory;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;
use Hyperf\WebSocketServer\Security;
use Phper666\JWTAuth\JWT;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class AuthMiddleware
 * @package App\Middleware
 */
class AuthMiddleware implements MiddlewareInterface
{
    private const HANDLE_SUCCESS_CODE = 101;

    private const HANDLE_FAIL_CODE = 401;

    private const HANDLE_BAD_REQUEST_CODE = 400;

    protected $jwt;
    protected $container;
    /**
     * @var  StdoutLoggerInterface
     */
    protected $logger;

    public function __construct (ContainerInterface $container, JWT $jwt)
    {
        $this->jwt = $jwt;
        $this->container = $container;
        $this->logger = $this->container->get(StdoutLoggerInterface::class);
    }

    /**
     * auth认证的中间件
     * Process an incoming server request and return a response, optionally delegating
     * response creation to a handler.
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws  \Psr\SimpleCache\InvalidArgumentException
     * @throws \Throwable
     */
    public function process (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = Context::get(ResponseInterface::class);
        $request = Context::get(ServerRequestInterface::class);

        $key = $request->getHeaderLine(Security::SEC_WEBSOCKET_KEY);
        $token = $request->getHeaderLine(Security::SEC_WEBSOCKET_PROTOCOL);

        if (!$this->checkToken($token)) {
            return $response
                ->withStatus(self::HANDLE_BAD_REQUEST_CODE);
        }

        $this->overrideContext($token);


        //握手
        $security = $this->container->get(Security::class);
        $headers = $security->handshakeHeaders($key);
        $controller = $this->dispatch($request);


        foreach ($headers as $key => $value) {
            $response = $response->withHeader($key, $value);
        }

        return $response
            ->withStatus(self::HANDLE_SUCCESS_CODE)
            ->withHeader(Security::SEC_WEBSOCKET_PROTOCOL, $token)
            ->withAttribute('class', $controller);

    }


    /**
     * @param $request
     * @param $token
     * @return mixed
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function checkToken ($token)
    {
        try {
            $this->jwt->checkToken($token);
            return true;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }

    /**
     * @param $token
     */
    private function overrideContext ($token)
    {
        $jwtData = $this->jwt->getParserData($token);
        WsContext::set('user', $jwtData);
    }


    /**
     * 转到路由
     * @param $request
     * @return mixed
     */
    private function dispatch ($request)
    {
        $uri = $request->getUri();
//        $this->logger->debug('请求的地址：' . $uri);
        $dispatcher = $this->container
            ->get(DispatcherFactory::class)
            ->getDispatcher('ws');
        $routes = $dispatcher->dispatch($request->getMethod(), $uri->getPath());
        return $routes[1]->callback;
    }

}
