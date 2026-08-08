<?php

declare(strict_types=1);

namespace app\task;

use app\database\dao\BookDao;
use app\utils\BookManager\BookManager;
use app\utils\BookOrganizer\Organizer;
use nova\framework\core\Logger;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\task\TaskLogger;
use Throwable;

/**
 * 按分类整理源文件并重命名（后台任务）。
 */
class OrganizeTask extends TaskerAbstract
{
    /** @param int[] $ids */
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
        $books = $dao->getByIds($this->ids);
        $total = count($books);
        TaskLogger::log("开始整理源文件，共 {$total} 本");

        $taken = [];
        $bm = BookManager::getInstance();
        foreach ($dao->select()->commit() as $b) {
            $rel = $bm->normalizeBookPath($b->filename);
            if ($rel !== '') {
                $taken[$rel] = true;
            }
        }

        $ok = 0;
        $skip = 0;
        $fail = 0;

        foreach (array_values($books) as $index => $book) {
            $fresh = $dao->getById((int)$book->id);
            if ($fresh === null) {
                $fail++;
                continue;
            }
            $title = $fresh->bookName !== '' ? $fresh->bookName : $fresh->filename;
            $pos = '（' . ($index + 1) . '/' . $total . '）《' . $title . '》';
            TaskLogger::log($pos . '整理中…');

            $result = Organizer::organizeOne($fresh, $taken);
            if ($result['skipped']) {
                $skip++;
                TaskLogger::log($pos . '跳过：' . $result['msg']);
            } elseif ($result['ok']) {
                $ok++;
                TaskLogger::log($pos . $result['from'] . ' → ' . $result['to']);
            } else {
                $fail++;
                TaskLogger::log($pos . '失败：' . $result['msg'], 'error');
            }
        }

        $dao->syncBooks();
        TaskLogger::log("整理完成：成功 {$ok}，跳过 {$skip}，失败 {$fail}");
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[OrganizeTask] 整理任务异常中止: ' . $e->getMessage());
        TaskLogger::log('整理任务异常中止：' . $e->getMessage(), 'error');
    }
}
