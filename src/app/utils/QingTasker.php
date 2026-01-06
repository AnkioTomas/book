<?php

declare(strict_types=1);

namespace app\utils;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use app\utils\BookOrganizer\Parser;
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
class QingTasker extends TaskerAbstract
{
    private const API_URL = "https://weixin65732.raysclab.com/ebook/v1.0/ebookPdf/getPdfByAppId?appId=2136122&t=OLA755jLm";
    private const PDF_BASE_URL = "https://oss.5rs.me/oss/uploadfe/pdf";
    private const DEFAULT_COOKIE = "userInfo=officialAccountsId%3D999%26wechatUserId%3D316275201%26hasSnapsis%3D0%26channelId%3D1000005632%26userType%3DTEMP%26spreadType%3D0";

    private string $downloadDir;
    private string $cookie;

    public function __construct()
    {
        $this->downloadDir = RUNTIME_PATH . DS . "青年文摘";
        File::mkdir($this->downloadDir);
        $this->cookie = self::DEFAULT_COOKIE;

    }

    public function getTimeOut(): int
    {
        return 7200; // 5分钟超时
    }

    public function onStart(): void
    {
        Logger::info("青年文摘下载任务开始");
        if (Context::instance()->cache->get(QingTasker::class) === true) return;
        Context::instance()->cache->set(QingTasker::class,true ,3600);
        $ebooks = $this->fetchEbookList();
        if (empty($ebooks)) {
            Logger::warning("未获取到电子书列表");
            Context::instance()->cache->delete(QingTasker::class);
            return;
        }

        Logger::info("获取到 " . count($ebooks) . " 本电子书");

        $success = 0;
        $bookManager = BookManager::instance();
        foreach ($ebooks as $ebook) {
            Context::instance()->cache->set(QingTasker::class,true ,3600);
            $filename = $ebook['ebookName'] ?? "";
            if (empty($filename)) continue;
            $filename .=".pdf";
            $model = BookDao::getInstance()->getByFileName($filename);
            if (empty($model) && $this->downloadEbook($ebook,$filename)) {
                $filepath = $this->downloadDir . '/' . $filename;
                if ($bookManager->uploadBook($filepath, $filename)) {
                    $model = new BookModel();
                    $model->deviceId = $bookManager->deviceId;
                    $model->addTime = time() * 1000;
                    $model->filename = $filename;
                    $model->series = "青年文摘";

                    $title = pathinfo($filename, PATHINFO_FILENAME);

                    $model->bookName = $title;
                    $model->author = "青年文摘编辑部";
                    $model->favorite = "杂志";
                    $model->downloadUrl = "[WebDav]/Apps/Books/" . $filename;
                    $model->extractSeriesNumber();
                    $path = Parser::cover($filepath,$model);
                    if (!empty($path)){
                        $bookManager->uploadCover($path, $model->filename);
                    }
                    BookDao::getInstance()->insertModel($model);
                }
                $success++;
            }else{
                $model->extractSeriesNumber();
            }


        }
        Context::instance()->cache->delete(QingTasker::class);
        Logger::info("下载完成: {$success}/" . count($ebooks));
        BookDao::getInstance()->syncBooks();


    }

    public function onStop(): void
    {
        Logger::info("青年文摘下载任务结束");
        File::del($this->downloadDir,true);
    }

    public function onAbort(Throwable $e): void
    {
        Context::instance()->cache->delete(QingTasker::class);
        Logger::error("青年文摘下载任务异常终止: " . $e->getMessage(), $e->getTrace());
    }

    private function dns():array
    {
        return [

        ];
    }
    /**
     * 获取电子书列表
     */
    private function fetchEbookList(): array
    {
        $client = HttpClient::init()
            ->setHeader('Referer', 'https://weixin65732.raysclab.com/')
            ->setOption(CURLOPT_COOKIE, $this->cookie)
            ->timeout(300)
            ->setOption(CURLOPT_DNS_SERVERS, '223.5.5.5,223.6.6.6')
            ->get();

        $response = $client->send(self::API_URL);

        if (!$response || $response->getHttpCode() !== 200) {
            Logger::error("API请求失败: HTTP " . ($response->getHttpCode()));
            return [];
        }

        $data = Json::decode($response->getBody(), true);

        if (!isset($data['errCode']) || $data['errCode'] !== 0) {
            Logger::error("API返回错误: " . ($data['message'] ?? '未知错误'));
            return [];
        }

        return $data['data'] ?? [];
    }

    /**
     * 下载单个电子书
     */
    private function downloadEbook(array $ebook,string $filename): bool
    {
        $resource = $ebook['resource'] ?? null;
        if ($resource === null) {
            Logger::warning("电子书资源数据不存在: " . Json::encode($ebook));
            return false;
        }

        $fileId = $resource['fileId'] ?? '';
        $ebookName = $ebook['ebookName'] ?? '';

        if (empty($fileId) || empty($ebookName)) {
            Logger::warning("电子书数据不完整: fileId={$fileId}, name={$ebookName}");
            return false;
        }


        $filepath = $this->downloadDir . '/' . $filename;

        // 跳过已存在的文件
        if (file_exists($filepath)) {
            Logger::info("跳过已存在文件: {$filename}");
            return true;
        }

        $pdfUrl = self::PDF_BASE_URL . '/' . $fileId . '.pdf';

        try {
            Logger::info("开始下载: {$filename}");
            $client = HttpClient::init()
                ->timeout(300)
                ->setOption(CURLOPT_DNS_SERVERS, '223.5.5.5,223.6.6.6')
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

