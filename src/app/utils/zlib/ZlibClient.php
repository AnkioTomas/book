<?php

declare(strict_types=1);

namespace app\utils\zlib;

use function nova\framework\config;

use nova\framework\core\Instance;
use nova\plugin\http\HttpClient;
use nova\plugin\http\HttpDownloadManager;
use RuntimeException;
use Throwable;

/**
 * Z-Library eAPI 客户端（移植自月读 store/zlib/client.lua）。
 * 同步实现：镜像竞速 + 登录会话 + 搜索/详情/下载。
 */
final class ZlibClient extends Instance
{
    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36';

    private const RACE_WIDTH = 5;
    private const SERIAL_ATTEMPTS = 3;
    private const MAX_REDIRECT_HOPS = 5;
    private const DEAD_TTL = 7 * 24 * 3600;
    private const TIMEOUT = 25;

    /** @var list<string> */
    private const SEED_URLS = [
        'https://iread.ink',
        'https://zh.per101.ru',
        'https://zlib.bz',
        'https://lexlib.tw',
        'https://librella.fi',
        'https://lexlib.fi',
        'https://bookabooki.tw',
        'https://bookabooki.fi',
        'https://librella.tw',
        'https://fuckfbi.ru',
        'https://zh.z-lib.gd',
        'https://z-library.la',
        'https://zh.z-library.sk',
        'https://z-library.sk',
        'https://thai-books.sk',
        'https://frenchbooks.sk',
        'https://portuguese-books.sk',
        'https://urdu-books.sk',
        'https://spanish-books.sk',
        'https://italian-books.sk',
        'https://czechbooks.sk',
        'https://z-lib.sk',
        'https://german-books.sk',
        'https://1lib.sk',
        'https://z-lib.gd',
        'https://z-lib.gl',
        'https://z-lib.bz',
        'https://z-lib.do',
        'https://z-lib.fo',
        'https://z-library.hn',
        'https://japanese-books.sk',
        'https://1lib.su',
        'https://zlib.by',
        'https://101ml.su',
        'https://zlib.re',
        'https://library-oceania.sk',
        'https://library-latin.sk',
        'https://library-asia.sk',
        'https://lib-africa.sk',
    ];

    /** @var array<string, string> origin => user:pass */
    private const MIRROR_AUTH = [
        'https://iread.ink' => 'iread:DGvG2h3JMOvEoS',
    ];

    /** @var list<string> */
    private const CHALLENGE_MARKERS = [
        'Verifying your browser',
        'DiamWall',
        '/cdn-cgi/mitigation/',
        '__cf_chl',
        'Just a moment',
        'Checking your browser',
    ];

    private ?string $pinnedBase = null;

    /** @return array<string, mixed> */
    public function cfg(): array
    {
        $cfg = config('zlib');
        return is_array($cfg) ? $cfg : [];
    }

    /** @param array<string, mixed> $cfg */
    public function saveCfg(array $cfg): void
    {
        config('zlib', $cfg);
    }

    public function hasCredentials(): bool
    {
        $cfg = $this->cfg();
        return trim((string)($cfg['email'] ?? '')) !== ''
            && trim((string)($cfg['password'] ?? '')) !== '';
    }

    public function hasSession(): bool
    {
        $cfg = $this->cfg();
        return trim((string)($cfg['user_id'] ?? '')) !== ''
            && trim((string)($cfg['user_key'] ?? '')) !== '';
    }

    /**
     * @param array{order?: string, extension?: string} $filters
     * @return list<array<string, mixed>>
     */
    public function search(string $query, int $page = 1, int $limit = 20, string $language = '', array $filters = []): array
    {
        $form = [
            'message' => $query,
            'page' => max(1, $page),
            'limit' => max(1, min(50, $limit)),
        ];
        if ($language !== '') {
            $form['languages[0]'] = $language;
        }
        if (!empty($filters['order'])) {
            $form['order'] = $filters['order'];
        }
        if (!empty($filters['extension'])) {
            $form['extensions[0]'] = $filters['extension'];
        }

        $data = $this->jsonRequest('POST', '/eapi/book/search', $form, true, false);
        if (isset($data['error'])) {
            throw new RuntimeException($this->apiError($data, '搜索失败'));
        }

        return $this->mapList($data);
    }

