<?php

declare(strict_types=1);

namespace app\utils;

use app\database\dao\BookDao;
use app\database\dao\ReadingProgressDao;
use app\database\model\BookModel;
use app\database\model\ReadingProgressModel;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\BookManager\ProgressManager;
use nova\framework\core\Context;
use nova\plugin\corn\schedule\TaskerAbstract;
use Throwable;

class SyncBooks extends TaskerAbstract
{
    /**
     * @inheritDoc
     */
    public function getTimeOut(): int
    {
        return 300;
    }

    /**
     * @inheritDoc
     */
    public function onStart(): void
    {
        $list = BookManager::getInstance()->list();
        $count = 0;
        $total = count($list);
        /**
         * @var $book BookModel
         */
        foreach ($list as $_book) {
            $book = new BookModel($_book);
            $count++;
            Context::instance()->cache->set("sync-books", "sync webdav → database $count / $total ", 30);
            $book->splitCategory2Series();
            $progress = $this->syncProgress($book->filename);
            if ($progress && $progress->percent >= 100) {
                $book->markFinished(true);
            }
            $filename = $book->filename;
            if (BookManager::getInstance()->bookExists($filename)) {

                $dbBook = BookDao::getInstance()->getByFilename($filename);
                if (empty($dbBook)) {
                    BookDao::getInstance()->insertModel($book, true);
                } else {
                    $mergedBook = $this->mergeBookByLongestValue($book, $dbBook);
                    BookDao::getInstance()->insertModel($mergedBook, true);
                }
            } else {
                BookManager::getInstance()->delete($filename);
            }
        }

        $books = BookDao::getInstance()->select()->commit();
        $count = 0;
        $total = count($books);
        foreach ($books as &$book) {
            $count++;
            Context::instance()->cache->set("sync-books", "sync database → webdav $count / $total ", 30);
            $book = $book->pushSeries2Category();
            if (!empty($book->coverUrl) && Context::instance()->cache->get("coverUrl/{$book->coverUrl}")) {
                $file = Douban::download($book->coverUrl);
                if (CoverManager::getInstance()->uploadCover($file, $book->filename)) {
                    Context::instance()->cache->delete("coverUrl/{$book->coverUrl}");
                }
            }
        }

        Context::instance()->cache->set("sync-books", "push book → webdav ", 120);
        BookManager::getInstance()->updateBookList($books);
    }

    private function syncProgress(string $filename): ?ReadingProgressModel
    {
        $progressRaw = ProgressManager::getInstance()->getProgressText($filename);
        if (!$progressRaw) {
            return null;
        }
        $progress = ReadingProgressModel::fromString($progressRaw);
        $progress->filename = $filename;
        ReadingProgressDao::getInstance()->insertModel($progress, true);
        return $progress;
    }

    /**
     * 按字段值长度合并书籍：新值更长则覆盖；长度相同保留数据库值。
     */
    private function mergeBookByLongestValue(BookModel $incomingBook, BookModel $dbBook): BookModel
    {
        foreach (array_keys(get_object_vars($dbBook)) as $field) {
            if (!property_exists($incomingBook, $field) || $field === 'filename') {
                continue;
            }

            $dbValue = $dbBook->$field;
            $incomingValue = $incomingBook->$field;

            // 书名/作者不做常规覆盖：仅在数据库为空时，才尝试用更长值补齐
            if (in_array($field, ['bookName', 'author'], true)) {
                if ($this->isEmptyValue($dbValue)
                    && $this->valueLength($incomingValue) > $this->valueLength($dbValue)
                ) {
                    $dbBook->$field = $incomingValue;
                }
                continue;
            }

            if ($this->valueLength($incomingValue) > $this->valueLength($dbValue)) {
                $dbBook->$field = $incomingValue;
            }
        }

        return $dbBook;
    }

    /**
     * 统一计算属性值长度，支持标量和数组。
     */
    private function valueLength($value): int
    {
        $lengthFn = function (string $text): int {
            return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
        };

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            return $encoded === false ? 0 : $lengthFn($encoded);
        }

        if ($value === null) {
            return 0;
        }

        return $lengthFn((string)$value);
    }

    /**
     * 判断值是否为空（字符串会先 trim）。
     */
    private function isEmptyValue($value): bool
    {
        if ($value === null) {
            return true;
        }
        if (is_string($value)) {
            return trim($value) === '';
        }
        if (is_array($value)) {
            return count($value) === 0;
        }
        return $value === '';
    }

    /**
     * @inheritDoc
     */
    public function onStop(): void
    {
        Context::instance()->cache->set("sync-books", 'complete');
    }

    /**
     * @inheritDoc
     */
    public function onAbort(Throwable $e): void
    {
        Context::instance()->cache->set("sync-books", 'complete');
    }
}
