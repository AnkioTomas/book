<?php

declare(strict_types=1);

namespace app\utils\BookManager;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use nova\framework\core\Context;

class SyncManager extends BaseManager
{
    private int $databaseTime = 0;

    private int $bookListTime = 0;

    public function __construct()
    {
        parent::__construct();
        $this->databaseTime = Context::instance()->cache->get("database_time", 0);
        //
        $file = $this->client->getResourceInfo($this->moon . "/books.sync");
        if ($file) {
            $this->bookListTime = $file["mtime"];
        }
    }

    // New, clearer method names
    public function isWebdavNewerThanDatabase(): bool
    {
        return $this->bookListTime > $this->databaseTime;
    }

    public function isDatabaseNewerThanWebdav(): bool
    {
        return $this->databaseTime > $this->bookListTime;
    }

    public function startSync()
    {
        // 同步优化
        Context::instance()->cache->set("last_sync_time", time());

        $webdavBooks = BookManager::getInstance()->list();

        // 从数据库获取当前书籍列表（BookModel 实例数组）
        $databaseBooks = BookDao::getInstance()->select()->commit();

        // 书籍同步
        // 若 webdavBooks 更新时间高于 databaseTime，则以 webdavBooks 为准：
        //  - 数据库中缺失的书（在webdav不存在）将被删除
        //  - 数据库中已存在的书会用 webdav 中非空字段填充
        // 若反之（数据库较新），则把数据库的数据推送到 webdav（覆盖远端 sync 文件）

        if ($this->isWebdavNewerThanDatabase()) {
            // 把 webdav 列表按 filename 建立索引，便于查找
            $webIndex = [];
            foreach ($webdavBooks as $wb) {
                if (isset($wb['filename'])) {
                    $webIndex[$wb['filename']] = $wb;
                }
            }

            // 遍历数据库书籍，删除在 webdav 不存在的条目，或用 webdav 的非空字段补全数据库
            foreach ($databaseBooks as $dbBook) {
                /** @var BookModel $dbBook */
                if (!isset($webIndex[$dbBook->filename])) {
                    // 远端已删除该文件，从数据库中删除记录
                    try {
                        BookDao::getInstance()->deleteById($dbBook->id);

                    } catch (\Throwable $e) {
                        // ignore deletion failure
                    }
                    continue;
                }

                $wb = $webIndex[$dbBook->filename];
                $needUpdate = false;
                $fields = ['bookName', 'author', 'description', 'category', 'downloadUrl', 'coverUrl', 'favorite', 'rate', 'deviceId', 'series', 'seriesNum'];
                foreach ($fields as $field) {
                    if (isset($wb[$field]) && $wb[$field] !== '' && empty($dbBook->$field)) {
                        $dbBook->$field = $wb[$field];
                        $needUpdate = true;
                    }
                }

                if (isset($wb['addTime'])) {
                    $addTime = (int)$wb['addTime'];
                    if ($addTime !== $dbBook->addTime) {
                        $dbBook->addTime = $addTime;
                        $needUpdate = true;
                    }
                }

                if ($needUpdate) {
                    try {
                        BookDao::getInstance()->updateModel($dbBook);
                    } catch (\Throwable $e) {
                        // ignore update failures
                    }
                }

                // 标记已处理，剩下的 webIndex 条目是数据库中不存在的新书
                unset($webIndex[$dbBook->filename]);
            }

            // 剩余的 webIndex 是新书，尝试插入到数据库（前先检查文件是否真在远端存在）
            foreach ($webIndex as $wb) {
                if (!isset($wb['filename'])) {
                    continue;
                }
                $book = new BookModel((array)$wb);
                $book->splitCategory2Series();
                try {
                    if (BookManager::getInstance()->bookExists($book->filename)) {
                        BookDao::getInstance()->insertModel($book, true);
                    }
                } catch (\Throwable $e) {
                    // ignore insert failures
                }
            }

        }
        $books = BookDao::getInstance()->select()->commit();
        $out = [];
        foreach ($books as $b) {
            /** @var BookModel $b */
            $b = $b->pushSeries2Category();
            $out[] = $b->toArray();
        }
        // 推到 webdav
        try {
            BookManager::getInstance()->updateBookList($out);
            // 更新远端与本地时间戳
            $now = time();
            Context::instance()->cache->set('database_time', $now);
        } catch (\Throwable $e) {
            // ignore push failure
        }
    }

    public function endSync()
    {
        //
    }

}
