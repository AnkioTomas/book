<?php

declare(strict_types=1);

namespace app\database\dao;

use app\database\model\AnnotationModel;
use nova\plugin\orm\object\Dao;
use Throwable;

class AnnotationDao extends Dao
{
    /**
     * 用设备上报的完整快照替换该书现有注解。
     *
     * @param AnnotationModel[] $annotations
     * @return array{upserted:int,deleted:int}
     */
    public function replaceSnapshot(string $filename, string $deviceId, array $annotations): array
    {
        $this->transactionBegin();
        try {
            $existing = $this->getByBookAndDevice($filename, $deviceId);
            $seen = [];
            $upserted = 0;

            foreach ($annotations as $annotation) {
                $seen[$this->identity($annotation)] = true;
                $this->insertModel($annotation, true);
                $upserted++;
            }

            $deleted = 0;
            foreach ($existing as $annotation) {
                if (isset($seen[$this->identity($annotation)])) {
                    continue;
                }
                $this->deleteModel($annotation);
                $deleted++;
            }

            $this->transactionCommit();
            return ['upserted' => $upserted, 'deleted' => $deleted];
        } catch (Throwable $e) {
            $this->transactionRollBack();
            throw $e;
        }
    }

    /** @return AnnotationModel[] */
    public function getByBookAndDevice(string $filename, string $deviceId): array
    {
        return $this->select()
            ->where(['book_filename' => $filename, 'device_id' => $deviceId])
            ->commit();
    }

    /** @return AnnotationModel[] */
    public function getByFilename(string $filename): array
    {
        return $this->select()
            ->where(['book_filename' => $filename])
            ->orderBy('datetime', 'DESC')
            ->commit();
    }

    /** @return AnnotationModel[] */
    public function getAllRows(): array
    {
        return $this->select()->orderBy('datetime', 'DESC')->commit();
    }

    private function identity(AnnotationModel $annotation): string
    {
        return $annotation->page_ref . "\0" . $annotation->datetime;
    }
}
