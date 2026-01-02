<?php

namespace app\controller\index;

use nova\framework\http\Response;
use function nova\framework\config;

class Webdav extends BaseController
{
    function config(): Response
    {
        if ($this->request->isGet()) {
            $deviceId = config('webdav.deviceId');
            if (empty($deviceId)) {
                config("webdav.deviceId", (string)(time() * 1000));
            }
            return Response::asJson([
                'code' => 200,
                'data' => config('webdav')
            ]);
        } else {
            config('webdav.url', $this->request->post('url'));
            config('webdav.username', $this->request->post('username'));
            config('webdav.password', $this->request->post('password'));
            return Response::asJson([
                'code' => 200,
                'msg' => '操作成功'
            ]);
        }
    }
}