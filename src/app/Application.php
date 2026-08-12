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

use app\task\AiIdentifyTask;
use app\task\SyncTask;
use nova\framework\App;

use function nova\framework\config;

use nova\framework\event\EventManager;

use function nova\framework\route;

use nova\framework\route\Route;
use nova\plugin\corn\schedule\TaskerManager;

use nova\plugin\corn\schedule\TaskerTime;

class Application extends App
{
    public function onFrameworkStart(): void
    {
        $adminRoute = ['index', 'main'];
        EventManager::trigger('admin.router', $adminRoute);

        Route::getInstance()
            ->get("/", route('index', 'main', 'index'))
            ->get("/webdav/{filename}", route('index', 'douban', 'webdav'))
            ->get("/proxy/{uri}", route('index', 'douban', 'proxy'));

        TaskerManager::add(TaskerTime::hour(1), new SyncTask(), 'sync_books', -1);

        if(config('book.autoFillOnUpload')){
            // 上传入库后的副作用（AI 补空等）走事件，Upload 不直接依赖任务层
            EventManager::addListener('book.uploaded', function (string $event, mixed &$data): void {
                $id = (int)($data['id'] ?? 0);
                if ($id <= 0) {
                    return;
                }
                $key = 'AI填充_upload_' . $id;
                TaskerManager::del($key);
                TaskerManager::add(TaskerTime::after(1), new AiIdentifyTask([$id], true), $key);
            });
        }

    }

    public const SYSTEM_NAME = "Ankio的书库";
}
