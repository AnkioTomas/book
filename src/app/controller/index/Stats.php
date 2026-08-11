<?php

declare(strict_types=1);

namespace app\controller\index;

use app\controller\ApiController;
use app\database\dao\BookDao;
use app\database\dao\PageStatDao;
use app\database\model\PageStatModel;
use app\utils\MoonReaderImport;
use app\utils\ReadingStats;
use nova\framework\http\Response;
use RuntimeException;
use Throwable;

/**
 * 高维阅读统计 API。唯一持久化表：page_stat。书名作者读时查书库。
 *
 * POST /index/stats/device
 * POST /index/stats/import
 * POST /index/stats/importMoon  (.mrpro 文件上传)
 * GET  /index/stats/summary
 * GET  /index/stats/insight
 * GET  /index/stats/books
 * GET  /index/stats/book?filename=
 * POST /index/stats/remap
 * POST /index/stats/create
 * POST /index/stats/removeBook
 */
class Stats extends ApiController
{
    private const UNKNOWN_DEVICE = 'manual-upload';

    /**
     * POST /index/stats/device
     * body: { id, model }
     * 兼容 KOReader：校验后直接 ok，不再落库。
     */
    public function device(): Response
    {
        $body = $this->jsonBody();
        $id = trim((string)($body['id'] ?? $this->request->post('id', '')));
        $model = trim((string)($body['model'] ?? $this->request->post('model', '')));

        if ($id === '' || $model === '') {
            return Response::asJson(['code' => 400, 'msg' => '缺少 device id 或 model', 'data' => []]);
        }

        return Response::asJson([
            'code' => 200,
            'msg' => 'ok',
            'data' => ['id' => $id, 'model' => $model],
        ]);
    }

    /**
     * POST /index/stats/import
     * body: {
     *   books: [{ filename, title?, authors? }],
     *   stats: [{ filename, page, start_time, duration, total_pages, device_id? }],
     *   device_id?: string
     * }
     *
     * 只写 page_stat。books[] 仅用于 normalize 路径（命中书库则用库路径）。
     */
    public function import(): Response
    {
        $body = $this->jsonBody();
        $books = is_array($body['books'] ?? null) ? $body['books'] : [];
        $stats = is_array($body['stats'] ?? null) ? $body['stats'] : [];
        $deviceIdOverride = trim((string)($body['device_id'] ?? ''));

        $pathByReport = [];
        foreach ($books as $row) {
            if (!is_array($row)) {
                continue;
            }
            $reported = PageStatDao::normalizeFilename((string)($row['filename'] ?? ''));
            if ($reported === '') {
                continue;
            }
            $pathByReport[$reported] = $this->resolveLibraryFilename($reported);
        }

        $safeStats = [];
        foreach ($stats as $row) {
            if (!is_array($row)) {
                continue;
            }
            $duration = (int)($row['duration'] ?? 0);
            $totalPages = (int)($row['total_pages'] ?? 0);
            if ($duration <= 0 || $totalPages <= 0) {
                continue;
            }
            $safeStats[] = $row;
        }

        $deviceId = $deviceIdOverride;
        if ($deviceId === '') {
            foreach ($safeStats as $row) {
                $did = trim((string)($row['device_id'] ?? ''));
                if ($did !== '') {
                    $deviceId = $did;
                    break;
                }
            }
        }
        if ($deviceId === '') {
            $deviceId = self::UNKNOWN_DEVICE;
        }

        $pageStatDao = PageStatDao::getInstance();
        $imported = 0;
        $seenBooks = [];
        foreach ($safeStats as $row) {
            $reported = PageStatDao::normalizeFilename((string)($row['filename'] ?? $row['book_filename'] ?? ''));
            if ($reported === '') {
                continue;
            }
            $fn = $pathByReport[$reported] ?? $this->resolveLibraryFilename($reported);
            $pathByReport[$reported] = $fn;
            $seenBooks[$fn] = true;

            $stat = new PageStatModel();
            $stat->book_filename = $fn;
            $stat->device_id = trim((string)($row['device_id'] ?? '')) ?: $deviceId;
            $stat->page = (int)($row['page'] ?? 0);
            $stat->start_time = ReadingStats::normalizeUnixSeconds((int)($row['start_time'] ?? 0));
            $stat->duration = (int)($row['duration'] ?? 0);
            $stat->total_pages = (int)($row['total_pages'] ?? 0);
            $pageStatDao->upsert($stat);
            $imported++;
        }

        return Response::asJson([
            'code' => 200,
            'msg' => 'ok',
            'data' => [
                'books' => count($seenBooks),
                'stats' => $imported,
                'device_id' => $deviceId,
            ],
        ]);
    }

