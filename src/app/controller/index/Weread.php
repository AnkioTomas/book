<?php

declare(strict_types=1);

namespace app\controller\index;

use app\controller\ApiController;
use app\utils\WereadSearch;
use nova\framework\http\Response;

/**
 * 微信读书图书搜索（编辑页元数据选择）。
 */
class Weread extends ApiController
{
    /**
     * POST /index/weread/search  q=
     */
    public function search(): Response
    {
        $query = trim((string)$this->request->post('q', ''));
        if ($query === '') {
            return Response::asJson([
                'code' => 400,
                'msg' => '搜索关键词不能为空',
            ]);
        }

        $results = WereadSearch::getInstance()->search($query);
        if ($results === []) {
            return Response::asJson([
                'code' => 404,
                'msg' => '未找到匹配的书籍',
            ]);
        }

        return Response::asJson([
            'code' => 200,
            'data' => $results,
        ]);
    }
}
