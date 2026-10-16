<?php

declare(strict_types=1);

namespace app\utils\BookOrganizer;

use app\database\model\BookModel;
use app\utils\EbookServiceClient;

use function nova\framework\config;

use nova\framework\core\File;
use nova\framework\core\Logger;

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
     * 读取 EPUB 的 OPF 元数据（dc:title / dc:creator）。非 EPUB、无 zip 扩展或解析失败时返回空数组。
     * @return array{title?:string, author?:string}
     */
    public static function epubMeta(string $bookPath, string $filename = ''): array
    {
        $ext = strtolower(pathinfo($filename !== '' ? $filename : $bookPath, PATHINFO_EXTENSION));
        if ($ext !== 'epub' || !class_exists(\ZipArchive::class) || !is_file($bookPath)) {
            return [];
        }
        $zip = new \ZipArchive();
        if ($zip->open($bookPath) !== true) {
            return [];
        }
        try {
            $container = $zip->getFromName('META-INF/container.xml');
            if ($container === false || !preg_match('/full-path\s*=\s*"([^"]+)"/i', $container, $m)) {
                return [];
            }
            $opf = $zip->getFromName($m[1]);
            if ($opf === false) {
                return [];
            }
            $out = [];
            if (preg_match('/<dc:title[^>]*>(.*?)<\/dc:title>/is', $opf, $t)) {
                $title = trim(html_entity_decode(strip_tags($t[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($title !== '') {
                    $out['title'] = $title;
                }
            }
            if (preg_match('/<dc:creator[^>]*>(.*?)<\/dc:creator>/is', $opf, $c)) {
                $author = trim(html_entity_decode(strip_tags($c[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
                if ($author !== '' && !in_array(mb_strtolower($author), ['unknown', '未知', 'anonymous'], true)) {
                    $out['author'] = $author;
                }
            }
            return $out;
        } catch (\Throwable $e) {
            Logger::debug('[Parser] 读取 OPF 失败: ' . $e->getMessage());
            return [];
        } finally {
            $zip->close();
        }
    }

    public static function cover(string $bookPath, BookModel $model): string
    {
        $key = md5($model->filename);
        $path = RUNTIME_PATH . DS. "images" . DS ;
        File::mkdir($path);
        $file = $path . $key . ".png";
        $client = new EbookServiceClient(config('calibre'));
        try {
            $client->extractCoverToFile($bookPath, $file);
            return $file;
        } catch (\RuntimeException $exception) {
            Logger::debug($exception->getMessage());
            return '';
        }
    }
}
