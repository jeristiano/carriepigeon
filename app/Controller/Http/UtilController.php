<?php

declare(strict_types=1);

namespace App\Controller\Http;

use App\Controller\AbstractController;
use App\Exception\ApiException;
use App\Request\FileUploadReuquest;
use App\Request\UploadRequest;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use League\Flysystem\Filesystem;
use App\Middleware\JwtAuthMiddleware;

/**
 * @Controller(prefix="util")
 * Class UtilController
 * @package App\Controller\Http
 */
class UtilController extends AbstractController
{
    /**
     * @Middleware(JwtAuthMiddleware::class)
     * @RequestMapping(path="uploadImg",methods="POST")
     * @param \League\Flysystem\Filesystem
     */
    public function uploadImage (UploadRequest $request,Filesystem $filesystem)
    {
        // Process Upload
        $file = $request->file('file');
        $stream = fopen($file->getRealPath(), 'r+');

        [$real_path, $relative_path] = $this->getImagePath($file);
        $filesystem->writeStream(
            $real_path,
            $stream
        );
        fclose($stream);
        return $this->response->success([
            'src' => env('STORAGE_IMG_URL') . $relative_path
        ]);
    }

    /**
     *
     * @param $file
     */
    private function getImagePath ($file)
    {
        $extName = $file->getExtension();
        $real_dir = '/storage/images/upload/' . $this->getRelativePath();
        if (!is_dir($real_dir)) @mkdir($real_dir, 0777, true);
        $fileName = time() . rand(1, 999999);
        return [$real_dir . $fileName . '.' . $extName, $this->getRelativePath() . $fileName . '.' . $extName];
    }


    /**
     * @return string
     */
    private function getRelativePath ()
    {
        return date('Ymd') . '/';
    }

    /**
     * @Middleware(JwtAuthMiddleware::class)
     * @RequestMapping(path="uploadFile",methods="POST")
     * @param \League\Flysystem\Filesystem
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function uploadFile(FileUploadReuquest $request,Filesystem $filesystem)
    {
        // Process Upload
        $file = $request->file('file');
        $stream = fopen($file->getRealPath(), 'r+');

        [$real_path, $relative_path] = $this->getFilePath($file);
        $filesystem->writeStream(
            $real_path,
            $stream
        );
        fclose($stream);
        return $this->response->success([
            'src' => env('STORAGE_FILE_URL') . $relative_path
        ]);
    }

    /**
     * @param $file
     */
    private function getFilePath ($file)
    {
        $extName = $file->getExtension();
        $real_dir = '/storage/files/upload/' . $this->getRelativePath();
        if (!is_dir($real_dir)) @mkdir($real_dir, 0777, true);
        $fileName = time() . rand(1, 999999);
        return [$real_dir . $fileName . '.' . $extName, $this->getRelativePath() . $fileName . '.' . $extName];
    }
}
