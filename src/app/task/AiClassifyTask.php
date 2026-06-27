<?php

declare(strict_types=1);

namespace app\task;

use app\ai\task\ClassifyTask;
use app\database\dao\BookDao;
use nova\framework\core\Logger;
use nova\framework\core\Text;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\task\TaskLogger;
use Throwable;

/**
 * AI 分类任务：对指定书籍逐本判断分类和标签并写库。
 */
class AiClassifyTask extends TaskerAbstract
{
    private const array EDITABLE = ['favorite', 'category'];

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
        $task = new ClassifyTask();
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
            TaskLogger::log($pos . '开始分类…');

            $out = $task->classify(
                $title,
                $book->author,
                $book->description,
                $book->category,
                static function (string $msg) use ($pos): void {
                    TaskLogger::log($pos . $msg);
                },
            );

            if ($out === null) {
                $fail++;
                TaskLogger::log($pos . '未识别到分类', 'warn');
                continue;
            }

            foreach (self::EDITABLE as $field) {
                if (isset($out[$field])) {
                    $book->$field = Text::parseType($book->$field, $out[$field]);
                }
            }
            $book->update_at = time() * 1000;

            if ($dao->updateModel($book)) {
                $ok++;
                TaskLogger::log($pos . '已更新');
            } else {
                $fail++;
                TaskLogger::log($pos . '写库失败', 'error');
            }
        }

        $dao->syncBooks();
        TaskLogger::log("分类完成：成功 {$ok}，失败 {$fail}");
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[AiClassifyTask] AI 分类任务异常中止: ' . $e->getMessage());
    }
}
