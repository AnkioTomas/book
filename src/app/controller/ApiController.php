<?php

declare(strict_types=1);

namespace app\controller;

use nova\framework\http\Response;
use nova\plugin\login\controller\BaseAPIController;
use nova\plugin\login\LoginManager;
use nova\plugin\login\route\Permission;
use nova\plugin\login\TokenManager;
use nova\plugin\tpl\Pjax;

/**
 * Book API 基类：login 插件个人 Token（Bearer）优先，否则回退 Session。
 * 带了 Bearer 却无效时返回 JSON 401，不 302 到登录页（墨水屏客户端要这个）。
 */
class ApiController extends BaseAPIController
{
    public function init(): ?Response
    {
        $this->userModel = LoginManager::getInstance()->checkLogin();
        if ($this->userModel === null) {
            if ((new TokenManager())->hasBearerToken()) {
                return Response::asJson([
                    'code' => 401,
                    'msg' => '无效或过期的访问令牌',
                    'data' => [],
                ]);
            }
            if ($this->request->isAjax() || $this->wantsJson()) {
                return Response::asJson([
                    'code' => 401,
                    'msg' => '未登录',
                    'data' => [],
                ]);
            }
            return Pjax::redirectTo(LoginManager::getInstance()->redirectLogin());
        }

        if (!Permission::getInstance()->hasPermission($this->userModel)) {
            return Response::asJson([
                'code' => 403,
                'msg' => '无权限',
                'data' => [],
            ]);
        }

        return null;
    }

    private function wantsJson(): bool
    {
        foreach ($this->request->getHeaders() as $key => $value) {
            if (strcasecmp((string)$key, 'Accept') === 0) {
                return str_contains((string)$value, 'application/json');
            }
        }
        return false;
    }
}