    /**
     * GET /index/stats/summary
     */
    public function summary(): Response
    {
        $stats = PageStatDao::getInstance()->getAllRows();
        $data = ReadingStats::summarize($stats);
        $data['statCount'] = count($stats);
        $data['bookCount'] = count(array_unique(array_map(
            static fn (PageStatModel $s) => $s->book_filename,
            $stats
        )));

        return Response::asJson([
            'code' => 200,
            'msg' => 'ok',
            'data' => $data,
        ]);
    }

    /**
     * GET /index/stats/insight
     * 多维统计页：阅读 KPI + 月/星期分布 + 日历 perDay。
     */
    public function insight(): Response
    {
        $pageStats = PageStatDao::getInstance()->getAllRows();

        return Response::asJson([
            'code' => 200,
            'msg' => 'ok',
            'data' => [
                'hasData' => $pageStats !== [],
                'initialYm' => date('Y-m'),
                'readingActivity' => $this->buildReadingActivity($pageStats),
                'perDay' => $this->buildPerDay($pageStats),
            ],
        ]);
    }

    /**
     * GET /index/stats/book?filename=
     */
    public function book(): Response
    {
        $filename = PageStatDao::normalizeFilename((string)$this->request->get('filename', ''));
        if ($filename === '') {
            return Response::asJson(['code' => 400, 'msg' => '缺少 filename', 'data' => []]);
        }

        $stats = PageStatDao::getInstance()->getByFilename($filename);
        if ($stats === []) {
            // 可能存的是书库完整路径，再试 resolve
            $lib = BookDao::getInstance()->resolveByFilename($filename);
            if ($lib !== null) {
                $filename = $lib->filename;
                $stats = PageStatDao::getInstance()->getByFilename($filename);
            }
        }
        if ($stats === []) {
            return Response::asJson(['code' => 404, 'msg' => '无阅读记录', 'data' => []]);
        }

        $meta = $this->bookMetaFor([$filename])[$filename];
        $list = [];
        foreach ($stats as $s) {
            $list[] = [
                'book_filename' => $s->book_filename,
                'device_id' => $s->device_id,
                'page' => $s->page,
                'start_time' => $s->start_time,
                'duration' => $s->duration,
                'total_pages' => $s->total_pages,
            ];
        }

        return Response::asJson([
            'code' => 200,
            'msg' => 'ok',
            'data' => [
                'book' => [
                    'filename' => $filename,
                    'title' => $meta['title'],
                    'authors' => $meta['authors'],
                ],
                'stats' => $list,
                'count' => count($list),
            ],
        ]);
    }

