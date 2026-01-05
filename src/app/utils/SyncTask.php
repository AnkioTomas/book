<?php

namespace app\utils;

use app\database\dao\BookDao;
use app\database\model\BookModel;
use nova\framework\core\Context;
use nova\plugin\corn\schedule\TaskerAbstract;
use Throwable;

class SyncTask extends TaskerAbstract
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
        $list = BookManager::instance()->list();
        /**
         * @var $book BookModel
         */
        foreach ($list as $book) {
            if ($book->id > 0){
                $dbBook = BookDao::getInstance()->getById($book->id);
                if (!$dbBook) continue;

                // 差异比较：用 $book 的非空值填充 $dbBook 的空字段
                $needUpdate = false;
                $fields = ['bookName', 'author', 'description',  'series', 'seriesNum', 'favorite', 'rate', 'coverUrl'];

                foreach ($fields as $field) {
                    if (!empty($book->$field) && empty($dbBook->$field)) {
                        $dbBook->$field = $book->$field;
                        $needUpdate = true;
                    }
                }

                if ($needUpdate) {
                    BookDao::getInstance()->updateModel($dbBook);
                }
            }else{
                $book->splitCategory2Series();
                try{
                    $filename = $book->filename;
                    if (BookManager::instance()->bookExists($filename)) {
                        BookDao::getInstance()->insertModel($book);
                    }
                }catch (\Exception $e){
                }
            }
        }

        $books = BookDao::getInstance()->select()->commit();

        foreach ($books as &$book) {
            $book = $book->pushSeries2Category();
            if (!empty($book->coverUrl) && Context::instance()->cache->get("coverUrl/{$book->coverUrl}")){
                $file = BookManager::proxy($book->coverUrl);
                if(BookManager::instance()->uploadCover($file,$book->filename)){
                    Context::instance()->cache->delete("coverUrl/{$book->coverUrl}");
                }
            }
        }


        BookManager::instance()->push($books);
    }

    /**
     * @inheritDoc
     */
    public function onStop(): void
    {

    }

    /**
     * @inheritDoc
     */
    public function onAbort(Throwable $e): void
    {

    }
}