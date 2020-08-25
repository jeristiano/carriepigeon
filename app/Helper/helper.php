<?php

use App\Constants\WsMessage;
use Hyperf\Server\Exception\ServerException;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Utils\Codec\Json;
use Hyperf\Utils\Context;
use Psr\Http\Message\ServerRequestInterface;

if (!function_exists('app')) {
    /**
     * 获得di容器
     * @param int $length
     * @return \Psr\Container\ContainerInterface
     */
    function app ()
    {
        return ApplicationContext::getContainer();
    }
}


/**
 * 获得客户端的真实ip
 */
if (!function_exists('get_client_ip')) {
    /**
     * @return mixed|string
     */
    function get_client_ip ()
    {
        try {
            /**
             * @var ServerRequestInterface $request
             */
            $request = Context::get(ServerRequestInterface::class);


            $ip_addr = $request->getHeaderLine('x-forwarded-for');
            if (verify_ip($ip_addr)) {
                return $ip_addr;
            }
            $ip_addr = $request->getHeaderLine('remote-host');
            if (verify_ip($ip_addr)) {
                return $ip_addr;
            }
            $ip_addr = $request->getHeaderLine('x-real-ip');
            if (verify_ip($ip_addr)) {
                return $ip_addr;
            }
            $ip_addr = $request->getServerParams()['remote_addr'] ?? '0.0.0.0';
            if (verify_ip($ip_addr)) {
                return $ip_addr;
            }
        } catch (\Throwable $e) {
            return '0.0.0.0';
        }
        return '0.0.0.0';
    }
}
if (!function_exists('verify_ip')) {
    /**
     * @param $realip
     * @return mixed
     */
    function verify_ip ($realip)
    {
        return filter_var($realip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }
}


if (!function_exists('wsSuccess')) {
    /**
     * @param string $cmd
     * @param string $method
     * @param array  $data
     * @param string $msg
     * @return string
     */
    function wsSuccess ($cmd = WsMessage::WS_MESSAGE_CMD_EVENT, $method = '', $data = [], $msg = 'Success')
    {
        $result = [
            'cmd' => $cmd,
            'method' => $method,
            'msg' => $msg,
            'data' => $data
        ];

        return Json::encode($result);
    }
}

if (!function_exists('wsError')) {
    /**
     * @param string $msg
     * @param string $cmd
     * @param array  $data
     * @return string
     */
    function wsError ($msg = 'Error', $cmd = WsMessage::WS_MESSAGE_CMD_ERROR, $data = [])
    {
        $result = [
            'cmd' => $cmd,
            'msg' => $msg,
            'data' => $data
        ];
        return Json::encode($result);
    }
}

if (!function_exists('exception')) {
    /**
     * @param        $class_name
     * @param int    $code
     * @param string $message
     */
    function exception ($class_name, $code = 0, $message = 'success')
    {
        if (!class_exists($class_name)) {
            throw new ServerException($class_name . '：此方法不存在');
        }
        throw new $class_name($code, $message);
    }
}

if (!function_exists('debug_print')) {

    /**
     * @param $messge
     */
    function debug_print ($message)
    {
        $time = date('Ymd');
        $filename = BASE_PATH . '/runtime/' . 'debug_print_' . $time . '.log';
        file_put_contents($filename, var_export($message, true), FILE_APPEND);

    }
}
