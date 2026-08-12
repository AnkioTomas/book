<?php

declare(strict_types=1);

namespace app\task;

use app\ai\task\MetadataFillTask;
use app\database\dao\BookDao;
use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\framework\core\Text;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\task\TaskLogger;
use Throwable;

/**
 * AI 识别任务：对指定书籍逐本检索元数据并直接写库（无需人工核对）。
 *
 * 由 Book 控制器在用户选书后通过 go() 提交到后台异步执行，进度写入后台任务面板。
 * $onlyEmpty=true 时只补空字段，不覆盖已有详情（用于「缺失详情」/上传后自动补全）。
 */
class AiIdentifyTask extends TaskerAbstract
{
    // AI 返回字段里能直接落库的可编辑列（其余如出版社/ISBN 无对应列，忽略）
    // favorite=分类(单值)，category=标签(换行分隔)
    private const array EDITABLE = ['bookName', 'author', 'description', 'rate', 'coverUrl', 'favorite', 'category'];

    /**
     * @param int[] $ids       需要识别的书籍 ID
     * @param bool  $onlyEmpty 仅填充当前为空的字段
     */
    public function __construct(
        private readonly array $ids,
        private readonly bool $onlyEmpty = false,
    ) {
    }

    public function getTimeOut(): int
    {
        return 1800;
    }

    public function onStart(): void
    {
        $dao = BookDao::getInstance();
        $resolver = MetadataFillTask::getInstance();
        $total = count($this->ids);
        $ok = 0;
        $fail = 0;
        $skip = 0;

        foreach (array_values($this->ids) as $index => $id) {
            $book = $dao->getById($id);
            if ($book === null) {
                $fail++;
                continue;
            }

            $title = $book->bookName !== '' ? $book->bookName : $book->filename;
            $pos = '（' . ($index + 1) . '/' . $total . '）《' . $title . '》';

            if ($this->onlyEmpty && !$book->isIncomplete()) {
                $skip++;
                TaskLogger::log($pos . '详情已完整，跳过');
                continue;
            }

            TaskLogger::log($pos . '开始识别…');

            $out = $resolver->fill($title, $book->author, static function (string $msg) use ($pos): void {
                TaskLogger::log($pos . $msg);
            });

            if ($out === null) {
                $fail++;
                TaskLogger::log($pos . '未识别到信息', 'warn');
                continue;
            }

            // 「已读」是本地阅读状态标签，与 AI 元数据无关：覆盖标签后需补回。
            $wasFinished = $book->hasFinishedTag();
            foreach (self::EDITABLE as $field) {
                if (!isset($out[$field])) {
                    continue;
                }
                if ($this->onlyEmpty && trim((string)$book->$field) !== '') {
                    continue;
                }
                $book->$field = Text::parseType($book->$field, $out[$field]);
            }
            if ($wasFinished) {
                $book->markFinished(true);
            }
            if (!empty($book->coverUrl)) {
                Context::instance()->cache->set("coverUrl/{$book->coverUrl}", true);
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
        $msg = "识别完成：成功 {$ok}，失败 {$fail}";
        if ($skip > 0) {
            $msg .= "，跳过 {$skip}";
        }
        TaskLogger::log($msg);
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[AiIdentifyTask] AI 识别任务异常中止: ' . $e->getMessage());
    }
}
