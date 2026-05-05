<?php

declare(strict_types=1);

namespace app\controller\index;

use app\Application;
use app\database\dao\BookDao;
use nova\framework\http\Response;
use nova\plugin\login\manager\PwdLoginManager;
use nova\plugin\login\manager\SSOLoginManager;
use nova\plugin\task\Task;
use nova\plugin\tpl\ViewResponse;

class Main extends BaseController
{
    protected ViewResponse $viewResponse;

    public function init(): ?Response
    {
        $data = parent::init();
        if (!empty($data)) {
            return $data;
        }
        $this->viewResponse = new ViewResponse();
        $this->viewResponse->init(
            '',
            [
                'title' => Application::SYSTEM_NAME,
            ]
        );
        if (!$this->request->isPjax()) {

            $menuInfo = [
                [
                    "title" => "仪表盘",
                    "url" => "/admin/dashboard",
                    "icon" => "dashboard",
                    "pjax" => true
                ],
                [
                    "title" => "书架管理",
                    "url" => "/admin/book",
                    "icon" => "library_books",
                    "pjax" => true
                ],
                [
                    "title" => "系统设置",
                    "icon" => "settings",
                    "sub" => [
                        [
                            "title" => "账户安全",
                            "url" => "/settings/account",
                            "icon" => "security",
                            "pjax" => true
                        ],
                        [
                            "title" => "统一认证登录",
                            "url" => "/settings/sso",
                            "icon" => "vpn_key",
                            "pjax" => true
                        ],
                        [
                            "title" => "WebDav配置",
                            "url" => "/admin/webdav",
                            "icon" => "cloud_sync",
                            "pjax" => true
                        ],
                        [
                            "title" => "任务列表",
                            "url" => "/admin/task",
                            "icon" => "checklist",
                            "pjax" => true
                        ],
                    ]
                ]
            ];


            return $this->viewResponse->asTpl("layout", [
                'menuConfig' => $menuInfo

            ]);
        }
        return null;
    }

    public function account(): Response
    {
        return $this->viewResponse->asTpl(PwdLoginManager::TPL_PASSWORD, [
            "username" => $this->userModel->username,
        ]);
    }

    public function sso(): Response
    {
        return $this->viewResponse->asTpl(SSOLoginManager::TPL_SSO);
    }

    public function dashboard():Response
    {
        $bookDao = new BookDao();
        
        // 1. 全局统计
        $totalBooks = $bookDao->getCount();
        $seriesNames = $bookDao->getSeriesNames();
        $categories = $bookDao->getCategories();
        $favorites = $bookDao->getFavorites();
        
        $globalStats = [
            'totalBooks' => $totalBooks,
            'seriesCount' => count($seriesNames),
            'categoryCount' => count($categories),
            'favoriteCount' => count($favorites),
        ];
        
        // 2. 最新添加 10本
        $recentBooks = $bookDao->getList(1, 10)['list'];
        // 预处理书籍数据
        $recentBooks = $this->processBookData($recentBooks);
        $currentReading = $recentBooks[0] ?? null;

        // 3. 高分推荐 (评分>=4)
        $highRatedBooks = $bookDao->getAll(
            null,
            ["rate >= '4'"],
            1,
            10,
            false,
            'rate',
            'DESC'
        )['data'];
        // 预处理书籍数据
        $highRatedBooks = $this->processBookData($highRatedBooks);

        $dashboardMeta = [
            'updatedAt' => date('Y-m-d H:i'),
            'recentCount' => count($recentBooks),
            'highRatedCount' => count($highRatedBooks),
        ];

        return $this->viewResponse->asTpl('dashboard',[
            'globalStats' => $globalStats,
            'currentReading' => $currentReading,
            'recentBooks' => $recentBooks,
            'highRatedBooks' => $highRatedBooks,
            'dashboardMeta' => $dashboardMeta,
        ]);
    }

    public function webdav():Response
    {
        return $this->viewResponse->asTpl();
    }

    public function book():Response
    {
        return $this->viewResponse->asTpl();
    }

    public function reader():Response
    {
        $filename = rawurldecode($this->request->get('file', ''));
        $filename = trim($filename);
        if ($filename === '') {
            return $this->viewResponse->asTpl();
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $supported = ['epub', 'pdf', 'mobi', 'azw', 'azw3'];
        if (!in_array($ext, $supported, true)) {
            return $this->viewResponse->asTpl();
        }

        $bookUrl = '/admin/api/book/file?filename=' . rawurlencode($filename);
        $readerUrl = '/static/foliate/reader.html?url=' . rawurlencode($bookUrl) . '&filename=' . rawurlencode($filename);
        return $this->redirectTo($readerUrl);
    }

    public function qing():Response
    {
        return $this->viewResponse->asTpl();
    }

    /**
     * 预处理书籍数据：添加格式化日期和评分星星
     */
    private function processBookData(array $books): array
    {
        foreach ($books as &$book) {

            // 格式化日期
            $book['formattedDate'] = date('Y-m-d', (int)($book['addTime'] / 1000));

            // 生成评分星星 HTML
            $rate = (int)$book['rate'];
            $stars = '';
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $rate) {
                    $stars .= '<mdui-icon name="star"></mdui-icon>';
                } else {
                    $stars .= '<mdui-icon name="star_border"></mdui-icon>';
                }
            }
            $book['ratingStars'] = $stars;


            $book['coverUrl'] = "/webdav/".rawurlencode($book['filename']);

        }
        return $books;
    }

    public function task():Response
    {
        return $this->viewResponse->asTpl(Task::TASK_TPL);
    }

    public function index():Response
    {
        return Response::asRedirect("/admin/dashboard");
    }


}
