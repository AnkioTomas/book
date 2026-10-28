<?php

declare(strict_types=1);

namespace app\controller\index;

use app\utils\BookIngest;
use nova\framework\http\Response;
use nova\plugin\login\controller\BaseAPIController;
use nova\plugin\upload\FileDao;
use nova\plugin\upload\UploadController;

class Upload extends BaseAPIController
{
    public function upload(): Response
    {
        $upload = UploadController::getInstance();

        $allowedTypes = [
            'ebooks' => [
                'epub',
                'mobi',
                'azw',
                'azw3',
            ],
            'documents' => [
                'pdf',
                'txt',
            ],
        ];

        return $upload->upload($this->request, $allowedTypes);
    }

    public function publish(): Response
    {
        $name = $this->request->post('name', '');
        $series = $this->request->post('series', '');
        $file = FileDao::getInstance()->getFile($name);
        if (empty($file)) {
            return Response::asJson(['code' => 404, 'msg' => '文件不存在']);
        }

        $result = BookIngest::fromLocalFile($file->path, $file->name, (string)$series);
        if (!$result['ok']) {
            $code = str_contains($result['msg'], '不存在') ? 404 : 400;
            return Response::asJson(['code' => $code, 'msg' => $result['msg']]);
        }

        return Response::asJson(['code' => 200, 'msg' => $result['msg']]);
    }
}
