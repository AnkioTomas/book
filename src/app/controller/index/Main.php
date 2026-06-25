<?php

declare(strict_types=1);

namespace app\controller\index;

use app\database\dao\BookDao;
use app\database\dao\ReadingProgressDao;
use app\database\model\BookModel;
use nova\framework\http\Response;
use nova\plugin\login\controller\BaseViewController;
use nova\plugin\tpl\Pjax;

class Main extends BaseViewController
{
    public function index(): Response
    {
        return Pjax::redirectTo($this->firstUri());
    }

    public function dashboard(): Response
    {
        $recentBooks = BookDao::getInstance()->getAll([], [], 1, 25, 'addTime', false)['data'];
        foreach ($recentBooks as &$book) {
            $book['formattedDate'] = date('Y-m-d', (int)($book['addTime'] / 1000));
            $book['coverUrl'] = '/webdav/' . rawurlencode($book['filename']);
        }
        unset($book);

        $_recentlyReadBooks = ReadingProgressDao::getInstance()->getAll([], [], 1, 25, 'timestamp', false)['data'];

        $recentlyReadBooks = [];

        foreach ($_recentlyReadBooks as $bookItem) {
            $readingBook = BookDao::getInstance()->getByFileName($bookItem['filename']);
            if (empty($readingBook)) {
                continue;
            }
            $bookItem += (array)$readingBook;
            $bookItem['formattedDate'] = date('Y-m-d', (int)($bookItem['addTime'] / 1000));
            $bookItem['coverUrl'] = '/webdav/' . rawurlencode($bookItem['filename']);
            $recentlyReadBooks[] = $bookItem;
        }

        $currentReading = $recentlyReadBooks[0] ?? null;

        return $this->viewResponse->asTpl('dashboard', [
            'currentReading' => $currentReading,
            'recentlyReadBooks' => $recentlyReadBooks,
            'recentBooks' => $recentBooks,
        ]);
    }

    /**
     * 统计面板。全量加载本地藏书与阅读进度，在内存中聚合（个人藏书量级，无需 SQL 聚合）。
     * 所有指标只用现成字段，复用 BookModel 的语义方法，不依赖任何埋点。
     */
    public function stats(): Response
    {
        /** @var \app\database\model\BookModel[] $books */
        $books = BookDao::getInstance()->getAll([], [], null, 0, null, true)['data'];
        /** @var \app\database\model\ReadingProgressModel[] $progressList */
        $progressList = ReadingProgressDao::getInstance()->getAll([], [], null, 0, null, true)['data'];

        $progressMap = [];
        foreach ($progressList as $p) {
            $progressMap[$p->filename] = $p->percent;
        }

        $total = count($books);
        $finished = 0;
        $reading = 0;
        $rateSum = 0.0;
        $rateCount = 0;

        $catCount = [];
        $ratingBuckets = array_fill(0, 6, 0); // 0=未评分, 1..5=星级
        $monthCount = [];
        $neverRead = [];
        $booksByFile = [];

        foreach ($books as $b) {
            $booksByFile[$b->filename] = $b;
            $isFinished = $b->hasFinishedTag();
            if ($isFinished) {
                $finished++;
            }

            $pct = $progressMap[$b->filename] ?? null;
            if ($pct !== null && $pct > 0 && $pct < 100 && !$isFinished) {
                $reading++;
            }

            $rate = max(0, min(5, (int)round((float)$b->rate)));
            $ratingBuckets[$rate]++;
            if ($rate > 0) {
                $rateSum += $rate;
                $rateCount++;
            }

            $cat = $b->getCategoryName();
            $cat = $cat === '' ? '未分类' : $cat;
            $catCount[$cat] = ($catCount[$cat] ?? 0) + 1;

            if ($b->addTime > 0) {
                $ym = date('Y-m', (int)($b->addTime / 1000));
                $monthCount[$ym] = ($monthCount[$ym] ?? 0) + 1;
            }

            if (!isset($progressMap[$b->filename])) {
                $neverRead[] = $b;
            }
        }

        // 分类分布：按数量降序取前 12，pct 为相对最大值的条宽
        arsort($catCount);
        $catMax = $catCount ? max($catCount) : 1;
        $categories = [];
        foreach (array_slice($catCount, 0, 12, true) as $name => $cnt) {
            $categories[] = ['name' => $name, 'count' => $cnt, 'pct' => (int)round($cnt / $catMax * 100)];
        }

        // 评分分布：5★→1★，末尾附「未评」
        $ratingMax = max(max($ratingBuckets), 1);
        $ratings = [];
        for ($s = 5; $s >= 1; $s--) {
            $ratings[] = ['label' => $s . '★', 'count' => $ratingBuckets[$s], 'pct' => (int)round($ratingBuckets[$s] / $ratingMax * 100)];
        }
        $ratings[] = ['label' => '未评', 'count' => $ratingBuckets[0], 'pct' => (int)round($ratingBuckets[0] / $ratingMax * 100)];

        // 近 12 个月入库趋势（唯一可信的时间序列）
        $series = [];
        $cur = new \DateTimeImmutable('first day of this month');
        for ($i = 11; $i >= 0; $i--) {
            $series[$cur->modify("-$i month")->format('Y-m')] = 0;
        }
        foreach ($monthCount as $ym => $cnt) {
            if (isset($series[$ym])) {
                $series[$ym] = $cnt;
            }
        }
        $monthMax = max(max($series), 1);
        $months = [];
        foreach ($series as $ym => $cnt) {
            $months[] = ['label' => substr($ym, 2), 'count' => $cnt, 'pct' => (int)round($cnt / $monthMax * 100)];
        }

        // 三个清单结构相同，用一个闭包消除重复
        $row = static fn (BookModel $b, string $meta): array => [
            'bookName' => $b->bookName,
            'author' => $b->author,
            'filename' => $b->filename,
            'coverUrl' => '/webdav/' . rawurlencode($b->filename),
            'meta' => $meta,
        ];
        $fmtDate = static fn (BookModel $b): string => $b->addTime > 0 ? date('Y-m-d', (int)($b->addTime / 1000)) : '—';

        // 从未翻开：最早入库优先（典型囤积）
        usort($neverRead, fn ($a, $b) => $a->addTime <=> $b->addTime);
        $dusty = [];
        foreach (array_slice($neverRead, 0, 8) as $b) {
            $dusty[] = $row($b, $fmtDate($b));
        }

        // 最近添加：入库时间倒序
        $byAdd = $books;
        usort($byAdd, fn ($a, $b) => $b->addTime <=> $a->addTime);
        $recentAdded = [];
        foreach (array_slice($byAdd, 0, 8) as $b) {
            $recentAdded[] = $row($b, $fmtDate($b));
        }

        // 最近阅读：进度更新时间倒序
        usort($progressList, fn ($a, $b) => $b->timestamp <=> $a->timestamp);
        $recentRead = [];
        foreach ($progressList as $p) {
            $b = $booksByFile[$p->filename] ?? null;
            if (!$b) {
                continue;
            }
            $recentRead[] = $row($b, round($p->percent) . '%');
            if (count($recentRead) >= 8) {
                break;
            }
        }

        return $this->viewResponse->asTpl('stats', [
            'kpi' => [
                'total' => $total,
                'finished' => $finished,
                'reading' => $reading,
                'avgRate' => $rateCount > 0 ? round($rateSum / $rateCount, 1) : 0,
                'neverRead' => count($neverRead),
            ],
            'categories' => $categories,
            'ratings' => $ratings,
            'months' => $months,
            'recentAdded' => $recentAdded,
            'recentRead' => $recentRead,
            'dusty' => $dusty,
        ]);
    }

