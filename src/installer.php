<?php

declare(strict_types=1);

/**
 * 安装向导步骤与字段配置。
 * 字段 path 对应 config.php 键（如 db.host）；提交后由安装插件写入配置。
 */
return [
    'agreement' => "欢迎使用本系统！\n请仔细阅读并同意以下条款：\n1. 本软件按原样提供，不提供任何形式的担保。\n2. 您可以自由修改和分发，但需保留版权信息。",
    'env' => [
        'php' => '8.3.0',
        'extensions' => ['curl', 'gd', 'mbstring', 'pcntl', 'pdo'],
        'optional_extensions' => ['redis'],
        'pdo_drivers' => ['sqlite', 'mysql'],
        'writable' => ['runtime'],
    ],
    'steps' => [
        '数据库配置' => [
            'fields' => [
                'db.type' => [
                    'title' => '数据库类型',
                    'type' => 'select',
                    'options' => [
                        'mysql' => 'MySQL / MariaDB',
                        'sqlite' => 'SQLite',
                    ],
                    'default' => 'sqlite',
                    'required' => true,
                    'desc' => 'SQLite 无需主机/账号，库名可留空（默认 database.sqlite）',
                ],
                'db.host' => [
                    'title' => '数据库主机',
                    'type' => 'input',
                    'default' => 'runtime/book.db',
                    'required' => true,
                    'desc' => 'MySQL 主机地址 或 SQLite 数据文件路径',
                ],
                'db.port' => [
                    'title' => '数据库端口',
                    'type' => 'input',
                    'default' => '3306',
                    'required' => true,
                    'desc' => '默认 3306，选 SQLite 忽略',
                ],
                'db.username' => [
                    'title' => '数据库账号',
                    'type' => 'input',
                    'default' => '',
                    'required' => false,
                    'desc' => '',
                ],
                'db.password' => [
                    'title' => '数据库密码',
                    'type' => 'password',
                    'default' => '',
                    'required' => false,
                    'desc' => '无密码可留空',
                ],
                'db.db' => [
                    'title' => '数据库名',
                    'type' => 'input',
                    'default' => 'book',
                    'required' => true,
                    'desc' => '请事先创建空库；SQLite保持默认。安装向导会自动建表',
                ],
            ],
        ],
        'WebDAV 配置' => [
            'fields' => [
                'webdav.url' => [
                    'title' => 'WebDAV 地址',
                    'type' => 'input',
                    'default' => '',
                    'required' => true,
                    'desc' => '须与静读天下 App 一致，如 https://dav.jianguoyun.com/dav/',
                ],
                'webdav.username' => [
                    'title' => 'WebDAV 账号',
                    'type' => 'input',
                    'default' => '',
                    'required' => true,
                    'desc' => '',
                ],
                'webdav.password' => [
                    'title' => 'WebDAV 密码',
                    'type' => 'password',
                    'default' => '',
                    'required' => true,
                    'desc' => '坚果云请使用应用密码，不要用登录密码',
                ],
            ],
        ],
        '基本设置' => [
            'fields' => [
                'login.systemName' => [
                    'title' => '系统名称',
                    'type' => 'input',
                    'default' => '我的书库',
                    'required' => true,
                    'desc' => '显示在页面标题与登录页',
                ],
            ],
        ],
    ],
    'require_admin' => true
];
