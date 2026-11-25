<?php

declare(strict_types=1);

/**
 * 静读天下（Moon+ Reader v10.7）WebDAV 协议兼容性测试。
 * 运行：php tests/MoonReaderCompatTest.php
 */

$srcRoot = dirname(__DIR__) . '/src';
if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', $srcRoot);
}
if (!defined('RUNTIME_PATH')) {
    $tmpRuntime = sys_get_temp_dir() . '/book-compat-test-' . getmypid();
    @mkdir($tmpRuntime, 0777, true);
    define('RUNTIME_PATH', $tmpRuntime);
}

spl_autoload_register(static function (string $class) use ($srcRoot): void {
    $path = $srcRoot . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

use app\database\model\BookModel;
use app\database\model\ReadingProgressModel;
use app\task\SyncTask;
use app\utils\BookManager\BaseManager;
use app\utils\BookManager\BookManager;
use app\utils\BookManager\CoverManager;
use app\utils\MoonReaderAnnotation;
use nova\plugin\webdav\SimpleWebDAVClient;

$assertions = 0;
$assertEq = static function (mixed $expected, mixed $actual, string $msg) use (&$assertions): void {
    $assertions++;
    if ($expected !== $actual) {
        fwrite(
            STDERR,
            "FAIL: {$msg}\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n"
        );
        exit(1);
    }
};

$assertTrue = static function (bool $cond, string $msg) use (&$assertions): void {
    $assertions++;
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
};

// =========================================================================
// 1. .po 进度格式（含 WebView 滚屏尾缀 |<lastWebY>）
// =========================================================================
$p1 = ReadingProgressModel::fromString('1745487877136*3@0#120:15.2%');
$assertEq('1745487877136', $p1->deviceId, 'po full device id');
$assertEq(0, $p1->timestamp, 'po full does not treat device id as version time');
$assertEq(3, $p1->spineIndex, 'po full spine');
$assertEq(0, $p1->pageIndex, 'po full page');
$assertEq(120, $p1->offset, 'po full offset');
$assertEq(15.2, $p1->percent, 'po full percent');
$assertEq('full', $p1->shape, 'po full shape');
$assertEq('1745487877136*3@0#120:15.2%', $p1->toString(), 'po full round trip');

$p2 = ReadingProgressModel::fromString('1745487877136*3@0#120:15.2%|480');
$assertEq('1745487877136', $p2->deviceId, 'po webview suffix device id');
$assertEq(480, $p2->webY, 'po webview suffix scroll');
$assertEq(3, $p2->spineIndex, 'po webview suffix spine');
$assertEq(120, $p2->offset, 'po webview suffix offset');
$assertEq(15.2, $p2->percent, 'po webview suffix percent');
$assertEq('1745487877136*3@0#120:15.2%|480', $p2->raw, 'po webview raw preserved');
$assertEq('1745487877136*3@0#120:15.2%|480', $p2->toString(), 'po webview round trip');

$p3 = ReadingProgressModel::fromString('1745487877136*2@5:33.5%|0');
$assertEq('nohash', $p3->shape, 'po noHash shape');
$assertEq(2, $p3->spineIndex, 'po noHash suffix spine');
$assertEq(5, $p3->pageIndex, 'po noHash suffix page');
$assertEq(33.5, $p3->percent, 'po noHash suffix percent');
$assertEq(0, $p3->webY, 'po noHash keeps zero scroll');
$assertEq('1745487877136*2@5:33.5%|0', $p3->toString(), 'po noHash round trip');

$p4 = ReadingProgressModel::fromString('1745487877136*12:42%|1024');
$assertEq('short', $p4->shape, 'po short shape');
$assertEq(12, $p4->pageIndex, 'po short suffix page');
$assertEq(42.0, $p4->percent, 'po short suffix percent');
$assertEq('1745487877136*12:42%|1024', $p4->toString(), 'po short round trip');

// =========================================================================
// 2. .an 注解解析（固定 16 列 + 跳过 *DELETED* + 纯数字正文 + 书签不串列）
// =========================================================================
$makeAnBlock = static function (array $cols): string {
    return "#\n" . implode("\n", $cols) . "\n";
};

$anText = "123456\nindent:true\ntrim:false\n"
    // 普通高亮
    . $makeAnBlock([
        '1', '三体', '/sd/三体.epub', '/sd/三体.epub',
        '5', '0', '1000', '6', '-256', '1761286619609',
        '', '', '给岁月以文明', '0', '0', '',
    ])
    // 批注 + 高亮 + 书签同时存在（旧实现会串列丢原文）
    . $makeAnBlock([
        '2', '三体', '/sd/三体.epub', '/sd/三体.epub',
        '6', '0', '2000', '8', '-16711936', '1761286620000',
        '第六章书签', '第一行<BR>第二行', '给时光以生命', '0', '0', '',
    ])
    // 纯数字批注与纯数字高亮（旧实现 isBareNumber 会直接丢弃）
    . $makeAnBlock([
        '3', '三体', '/sd/三体.epub', '/sd/三体.epub',
        '7', '0', '3000', '4', '-256', '1761286621000',
        '', '1', '1984', '0', '0', '',
    ])
    // 纯书签（bookmark 非空，note/original 均为空）
    . $makeAnBlock([
        '4', '三体', '/sd/三体.epub', '/sd/三体.epub',
        '8', '0', '4000', '0', '0', '1761286622000',
        '第八章书签', '', '', '0', '0', '',
    ])
    // 静读天下删除墓碑（BookDb.deletedNotesToText 格式）
    . $makeAnBlock([
        '0', '0', '0', '0',
        '5', '0', '1000', '0', '0', '1761286623000',
        '已删书签', '已删批注', '0', '0', '0', '*DELETED*',
    ]);

$anZlib = zlib_encode($anText, ZLIB_ENCODING_DEFLATE);
$assertTrue(is_string($anZlib), 'zlib encode .an');

$models = MoonReaderAnnotation::parseToModels($anZlib, '科幻/三体.epub');
$assertEq(4, count($models), 'an skips *DELETED* and keeps 4 real notes');

$assertEq('给岁月以文明', $models[0]->text, 'an[0] highlight');
$assertEq('', $models[0]->note, 'an[0] empty note');
$assertEq('highlight', $models[0]->annotation_type, 'an[0] type');

$assertEq('给时光以生命', $models[1]->text, 'an[1] highlight not shifted by bookmark');
$assertEq("第一行\n第二行", $models[1]->note, 'an[1] multiline note');
$assertEq('note', $models[1]->annotation_type, 'an[1] type');

$assertEq('1984', $models[2]->text, 'an[2] numeric highlight preserved');
$assertEq('1', $models[2]->note, 'an[2] numeric note preserved');

$assertEq('第八章书签', $models[3]->text, 'an[3] pure bookmark fallback');
$assertEq('', $models[3]->note, 'an[3] pure bookmark empty note');

// 无头部直接 #\n 开头明文也能识别
$rawNoHeader = $makeAnBlock([
    '9', '三体', '/sd/三体.epub', '/sd/三体.epub',
    '1', '0', '10', '2', '-256', '1761286624000',
    '', '', '首行无头测试', '0', '0', '',
]);
$modelsNoHeader = MoonReaderAnnotation::parseToModels($rawNoHeader, '三体.epub');
$assertEq(1, count($modelsNoHeader), 'an without header parsed');
$assertEq('首行无头测试', $modelsNoHeader[0]->text, 'an without header text');

// =========================================================================
// 3. books.sync 子目录 filename / downloadUrl / deviceId 双向兼容
// =========================================================================
class FakeWebDAVClient extends SimpleWebDAVClient
{
    /** @var array<string, string> remotePath => content */
    public array $uploads = [];
    /** @var list<array{from:string,to:string}> */
    public array $moves = [];

    public function __construct()
    {
        parent::__construct('http://fake.local/dav');
    }

    public function mkdir(string $path): bool
    {
        return true;
    }

    public function upload(string $localPath, string $remotePath): bool
    {
        $this->uploads[$remotePath] = (string)file_get_contents($localPath);
        return true;
    }

    public function move(string $sourcePath, string $destPath, bool $overwrite = false): bool
    {
        $this->moves[] = ['from' => $sourcePath, 'to' => $destPath];
        return true;
    }
}

$makeManager = static function (string $class, FakeWebDAVClient $client, string $deviceId): object {
    $ref = new ReflectionClass($class);
    $obj = $ref->newInstanceWithoutConstructor();
    $obj->deviceId = $deviceId;

    $pRuntime = $ref->getProperty('runtime');
    $pRuntime->setValue($obj, RUNTIME_PATH . DS . 'books' . DS);
    @mkdir(RUNTIME_PATH . DS . 'books', 0777, true);

    $pClient = $ref->getProperty('client');
    $pClient->setValue($obj, $client);

    return $obj;
};

$fakeClient = new FakeWebDAVClient();
/** @var BookManager $bm */
$bm = $makeManager(BookManager::class, $fakeClient, 'php-dev-999');

$assertEq(
    '科幻/三体.epub',
    $bm->relPathFromDownloadUrl('[WebDav]/Apps/Books/科幻/三体.epub'),
    'relPathFromDownloadUrl plain utf8'
);
$assertEq(
    '科幻/三体.epub',
    $bm->relPathFromDownloadUrl('[WebDav]/Apps/Books/%E7%A7%91%E5%B9%BB/%E4%B8%89%E4%BD%93.epub'),
    'relPathFromDownloadUrl percent-encoded'
);
$assertEq(
    '',
    $bm->relPathFromDownloadUrl('[Gdrive]/Books/三体.epub'),
    'relPathFromDownloadUrl ignores non-WebDav'
);

$syncTask = (new ReflectionClass(SyncTask::class))->newInstanceWithoutConstructor();
$mResolve = new ReflectionMethod(SyncTask::class, 'resolveEntryPath');
$mToRemote = new ReflectionMethod(SyncTask::class, 'toRemoteEntry');

// 读：Moon+ 规范（filename=basename, downloadUrl=带子目录）
$assertEq(
    '科幻/三体.epub',
    $mResolve->invoke($syncTask, $bm, [
        'filename' => '三体.epub',
        'downloadUrl' => '[WebDav]/Apps/Books/科幻/三体.epub',
    ]),
    'resolveEntryPath Moon+ subdir'
);

// 读：旧版 PHP books.sync（filename 直接带子目录）
$assertEq(
    '科幻/三体.epub',
    $mResolve->invoke($syncTask, $bm, [
        'filename' => '科幻/三体.epub',
        'downloadUrl' => '',
    ]),
    'resolveEntryPath legacy PHP subdir'
);

// 读：根目录书
$assertEq(
    '局外人.epub',
    $mResolve->invoke($syncTask, $bm, [
        'filename' => '局外人.epub',
        'downloadUrl' => '',
    ]),
    'resolveEntryPath root book'
);

// 写：子目录书导出为 pure basename + 完整 downloadUrl + 本地改动刷新 deviceId
$bookEdited = new BookModel();
$bookEdited->filename = '科幻/三体.epub';
$bookEdited->bookName = '三体';
$bookEdited->downloadUrl = '';
$bookEdited->deviceId = 'moon-old-111';
$bookEdited->update_at = 2000;

$remoteEdited = $mToRemote->invoke($syncTask, $bookEdited, $bm, 1000);
$assertEq('三体.epub', $remoteEdited['filename'], 'toRemoteEntry filename is pure basename');
$assertEq('[WebDav]/Apps/Books/科幻/三体.epub', $remoteEdited['downloadUrl'], 'toRemoteEntry downloadUrl keeps subdir');
$assertEq('php-dev-999', $remoteEdited['deviceId'], 'toRemoteEntry updates deviceId when locally edited');
$assertTrue(!array_key_exists('update_at', $remoteEdited), 'toRemoteEntry strips update_at');

// 写：未改动的远端书保持原 deviceId
$bookUntouched = new BookModel();
$bookUntouched->filename = '局外人.epub';
$bookUntouched->deviceId = 'moon-old-111';
$bookUntouched->update_at = 0;

$remoteUntouched = $mToRemote->invoke($syncTask, $bookUntouched, $bm, 1000);
$assertEq('moon-old-111', $remoteUntouched['deviceId'], 'toRemoteEntry keeps original deviceId when untouched');

// =========================================================================
// 4. books.id 与 covers.id 标记文件同步写入
// =========================================================================
$assertTrue($bm->updateBookList([$remoteEdited]), 'updateBookList succeeds');
$assertTrue(isset($fakeClient->uploads['/Apps/Books/.Moon+/books.sync']), 'books.sync uploaded');
$assertEq('php-dev-999', $fakeClient->uploads['/Apps/Books/.Moon+/books.id'] ?? null, 'books.id written with deviceId');

$decodedBooks = json_decode((string)zlib_decode($fakeClient->uploads['/Apps/Books/.Moon+/books.sync']), true);
$assertEq('三体.epub', $decodedBooks[0]['filename'] ?? null, 'books.sync payload has pure basename');

/** @var CoverManager $cm */
$cm = $makeManager(CoverManager::class, $fakeClient, 'php-dev-999');
$tmpCover = RUNTIME_PATH . '/sample_cover.png';
file_put_contents($tmpCover, "\x89PNG\r\n\x1A\nfake");

// 为 CoverManager::dropCache 注入最小 Context + Cache stub（不触发真实配置/文件缓存）
$ctxRef = new ReflectionClass(\nova\framework\core\Context::class);
$dummyCtx = $ctxRef->newInstanceWithoutConstructor();
$dummyCtx->cache = new class extends \nova\framework\cache\Cache {
    public function __construct()
    {
    }

    public function delete(string $key): void
    {
    }
};
$GLOBALS['context'] = $dummyCtx;

$assertTrue($cm->uploadCover($tmpCover, '科幻/三体.epub'), 'uploadCover succeeds');
$assertTrue(isset($fakeClient->uploads['/Apps/Books/.Moon+/Cover/三体.epub_2.png']), 'cover uploaded to basename path');
$assertEq('php-dev-999', $fakeClient->uploads['/Apps/Books/.Moon+/covers.id'] ?? null, 'covers.id written after uploadCover');

unset($fakeClient->uploads['/Apps/Books/.Moon+/covers.id']);
$assertTrue($cm->moveCover('科幻/三体.epub', '科幻/三体1.epub'), 'moveCover succeeds');
$assertEq('php-dev-999', $fakeClient->uploads['/Apps/Books/.Moon+/covers.id'] ?? null, 'covers.id written after moveCover');

// 清理临时目录
@unlink($tmpCover);
@unlink(RUNTIME_PATH . '/books/books.sync');
@unlink(RUNTIME_PATH . '/books/books.id');
@unlink(RUNTIME_PATH . '/books/covers.id');
@rmdir(RUNTIME_PATH . '/books');
@rmdir(RUNTIME_PATH . '/images');
@rmdir(RUNTIME_PATH);

$fillMissing = new ReflectionMethod(\nova\framework\core\Config::class, 'fillMissing');
$configObj = (new ReflectionClass(\nova\framework\core\Config::class))->newInstanceWithoutConstructor();
$filled = $fillMissing->invoke($configObj, [
    'webdav' => ['url' => 'https://dav.example', 'password' => 'keep'],
    'framework_start' => ['App'],
], [
    'webdav' => ['url' => '', 'password' => '', 'path' => '/Apps/Books'],
    'framework_start' => ['App', 'Extra'],
    'book' => ['autoFillOnUpload' => false],
]);
$assertEq('/Apps/Books', $filled['webdav']['path'] ?? null, 'missing webdav.path is filled from example');
$assertEq('https://dav.example', $filled['webdav']['url'] ?? null, 'existing webdav.url is not overwritten');
$assertEq('keep', $filled['webdav']['password'] ?? null, 'existing webdav.password is not overwritten');
$assertEq(['App'], $filled['framework_start'] ?? null, 'list config is not merged by index');
$assertEq(false, $filled['book']['autoFillOnUpload'] ?? null, 'missing top-level config is still filled');

$libraryPath = new ReflectionMethod(BaseManager::class, 'libraryPath');
$assertEq('/Apps/Books', $libraryPath->invoke(null, ''), 'empty webdav path defaults to /Apps/Books');
$assertEq('/Apps/Books', $libraryPath->invoke(null, 'Apps/Books/'), 'path gains a leading slash and drops the trailing one');
$assertEq('/dav/books', $libraryPath->invoke(null, '/dav/books'), 'custom library path is kept');
$assertEq('/Apps/Books', $libraryPath->invoke(null, '/a/../b'), 'path with .. falls back to default');

echo "OK: {$assertions} assertions passed.\n";