    public function calibre(): Response
    {
        return $this->viewResponse->asTpl();
    }

    public function book(): Response
    {
        return $this->viewResponse->asTpl();
    }

    private function subMenus(): array
    {
        $bookBase = '/index/main/book';

        $series = [];
        foreach (BookDao::getInstance()->getSeriesNames() as $item) {
            $series[] = [
                'title' => $item,
                'icon' => 'book',
                'url' => $bookBase . '?series=' . rawurlencode($item),
                'pjax' => true,
                'match' => '^/index/main/book\?([^#]*&)?series=' . preg_quote(rawurlencode($item), '/') . '(&|$)',
            ];
        }

        $categories = [];
        foreach (BookDao::getInstance()->getCategories() as $item) {
            $categories[] = [
                'title' => $item,
                'icon' => 'folder',
                'url' => $bookBase . '?favorite=' . rawurlencode($item),
                'pjax' => true,
                'match' => '^/index/main/book\?([^#]*&)?favorite=' . preg_quote(rawurlencode($item), '/') . '(&|$)',
            ];
        }
        $categories[] = [
            'title' => '无分类',
            'icon' => 'folder',
            'url' => $bookBase . '?favorite=empty',
            'pjax' => true,
            'match' => '^/index/main/book\?([^#]*&)?favorite=empty(&|$)',
        ];

        $tags = [];
        foreach (BookDao::getInstance()->getTags() as $item) {
            $tags[] = [
                'title' => $item,
                'icon' => 'label',
                'url' => $bookBase . '?category=' . rawurlencode($item),
                'pjax' => true,
                'match' => '^/index/main/book\?([^#]*&)?category=' . preg_quote(rawurlencode($item), '/') . '(&|$)',
            ];
        }

        $p = 'series|favorite|category|seriesNum';
        $rule = '^/index/main/book/?(?:$|\?(?!(?:' . $p . ')=)(?!.*[?&](?:' . $p . ')=)[^#]*)(?:#.*)?$';

        return [
            [
                'title' => '全部书籍',
                'icon' => 'menu_book',
                'url' => $bookBase,
                'pjax' => true,
                'match' => $rule,
            ],
            [
                'title' => '系列',
                'icon' => 'bookmarks',
                'pjax' => true,
                'sub' => $series,
            ],
            [
                'title' => '分类',
                'icon' => 'topic',
                'pjax' => true,
                'sub' => $categories,
            ],
            [
                'title' => '标签',
                'icon' => 'sell',
                'pjax' => true,
                'sub' => $tags,
            ],
        ];
    }

    protected function getMenu(): array
    {
        return [
            [
                'title' => '统计',
                'url' => '/index/main/stats',
                'icon' => 'analytics',
                'pjax' => true,
            ],
            [
                'title' => '继续阅读',
                'url' => '/index/main/dashboard',
                'icon' => 'dashboard',
                'pjax' => true,
            ],
            [
                'title' => '书库管理',
                'url' => '/index/main/book',
                'icon' => 'library_books',
                'pjax' => true,
                'sub' => $this->subMenus(),
            ],
            [
                'title' => '系统设置',
                'icon' => 'settings',
                'sub' => [
                    [
                        'title' => '账户安全',
                        'url' => '/login/pwd',
                        'icon' => 'security',
                        'pjax' => true,
                    ],
                    [
                        'title' => '统一认证登录',
                        'url' => '/login/oidc',
                        'icon' => 'vpn_key',
                        'pjax' => true,
                    ],
                    [
                        'title' => 'Calibre配置',
                        'url' => '/index/main/calibre',
                        'icon' => 'auto_stories',
                        'pjax' => true,
                    ],
                ],
            ],
        ];
    }
}
