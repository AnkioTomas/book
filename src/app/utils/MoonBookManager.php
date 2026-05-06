<?php

namespace app\utils;

use app\database\model\BookModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookManager\ProgressManager;
use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\core\Instance;
use nova\framework\http\Response;
use nova\framework\json\Json;
use nova\framework\json\JsonEncodeException;
use nova\plugin\http\HttpClient;
use nova\plugin\webdav\SimpleWebDAVClient;
use function nova\framework\config;
use function nova\framework\dump;

class MoonBookManager extends Instance
{
    private SimpleWebDAVClient $client;

    public string $deviceId = "";

    private string $path = "/Apps/Books";

    private string $moon = "/Apps/Books/.Moon+";

    private string $runtime = "";

    public function __construct()
    {
        $this->runtime = RUNTIME_PATH . DS . "books" . DS;
        File::mkDir($this->runtime);
        $url = config('webdav.url');
        $username = config('webdav.username');
        $password = config('webdav.password');
        $this->deviceId = config('webdav.deviceId') ?? (string)(time() * 1000);
        $this->client = new SimpleWebDAVClient($url, $username, $password);
    }

    static function instance(): MoonBookManager
    {
        return Context::instance()->getOrCreateInstance(self::class, function () {
            return new MoonBookManager();
        });
    }

    public function updateSync()
    {
        // 用户访问后台的时候应该检查是否远端有更新

        // 1. 获取远端的更新时间

        // 2. 和本地的更新时间做对比，以时间较新的为准


    }

    /**
     * 返回纯数组的数据信息
     * @return array
     *
     */
    public function list(): array
    {
        $path = $this->moon . "/books.sync";
        $sync = $this->runtime . "books.sync";
        if ($this->client->download($path, $sync)) {
            $data = file_get_contents($sync);
            return Json::decode(zlib_decode($data), true);
        }
        return [];
    }

    /**
     * 将本地的书籍推送到webdav
     * @param array $books
     * @return bool
     * @throws JsonEncodeException
     */
    public function push(array $books): bool
    {
        $path = $this->moon . "/books.sync";
        $sync = $this->runtime . "books.sync";
        $data = zlib_encode(Json::encode($books), ZLIB_ENCODING_DEFLATE);
        File::write($sync, $data);
        return $this->client->upload($sync, $path);
    }

    public function deleteBook(string $filename): bool
    {
        $path = $this->path . DS . $filename;
        return $this->client->delete($path);
    }


    public function uploadBook(string $file, string $filename): bool
    {
        return $this->client->upload($file, $this->path . DS . $filename);
    }

    public function downloadBook(string $filename, string $localPath): bool
    {
        File::mkDir(dirname($localPath));
        return $this->client->download($this->path . DS . $filename, $localPath);
    }


    public function bookExists(string $filename): bool
    {
        $path = $this->path . DS . $filename;
        try {
            $this->client->getResourceInfo($path);
            return true;
        } catch (\RuntimeException $exception) {
            return false;
        }
    }

    function delete(string $filename)
    {
        BookManager::getInstance()->deleteBook($filename);
        CoverManager::getInstance()->deleteCover($filename);
        ProgressManager::getInstance()->deleteProgress($filename);
    }


}


