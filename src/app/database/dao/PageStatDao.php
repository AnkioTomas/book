<?php

declare(strict_types=1);

namespace app\database\dao;

use app\database\model\PageStatModel;
use nova\framework\core\Context;
use nova\plugin\orm\object\Dao;
use Throwable;

class PageStatDao extends Dao
{
    public function initTable(): bool
    {
        $ok = parent::initTable();
        $this->recreateIfMissing();
        return $ok;
    }

    private function recreateIfMissing(): void
    {
        try {
            $quoted = $this->db->getDriver()->quoteIdentifier($this->getTable());
            $this->db->getDriver()->getDbConnect()->query("SELECT count(*) FROM {$quoted} LIMIT 1");
        } catch (Throwable $e) {
            $model = new PageStatModel();
            $this->db->initTable($this, $model, $this->getTable());
            Context::instance()->cache->set('table_version_' . $this->getTable(), $model->getSchemaVersion());
        }
    }

    public static function normalizeFilename(string $filename): string
    {
        $filename = trim(str_replace('\\', '/', $filename));
        return ltrim($filename, '/');
    }

    /** 冲突时更新 duration / total_pages。 */
    public function upsert(PageStatModel $stat): void
    {
        $stat->book_filename = self::normalizeFilename($stat->book_filename);
        if ($stat->book_filename === '' || $stat->device_id === '') {
            return;
        }
        $this->insertModel($stat, true);
    }

    /**
     * @return PageStatModel[]
     */
    public function getAllRows(): array
    {
        return $this->select()->commit();
    }

    /**
     * @return PageStatModel[]
     */
    public function getByFilename(string $filename): array
    {
        $filename = self::normalizeFilename($filename);
        if ($filename === '') {
            return [];
        }
        return $this->select()
            ->where(['book_filename' => $filename])
            ->orderBy('start_time', 'ASC')
            ->commit();
    }

    /** 删除某设备全部 page_stat（Moon 全量重导入用）。 */
    public function deleteByDevice(string $deviceId): void
    {
        $deviceId = trim($deviceId);
        if ($deviceId === '') {
            return;
        }
        $this->delete()->where(['device_id' => $deviceId])->commit();
    }
}