    /**
     * GET /index/stats/books
     * DataTable：page_stat 中出现过的书（聚合）。
     */
    public function books(): Response
    {
        $page = max(1, (int)$this->request->get('page', 1));
        $pageSize = max(1, min(100, (int)$this->request->get('pageSize', 20)));
        $search = trim((string)$this->request->get('search', ''));
        $unmatchedOnly = (string)$this->request->get('unmatched', '') === '1';

        $pageStats = PageStatDao::getInstance()->getAllRows();

        /** @var array<string, array{filename: string, duration: int, records: int, lastRead: int}> $agg */
        $agg = [];
        foreach ($pageStats as $s) {
            $fn = $s->book_filename;
            if ($fn === '') {
                continue;
            }
            if (!isset($agg[$fn])) {
                $agg[$fn] = [
                    'filename' => $fn,
                    'duration' => 0,
                    'records' => 0,
                    'lastRead' => 0,
                ];
            }
            $agg[$fn]['records']++;
            $agg[$fn]['lastRead'] = max($agg[$fn]['lastRead'], $s->start_time);
        }
        // 用 perDay()（对外稳定 API），勿直接调内部 aggregateBookDays
        foreach (ReadingStats::perDay($pageStats) as $info) {
            foreach ($info['books'] as $fn => $dur) {
                if (!isset($agg[$fn])) {
                    continue;
                }
                $agg[$fn]['duration'] += $dur;
            }
        }

        $meta = $this->bookMetaFor(array_keys($agg));
        $rows = [];
        foreach ($agg as $fn => $a) {
            $m = $meta[$fn];
            if ($unmatchedOnly && $m['inLibrary']) {
                continue;
            }
            $title = $m['title'];
            $authors = $m['authors'];
            if ($search !== '') {
                $hay = mb_strtolower($title . ' ' . $authors . ' ' . $fn);
                if (!str_contains($hay, mb_strtolower($search))) {
                    continue;
                }
            }
            $rows[] = [
                'filename' => $fn,
                'title' => $title,
                'authors' => $authors,
                'coverUrl' => $m['coverUrl'],
                'inLibrary' => $m['inLibrary'],
                'duration' => $a['duration'],
                'durationText' => ReadingStats::formatDuration($a['duration']),
                'records' => $a['records'],
                'lastRead' => $a['lastRead'],
                'lastReadText' => $a['lastRead'] > 0 ? date('Y-m-d H:i', $a['lastRead']) : '—',
            ];
        }

        usort($rows, static fn ($a, $b) => $b['lastRead'] <=> $a['lastRead']);
        $total = count($rows);
        $slice = array_slice($rows, ($page - 1) * $pageSize, $pageSize);

        return Response::asJson([
            'code' => 200,
            'msg' => 'ok',
            'data' => $slice,
            'count' => $total,
        ]);
    }

    /**
     * POST /index/stats/remap
     * body: { from, to } — 将阅读记录改绑到书库书籍
     */
    public function remap(): Response
    {
        $body = $this->jsonBody();
        $from = PageStatDao::normalizeFilename((string)($body['from'] ?? $this->request->post('from', '')));
        $to = PageStatDao::normalizeFilename((string)($body['to'] ?? $this->request->post('to', '')));
        if ($from === '' || $to === '') {
            return Response::asJson(['code' => 400, 'msg' => '缺少 from 或 to', 'data' => []]);
        }

        $lib = BookDao::getInstance()->resolveByFilename($to);
        if ($lib === null) {
            return Response::asJson(['code' => 400, 'msg' => '目标书不在书库中', 'data' => []]);
        }
        $to = $lib->filename;

        $n = PageStatDao::getInstance()->remapFilename($from, $to);
        return Response::asJson([
            'code' => 200,
            'msg' => $n > 0 ? "已改绑 {$n} 条记录" : '没有需要改绑的记录',
            'data' => ['from' => $from, 'to' => $to, 'count' => $n],
        ]);
    }

    /**
     * POST /index/stats/create
     * body: { filename, date(Y-m-d), minutes, progress? }
     * 手工补一条日粒度阅读记录（device_id=manual）。
     */
    public function create(): Response
    {
        $body = $this->jsonBody();
        $filename = PageStatDao::normalizeFilename((string)($body['filename'] ?? $this->request->post('filename', '')));
        $date = trim((string)($body['date'] ?? $this->request->post('date', '')));
        $minutes = (int)($body['minutes'] ?? $this->request->post('minutes', 0));
        $progress = $body['progress'] ?? $this->request->post('progress', '');

        if ($filename === '') {
            return Response::asJson(['code' => 400, 'msg' => '请选择书籍', 'data' => []]);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return Response::asJson(['code' => 400, 'msg' => '日期格式应为 YYYY-MM-DD', 'data' => []]);
        }
        if ($minutes <= 0 || $minutes > 24 * 60) {
            return Response::asJson(['code' => 400, 'msg' => '阅读时长须在 1～1440 分钟', 'data' => []]);
        }

        $lib = BookDao::getInstance()->resolveByFilename($filename);
        if ($lib === null) {
            return Response::asJson(['code' => 400, 'msg' => '书籍不在书库中', 'data' => []]);
        }
        $filename = $lib->filename;

        $start = strtotime($date . ' 12:00:00');
        if ($start === false) {
            return Response::asJson(['code' => 400, 'msg' => '无效日期', 'data' => []]);
        }

        $page = 0;
        if ($progress !== '' && $progress !== null) {
            $page = (int)round(max(0, min(100, (float)$progress)));
        }

        $stat = new PageStatModel();
        $stat->book_filename = $filename;
        $stat->device_id = self::UNKNOWN_DEVICE;
        $stat->page = $page;
        $stat->start_time = $start;
        $stat->duration = $minutes * 60;
        $stat->total_pages = 100;
        PageStatDao::getInstance()->upsert($stat);

        return Response::asJson([
            'code' => 200,
            'msg' => '已添加阅读记录',
            'data' => [
                'filename' => $filename,
                'date' => $date,
                'duration' => $stat->duration,
                'durationText' => ReadingStats::formatDuration($stat->duration),
            ],
        ]);
    }

