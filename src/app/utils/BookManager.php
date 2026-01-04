<?php

namespace app\utils;

use app\database\model\BookModel;
use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\http\Response;
use nova\framework\json\Json;
use nova\plugin\http\HttpClient;
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

    private string $runtime = "";

    public function __construct()
    {
        $this->runtime = RUNTIME_PATH . DS . "books" . DS;
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


    public function list(): array
    {
        $path = $this->moon . "/books.sync";
        File::mkDir($this->runtime);
        $runtime = $this->runtime . "books.sync";
        if ($this->client->download($path, $runtime)) {
            $data = file_get_contents($runtime);

            $json = Json::decode(zlib_decode($data), true);
            $items = [];
            foreach ($json as $book) {
                $items[] = new BookModel($book);
            }
            return $items;
        }
        return [];
    }

    public function push(array $books): bool
    {
        $path = $this->moon . "/books.sync";
        File::mkDir($this->runtime);
        $runtime = $this->runtime . "books.sync";
        $data = zlib_encode(Json::encode($books), ZLIB_ENCODING_DEFLATE);
        File::write($runtime, $data);
        return $this->client->upload($runtime, $path);
    }

    public function deleteBook(string $filename): bool
    {
        $path = $this->path . DS . $filename;
        return $this->client->delete($path);
    }

    public function deleteCover(string $filename): bool
    {
        $path = $this->moon . DS . "Cover" . DS . $filename . "_2.png";
        return $this->client->delete($path);
    }

    public function uploadCover(string $file, string $filename): bool
    {
        $path = $this->moon . DS . "Cover" . DS . $filename . "_2.png";
        return $this->client->upload($file, $path);
    }

    public function getCover(string $filename): string
    {
        $remotePath = $this->moon . DS . "Cover" . DS . $filename . "_2.png";
        $key = md5($filename);

        $path = RUNTIME_PATH . DS. "images" . DS ;
        File::mkdir($path);
        $file = $path . $key . ".png";

        if ($this->client->download($remotePath,$file)){
            return $file;
        }
        return '';
    }



    public function uploadBook(string $file, string $filename): bool
    {
        return $this->client->upload($file, $this->path . DS . $filename);
    }

    public static function proxy($uri):string
    {
        $uri = urldecode($uri);
        $key = md5($uri);

        $path = RUNTIME_PATH . DS. "images" . DS ;
        File::mkdir($path);
        $file = $path . $key . ".png";
        if (file_exists($file)) return $file;
        $client = HttpClient::init()
            ->timeout(300)
            ->setHeader('User-Agent', self::getRandomUserAgent())
            ->setHeader('X-Forwarded-For', self::getRandomIP())
            ->setHeader("Referer", $uri)
            ->send($uri);

        File::write($file, $client->getBody());
        return $file;
    }

    /**
     * User-Agent 池（常见的真实浏览器）
     */
    private  const USER_AGENTS = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
    ];

    /**
     * 获取随机 User-Agent
     *
     * @return string 随机UA
     */
    public static function getRandomUserAgent(): string
    {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }
    public static function getRandomIP(): string
    {
        // 生成真实的公网IP段（避免使用内网IP以防被识别）
        // 使用常见的运营商IP段
        $segments = [
            [1, 126],      // A类地址
            [128, 223],    // B类地址
        ];

        $segment = $segments[array_rand($segments)];

        return sprintf(
            '%d.%d.%d.%d',
            random_int($segment[0], $segment[1]),
            random_int(1, 254),
            random_int(1, 254),
            random_int(1, 254)
        );
    }

    public function bookExists(string $filename): bool
    {
        $path = $this->path.DS.$filename;
        try {
            $this->client->getResourceInfo($path);
            return true;
        }catch (\RuntimeException $exception){
            return false;
        }
    }
}