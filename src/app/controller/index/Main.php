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
                    "url" => "/dashboard",
                    "icon" => "dashboard",
                    "pjax" => true
                ],
                [
                    "title" => "站点管理",
                    "url" => "/site",
                    "icon" => "public",
                    "pjax" => true
                ],
                [
                    "title" => "通知设置",
                    "icon" => "notifications",
                    "sub" => [
                        [
                            "title" => "通知渠道",
                            "url" => "/notify",
                            "icon" => "notifications",
                            "pjax" => true
                        ],
                        [
                            "title" => "邮件配置",
                            "url" => "/notify/email",
                            "icon" => "email",
                            "pjax" => true
                        ],
                        [
                            "title" => "企业微信配置",
                            "url" => "/notify/wechat",
                            "icon" => "chat",
                            "pjax" => true
                        ],
                        [
                            "title" => "Webhook配置",
                            "url" => "/notify/webhook",
                            "icon" => "webhook",
                            "pjax" => true
                        ]
                    ]
                ],
                [
                    "title" => "用户管理",
                    "url" => "/users",
                    "icon" => "group",
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
}
