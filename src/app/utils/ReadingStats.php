<?php

declare(strict_types=1);

namespace app\utils;

use app\database\model\PageStatModel;

/**
 * 阅读统计聚合。纯函数，无 ORM。
 * 书籍身份：filename；start_time 为 Unix 秒；duration 为秒。
 *
 * 同书同日多设备：先按设备求和（KOReader 多页），再跨设备取 max
 * （避免 Moon 重复导入 / 多 device_id 把同一天时长加两遍）。
 * 单日墙钟上限 86400 秒。
 */
class ReadingStats
{
    private const int DAY_SECONDS = 86400;

    private const array WEEKDAY_NAMES = [
        0 => '周日',
        1 => '周一',
        2 => '周二',
        3 => '周三',
        4 => '周四',
        5 => '周五',
        6 => '周六',
    ];

    /**
     * @param PageStatModel[] $stats
     * @return array{
     *   totalReadingTime: int,
     *   last7DaysReadTime: int,
     *   longestDay: int,
     *   mostPagesInADay: int,
     *   totalPagesRead: int,
     *   perMonth: list<array{month: string, duration: int, date: int}>,
     *   perDayOfTheWeek: list<array{name: string, value: int, day: int}>
     * }
     */
    public static function summarize(array $stats): array
    {
        $bookDays = self::aggregateBookDays($stats);

        $cutoffDay = date('Y-m-d', time() - 7 * self::DAY_SECONDS);
        $total = 0;
        $last7 = 0;
        $perDayDur = [];
        $perMonth = [];
        $weekday = array_fill(0, 7, 0);

        foreach ($bookDays as $day => $books) {
            $dayTotal = 0;
            foreach ($books as $dur) {
                $dayTotal += $dur;
            }
            $dayTotal = min(self::DAY_SECONDS, $dayTotal);
            $perDayDur[$day] = $dayTotal;
            $total += $dayTotal;

            if ($day >= $cutoffDay) {
                $last7 += $dayTotal;
            }

            $monthKey = substr($day, 0, 7);
            if (!isset($perMonth[$monthKey])) {
                $perMonth[$monthKey] = [
                    'month' => $monthKey,
                    'duration' => 0,
                    'date' => strtotime($monthKey . '-01') ?: 0,
                ];
            }
            $perMonth[$monthKey]['duration'] += $dayTotal;

            $dow = (int)date('w', strtotime($day . ' 12:00:00') ?: 0);
            $weekday[$dow] += $dayTotal;
        }

        $perDayPages = [];
        $seenPages = [];
        foreach ($stats as $s) {
            $day = date('Y-m-d', $s->start_time);
            $perDayPages[$day] = ($perDayPages[$day] ?? 0) + 1;
            $seenPages[$s->book_filename . '#' . $s->page] = true;
        }

        $monthList = array_values($perMonth);
        usort($monthList, static fn ($a, $b) => $a['date'] <=> $b['date']);

        $weekdayList = [];
        for ($d = 0; $d <= 6; $d++) {
            $weekdayList[] = [
                'name' => self::WEEKDAY_NAMES[$d],
                'value' => $weekday[$d],
                'day' => $d,
            ];
        }

        return [
            'totalReadingTime' => $total,
            'last7DaysReadTime' => $last7,
            'longestDay' => $perDayDur === [] ? 0 : max($perDayDur),
            'mostPagesInADay' => $perDayPages === [] ? 0 : max($perDayPages),
            'totalPagesRead' => count($seenPages),
            'perMonth' => $monthList,
            'perDayOfTheWeek' => $weekdayList,
        ];
    }

    public static function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);
        if ($seconds < 60) {
            return $seconds . '秒';
        }
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}小时{$minutes}分" : "{$hours}小时";
        }
        return $minutes . '分钟';
    }

    public static function normalizeUnixSeconds(int $ts): int
    {
        if ($ts > 1_000_000_000_000) {
            return (int)floor($ts / 1000);
        }
        return $ts;
    }

    /**
     * @param  PageStatModel[] $stats
     * @return array<string, array{duration: int, books: array<string, int>}>
     *         day => { duration, books: filename => duration }
     */
    public static function perDay(array $stats): array
    {
        $bookDays = self::aggregateBookDays($stats);
        $out = [];
        foreach ($bookDays as $day => $books) {
            $dayTotal = 0;
            foreach ($books as $dur) {
                $dayTotal += $dur;
            }
            $out[$day] = [
                'duration' => min(self::DAY_SECONDS, $dayTotal),
                'books' => $books,
            ];
        }
        ksort($out);
        return $out;
    }

    /**
     * 由 page_stat 估算当前进度：max(page) / max(total_pages)。
     *
     * @param  PageStatModel[] $stats
     * @return array<string, float> filename => 0–100
     */
    public static function progressByFilename(array $stats): array
    {
        $info = [];
        foreach ($stats as $s) {
            $cur = $info[$s->book_filename] ?? ['page' => 0, 'total' => 0];
            $cur['page'] = max($cur['page'], $s->page);
            $cur['total'] = max($cur['total'], $s->total_pages);
            $info[$s->book_filename] = $cur;
        }
        $out = [];
        foreach ($info as $fn => $row) {
            $out[$fn] = $row['total'] > 0
                ? round(min(100, $row['page'] / $row['total'] * 100), 1)
                : 0.0;
        }
        return $out;
    }

    /**
     * @param  PageStatModel[] $stats
     * @return array<string, array<string, int>> day => filename => duration(sec)
     */
    public static function aggregateBookDays(array $stats): array
    {
        /** @var array<string, array<string, array<string, int>>> $byDayBookDev */
        $byDayBookDev = [];
        foreach ($stats as $s) {
            if ($s->book_filename === '' || $s->duration <= 0) {
                continue;
            }
            $day = date('Y-m-d', $s->start_time);
            $dev = $s->device_id !== '' ? $s->device_id : '_';
            $byDayBookDev[$day][$s->book_filename][$dev] =
                ($byDayBookDev[$day][$s->book_filename][$dev] ?? 0)
                + min(self::DAY_SECONDS, $s->duration);
        }

        $out = [];
        foreach ($byDayBookDev as $day => $books) {
            foreach ($books as $fn => $devs) {
                // 同书同日：各设备内已求和，跨设备取 max（防重复导入加倍）
                $out[$day][$fn] = min(self::DAY_SECONDS, max($devs));
            }
        }
        return $out;
    }
}
