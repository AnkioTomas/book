<?php

declare(strict_types=1);

namespace app\controller\index;

use app\Application;


use nova\framework\http\Response;
use nova\plugin\login\manager\PwdLoginManager;
use nova\plugin\login\manager\SSOLoginManager;
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
                    "icon" => "public",
                    "pjax" => true
                ],
                [
                    "title" => "定期采集",
                    "icon" => "notifications",
                    "sub" => [
                        [
                            "title" => "青年文摘采集",
                            "url" => "/admin/qing",
                            "icon" => "notifications",
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
                            "icon" => "vpn_key",
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
        return $this->viewResponse->asTpl();
    }

    public function webdav():Response
    {
        return $this->viewResponse->asTpl();
    }

    public function book():Response
    {
        return $this->viewResponse->asTpl();
    }
}
