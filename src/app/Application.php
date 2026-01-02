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
use nova\framework\event\EventManager;
use nova\framework\exception\AppExitException;
use nova\framework\http\Response;

use function nova\framework\config;
use function nova\framework\route;

use nova\framework\route\Route;

use const ROOT_PATH;

class Application extends App
{
    public function onFrameworkStart(): void
    {
        // Route::getInstance()->get()
        Route::getInstance()
            ->get("/dashboard",route('index','main','dashboard'))

            ->get("/admin/book",route('index','main','book'))
            ->get("/admin/webdav",route('index','main','webdav'))

            ->getOrPost('/admin/api/book/list',route('index','book','list'))
            ->getOrPost('/admin/api/book/filters',route('index','book','filters'))
            //filters
            ->getOrPost('/admin/api/webdav',route('index','webdav','config'))

         ->get("/settings/account", route('index', 'main', 'account'))//√
        ->get("/settings/sso", route('index', 'main', 'sso'))



            ->post("/admin/api/upload", route("index", "upload", "upload")) // 文件上传
            ->post("/admin/api/publish", route("index", "upload", "publish")) // 文件上传


        ;

    }

    public const SYSTEM_NAME = "Ankio的书库";
}
