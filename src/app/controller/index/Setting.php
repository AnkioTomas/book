<?php

declare(strict_types=1);

namespace app\controller\index;

use function nova\framework\config;

use nova\framework\http\Response;

use nova\plugin\login\controller\BaseAPIController;

/**
 * 书库行为开关（与 AI 服务商配置无关）
 * GET/POST /index/setting/bookConfig
 */
class Setting extends BaseAPIController
{
    public function bookConfig(): Response
    {
        if ($this->request->isGet()) {
            return Response::asJson([
                'code' => 200,
                'data' => config('book'),
            ]);
        }

        config('book', $this->request->post());

        return Response::asJson([
            'code' => 200,
            'msg'  => '保存成功',
        ]);
    }
}
