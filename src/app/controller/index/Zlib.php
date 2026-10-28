<?php

declare(strict_types=1);

namespace app\controller\index;

use app\utils\BookIngest;
use app\utils\meta\MetaHit;
use app\utils\zlib\ZlibClient;

use function nova\framework\config;

use nova\framework\http\Response;
use nova\plugin\login\controller\BaseAPIController;
use RuntimeException;
use Throwable;

use const ROOT_PATH;

/**
 * Z-Library：配置 / 搜索 / 下载并入库。
 */
class Zlib extends BaseAPIController
{
    /** GET/POST /index/zlib/config */
    public function config(): Response
    {
        if ($this->request->isGet()) {
            $cfg = config('zlib');
            if (!is_array($cfg)) {
                $cfg = [];
            }
            return Response::asJson([
                'code' => 200,
                'data' => [
                    'email' => (string)($cfg['email'] ?? ''),
                    'password' => (string)($cfg['password'] ?? ''),
                    'base_url' => (string)($cfg['base_url'] ?? ''),
                    'has_session' => ZlibClient::getInstance()->hasSession(),
                ],
            ]);
        }

        $email = trim((string)$this->request->post('email', ''));
        $password = (string)$this->request->post('password', '');
        $baseUrl = trim((string)$this->request->post('base_url', ''));

        $old = config('zlib');
        $old = is_array($old) ? $old : [];

        $cfg = [
            'email' => $email,
            'password' => $password,
            'base_url' => $baseUrl,
            'dead_bases' => is_array($old['dead_bases'] ?? null) ? $old['dead_bases'] : [],
            // 改账号清会话
            'user_id' => '',
            'user_key' => '',
        ];
        if ($email === (string)($old['email'] ?? '') && $password === (string)($old['password'] ?? '')) {
            $cfg['user_id'] = (string)($old['user_id'] ?? '');
            $cfg['user_key'] = (string)($old['user_key'] ?? '');
        }

        config('zlib', $cfg);

        return Response::asJson(['code' => 200, 'msg' => '保存成功']);
    }

    /** POST /index/zlib/testLogin */
    public function testLogin(): Response
    {
        try {
            ZlibClient::getInstance()->login();
            return Response::asJson(['code' => 200, 'msg' => '登录成功']);
        } catch (Throwable $e) {
            return Response::asJson(['code' => 400, 'msg' => $e->getMessage()]);
        }
    }

    /** POST /index/zlib/search  q=&page=&limit=&language=&order=&extension= */
    public function search(): Response
    {
        $q = trim((string)$this->request->post('q', ''));
        $page = (int)$this->request->post('page', 1);
        $limit = (int)$this->request->post('limit', 20);
        $language = trim((string)$this->request->post('language', ''));
        $order = trim((string)$this->request->post('order', ''));
        $extension = trim((string)$this->request->post('extension', ''));

        $filters = [];
        if ($order !== '') {
            $filters['order'] = $order;
        }
        if ($extension !== '') {
            $filters['extension'] = $extension;
        }

        try {
            $list = ZlibClient::getInstance()->search($q, $page, $limit, $language, $filters);
        } catch (Throwable $e) {
            return Response::asJson(['code' => 500, 'msg' => $e->getMessage()]);
        }

        if ($list === []) {
            return Response::asJson(['code' => 404, 'msg' => '未找到匹配的书籍']);
        }

        foreach ($list as &$row) {
            $row['author'] = MetaHit::cleanAuthor((string)($row['author'] ?? ''));
        }
        unset($row);

        return Response::asJson(['code' => 200, 'data' => $list]);
    }

    /**
     * POST /index/zlib/download
     * id=&hash= 或 stable_id=id:hash
     * 可选 title/author/extension 用于文件名
     */
    public function download(): Response
    {
        @set_time_limit(300);
        $id = trim((string)$this->request->post('id', ''));
        $hash = trim((string)$this->request->post('hash', ''));
        $stable = trim((string)$this->request->post('stable_id', ''));
        if (($id === '' || $hash === '') && $stable !== '' && str_contains($stable, ':')) {
            [$id, $hash] = explode(':', $stable, 2);
        }
        if ($id === '' || $hash === '') {
            return Response::asJson(['code' => 400, 'msg' => '缺少书籍 id/hash']);
        }

        $client = ZlibClient::getInstance();
        $title = trim((string)$this->request->post('title', ''));
        $author = MetaHit::cleanAuthor((string)$this->request->post('author', ''));
        $ext = strtolower(trim((string)$this->request->post('extension', 'epub')));

        try {
            if ($title === '') {
                $detail = $client->detail($id, $hash);
                $title = (string)($detail['title'] ?? 'book');
                if ($author === '') {
                    $author = MetaHit::cleanAuthor((string)($detail['author'] ?? ''));
                }
                if ($ext === '' || $ext === 'epub') {
                    $ext = (string)($detail['extension'] ?? 'epub');
                }
            }

            $filename = ZlibClient::safeFilename($title, $author, $ext);
            $tmpDir = ROOT_PATH . '/runtime/zlib';
            if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
                throw new RuntimeException('无法创建临时目录');
            }
            $tmp = $tmpDir . '/' . md5($id . ':' . $hash . microtime(true)) . '.' . pathinfo($filename, PATHINFO_EXTENSION);

            $client->download($id, $hash, $tmp);
            $result = BookIngest::fromLocalFile($tmp, $filename);
            @unlink($tmp);

            if (!$result['ok']) {
                return Response::asJson(['code' => 400, 'msg' => $result['msg']]);
            }

            return Response::asJson([
                'code' => 200,
                'msg' => $result['msg'],
                'data' => [
                    'id' => $result['id'] ?? 0,
                    'title' => $result['title'] ?? $title,
                    'isNew' => $result['isNew'] ?? false,
                ],
            ]);
        } catch (Throwable $e) {
            if (isset($tmp) && is_file($tmp)) {
                @unlink($tmp);
            }
            return Response::asJson(['code' => 500, 'msg' => $e->getMessage()]);
        }
    }
}
