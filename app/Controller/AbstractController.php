<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\View\RenderInterface;
use Psr\Container\ContainerInterface;

/**
 * Class AbstractController
 * @package App\Controller
 */
abstract class AbstractController
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @Inject()
     * @var RequestInterface
     */
    protected $request;

    /**
     * @Inject()
     * @var \App\Component\Response
     */
    protected $response;


    /**
     * @Inject()
     * @var RenderInterface
     */
    protected $view;

    /**
     * @var LoggerFactory
     */
    protected $logger;

    /**
     * @Inject()
     * @var \Hyperf\Validation\Contract\ValidatorFactoryInterface
     */
    protected $validator;

    public function __construct (ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('default');
    }


}
