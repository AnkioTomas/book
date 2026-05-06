<?php

namespace app\database\dao;

use app\database\model\ReadingProgressModel;
use nova\plugin\orm\object\Dao;

class ReadingProgressDao extends Dao
{
    public function getByFilename(string $filename): ?ReadingProgressModel
    {
        return $this->find(null, ['filename' => $filename]);
    }


    /**
     * @param string[] $filenames
     * @return ReadingProgressModel[]
     */
    public function getByFilenames(array $filenames): array
    {
        if (empty($filenames)) {
            return [];
        }
        $in = implode(',', $filenames);
        return $this->select()
            ->where(['filename in (:in)', ':in' => $in])
            ->commit();
    }

    public function updateItem(ReadingProgressModel $progress)
    {
        $array = $progress->toArray();
        unset($array['filename']);
        if ($progress->id <=0 )return;
        ReadingProgressDao::getInstance()->update()->where(['filename' => $progress->filename])->set($array)->commit();
    }
}
