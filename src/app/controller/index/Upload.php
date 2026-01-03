<?php

namespace app\controller\index;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use app\utils\BookManager;
use app\utils\BookOrganizer\Parser;
use nova\framework\http\Response;
use nova\plugin\upload\FileDao;
use nova\plugin\upload\UploadController;

class Upload extends BaseController
{
    public function upload(): Response
    {

        // 使用目标 domain 的 workspace 初始化上传控制器
        $upload = UploadController::getInstance();

        // 允许上传的电子书格式
        $allowedTypes = [
            // 主流电子书格式
            "ebooks" => [
                'epub',  // EPUB - 最流行的开放格式
                'mobi',  // MOBI - Amazon Kindle
                'azw',   // AZW - Amazon Kindle
                'azw3',  // AZW3 - Amazon Kindle (KF8)
            ],
            // PDF 和文档格式
            "documents" => [
                'pdf',   // PDF
                'txt',   // 纯文本
            ],
        ];

        return $upload->upload($this->request, $allowedTypes);
    }

    public function publish(): Response
    {

        $name = $this->request->post('name', '');
        $file = FileDao::getInstance()->getFile($name);
        if (empty($file)) return Response::asJson(["code" => 404, "msg" => "文件不存在"]);

        $path = $file->path;

        if (!file_exists($path)) {
            return Response::asJson(["code" => 404, "msg" => "文件不存在"]);
        }

        $model = BookDao::getInstance()->getByFileName($file->name);
        $bookManager = BookManager::instance();
        if ($bookManager->uploadBook($file->path, $file->name)) {
            [$author, $title, $year, $ext] = Parser::filename($file->name);
            if (empty($title)){
                return Response::asJson(["code" => 400, "msg" => "后台上传失败"]);
            }
            if (empty($model)){
                $model = new BookModel();
                $model->deviceId = $bookManager->deviceId;
                $model->addTime = time() * 1000;
                $model->filename = $file->name;

                $model->bookName = $title;
                $model->author = $author ?? "";
                $model->downloadUrl = "[WebDav]/Apps/Books/".$file->name;

                BookDao::getInstance()->insertModel($model);
            }

            return Response::asJson(["code" => 200, "msg" => "后台上传成功: $title"]);
        } else {
            return Response::asJson(["code" => 400, "msg" => "后台上传失败"]);
        }


    }


}