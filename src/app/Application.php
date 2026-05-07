<?php

declare(strict_types=1);

/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace app;

use app\utils\Installer;
use nova\framework\App;

use function nova\framework\route;

use nova\framework\route\Route;
use nova\plugin\task\PoolManager;

class Application extends App
{
    public function onFrameworkStart(): void
    {
        Installer::register();
        PoolManager::start();
        // Route::getInstance()->get()
        Route::getInstance()
            ->get("/", route('index', 'main', 'index'))
            ->get("/admin/dashboard", route('index', 'main', 'dashboard'))
            ->get("/admin/book", route('index', 'main', 'book'))
            ->get("/admin/reader", route('index', 'book', 'reader'))
            ->get("/admin/webdav", route('index', 'main', 'webdav'))
            ->get("/admin/task", route('index', 'main', 'task'))
            ->getOrPost('/admin/api/book/list', route('index', 'book', 'list'))
            ->getOrPost('/admin/api/book/filters', route('index', 'book', 'filters'))
            ->get('/admin/api/book/file', route('index', 'book', 'file'))
            ->get('/admin/api/book/progress', route('index', 'book', 'progress'))
            ->post('/admin/api/book/progressSync', route('index', 'book', 'progressSync'))
            ->post('/admin/api/book/progressUpdate', route('index', 'book', 'progressUpdate'))
            ->post('/admin/api/book/update', route('index', 'book', 'update'))
            ->post('/admin/api/book/delete', route('index', 'book', 'delete'))
            ->post('/admin/api/sync', route('index', 'book', 'sync'))
            ->post("/admin/api/book/removeDuplicates", route('index', 'book', 'removeDuplicates'))
            ->post('/admin/api/book/scrapeCover', route('index', 'book', 'scrapeCover'))
            //filters
            ->getOrPost('/admin/api/webdav', route('index', 'webdav', 'config'))
            ->get('/admin/calibre', route('index', 'main', 'calibre'))
            ->getOrPost('/admin/api/calibre', route('index', 'calibre', 'config'))
            ->post('/admin/api/calibre/test', route('index', 'calibre', 'test'))
            ->get("/settings/account", route('index', 'main', 'account'))//√
            ->get("/settings/sso", route('index', 'main', 'sso'))
            ->post("/admin/api/upload", route("index", "upload", "upload")) // 文件上传
            ->post("/admin/api/publish", route("index", "upload", "publish")) // 文件上传
            ->post("/admin/api/douban", route("index", "douban", "search"))
            ->get("/webdav/{filename}", route('index', 'douban', 'webdav'))
            ->get("/proxy/{uri}", route('index', 'douban', 'proxy'));

    }

    public const SYSTEM_NAME = "Ankio的书库";
}
