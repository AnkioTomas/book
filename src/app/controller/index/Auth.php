<?php

declare(strict_types=1);

namespace app\controller\index;

use app\controller\ApiController;
use nova\framework\http\Response;

/**
 * 客户端凭证探测：Authorization: Bearer bk_xxx
 * GET /index/auth/ping
 */
class Auth extends ApiController
{
    public function ping(): Response
    {
        return Response::asJson([
            'code' => 200,
            'msg' => 'ok',
            'data' => [
                'user_id' => $this->userModel->id,
                'username' => $this->userModel->username,
                'display_name' => $this->userModel->display_name !== ''
                    ? $this->userModel->display_name
                    : $this->userModel->username,
            ],
        ]);
    }
}
