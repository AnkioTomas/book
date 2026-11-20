<?php

declare(strict_types=1);

namespace app\utils\BookOrganizer;

use app\database\model\BookModel;

use nova\framework\core\File;

class Parser
{
    public static function filename(string $filename): array
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // 1. 清理无关标记
        $name = preg_replace('/\s*\(Z-Library\)/u', '', $name);
        $name = trim($name);
        // 去掉排序用的前导序号（如「3 龙族」「03. 书名」「1、书名」）；必须有分隔符，避免误伤「1984」「1Q84」
        $name = preg_replace('/^\d{1,3}(?:\s*[.、．_\-]\s*|\s+)(?=\S)/u', '', $name);
        $name = trim($name);

        // 2. 提取年份
        $year = null;
        if (preg_match('/\((\d{4})\)/u', $name, $m)) {
            $year = $m[1];
        }

        // 3. 提取作者
        $author = null;

        if (preg_match_all('/\(([^()]+)\)/u', $name, $matches)) {
            foreach ($matches[1] as $possible_author) {
                $possible_author = trim($possible_author);

                if (ctype_digit($possible_author) || mb_strlen($possible_author) > 20) {
                    continue;
                }

                if (preg_match('/〔[^〕]+〕|\([^)]+\)|\[[^\]]+\]|（[^）]+）/u', $possible_author)) {
                    $author = preg_replace(
                        '/〔[^〕]+〕|\([^)]+\)|\[[^\]]+\]|（[^）]+）/u',
                        '',
                        $possible_author
                    );
                    $author = trim($author);
                    break;
                }

                $author = $possible_author;
                break;
            }
        }

        if (!$author && str_contains($name, ' - ')) {
            [$left] = explode(' - ', $name, 2);
            if (mb_strlen(trim($left)) < 20) {
                $author = trim($left);
            }
        }

        // 4. 提取标题
        $title = null;

        if (preg_match('/《([^》]+)》/u', $name, $m)) {
            $title = trim($m[1]);
        } else {
            // 去掉括号注释，但保留分卷标记（统一成全角括号），如「（上）」「(2)」「（第三卷）」
            $clean_name = preg_replace_callback(
                '/\(([^)]*)\)|\[[^\]]*]|（([^）]*)）|【[^】]*】/u',
                static function (array $m): string {
                    $inner = ($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? '');
                    if ($inner !== '' && Volume::isVolumeInner($inner)) {
                        return '（' . trim($inner) . '）';
                    }
                    return ' ';
                },
                $name
            );
            $clean_name = preg_replace('/\s+（/u', '（', $clean_name);
            $clean_name = trim($clean_name);

            if ($author && str_contains($clean_name, ' - ')) {
                [$left, $right] = explode(' - ', $clean_name, 2);
                $title = (trim($left) === $author) ? trim($right) : $clean_name;
            } else {
                $title = $clean_name;
            }
        }

        // 5. 清理标题和作者
        if ($title) {
            $title = preg_replace('/【[^】]*】|《|》/u', '', $title);
            $title = trim($title);

            if (mb_strlen($title) > 30) {
                if (preg_match('/^[^，。：；！？,.:;!?]+/u', $title, $m)) {
                    $title = trim($m[0]);
                }
            }

        }

        if ($author) {
            $author = preg_replace(
                '/\[[^\]]*]|〔[^〕]*〕|\([^)]*\)|（[^）]*）|【[^】]*】/u',
                '',
                $author
            );
            $author = trim($author);
        }

        return [$author, $title, $year, $ext];
    }

    /**
     * 从书籍文件本身读取元数据（EPUB / MOBI / AZW3），读不到时返回空数组。
     * @return array{title?:string, author?:string, publisher?:string, description?:string, language?:string, isbn?:string, year?:string}
     */
    public static function epubMeta(string $bookPath, string $filename = ''): array
    {
        return EbookMeta::read($bookPath, $filename);
    }

    public static function cover(string $bookPath, BookModel $model): string
    {
        $key = md5($model->filename);
        $path = RUNTIME_PATH . DS. "images" . DS ;
        File::mkdir($path);
        $file = $path . $key . ".png";
        if (is_file($file)) {
            File::del($file);
        }

        if (EbookMeta::extractCover($bookPath, $file, $model->filename)) {
            return $file;
        }

        return '';
    }
}
