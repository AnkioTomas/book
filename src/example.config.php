<?php

declare(strict_types=1);
return array(
    'debug' => false,
    'installed' => false,
    'timezone' => 'Asia/Shanghai',
    'default_route' => true,
    'domain' =>
    array(
        0 => '0.0.0.0',
    ),
    'version' => '1.0.9',
    'framework_start' =>
    array(
        0 => 'nova\\plugin\\installer\\InstallerManager',
        1 => 'nova\\plugin\\login\\LoginManager',
        2 => 'nova\\plugin\\tpl\\Handler',
        3 => 'nova\\plugin\\task\\TaskPanelManager',
        4 => 'nova\\plugin\\corn\\CornManager',
        5 => 'nova\\plugin\\ai\\AiPluginManager',
        6 => 'nova\\plugin\\webdav\\WebdavManager',
        7 => 'nova\\plugin\\update\\UpdateManager',
    ),
    'update' =>
    array(
        'repo' => 'AnkioTomas/book',
        'name' => 'book',
        'asset' => '{name}-{version}.zip',
        'token' => '',
    ),
    'db' =>
    array(
        'host' => '127.0.0.1',
        'type' => 'mysql',
        'port' => 3306,
        'username' => '',
        'password' => '',
        'db' => 'book',
        'charset' => 'utf8mb4',
    ),
    'session' =>
    array(
        'time' => 0,
        'session_name' => 'NovaSession',
    ),
    'login' =>
    array(
        'allowedLoginCount' => 1,
        'loginCallback' => '/',
        'systemName' => '我的书库',
        'ssoEnable' => false,
    ),
    'webdav' =>
    array(
        'deviceId' => '',
        'url' => '',
        'username' => '',
        'password' => '',
    ),
    'calibre' => '',
    'ai' =>
    array(
        'currentProvider' => 'ChatGPT',
        'providers' =>
        array(
            'chatgpt' =>
            array(
                'api_key' => '',
                'api_url' => '',
                'api_model' => '',
                'proxy' => '',
            ),
            'openrouter' =>
            array(
                'api_key' => '',
                'api_url' => '',
                'api_model' => '',
                'proxy' => '',
            ),
        ),
    ),
    'ext' =>
    array(
    ),
);
