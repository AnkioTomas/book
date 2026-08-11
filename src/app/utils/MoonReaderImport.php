<?php

declare(strict_types=1);

namespace app\utils;

use app\database\dao\BookDao;
use app\database\dao\PageStatDao;
use app\database\model\PageStatModel;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * 静读天下（Moon+ Reader Pro）.mrpro 备份 → 日粒度 PageStat。
 * 合成规则：每本书每天一条；page=进度四舍五入（无则 0）；total_pages=100。
 * device_id 必须来自备份 options 的 deviceRandomID2（前缀 moon-）。
 * 历史误用的 moon-import 在每次导入时直接清掉。
 */
class MoonReaderImport
{
    /** 旧版硬编码设备 ID，导入时默认 DROP */
    public const DEVICE_LEGACY = 'moon-import';
    private const TOTAL_PAGES = 100;

    /**
     * @return array{
     *   books: int,
     *   stats: int,
     *   days: int,
     *   device_id: string,
     *   unmatched: list<string>
     * }
     */
    public static function importFromMrpro(string $path): array
    {
        $bundle = self::loadMrpro($path);
        $rows = $bundle['rows'];
        $deviceId = $bundle['device_id'];
        if ($rows === []) {
            throw new RuntimeException('备份中没有可读的 statistics 数据');
        }

        $libByBase = self::libraryByBasename();
        $pageStatDao = PageStatDao::getInstance();

        $pageStatDao->deleteByDevice(self::DEVICE_LEGACY);
        $pageStatDao->deleteByDevice($deviceId);

        $bookCount = 0;
        $statCount = 0;
        $dayCount = 0;
        $unmatched = [];
        $seenBook = [];

        foreach ($rows as $row) {
            $base = $row['basename'];
            $lib = $libByBase[strtolower($base)] ?? null;
            $filename = $lib['filename'] ?? $base;

            if ($lib === null) {
                $unmatched[$base] = true;
            }

            if (!isset($seenBook[$filename])) {
                $seenBook[$filename] = true;
                $bookCount++;
            }

            foreach ($row['days'] as $day) {
                $duration = $day['duration'];
                if ($duration <= 0) {
                    continue;
                }
                $progress = $day['progress'];
                $stat = new PageStatModel();
                $stat->book_filename = $filename;
                $stat->device_id = $deviceId;
                $stat->page = $progress !== null ? (int)round(max(0, min(100, $progress))) : 0;
                $stat->start_time = $day['start_time'];
                $stat->duration = $duration;
                $stat->total_pages = self::TOTAL_PAGES;
                $pageStatDao->upsert($stat);
                $statCount++;
                $dayCount++;
            }
        }

        return [
            'books' => $bookCount,
            'stats' => $statCount,
            'days' => $dayCount,
            'device_id' => $deviceId,
            'unmatched' => array_keys($unmatched),
        ];
    }

    /**
     * 打开 .mrpro：抽出 device_id + statistics。
     *
     * @return array{
     *   device_id: string,
     *   rows: list<array{
     *     basename: string,
     *     path: string,
     *     days: list<array{start_time: int, duration: int, progress: ?float}>
     *   }>
     * }
     */
    public static function loadMrpro(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('无法读取备份文件');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('不是有效的 .mrpro（zip）备份');
        }

        $tmpDb = tempnam(sys_get_temp_dir(), 'mrbooks_');
        if ($tmpDb === false) {
            $zip->close();
            throw new RuntimeException('无法创建临时文件');
        }
        @unlink($tmpDb);
        $tmpDb .= '.db';

        $rawDeviceId = '';
        $foundDb = false;

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if ($name === false) {
                    continue;
                }
                $stream = $zip->getStream($name);
                if ($stream === false) {
                    continue;
                }
                $head = fread($stream, 16);
                if ($head === false) {
                    fclose($stream);
                    continue;
                }

                if (!$foundDb && str_starts_with($head, 'SQLite format 3')) {
                    $out = fopen($tmpDb, 'wb');
                    if ($out === false) {
                        fclose($stream);
                        throw new RuntimeException('无法写入临时数据库');
                    }
                    fwrite($out, $head);
                    stream_copy_to_stream($stream, $out);
                    fclose($out);
                    fclose($stream);
                    $foundDb = true;
                    continue;
                }

