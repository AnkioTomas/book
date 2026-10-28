<?php

declare(strict_types=1);

namespace app\controller\index;

use app\controller\ApiController;
use app\utils\meta\MetaRegistry;
use nova\framework\http\Response;

/**
 * 元数据补全：来源列表 + 按源搜索。
 */
class Meta extends ApiController
{
    /** GET /index/meta/sources */
    public function sources(): Response
    {
        return Response::asJson([
            'code' => 200,
            'data' => MetaRegistry::getInstance()->listSources(),
        ]);
    }

    /**
     * POST /index/meta/search  source= & q=
     */
    public function search(): Response
    {
        $source = trim((string)$this->request->post('source', ''));
        $query = trim((string)$this->request->post('q', ''));
        if ($source === '' || $source === 'ai') {
            return Response::asJson(['code' => 400, 'msg' => '请选择有效数据源']);
        }
        if ($query === '') {
            return Response::asJson(['code' => 400, 'msg' => '搜索关键词不能为空']);
        }

        try {
            $results = MetaRegistry::getInstance()->search($source, $query);
        } catch (\InvalidArgumentException $e) {
            return Response::asJson(['code' => 400, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return Response::asJson(['code' => 500, 'msg' => '搜索失败: ' . $e->getMessage()]);
        }

        if ($results === []) {
            return Response::asJson(['code' => 404, 'msg' => '未找到匹配的书籍']);
        }

        return Response::asJson(['code' => 200, 'data' => $results]);
    }
}
