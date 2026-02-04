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

use nova\framework\App;
use nova\framework\route\Route;
use nova\plugin\task\PoolManager;
use function nova\framework\route;

class Application extends App
{
    public function onFrameworkStart(): void
    {
        PoolManager::start();
        // Route::getInstance()->get()
        Route::getInstance()
            ->get("/", route('index', 'main', 'index'))
            ->get("/admin/dashboard", route('index', 'main', 'dashboard'))
            ->get("/admin/book", route('index', 'main', 'book'))
            ->get("/admin/reader", route('index', 'main', 'reader'))
            ->get("/admin/webdav", route('index', 'main', 'webdav'))
            ->get("/admin/qing", route('index', 'main', 'qing'))
            ->get("/admin/dzg", route('index', 'main', 'dzg'))
            ->get("/admin/task", route('index', 'main', 'task'))
            ->getOrPost('/admin/api/book/list', route('index', 'book', 'list'))
            ->getOrPost('/admin/api/book/filters', route('index', 'book', 'filters'))
            ->get('/admin/api/book/file', route('index', 'book', 'file'))
            ->post('/admin/api/book/update', route('index', 'book', 'update'))
            ->post('/admin/api/book/delete', route('index', 'book', 'delete'))
            ->post('/admin/api/sync', route('index', 'book', 'sync'))
            ->post("/admin/api/book/removeDuplicates", route('index', 'book', 'removeDuplicates'))
            ->post('/admin/api/book/scrapeCover', route('index', 'book', 'scrapeCover'))
            //filters
            ->getOrPost('/admin/api/webdav', route('index', 'webdav', 'config'))
            ->get("/settings/account", route('index', 'main', 'account'))//√
            ->get("/settings/sso", route('index', 'main', 'sso'))
            ->post("/admin/api/upload", route("index", "upload", "upload")) // 文件上传
            ->post("/admin/api/publish", route("index", "upload", "publish")) // 文件上传
            ->post("/admin/api/douban", route("index", "douban", "search"))
            ->getOrPost("/admin/api/qing/cron", route("index", "qing", "cron"))
            ->getOrPost("/admin/api/dzg/cron", route("index", "duzhege", "cron"))
            ->post("/admin/api/dzg/test", route("index", "duzhege", "test"))
            ->get("/webdav/{filename}", route('index', 'douban', 'webdav'))
            ->get("/proxy/{uri}", route('index', 'douban', 'proxy'));


    }

    public const SYSTEM_NAME = "Ankio的书库";
}