    /** @return array<string, mixed> */
    public function detail(string $id, string $hash): array
    {
        $data = $this->jsonRequest('GET', '/eapi/book/' . rawurlencode($id) . '/' . rawurlencode($hash), null, true, false);
        if ((int)($data['success'] ?? 0) !== 1 || !is_array($data['book'] ?? null)) {
            throw new RuntimeException($this->apiError($data, '获取详情失败'));
        }
        $book = $this->mapBook($data['book']);
        if ($book === null) {
            throw new RuntimeException('详情无效');
        }
        return $book;
    }

    /**
     * 下载到本地路径（自动登录 / 会话失效重登一次）。
     */
    public function download(string $id, string $hash, string $dest): void
    {
        if (!$this->hasSession()) {
            $this->login();
        }

        try {
            $link = $this->downloadLink($id, $hash);
        } catch (RuntimeException $e) {
            if (!$this->isLoginError($e->getMessage())) {
                throw $e;
            }
            $cfg = $this->cfg();
            $cfg['user_id'] = '';
            $cfg['user_key'] = '';
            $this->saveCfg($cfg);
            $this->login();
            $link = $this->downloadLink($id, $hash);
        }

        $dir = dirname($dest);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('无法创建临时目录');
        }

        $cfg = $this->cfg();
        $client = HttpClient::init()
            ->timeout(300)
            ->setHeader('User-Agent', self::USER_AGENT)
            ->setHeader('Accept', '*/*')
            ->setHeader('Referer', ($this->pinnedBase ?: self::SEED_URLS[0]) . '/');
        // Cookie 用 CURLOPT_COOKIE：跨域跳转到 CDN 时比裸 Header 更稳
        if ($this->hasSession()) {
            $client->setOption(
                CURLOPT_COOKIE,
                'remix_userid=' . $cfg['user_id'] . '; remix_userkey=' . $cfg['user_key']
            );
        }
        $origin = $this->originOf($link) ?? '';
        if ($origin !== '' && isset(self::MIRROR_AUTH[$origin])) {
            $client->setHeader('Authorization', 'Basic ' . base64_encode(self::MIRROR_AUTH[$origin]));
            // 跳转到其它域名时仍带上 Authorization（部分镜像需要）
            $client->setOption(CURLOPT_UNRESTRICTED_AUTH, true);
        }
        $client->setOption(CURLOPT_FOLLOWLOCATION, true);
        $client->setOption(CURLOPT_MAXREDIRS, self::MAX_REDIRECT_HOPS);

        try {
            $ok = (new HttpDownloadManager($client))->download($link, $dest);
        } catch (Throwable $e) {
            @unlink($dest);
            $host = parse_url($link, PHP_URL_HOST) ?: 'unknown';
            throw new RuntimeException('下载失败(' . $host . '): ' . $e->getMessage(), 0, $e);
        }
        if (!$ok || !is_file($dest) || filesize($dest) < 64) {
            $size = is_file($dest) ? filesize($dest) : 0;
            @unlink($dest);
            $host = parse_url($link, PHP_URL_HOST) ?: 'unknown';
            throw new RuntimeException('下载失败或文件过小(' . $host . ', ' . $size . ' bytes)');
        }

