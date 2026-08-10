<?php

declare(strict_types=1);

namespace app\controller\index;

use app\database\dao\DeviceTokenDao;
use nova\framework\http\Response;
use nova\plugin\login\controller\BaseAPIController;

/**
 * 设备长期凭证管理（仅 Session，禁止用 Token 自签 Token）。
 */
class Token extends BaseAPIController
{
    /**
     * GET /index/token/list
     */
    public function list(): Response
    {
        $rows = DeviceTokenDao::getInstance()->listByUser($this->userModel->id);
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => $row->id,
                'name' => $row->name,
                'created_at' => $row->created_at,
                'last_used_at' => $row->last_used_at,
                'expires_at' => $row->expires_at,
            ];
        }
        return Response::asJson(['code' => 200, 'msg' => 'success', 'data' => $data]);
    }

    /**
     * POST /index/token/create  name=设备名
     * 明文 token 只在此响应出现一次。
     */
    public function create(): Response
    {
        $name = trim((string)$this->request->post('name', 'KOReader'));
        $created = DeviceTokenDao::getInstance()->createToken($this->userModel->id, $name);
        return Response::asJson([
            'code' => 200,
            'msg' => '已创建，请立即复制保存，关闭后无法再查看完整令牌',
            'data' => $created,
        ]);
    }

    /**
     * POST /index/token/revoke  id=
     */
    public function revoke(): Response
    {
        $id = (int)$this->request->post('id', 0);
        if ($id <= 0) {
            return Response::asJson(['code' => 400, 'msg' => '参数错误']);
        }

        $row = DeviceTokenDao::getInstance()->find(null, [
            'id' => $id,
            'user_id' => $this->userModel->id,
        ]);
        if ($row === null) {
            return Response::asJson(['code' => 404, 'msg' => '令牌不存在']);
        }

        DeviceTokenDao::getInstance()->deleteModel($row);
        return Response::asJson(['code' => 200, 'msg' => '已撤销', 'data' => []]);
    }
}
