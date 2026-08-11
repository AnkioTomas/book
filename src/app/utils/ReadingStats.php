<?php

declare(strict_types=1);

namespace app\utils;

use app\database\model\PageStatModel;

/**
 * 阅读统计聚合。纯函数，无 ORM。
 * 书籍身份：filename；start_time 为 Unix 秒；duration 为秒。
 */
class ReadingStats
{
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
        $cutoff = time() - 7 * 86400;
        $total = 0;
        $last7 = 0;
        $perDayDur = [];
        $perDayPages = [];
        $seenPages = [];
        $perMonth = [];
        $weekday = array_fill(0, 7, 0);

        foreach ($stats as $s) {
            $total += $s->duration;
            if ($s->start_time >= $cutoff) {
                $last7 += $s->duration;
            }
            $day = date('Y-m-d', $s->start_time);
            $perDayDur[$day] = ($perDayDur[$day] ?? 0) + $s->duration;
            $perDayPages[$day] = ($perDayPages[$day] ?? 0) + 1;
            $seenPages[$s->book_filename . '#' . $s->page] = true;

            $monthKey = date('Y-m', $s->start_time);
            if (!isset($perMonth[$monthKey])) {
                $perMonth[$monthKey] = [
                    'month' => $monthKey,
                    'duration' => 0,
                    'date' => strtotime($monthKey . '-01') ?: $s->start_time,
                ];
            }
            $perMonth[$monthKey]['duration'] += $s->duration;
            $weekday[(int)date('w', $s->start_time)] += $s->duration;
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
        /** @var array<string, array{duration: int, books: array<string, int>}> $acc */
        $acc = [];
        foreach ($stats as $s) {
            $day = date('Y-m-d', $s->start_time);
            if (!isset($acc[$day])) {
                $acc[$day] = ['duration' => 0, 'books' => []];
            }
            $acc[$day]['duration'] += $s->duration;
            $acc[$day]['books'][$s->book_filename] =
                ($acc[$day]['books'][$s->book_filename] ?? 0) + $s->duration;
        }
        ksort($acc);
        return $acc;
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
}
