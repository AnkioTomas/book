<?php

declare(strict_types=1);
/**
 * 默认配置模板
 *
 * 当 src/config.php 不存在时，框架会加载本文件作为初始配置。
 * 本模板里 installed 默认为 false，因此首次访问会被引导到 /install 安装向导。
 *
 * 安装向导提交后会写入真实的 config.php，此后这份文件就不再被加载。
 */
return [
    'debug'        => false,
    'installed'    => false,
    'timezone'     => 'Asia/Shanghai',
    'default_route' => true,
    'domain'       => ['0.0.0.0'],
    'version'      => '1.0.0',

    'framework_start' => [
        'nova\\plugin\\login\\LoginManager',
        'nova\\plugin\\tpl\\Handler',
        'nova\\plugin\\task\\Task',
        'nova\\plugin\\corn\\Schedule',
    ],

    'db' => [
        'host'     => '127.0.0.1',
        'type'     => 'mysql',
        'port'     => 3306,
        'username' => '',
        'password' => '',
        'db'       => 'book',
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'time'         => 0,
        'session_name' => 'NovaSession',
    ],

    'login' => [
        'allowedLoginCount' => 1,
        'loginCallback'     => '/',
        'systemName'        => '我的书库',
        'ssoEnable'         => false,
    ],

    'webdav' => [
        'deviceId' => '',
        'url'      => '',
        'username' => '',
        'password' => '',
    ],

    'calibre' => '',
];
