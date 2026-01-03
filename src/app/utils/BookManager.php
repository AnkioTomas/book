<?php

namespace app\utils;

use app\database\model\BookModel;
use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\json\Json;
use nova\plugin\webdav\SimpleWebDAVClient;
use function nova\framework\config;
use function nova\framework\dump;

class BookManager
{
    private SimpleWebDAVClient $client;
    private string $url = "";
    private string $username = "";
    private string $password = "";

    public string $deviceId = "";

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
        File::mkDir($this->runtime);
        $runtime = $this->runtime . "books.sync";
        if ($this->client->download($path,$runtime)){
            $json = Json::decode(zlib_decode(file_get_contents($runtime)),true);
            $items = [];
            foreach ($json as $book){
                $items[] = new BookModel($book);
            }
            return $items;
        }
        return [];
    }

    public function push(array $books):bool
    {
        $path = $this->moon."/books.sync";
        File::mkDir($this->runtime);
        $runtime = $this->runtime . "books.sync";
        $data = zlib_encode(Json::encode($books),6);
        File::write($runtime, $data);
        return $this->client->upload($runtime,$path);
    }

    public function upload(string $file,string $filename):bool
    {
        return $this->client->upload($file,$this->path.DS.$filename);
    }

}