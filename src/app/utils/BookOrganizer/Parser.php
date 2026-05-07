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
            $clean_name = preg_replace(
                '/\([^)]*\)|\[[^\]]*]|（[^）]*）|【[^】]*】/u',
                ' ',
                $name
            );
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