    /**
     * POST /index/stats/removeBook
     * body: { filename } — 删除该书全部阅读记录
     */
    public function removeBook(): Response
    {
        $body = $this->jsonBody();
        $filename = PageStatDao::normalizeFilename((string)($body['filename'] ?? $this->request->post('filename', '')));
        if ($filename === '') {
            return Response::asJson(['code' => 400, 'msg' => '缺少 filename', 'data' => []]);
        }

        $dao = PageStatDao::getInstance();
        $before = count($dao->getByFilename($filename));
        $dao->deleteByFilename($filename);

        return Response::asJson([
            'code' => 200,
            'msg' => $before > 0 ? "已删除 {$before} 条阅读记录" : '没有可删记录',
            'data' => ['filename' => $filename, 'count' => $before],
        ]);
    }

    /**
     * POST /index/stats/importMoon
     * multipart: file = *.mrpro（静读天下备份 zip）
     * 日粒度写入 page_stat；同设备旧数据整表替换。
     */
    public function importMoon(): Response
    {
        $file = $this->request->file('file');
        if ($file === null || $file->tmp_name === '' || !is_file($file->tmp_name)) {
            return Response::asJson(['code' => 400, 'msg' => '请上传 .mrpro 备份文件', 'data' => []]);
        }

        $name = strtolower($file->name !== '' ? $file->name : $file->full_path);
        if ($name !== '' && !str_ends_with($name, '.mrpro') && !str_ends_with($name, '.zip')) {
            return Response::asJson(['code' => 400, 'msg' => '仅支持 .mrpro 备份', 'data' => []]);
        }

        if ($file->size > 50 * 1024 * 1024) {
            return Response::asJson(['code' => 400, 'msg' => '备份文件过大（上限 50MB）', 'data' => []]);
        }

        try {
            $result = MoonReaderImport::importFromMrpro($file->tmp_name);
        } catch (RuntimeException $e) {
            return Response::asJson(['code' => 400, 'msg' => $e->getMessage(), 'data' => []]);
        } catch (Throwable $e) {
            return Response::asJson(['code' => 500, 'msg' => '导入失败：' . $e->getMessage(), 'data' => []]);
        }

        $unmatched = $result['unmatched'];
        $msg = sprintf(
            '已导入 %d 本书、%d 条日记录（设备 %s）',
            $result['books'],
            $result['stats'],
            $result['device_id']
        );
        if ($unmatched !== []) {
            $msg .= sprintf('（%d 本未匹配书库，已用文件名）', count($unmatched));
        }

        return Response::asJson([
            'code' => 200,
            'msg' => $msg,
            'data' => $result,
        ]);
    }

    /** 命中书库则用库路径，否则原样。 */
    private function resolveLibraryFilename(string $filename): string
    {
        $lib = BookDao::getInstance()->resolveByFilename($filename);
        return $lib ? $lib->filename : $filename;
    }

