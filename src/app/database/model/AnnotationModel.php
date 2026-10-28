<?php

declare(strict_types=1);

namespace app\database\model;

use nova\plugin\orm\object\Model;

/**
 * 高亮、笔记和书签。
 *
 * 写入方：
 * - KOReader 月读插件：device_id 由客户端上报，page_ref + datetime 为稳定身份
 * - 静读天下 .an：device_id = moonreader，page_ref = 注解 id
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
