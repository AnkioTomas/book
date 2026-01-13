<?php

namespace app\controller\index;

use nova\framework\http\Response;
use nova\framework\route\Controller;
use nova\plugin\login\db\Model\UserModel;
use nova\plugin\login\LoginManager;

class BaseController extends Controller
{
    // 当前登录用户模型
    protected ?UserModel $userModel;
    /**
     * 初始化方法，进行域名和登录校验
     * @return Response|null
     */
    public function init(): ?Response
    {
        $this->userModel = LoginManager::getInstance()->checkLogin();

        if (empty($this->userModel)) {
            // 获取登录跳转地址
            $uri = LoginManager::getInstance()->redirectLogin();
            // 如果是 UI 侧控制器，使用 redirectTo 方法跳转
            if (strtolower($this->request->getRoute()->controller) === "main") { //这是UI侧
                return $this->redirectTo($uri);
            }
            // 否则返回 JSON 格式的未登录信息
            return Response::asJson([
                "code" => 401,
                "data" => $uri
            ]);
        }
        // 调用父类的初始化方法
        return parent::init();
    }

    protected function redirectTo(string $link): Response
    {
        // 如果不是 PJAX 请求，直接重定向
        if (!$this->request->isPjax()) {
            //跳转后台
            return Response::asRedirect($link);
        } else {
            // PJAX 请求下，返回 HTML 片段并用 JS 跳转
            return Response::asHtml(
                <<<EOF
<title id="title">302 Redirect</title>
<style id="style"></style>
<div id="container" class="container"></div>
<script id="script">
    window.pageLoadFiles = [];
    window.pageOnLoad = function (loading) {
        location.replace('$link');
        return false
    };
</script>
EOF
            );
        }
    }
}