                if ($rawDeviceId === '' && (str_ends_with(strtolower($name), '.xml') || str_contains($head, '<'))) {
                    $body = $head . stream_get_contents($stream);
                    fclose($stream);
                    $id = self::extractDeviceRandomId($body);
                    if ($id !== '') {
                        $rawDeviceId = $id;
                    }
                    continue;
                }

                fclose($stream);
            }
            $zip->close();

            if (!$foundDb) {
                throw new RuntimeException('备份中未找到 mrbooks.db');
            }

            return [
                'device_id' => self::normalizeDeviceId($rawDeviceId),
                'rows' => self::parseStatisticsDb($tmpDb),
            ];
        } finally {
            if (is_file($tmpDb)) {
                @unlink($tmpDb);
            }
        }
    }

    /** 备份里的 deviceRandomID2 → 我们的 device_id；缺失则失败 */
    public static function normalizeDeviceId(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^[0-9A-Za-z._:-]+$/', $raw)) {
            throw new RuntimeException('备份中缺少 deviceRandomID2，无法确定设备');
        }
        if (str_starts_with($raw, 'moon-')) {
            return $raw;
        }
        return 'moon-' . $raw;
    }

    private static function extractDeviceRandomId(string $xml): string
    {
        if (preg_match('/name="deviceRandomID2"\s*>([^<]+)</', $xml, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/name="deviceRandomID"\s*>([^<]+)</', $xml, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * @return list<array{
     *   basename: string,
     *   path: string,
     *   days: list<array{start_time: int, duration: int, progress: ?float}>
     * }>
     */
    public static function parseStatisticsDb(string $dbPath): array
    {
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll();
        $names = array_column($tables, 'name');
        if (!in_array('statistics', $names, true)) {
            throw new RuntimeException('数据库缺少 statistics 表');
        }

        $stmt = $pdo->query('SELECT filename, usedTime, readWords, dates FROM statistics');
        $out = [];
        while ($row = $stmt->fetch()) {
            $path = (string)($row['filename'] ?? '');
            $base = basename(str_replace('\\', '/', $path));
            if ($base === '' || $base === '.' || $base === '..') {
                continue;
            }
            $days = self::parseDatesBlob((string)($row['dates'] ?? ''));
            if ($days === []) {
                continue;
            }
            $out[] = [
                'basename' => $base,
                'path' => $path,
                'days' => $days,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{start_time: int, duration: int, progress: ?float}>
     */
    public static function parseDatesBlob(string $dates): array
    {
        $out = [];
        foreach (preg_split('/\R+/', $dates) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, '|')) {
                continue;
            }
            // 20486|4402521@15756 #4.7%
            if (!preg_match(
                '/^(\d+)\|(\d+)@(\d+)(?:\s*#\s*([\d.]+)\s*%?)?/',
                $line,
                $m
            )) {
                continue;
            }
            $dayNum = (int)$m[1];
            $ms = (int)$m[2];
            $duration = (int)floor($ms / 1000);
            if ($duration <= 0 || $dayNum <= 0) {
                continue;
            }
            $progress = isset($m[4]) && $m[4] !== '' ? (float)$m[4] : null;
            // 正午 UTC，避免跨时区把日历日挤到相邻天
            $start = $dayNum * 86400 + 12 * 3600;
            $out[] = [
                'start_time' => $start,
                'duration' => $duration,
                'progress' => $progress,
            ];
        }
        return $out;
    }

    /** @return array<string, array{filename: string, bookName: string, author: string}> */
    private static function libraryByBasename(): array
    {
        $rows = BookDao::getInstance()
            ->select('filename', 'bookName', 'author')
            ->commit(object: false);

        $map = [];
        foreach ($rows as $r) {
            $fn = (string)($r['filename'] ?? '');
            $base = strtolower(basename(str_replace('\\', '/', $fn)));
            if ($base === '') {
                continue;
            }
            $candidate = [
                'filename' => $fn,
                'bookName' => (string)($r['bookName'] ?? ''),
                'author' => (string)($r['author'] ?? ''),
            ];
            // 同 basename 优先更短路径（更接近根目录）
            if (!isset($map[$base]) || strlen($fn) < strlen($map[$base]['filename'])) {
                $map[$base] = $candidate;
            }
        }
        return $map;
    }
}
