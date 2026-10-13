<?php

declare(strict_types=1);

namespace app\utils;

use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\core\Logger;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpException;

class Douban
{
    public static function download($uri): string
    {
        $uri = urldecode($uri);
        $key = md5($uri);

        $path = RUNTIME_PATH . DS. "images" . DS ;
        File::mkdir($path);
        $file = $path . $key . ".png";
        if (file_exists($file)) {
            return $file;
        }

        // 豆瓣限流时一律回 418，失败后短时间内不再请求，免得越刷越被限
        $cache = Context::instance()->cache;
        $failKey = "douban_fail/{$key}";
        if ($cache->get($failKey)) {
            return '';
        }

        try {
            $client = HttpClient::init()
                ->timeout(15)
                ->setHeader('User-Agent', self::getRandomUserAgent())
                ->setHeader('X-Forwarded-For', self::getRandomIP())
                ->setHeader("Referer", $uri)
                ->send($uri);
        } catch (HttpException $e) {
            Logger::warning("Douban download failed: {$uri} : " . $e->getMessage());
            $cache->set($failKey, true, 600);
            return '';
        }

        if ($client->getHttpCode() !== 200) {
            Logger::warning("Douban download failed: {$uri} : HTTP " . $client->getHttpCode());
            $cache->set($failKey, true, 600);
            return '';
        }

        File::write($file, $client->getBody());
        return $file;
    }

    /**
     * User-Agent 池（常见的真实浏览器）
     */
    private const USER_AGENTS = [
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

}
