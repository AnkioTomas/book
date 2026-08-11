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

    /** 删除某书全部阅读记录。 */
    public function deleteByFilename(string $filename): void
    {
        $filename = self::normalizeFilename($filename);
        if ($filename === '') {
            return;
        }
        $this->delete()->where(['book_filename' => $filename])->commit();
    }

    /**
     * 将 from 的全部记录改绑到 to（库内路径）。
     * 目标键已存在则合并 duration，再删旧行。
     */
    public function remapFilename(string $from, string $to): int
    {
        $from = self::normalizeFilename($from);
        $to = self::normalizeFilename($to);
        if ($from === '' || $to === '' || $from === $to) {
            return 0;
        }
        $rows = $this->getByFilename($from);
        $n = 0;
        foreach ($rows as $row) {
            /** @var PageStatModel|null $existing */
            $existing = $this->find(null, [
                'device_id' => $row->device_id,
                'book_filename' => $to,
                'page' => $row->page,
                'start_time' => $row->start_time,
            ]);
            if ($existing !== null) {
                $existing->duration += $row->duration;
                $existing->total_pages = max($existing->total_pages, $row->total_pages);
                $this->updateModel($existing);
                $this->deleteModel($row);
            } else {
                $row->book_filename = $to;
                $this->updateModel($row);
            }
            $n++;
        }
        return $n;
    }
}
