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

use app\task\SyncTask;
use app\utils\Installer;
use nova\framework\App;
use nova\framework\event\EventManager;

use function nova\framework\route;

use nova\framework\route\Route;
use nova\plugin\corn\schedule\TaskerManager;

use nova\plugin\corn\schedule\TaskerTime;

class Application extends App
{
    public function onFrameworkStart(): void
    {
        Installer::register();

        $adminRoute = ['index', 'main'];
        EventManager::trigger('admin.router', $adminRoute);

        Route::getInstance()
            ->get("/", route('index', 'main', 'index'))
            ->get("/webdav/{filename}", route('index', 'douban', 'webdav'))
            ->get("/proxy/{uri}", route('index', 'douban', 'proxy'));

        TaskerManager::add(TaskerTime::hour(1), new SyncTask(), 'sync_books', -1);

    }

    public const SYSTEM_NAME = "Ankio的书库";
}
