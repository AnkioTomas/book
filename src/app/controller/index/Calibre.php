<?php

declare(strict_types=1);

namespace app\controller\index;

use app\utils\EbookServiceClient;
use nova\framework\http\Response;
use Throwable;

use function nova\framework\config;

/**
 * Calibre 微服务地址配置
 *
 * config.calibre 在系统里以字符串形式存储（兼容现有 EbookServiceClient / Parser），
 * 这里用单字段表单适配。
 */
class Calibre extends BaseController
{
    public function config(): Response
    {
        if ($this->request->isGet()) {
            return Response::asJson([
                'code' => 200,
                'data' => [
                    'url' => (string)config('calibre'),
                ],
            ]);
        }

        $url = trim((string)$this->request->post('url', ''));
        config('calibre', $url);

        return Response::asJson([
            'code' => 200,
            'msg'  => '保存成功',
        ]);
    }

    /**
     * 测试连接（短超时），以表单当前填写的地址为准；为空则用已保存值
     */
    public function test(): Response
    {
        $url = trim((string)$this->request->post('url', ''));
        if ($url === '') {
            $url = (string)config('calibre');
        }
        if ($url === '') {
            return Response::asJson(['code' => 400, 'msg' => '请先填写 Calibre 服务地址']);
        }

        try {
            $client = new EbookServiceClient($url, 5);
            $health = $client->health();

            $version = $health['calibre'] ?? '未知版本';
            $status  = $health['status']  ?? 'unknown';

            if ($status !== 'ok') {
                return Response::asJson([
                    'code' => 400,
                    'msg'  => '服务返回异常：' . json_encode($health, JSON_UNESCAPED_UNICODE),
                ]);
            }

            return Response::asJson([
                'code' => 200,
                'msg'  => '连接成功：' . $version,
            ]);
        } catch (Throwable $e) {
            return Response::asJson([
                'code' => 400,
                'msg'  => '连接失败：' . $e->getMessage(),
            ]);
        }
    }
}
