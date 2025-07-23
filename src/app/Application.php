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
    public function routeStatic(): void
    {
        EventManager::addListener("response.static.after", function ($event, $file) {
            $name = str_replace(ROOT_PATH . '/app', '', $file);
            if (!str_ends_with($name, ".js")) {
                return;
            }
            echo <<<EOF
;if(!window.novaFiles){window.novaFiles = {};}
window.novaFiles['$name'] = true;
EOF;

            if(str_ends_with($name, "bootloader.js")) {
                $version = config("version");
                $debug = config("debug") ? "true":"false";
                echo <<<EOF
window.debug = $debug;
window.version = '$version';
EOF;
            }
        });
        EventManager::addListener("route.before", function ($event, &$uri) {
            if (str_starts_with($uri, "/static/")) {
                $file = substr($uri, 8);
                $file = str_replace("..", "", $file);
                throw new AppExitException(Response::asStatic(ROOT_PATH.'/app/static/'.$file), "Send static file");
            }
        });
    }

    public function onFrameworkStart(): void
    {
        $this->routeStatic();

    }
}
