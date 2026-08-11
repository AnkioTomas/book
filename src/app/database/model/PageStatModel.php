<?php

declare(strict_types=1);

namespace app\database\model;

use nova\plugin\orm\object\Model;

/**
 * 阅读事实表：单次页停留。唯一统计持久化表。
 * book_filename = 书库相对路径；start_time = Unix 秒。
 */
class PageStatModel extends Model
{
    public string $book_filename = '';
    public string $device_id = '';
    public int $page = 0;
    public int $start_time = 0;
    public int $duration = 0;
    public int $total_pages = 0;

    public function getUnique(): array
    {
        return [['device_id', 'book_filename', 'page', 'start_time']];
    }

    public function getSchemaVersion(): int
    {
        return 4;
    }

    public function getUpgradeSql(): array
    {
        return [
            '1_2' => ['DROP TABLE IF EXISTS `pagestat`'],
            '2_3' => ['DROP TABLE IF EXISTS `pagestat`'],
            '3_4' => [
                'DROP TABLE IF EXISTS `statbook`',
                'DROP TABLE IF EXISTS `readingdevice`',
                'DROP TABLE IF EXISTS `bookdevice`',
            ],
        ];
    }
}
