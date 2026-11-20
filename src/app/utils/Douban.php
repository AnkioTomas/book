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
    /**
     * 豆瓣封面优先取大图（l），没有再退回小图（s）
     *
     * @return string[] 按优先级排列的候选地址
     */
    public static function coverCandidates(string $uri): array
    {
        if (!preg_match('#/view/subject/[sml]/public/#', $uri)) {
            return [$uri];
        }
        $list = [];
        foreach (['l', 'm', 's'] as $size) {
            $list[] = preg_replace('#/view/subject/[sml]/public/#', "/view/subject/{$size}/public/", $uri);
        }
        // 原地址若本身就是更小的尺寸，也要保留为最后兜底
        return array_values(array_unique(array_merge($list, [$uri])));
    }

    public static function largeCover(?string $uri): string
    {
        return $uri ? self::coverCandidates($uri)[0] : '';
    }

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

        foreach (self::coverCandidates($uri) as $candidate) {
            try {
                $client = HttpClient::init()
                    ->timeout(15)
                    ->setHeader('User-Agent', self::getRandomUserAgent())
                    ->setHeader('X-Forwarded-For', self::getRandomIP())
                    ->setHeader("Referer", $candidate)
                    ->send($candidate);
            } catch (HttpException $e) {
                Logger::warning("Douban download failed: {$candidate} : " . $e->getMessage());
                continue;
            }

            if ($client->getHttpCode() !== 200) {
                Logger::warning("Douban download failed: {$candidate} : HTTP " . $client->getHttpCode());
                continue;
            }

            File::write($file, $client->getBody());
            return $file;
        }

        $cache->set($failKey, true, 600);
        return '';
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