        $head = (string)file_get_contents($dest, false, null, 0, 256);
        if (stripos($head, '<html') !== false || stripos($head, '<!DOCTYPE') !== false) {
            @unlink($dest);
            throw new RuntimeException('已达下载限额或返回了网页（请检查 Z站账号额度/登录态）');
        }
    }

    public function login(): void
    {
        if (!$this->hasCredentials()) {
            throw new RuntimeException('请先在系统设置里填写 Z-Library 邮箱和密码');
        }

        $cfg = $this->cfg();
        $base = $this->pinnedBase ?: $this->normalizeBase((string)($cfg['base_url'] ?? '')) ?: self::SEED_URLS[0];

        $attempts = [
            [
                'path' => '/rpc.php',
                'form' => [
                    'isModal' => 'true',
                    'email' => (string)$cfg['email'],
                    'password' => (string)$cfg['password'],
                    'site_mode' => 'books',
                    'action' => 'login',
                    'gg_json_mode' => '1',
                    'redirectUrl' => $base . '/',
                ],
            ],
            [
                'path' => '/eapi/user/login',
                'form' => [
                    'email' => (string)$cfg['email'],
                    'password' => (string)$cfg['password'],
                ],
            ],
        ];

        $firstErr = null;
        foreach ($attempts as $at) {
            try {
                $data = $this->jsonRequest('POST', $at['path'], $at['form'], false, true);
            } catch (RuntimeException $e) {
                $firstErr = $firstErr ?? $e->getMessage();
                if (str_contains($e->getMessage(), 'Too many logins')) {
                    break;
                }
                continue;
            }
            [$id, $key] = $this->sessionOf($data);
            if ($id !== null) {
                $cfg['user_id'] = $id;
                $cfg['user_key'] = $key;
                $this->saveCfg($cfg);
                return;
            }
            $msg = $this->loginError($data);
            $firstErr = $firstErr ?? $msg;
            if (str_contains($msg, 'Too many logins')) {
                break;
            }
        }

        throw new RuntimeException($firstErr ?? '登录失败');
    }

    private function downloadLink(string $id, string $hash): string
    {
        $data = $this->jsonRequest(
            'GET',
            '/eapi/book/' . rawurlencode($id) . '/' . rawurlencode($hash) . '/file',
            null,
            true,
            true
        );
        $file = $data['file'] ?? null;
        if ((int)($data['success'] ?? 0) !== 1 || !is_array($file)) {
            throw new RuntimeException($this->apiError($data, '获取下载链接失败'));
        }
        if (($file['allowDownload'] ?? true) === false) {
            throw new RuntimeException('已达下载限额，请稍后再试');
        }
        $link = (string)($file['downloadLink'] ?? '');
        if ($link === '') {
            throw new RuntimeException('未返回下载链接');
        }
        return $link;
    }

    /**
     * @param array<string, scalar>|null $form
     * @return array<string, mixed>
     */
    private function jsonRequest(string $method, string $path, ?array $form, bool $withSession, bool $serial): array
    {
        $race = !$serial && $this->pinnedBase === null;
        $bases = $this->baseCandidates($race ? self::RACE_WIDTH : self::SERIAL_ATTEMPTS);
        if ($bases === []) {
            throw new RuntimeException('没有可用镜像');
        }

        if ($race && count($bases) > 1) {
            return $this->raceJson($method, $path, $form, $withSession, $bases);
        }

        $lastErr = '网络请求失败';
        $failed = [];
        foreach ($bases as $base) {
            try {
                $data = $this->requestOnce($base, $method, $path, $form, $withSession);
                $this->pinnedBase = $this->originOf($base) ?? $base;
                $this->revivePinned($failed);
                return $data;
            } catch (RuntimeException $e) {
                $lastErr = $e->getMessage();
                $failed[$base] = time();
                if ($this->originOf($base) === $this->pinnedBase) {
                    $this->pinnedBase = null;
                }
            }
        }
        $this->blacklist($failed);
        throw new RuntimeException($lastErr);
    }

    /**
     * @param list<string> $bases
     * @param array<string, scalar>|null $form
     * @return array<string, mixed>
     */
    private function raceJson(string $method, string $path, ?array $form, bool $withSession, array $bases): array
    {
        $mh = curl_multi_init();
        $handles = [];
        $failed = [];

        foreach ($bases as $base) {
            $ch = $this->buildCurl($base, $method, $path, $form, $withSession);
            curl_multi_add_handle($mh, $ch);
            $handles[(int)$ch] = ['ch' => $ch, 'base' => $base];
        }

        $winner = null;
        $lastErr = '网络请求失败';
        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($status > CURLM_OK) {
                break;
            }
            while ($info = curl_multi_info_read($mh)) {
                /** @var \CurlHandle $ch */
                $ch = $info['handle'];
                $meta = $handles[(int)$ch] ?? null;
                if ($meta === null) {
                    continue;
                }
                $base = $meta['base'];
                $raw = curl_multi_getcontent($ch);
                $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = curl_error($ch);
                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
                unset($handles[(int)$ch]);

                if ($winner !== null) {
                    continue;
                }

                try {
                    $data = $this->parseResponse($base, $method, $path, $form, $withSession, $raw ?: '', $code, $err, 0);
                    $winner = $data;
                    $this->pinnedBase = $this->originOf($base) ?? $base;
                    // 未完成的车道只取消，不当失败（避免误伤可用镜像）
                    foreach ($handles as $left) {
                        curl_multi_remove_handle($mh, $left['ch']);
                        curl_close($left['ch']);
                    }
                    $handles = [];
                    $running = 0;
                } catch (RuntimeException $e) {
                    $lastErr = $e->getMessage();
                    $failed[$base] = time();
                }
            }
            if ($running > 0) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running > 0 && $winner === null);

        foreach ($handles as $left) {
            curl_multi_remove_handle($mh, $left['ch']);
            curl_close($left['ch']);
        }
        curl_multi_close($mh);

        if ($winner !== null) {
            $this->blacklist($failed);
            $this->revivePinned([]);
            return $winner;
        }

        $this->blacklist($failed);
        throw new RuntimeException($lastErr);
    }

    /**
     * @param array<string, scalar>|null $form
     * @return array<string, mixed>
     */
    private function requestOnce(string $base, string $method, string $path, ?array $form, bool $withSession): array
    {
        $ch = $this->buildCurl($base, $method, $path, $form, $withSession);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return $this->parseResponse($base, $method, $path, $form, $withSession, is_string($raw) ? $raw : '', $code, $err, 0);
    }

    /**
     * 手动跟随重定向（跨主机钉住新 origin）。
     *
     * @param array<string, scalar>|null $form
     * @return array<string, mixed>
     */
    private function parseResponse(
        string $base,
        string $method,
        string $path,
        ?array $form,
        bool $withSession,
        string $raw,
        int $code,
        string $curlErr,
        int $hops,
        ?string $currentUrl = null
    ): array {
        $url = $currentUrl ?? ($base . $path);
        if ($code === 0) {
            throw new RuntimeException($curlErr !== '' ? $curlErr : '连接失败');
        }

        if (in_array($code, [301, 302, 303, 307, 308], true)) {
            if ($hops >= self::MAX_REDIRECT_HOPS) {
                throw new RuntimeException('重定向过多');
            }
            if (!preg_match('/^Location:\s*(.+)$/mi', $raw, $m)) {
                throw new RuntimeException('HTTP ' . $code);
            }
            $target = $this->absoluteUrl($url, trim($m[1]));
            if ($target === null) {
                throw new RuntimeException('无效重定向');
            }
            $newOrigin = $this->originOf($target);
            $isMirrorMove = $newOrigin !== null && $newOrigin !== $this->originOf($url);
            $nextMethod = $method;
            $nextForm = $form;
            if (!$isMirrorMove && !in_array($code, [307, 308], true) && strtoupper($method) !== 'GET') {
                $nextMethod = 'GET';
                $nextForm = null;
            }
            if ($isMirrorMove && $newOrigin !== null) {
                $this->pinnedBase = $newOrigin;
            }
            $ch = $this->buildCurlAbsolute($target, $nextMethod, $nextForm, $withSession);
            $raw2 = curl_exec($ch);
            $code2 = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err2 = curl_error($ch);
            curl_close($ch);
            return $this->parseResponse(
                $newOrigin ?? $base,
                $nextMethod,
                '',
                $nextForm,
                $withSession,
                is_string($raw2) ? $raw2 : '',
                $code2,
                $err2,
                $hops + 1,
                $target
            );
        }

        if ($code >= 500) {
            throw new RuntimeException('HTTP ' . $code);
        }

        $body = $this->splitBody($raw);
        if ($this->looksLikeChallenge($body)) {
            throw new RuntimeException('服务器拒绝自动访问，正在尝试其它镜像');
        }

        if ($code < 200 || $code >= 300) {
            $data = json_decode($body, true);
            throw new RuntimeException($this->apiError(is_array($data) ? $data : null, 'HTTP ' . $code));
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new RuntimeException('无效响应格式');
        }
        return $data;
    }

    /**
     * @param array<string, scalar>|null $form
     */
    private function buildCurl(string $base, string $method, string $path, ?array $form, bool $withSession): \CurlHandle
    {
        return $this->buildCurlAbsolute($base . $path, $method, $form, $withSession);
    }

    /**
     * @param array<string, scalar>|null $form
     */
    private function buildCurlAbsolute(string $url, string $method, ?array $form, bool $withSession): \CurlHandle
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('无法初始化请求');
        }

        $headers = [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'User-Agent: ' . self::USER_AGENT,
        ];
        $cfg = $this->cfg();
        if ($withSession && $this->hasSession()) {
            $headers[] = 'Cookie: remix_userid=' . $cfg['user_id'] . '; remix_userkey=' . $cfg['user_key'];
        }
        $origin = $this->originOf($url) ?? '';
        if ($origin !== '' && isset(self::MIRROR_AUTH[$origin])) {
            $headers[] = 'Authorization: Basic ' . base64_encode(self::MIRROR_AUTH[$origin]);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
        ]);

        if ($form !== null) {
            $body = http_build_query($form);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8';
            $headers[] = 'X-Requested-With: XMLHttpRequest';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if (strtoupper($method) === 'GET') {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            }
        }

        return $ch;
    }

    /** @return list<string> */
    private function baseCandidates(int $cap): array
    {
        $cfg = $this->cfg();
        $dead = is_array($cfg['dead_bases'] ?? null) ? $cfg['dead_bases'] : [];
        $now = time();
        $out = [];
        $seen = [];

        $add = function (?string $u) use (&$out, &$seen, $dead, $now, $cfg): void {
            if ($u === null) {
                return;
            }
            $u = $this->normalizeBase($u);
            if ($u === '') {
                return;
            }
            $died = (int)($dead[$u] ?? 0);
            $skip = $died > 0
                && ($now - $died) < self::DEAD_TTL
                && $u !== $this->normalizeBase((string)($cfg['base_url'] ?? ''))
                && $u !== $this->pinnedBase;
            if (!isset($seen[$u]) && !$skip) {
                $seen[$u] = true;
                $out[] = $u;
            }
        };

        $add((string)($cfg['base_url'] ?? ''));
        $add($this->pinnedBase);
        foreach (self::SEED_URLS as $seed) {
            $add($seed);
        }

        if ($out === [] && $dead !== []) {
            $cfg['dead_bases'] = [];
            $this->saveCfg($cfg);
            return $this->baseCandidates($cap);
        }

        return array_slice($out, 0, max(1, $cap));
    }

    /** @param array<string, int> $failed */
    private function blacklist(array $failed): void
    {
        if ($failed === []) {
            return;
        }
        $cfg = $this->cfg();
        $dead = is_array($cfg['dead_bases'] ?? null) ? $cfg['dead_bases'] : [];
        $now = time();
        foreach ($failed as $base => $ts) {
            if ($base === $this->pinnedBase) {
                continue;
            }
            $dead[$base] = $ts ?: $now;
        }
        if ($this->pinnedBase !== null) {
            unset($dead[$this->pinnedBase]);
        }
        $cfg['dead_bases'] = $dead;
        $this->saveCfg($cfg);
    }

    /** @param array<string, int> $failed */
    private function revivePinned(array $failed): void
    {
        if ($this->pinnedBase === null) {
            return;
        }
        $cfg = $this->cfg();
        $dead = is_array($cfg['dead_bases'] ?? null) ? $cfg['dead_bases'] : [];
        if (!isset($dead[$this->pinnedBase]) && $failed === []) {
            return;
        }
        unset($dead[$this->pinnedBase]);
        $cfg['dead_bases'] = $dead;
        $this->saveCfg($cfg);
    }

    private function normalizeBase(string $u): string
    {
        $u = preg_replace('/\s+/', '', $u) ?? $u;
        $u = rtrim($u, '/');
        if ($u === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $u)) {
            $u = 'https://' . $u;
        }
        return $u;
    }

    private function originOf(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        if (!preg_match('#^(https?)://([^/:]+)(?::(\d+))?#i', $url, $m)) {
            return null;
        }
        $origin = strtolower($m[1]) . '://' . $m[2];
        if (!empty($m[3])) {
            $origin .= ':' . $m[3];
        }
        return $origin;
    }

    private function absoluteUrl(string $from, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $origin = $this->originOf($from);
        if ($origin === null) {
            return null;
        }
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $dir = preg_replace('#/[^/]*$#', '/', parse_url($from, PHP_URL_PATH) ?: '/') ?: '/';
        return $origin . $dir . $location;
    }

    private function splitBody(string $raw): string
    {
        $pos = strpos($raw, "\r\n\r\n");
        if ($pos === false) {
            $pos = strpos($raw, "\n\n");
            if ($pos === false) {
                return $raw;
            }
            return substr($raw, $pos + 2);
        }
        return substr($raw, $pos + 4);
    }

    private function looksLikeChallenge(string $body): bool
    {
        if ($body === '' || !str_contains(substr($body, 0, 4096), '<')) {
            return false;
        }
        $head = substr($body, 0, 4096);
        foreach (self::CHALLENGE_MARKERS as $marker) {
            if (str_contains($head, $marker)) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed>|null $data */
    private function apiError(?array $data, string $fallback): string
    {
        $err = $data['error'] ?? null;
        if (is_array($err)) {
            $err = $err['message'] ?? null;
        }
        if (is_string($err) && $err !== '') {
            return $err;
        }
        if (is_string($data['message'] ?? null) && $data['message'] !== '') {
            return (string)$data['message'];
        }
        return $fallback;
    }

    /** @param array<string, mixed>|null $data */
    private function loginError(?array $data): string
    {
        $resp = $data['response']['message'] ?? null;
        if (is_string($resp) && $resp !== '') {
            return $resp;
        }
        $first = $data['errors'][0] ?? null;
        if (is_array($first) && is_string($first['message'] ?? null) && $first['message'] !== '') {
            return (string)$first['message'];
        }
        return $this->apiError($data, '登录失败');
    }

    /**
     * @param array<string, mixed> $data
     * @return array{0: ?string, 1: ?string}
     */
    private function sessionOf(array $data): array
    {
        $user = [];
        if (is_array($data['response'] ?? null)) {
            $user = $data['response'];
        } elseif (is_array($data['user'] ?? null)) {
            $user = $data['user'];
        }
        $id = (string)($user['id'] ?? $user['user_id'] ?? '');
        $key = (string)($user['remix_userkey'] ?? $user['user_key'] ?? '');
        if ($id === '' || $key === '') {
            return [null, null];
        }
        return [$id, $key];
    }

    private function isLoginError(string $msg): bool
    {
        return str_contains($msg, 'Please login') || str_contains($msg, '请登录');
    }

    /**
     * @param array<string, mixed> $wire
     * @return list<array<string, mixed>>
     */
    private function mapList(array $wire): array
    {
        $rows = $wire['books'] ?? null;
        if (!is_array($rows) && is_array($wire['exactMatch']['books'] ?? null)) {
            $rows = $wire['exactMatch']['books'];
        }
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $book = $this->mapBook($row);
            if ($book !== null) {
                $out[] = $book;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    private function mapBook(array $row): ?array
    {
        $id = (string)($row['id'] ?? '');
        $hash = (string)($row['hash'] ?? '');
        if ($id === '' || $hash === '') {
            return null;
        }
        $cover = (string)($row['cover'] ?? '');
        if ($cover !== '' && !preg_match('#^https?://#i', $cover)) {
            $cover = '';
        }
        $desc = (string)($row['description'] ?? $row['intro'] ?? '');
        $desc = trim(html_entity_decode(strip_tags($desc), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $ext = strtolower((string)($row['extension'] ?? $row['format'] ?? 'epub'));
        if (!in_array($ext, ['epub', 'mobi', 'azw3', 'azw', 'pdf', 'txt'], true)) {
            $ext = 'epub';
        }

        return [
            'id' => $id,
            'hash' => $hash,
            'stable_id' => $id . ':' . $hash,
            'title' => trim((string)($row['title'] ?? '')) ?: '未知书名',
            'author' => trim((string)($row['author'] ?? $row['authors'] ?? '')),
            'cover' => $cover,
            'intro' => $desc,
            'filesize' => isset($row['filesize']) ? (int)$row['filesize'] : 0,
            'extension' => $ext,
            'language' => (string)($row['language'] ?? ''),
            'year' => (string)($row['year'] ?? ''),
            'publisher' => (string)($row['publisher'] ?? ''),
        ];
    }

    public static function safeFilename(string $title, string $author, string $ext): string
    {
        $title = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $title) ?? $title);
        $author = trim(preg_replace('/[\\\\\/:*?"<>|]+/u', ' ', $author) ?? $author);
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        $author = trim(preg_replace('/\s+/u', ' ', $author) ?? $author);
        if ($title === '') {
            $title = 'book';
        }
        $name = $author !== '' ? ($title . ' - ' . $author) : $title;
        if (strlen($name) > 180) {
            $name = mb_substr($name, 0, 180);
        }
        $ext = strtolower($ext);
        if (!in_array($ext, ['epub', 'mobi', 'azw3', 'azw', 'pdf', 'txt'], true)) {
            $ext = 'epub';
        }
        return $name . '.' . $ext;
    }
}
