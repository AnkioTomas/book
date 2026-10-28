<?php

declare(strict_types=1);

namespace app\utils;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookOrganizer\Parser;
use nova\framework\event\EventManager;

/**
 * 本地电子书入库（上传分片完成 / Zlib 下载后共用）。
 */
final class BookIngest
{
    /**
     * @return array{ok: bool, msg: string, id?: int, title?: string, isNew?: bool}
     */
    public static function fromLocalFile(string $path, string $filename, string $series = ''): array
    {
        if ($path === '' || $filename === '' || !is_file($path)) {
            return ['ok' => false, 'msg' => '文件不存在'];
        }

        $dao = BookDao::getInstance();
        $manager = BookManager::getInstance();
        $model = $dao->getByFileName($filename);
        $isNew = empty($model);

        if (!$manager->uploadBook($path, $filename)) {
            return ['ok' => false, 'msg' => '后台上传失败'];
        }

        [$author, $title, $year, $ext] = Parser::filename($filename);
        $meta = Parser::epubMeta($path, $filename);
        if (!empty($meta['title'])) {
            $title = $meta['title'];
        }
        if (!empty($meta['author'])) {
            $author = $meta['author'];
        }
        if ($title === '' || $title === null) {
            return ['ok' => false, 'msg' => '无法解析书名'];
        }

        $id = 0;
        if ($isNew) {
            $model = new BookModel();
            $model->deviceId = $manager->deviceId;
            $model->addTime = time() * 1000;
            $model->update_at = time() * 1000;
            $model->filename = $filename;
            $model->series = $series;
            $model->bookName = $title;
            $model->author = $author ?? '';
            $model->downloadUrl = $manager->downloadUrlFor($filename);
            $coverPath = Parser::cover($path, $model);
            if (!empty($coverPath)) {
                CoverManager::getInstance()->uploadCover($coverPath, $model->filename);
            }
            $id = (int)$dao->insertModel($model);
            $payload = ['id' => $id];
            EventManager::trigger('book.uploaded', $payload);
        } else {
            $id = (int)($model->id ?? 0);
        }

        $dao->syncBooks();

        return [
            'ok' => true,
            'msg' => '入库成功: ' . $title,
            'id' => $id,
            'title' => $title,
            'isNew' => $isNew,
        ];
    }
}
