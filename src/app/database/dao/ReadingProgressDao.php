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

    public function updateItem(string $filename, ReadingProgressModel $progress)
    {
        $_progress = $this->getByFilename($filename);
        if(empty($_progress)){
            $progress->id =   $this->insertModel($progress);
        }else{
            $progress->id =   $_progress->id;
            $this->updateModel($progress);
        }
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
}
