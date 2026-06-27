<?php

declare(strict_types=1);

namespace app\task;

use app\database\dao\BookDao;
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

            $tempPath = RUNTIME_PATH . DS . 'temp' . DS . $book->filename;
            if (!BookManager::getInstance()->downloadBook($book->filename, $tempPath)) {
                $fail++;
                TaskLogger::log($pos . '下载书籍失败', 'error');
                continue;
            }

            $coverPath = Parser::cover($tempPath, $book);
            if (empty($coverPath)) {
                File::del($tempPath);
                $fail++;
                TaskLogger::log($pos . '提取封面失败', 'warn');
                continue;
            }

            if (CoverManager::getInstance()->uploadCover($coverPath, $book->filename)) {
                $ok++;
                TaskLogger::log($pos . '刮削成功');
            } else {
                $fail++;
                TaskLogger::log($pos . '上传封面失败', 'error');
            }
            File::del($tempPath);
        }

        $dao->syncBooks();
        TaskLogger::log("刮削完成：成功 {$ok}，失败 {$fail}");
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[CoverScrapeTask] 封面刮削任务异常中止: ' . $e->getMessage());
    }
}
