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
                    "title" => "定期采集",
                    "icon" => "schedule",
                    "sub" => [
                        [
                            "title" => "青年文摘采集",
                            "url" => "/admin/qing",
                            "icon" => "article",
                            "pjax" => true
                        ],
                        [
                            "title" => "读者阁采集",
                            "url" => "/admin/dzg",
                            "icon" => "article",
                            "pjax" => true
                        ],
                    ]
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
        
        return $this->viewResponse->asTpl('dashboard',[
            'globalStats' => $globalStats,
            'recentBooks' => $recentBooks,
            'highRatedBooks' => $highRatedBooks,
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

    public function dzg():Response
    {
        return $this->viewResponse->asTpl('',['books' => Duzhege::$books]);
    }

}