    /**
     * @param  PageStatModel[] $stats
     * @return array{hasData: bool, kpi: array, perMonth: list, perWeekday: list}
     */
    private function buildReadingActivity(array $stats): array
    {
        $empty = [
            'hasData' => false,
            'kpi' => [],
            'perMonth' => [],
            'perWeekday' => [],
        ];
        if ($stats === []) {
            return $empty;
        }

        $summary = ReadingStats::summarize($stats);

        $monthMax = 1;
        foreach ($summary['perMonth'] as $m) {
            $monthMax = max($monthMax, $m['duration']);
        }
        $perMonth = [];
        foreach ($summary['perMonth'] as $m) {
            $perMonth[] = [
                'label' => substr($m['month'], 2),
                'count' => ReadingStats::formatDuration($m['duration']),
                'pct' => (int)round($m['duration'] / $monthMax * 100),
            ];
        }
        if ($perMonth === []) {
            $cur = new \DateTimeImmutable('first day of this month');
            for ($i = 5; $i >= 0; $i--) {
                $ym = $cur->modify("-$i month")->format('Y-m');
                $perMonth[] = [
                    'label' => substr($ym, 2),
                    'count' => '0',
                    'pct' => 0,
                ];
            }
        }

        $weekMax = 1;
        foreach ($summary['perDayOfTheWeek'] as $d) {
            $weekMax = max($weekMax, $d['value']);
        }
        $perWeekday = [];
        foreach ($summary['perDayOfTheWeek'] as $d) {
            $perWeekday[] = [
                'name' => $d['name'],
                'count' => ReadingStats::formatDuration($d['value']),
                'pct' => (int)round($d['value'] / $weekMax * 100),
            ];
        }

        return [
            'hasData' => true,
            'kpi' => [
                'totalReadingTime' => ReadingStats::formatDuration($summary['totalReadingTime']),
                'last7DaysReadTime' => ReadingStats::formatDuration($summary['last7DaysReadTime']),
                'longestDay' => ReadingStats::formatDuration($summary['longestDay']),
                'mostPagesInADay' => $summary['mostPagesInADay'],
                'totalPagesRead' => $summary['totalPagesRead'],
            ],
            'perMonth' => $perMonth,
            'perWeekday' => $perWeekday,
        ];
    }

    /**
     * @param  PageStatModel[] $pageStats
     * @return array<string, array{duration: int, durationText: string, books: list}>
     */
    private function buildPerDay(array $pageStats): array
    {
        $filenames = [];
        foreach ($pageStats as $s) {
            if ($s->book_filename !== '') {
                $filenames[$s->book_filename] = true;
            }
        }
        $bookMeta = $this->bookMetaFor(array_keys($filenames));
        $progressByFile = ReadingStats::progressByFilename($pageStats);

        $out = [];
        foreach (ReadingStats::perDay($pageStats) as $day => $info) {
            $dayBooks = [];
            foreach ($info['books'] as $fn => $dur) {
                $meta = $bookMeta[$fn];
                $pct = $progressByFile[$fn] ?? 0.0;
                $dayBooks[] = [
                    'filename' => $fn,
                    'title' => $meta['title'],
                    'authors' => $meta['authors'],
                    'coverUrl' => $meta['coverUrl'],
                    'duration' => $dur,
                    'durationText' => ReadingStats::formatDuration($dur),
                    'progress' => $pct,
                    'progressText' => rtrim(rtrim(number_format($pct, 1, '.', ''), '0'), '.') . '%',
                ];
            }
            usort($dayBooks, static fn ($a, $b) => $b['duration'] <=> $a['duration']);
            $out[$day] = [
                'duration' => $info['duration'],
                'durationText' => ReadingStats::formatDuration($info['duration']),
                'books' => $dayBooks,
            ];
        }

        return $out;
    }

    /**
     * @param  string[] $filenames
     * @return array<string, array{title: string, authors: string, coverUrl: string, inLibrary: bool}>
     */
    private function bookMetaFor(array $filenames): array
    {
        $libraryByFile = [];
        foreach (BookDao::getInstance()->getByFilenames($filenames) as $lib) {
            $libraryByFile[$lib->filename] = $lib;
        }

        $out = [];
        foreach ($filenames as $fn) {
            $lib = $libraryByFile[$fn] ?? null;
            if ($lib === null) {
                $lib = BookDao::getInstance()->resolveByFilename($fn);
            }
            if ($lib !== null) {
                $out[$fn] = [
                    'title' => $lib->bookName !== '' ? $lib->bookName : $fn,
                    'authors' => $lib->author,
                    'coverUrl' => '/webdav/' . rawurlencode($lib->filename),
                    'inLibrary' => true,
                ];
                continue;
            }
            $base = basename(str_replace('\\', '/', $fn));
            $title = preg_replace('/\.[^.]+$/', '', $base) ?? $base;
            $out[$fn] = [
                'title' => $title !== '' ? $title : $fn,
                'authors' => '',
                'coverUrl' => '/webdav/' . rawurlencode($fn),
                'inLibrary' => false,
            ];
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $json = $this->request->json();
        return is_array($json) ? $json : [];
    }
}
