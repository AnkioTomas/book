<?php

declare(strict_types=1);

namespace app\utils;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use nova\framework\core\Context;
use nova\framework\core\File;
use nova\framework\core\Logger;
use nova\framework\json\Json;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpDownloadManager;
use Throwable;

/**
 * 青年文摘自动下载任务
 */
class DuzhegeTasker extends TaskerAbstract
{
    private const string API_URL = "https://yun.duzhege.cn/api/fs/list";
    private string $downloadDir;

    public function __construct(private readonly string $book, private readonly string $path, private readonly int $minYear)
    {
        $this->downloadDir = RUNTIME_PATH . DS . $book;
        File::mkdir($this->downloadDir);
    }

    public function getTimeOut(): int
    {
        return 7200; // 5分钟超时
    }

    public function onStart(): void
    {
        Logger::info($this->book . "下载任务开始");
        if (Context::instance()->cache->get($this->book) === true) return;
        Context::instance()->cache->set($this->book, true, 3600);
        $ebooks = $this->fetchEbookList();
        if (empty($ebooks)) {
            Logger::warning("未获取到电子书列表");
            Context::instance()->cache->delete($this->book);
            return;
        }

        Logger::info("获取到 " . count($ebooks) . " 本电子书");

        $success = 0;
        $bookManager = BookManager::instance();
        foreach ($ebooks as $ebook) {
            Context::instance()->cache->set($this->book, true, 3600);
            $filename = $ebook['name'] ?? "";
            $url = $ebook['url'] ?? "";
            if (empty($filename)) continue;
            $model = BookDao::getInstance()->getByFileName($filename);
            if (empty($model) && $this->downloadEbook($url, $filename)) {
                $filepath = $this->downloadDir . '/' . $filename;
                if ($bookManager->uploadBook($filepath, $filename)) {
                    $model = new BookModel();
                    $model->deviceId = $bookManager->deviceId;
                    $model->addTime = time() * 1000;
                    $model->filename = $filename;
                    $model->series = $this->book;

                    $title = pathinfo($filename, PATHINFO_FILENAME);

                    $model->bookName = $title;
                    $model->author = $this->book . "编辑部";
                    $model->favorite = "杂志";
                    $model->downloadUrl = "[WebDav]/Apps/Books/" . $filename;
                    $model->extractSeriesNumber();
                    BookDao::getInstance()->insertModel($model);
                }
                $success++;
            }else{
                $model->extractSeriesNumber();
            }


        }
        Context::instance()->cache->delete($this->book);
        Logger::info("下载完成: {$success}/" . count($ebooks));
        BookDao::getInstance()->syncBooks();
        File::del($this->downloadDir);
    }

    public function onStop(): void
    {
        Logger::info($this->book . "下载任务结束");
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error($this->book . "下载任务异常终止: " . $e->getMessage(), $e->getTrace());
    }

    /**
     * 获取电子书列表
     */
    private function fetchEbookList(): array
    {

        $year = $this->minYear;

        $now = (int)date("Y");
        $path = $this->path;

        $links = [];

        for ($i = $year; $i <= $now; $i++) {
            $link = str_replace("{year}", (string)$i, $path);
            $response = HttpClient::init()
                ->setHeader('Referer', 'https://yun.duzhege.cn')
                ->timeout(300)
                ->post([
                    'path' => $link,
                    'password' => '',
                    'page' => 1,
                    'per_page' => 0,
                    'refresh' => false
                ])
                ->send(self::API_URL);
            if (!$response || $response->getHttpCode() !== 200) {
                Logger::error("API请求失败: HTTP " . ($response->getHttpCode()));
                continue;
            }

            $data = Json::decode($response->getBody(), true);

            if ($data['code'] != 200) {
                Logger::error("API请求失败 $link ", $data);
                continue;
            }
            // https://yun.duzhege.cn/d/OneDrive/%E6%B0%91%E9%97%B4%E4%BC%A0%E5%A5%87%E6%95%85%E4%BA%8B/2024/%E6%AD%A3%E5%88%8A/%E3%80%8A%E6%B0%91%E9%97%B4%E4%BC%A0%E5%A5%87%E6%95%85%E4%BA%8B%E3%80%8B2024%E5%B9%B4%E7%AC%AC12%E6%9C%9F.pdf

            $content = $data['data']['content'];

            foreach ($content as $ebook) {
                $links[] = [
                    "name" => $ebook['name'],
                    "url" => "https://yun.duzhege.cn/d" . $link . "/" . rawurlencode($ebook['name']),
                ];
            }
        }

        return $links;
    }

    /**
     * 下载单个电子书
     */
    private function downloadEbook(string $pdfUrl, string $filename): bool
    {

        $filepath = $this->downloadDir . '/' . $filename;

        // 跳过已存在的文件
        if (file_exists($filepath)) {
            Logger::info("跳过已存在文件: {$filename}");
            return true;
        }

        try {
            Logger::info("开始下载: {$filename}");
            $client = HttpClient::init()
                ->timeout(300)
                ->get();
            $download = new HttpDownloadManager($client);

            if (!$download->download($pdfUrl, $filepath)) {
                Logger::error("下载失败: {$filename} - HTTP ");
                return false;
            }
            return true;
        } catch (Throwable $e) {
            Logger::error("下载异常: {$filename} - " . $e->getMessage());

            // 清理失败的文件
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            return false;
        }
    }

}

