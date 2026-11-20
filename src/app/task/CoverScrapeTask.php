<?php

declare(strict_types=1);

namespace app\task;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookOrganizer\Parser;
use nova\framework\core\File;
use nova\framework\core\Logger;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\task\TaskLogger;
use Throwable;

/**
 * 封面刮削任务：对指定书籍逐本下载、提取封面并上传。
 *
 * 由 Book 控制器在用户选书后提交到后台异步执行，进度写入后台任务面板。
 */
class CoverScrapeTask extends TaskerAbstract
{
    /**
     * @param int[] $ids 需要刮削封面的书籍 ID
     */
    public function __construct(private readonly array $ids)
    {
    }

    public function getTimeOut(): int
    {
        return 1800;
    }

    public function onStart(): void
    {
        $dao = BookDao::getInstance();
        $total = count($this->ids);
        $ok = 0;
        $fail = 0;

        foreach (array_values($this->ids) as $index => $id) {
            $book = $dao->getById($id);
            if ($book === null) {
                $fail++;
                continue;
            }

            $title = $book->bookName !== '' ? $book->bookName : $book->filename;
            $pos = '（' . ($index + 1) . '/' . $total . '）《' . $title . '》';
            TaskLogger::log($pos . '开始刮削…');

            $error = self::scrapeOne($book);
            if ($error === null) {
                $ok++;
                TaskLogger::log($pos . '刮削成功');
            } else {
                $fail++;
                TaskLogger::log($pos . $error, 'error');
            }
        }

        $dao->syncBooks();
        TaskLogger::log("刮削完成：成功 {$ok}，失败 {$fail}");
    }

    /**
     * 从书籍文档元数据提取封面并上传，成功返回 null，失败返回原因。
     */
    public static function scrapeOne(object $book): ?string
    {
        $tempPath = RUNTIME_PATH . DS . 'temp' . DS . $book->filename;
        if (!BookManager::getInstance()->downloadBook($book->filename, $tempPath)) {
            return '下载书籍失败';
        }

        try {
            $coverPath = Parser::cover($tempPath, $book);
            if (empty($coverPath)) {
                return '文档中没有可提取的封面';
            }
            return CoverManager::getInstance()->uploadCover($coverPath, $book->filename) ? null : '上传封面失败';
        } finally {
            File::del($tempPath);
        }
    }

    /**
     * 从文档元数据同步书名/作者/简介和封面（同步执行）。
     * 返回 ['changes' => 变更描述列表, 'error' => 失败原因|null]；元数据与封面都没取到才算失败。
     */
    public static function syncFromDocument(BookModel $book): array
    {
        $tempPath = RUNTIME_PATH . DS . 'temp' . DS . $book->filename;
        if (!BookManager::getInstance()->downloadBook($book->filename, $tempPath)) {
            return ['changes' => [], 'error' => '下载书籍失败'];
        }

        $changes = [];
        try {
            $meta = Parser::epubMeta($tempPath, $book->filename);
            $labels = ['title' => ['bookName', '书名'], 'author' => ['author', '作者'], 'description' => ['description', '简介']];
            foreach ($labels as $key => [$field, $label]) {
                $value = trim((string)($meta[$key] ?? ''));
                if ($value !== '' && $value !== $book->$field) {
                    $changes[] = $key === 'description'
                        ? "{$label}已更新"
                        : "{$label}：" . ($book->$field !== '' ? $book->$field : '空') . ' → ' . $value;
                    $book->$field = $value;
                }
            }

            $coverPath = Parser::cover($tempPath, $book);
            if ($coverPath !== '' && CoverManager::getInstance()->uploadCover($coverPath, $book->filename)) {
                $changes[] = '封面已更新';
            }
        } finally {
            File::del($tempPath);
        }

        if ($changes === [] && $meta === []) {
            return ['changes' => [], 'error' => '文档中没有可读取的元数据或封面'];
        }
        if ($changes !== []) {
            $book->update_at = time() * 1000;
            BookDao::getInstance()->updateModel($book);
        }
        return ['changes' => $changes, 'error' => null];
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[CoverScrapeTask] 封面刮削任务异常中止: ' . $e->getMessage());
    }
}
