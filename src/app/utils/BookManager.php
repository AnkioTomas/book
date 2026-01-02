<?php

namespace app\utils;

use app\database\model\BookModel;
use nova\framework\core\Context;
use nova\framework\json\Json;
use nova\plugin\webdav\SimpleWebDAVClient;
use function nova\framework\config;

class BookManager
{
    private SimpleWebDAVClient $client;
    private string $url = "";
    private string $username = "";
    private string $password = "";

    private string $deviceId = "";

    private string $path = "/Apps/Books";

    private string $moon = "/Apps/Books/.Moon+";

    private string $runtime  = "";
    public function __construct()
    {
        $this->runtime = RUNTIME_PATH . DS . "books".DS;
        $this->url = config('webdav.url');
        $this->username = config('webdav.username');
        $this->password = config('webdav.password');
        $this->deviceId = config('webdav.deviceId') ?? (string)(time() * 1000);
        $this->client = new SimpleWebDAVClient($this->url, $this->username, $this->password);
    }

    static function instance(): BookManager
    {
        return Context::instance()->getOrCreateInstance(self::class, function () {
            return new BookManager();
        });
    }


    public function list():array
    {
        $path = $this->moon."/books.sync";
        $runtime = $this->runtime . "books.sync";
        if ($this->client->download($path,$runtime)){
            $json = Json::decode(zlib_decode($runtime),true);
            $items = [];
            foreach ($json as $book){
                $items[] = new BookModel($book);
            }
            return $items;
        }
        return [];
    }

}