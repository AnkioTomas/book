<?php

declare(strict_types=1);

namespace app\controller\index;

use app\Application;
use app\database\dao\BookDao;
use app\database\dao\ReadingProgressDao;
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
                    "title" => "继续阅读",
                    "url" => "/admin/dashboard",
                    "icon" => "dashboard",
                    "pjax" => true
                ],
                [
                    "title" => "书库管理",
                    "url" => "/admin/book",
                    "icon" => "library_books",
                    "pjax" => true,
                    "sub" => $this->subMenus()
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
                            "title" => "Calibre配置",
                            "url" => "/admin/calibre",
                            "icon" => "auto_stories",
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
                'menuConfig' => $menuInfo,
            ]);
        }
        return null;
    }

    private function subMenus(): array
    {

        $series = [];
        foreach (BookDao::getInstance()->getSeriesNames() as $item) {
            $series[] = [
                "title" => $item,
                "icon" => "book",
                "url" => "/admin/book?series=".rawurlencode($item),
                "pjax" => true,
                "match" => "^/admin/book\?([^#]*&)?series=".preg_quote(rawurlencode($item), '/')."(&|$)",
            ];
        }
        $categories = [];
        foreach (BookDao::getInstance()->getCategories() as $item) {
            $categories[] = [
                "title" => $item,
                "icon" => "folder",
                "url" => "/admin/book?favorite=".rawurlencode($item),
                "pjax" => true,
                "match" => "^/admin/book\?([^#]*&)?favorite=".preg_quote(rawurlencode($item), '/')."(&|$)",
            ];
        }
        $categories[] = [
            "title" => '无分类',
            "icon" => "folder",
            "url" => "/admin/book?favorite=empty",
            "pjax" => true,
            "match" => "^/admin/book\?([^#]*&)?favorite=empty(&|$)",
        ];
        $tags = [];
        foreach (BookDao::getInstance()->getTags() as $item) {
            $tags[] = [
                "title" => $item,
                "icon" => "label",
                "url" => "/admin/book?category=".rawurlencode($item),
                "pjax" => true,
                "match" => "^/admin/book\?([^#]*&)?category=".preg_quote(rawurlencode($item), '/')."(&|$)",
            ];
        }
        $p = 'series|favorite|category|seriesNum';

        // 查询串起点或 & 之后出现「维度参数名=」则视为已做书架筛选，不计入「全部书籍」
        $rule = '^/admin/book/?(?:$|\?(?!(?:'.$p.')=)(?!.*[?&](?:'.$p.')=)[^#]*)(?:#.*)?$';
        $menu = [
            [
                "title" => "全部书籍",
                "icon" => "menu_book",
                "url" => "/admin/book",
                "pjax" => true,
                "match" => $rule,
            ],
            [
                "title" => "系列",
                "icon" => "bookmarks",
                "pjax" => true,
                "sub" => $series,
            ],
            [
                "title" => "分类",
                "icon" => "topic",
                "pjax" => true,
                "sub" => $categories,
            ],
            [
                "title" => "标签",
                "icon" => "sell",
                "pjax" => true,
                "sub" => $tags,
            ],
        ];

        return $menu;
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

    public function dashboard(): Response
    {
        // 1. 最近添加（后端渲染，按 addTime 降序）
        $recentBooks = BookDao::getInstance()->getAll([], [], 1, 25, 'addTime', false)['data'];
        foreach ($recentBooks as &$book) {
            $book['formattedDate'] = date('Y-m-d', (int)($book['addTime'] / 1000));
            $book['coverUrl'] = "/webdav/".rawurlencode($book['filename']);
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
            $bookItem['coverUrl'] = "/webdav/".rawurlencode($bookItem['filename']);
            $recentlyReadBooks[] = $bookItem;
        }

        // 4. 继续阅读：优先取最近阅读第一本
        $currentReading = $recentlyReadBooks[0] ?? null;

        return $this->viewResponse->asTpl('dashboard', [
            'currentReading' => $currentReading,
            'recentlyReadBooks' => $recentlyReadBooks,
            'recentBooks' => $recentBooks,
        ]);
    }

    public function webdav(): Response
    {
        return $this->viewResponse->asTpl();
    }

    public function calibre(): Response
    {
        return $this->viewResponse->asTpl();
    }

    public function book(): Response
    {
        return $this->viewResponse->asTpl();
    }

    public function reader(): Response
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
        $readerUrl = '/static/foliate/reader.html?url=' . rawurlencode($bookUrl)
            . '&filename=' . rawurlencode($filename);
        foreach (['cfi', 'fraction', 'frac'] as $key) {
            $v = $this->request->get($key, '');
            if ($v === '') {
                continue;
            }
            $readerUrl .= '&' . rawurlencode($key) . '=' . rawurlencode((string)$v);
        }
        return $this->redirectTo($readerUrl);
    }

    public function task(): Response
    {
        return $this->viewResponse->asTpl(Task::TASK_TPL);
    }

    public function index(): Response
    {
        return Response::asRedirect("/admin/dashboard");
    }

}
