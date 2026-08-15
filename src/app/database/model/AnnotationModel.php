<?php

declare(strict_types=1);

namespace app\database\model;

use nova\plugin\orm\object\Model;

/**
 * KOReader 高亮、笔记和书签。
 *
 * 同一设备内，page_ref + datetime 是 KOReader 注解的稳定身份。
 */
class AnnotationModel extends Model
{
    public string $book_filename = '';
    public string $device_id = '';
    public string $annotation_type = 'highlight';
    public string $text = '';
    public string $note = '';
    public string $drawer = '';
    public string $color = '';
    public string $chapter = '';
    public int $pageno = 0;
    public string $page_ref = '';
    public int $total_pages = 0;
    public string $pos0 = '';
    public string $pos1 = '';
    public string $datetime = '';
    public string $datetime_updated = '';

    public function getUnique(): array
    {
        return [['book_filename', 'device_id', 'page_ref', 'datetime']];
    }
}
