<?php

declare(strict_types=1);

/**
 * 安装向导步骤与字段配置。
 * 字段 path 对应 config.php 键（如 db.host）；提交后由安装插件写入配置。
 */
return [
    'agreement' => <<<EOT
【最终用户许可与免责协议】

欢迎使用本系统！继续安装和使用即表示您知悉并完全同意以下条款：

1. 核心免责声明
本软件按“原样（AS IS）”免费提供，没有任何明示或暗示的保证（包括但不限于对适销性、特定用途的适用性和非侵权性的保证）。

2. 数据安全与隐私
本系统涉及操作您的 WebDAV 个人网盘数据。在任何情况下，作者或版权所有者均不对任何直接或间接的索赔、损害、数据丢失或凭证泄露负责（无论是由于合同、侵权还是其他原因造成的）。您须对自身存储数据的安全性负全部责任，强烈建议在使用前做好重要数据的备份，并使用应用专用密码（而非账号登录密码）。

3. 权利与限制
您可以自由修改、学习或分发本软件，但需在副本中保留原有的版权声明。严禁利用本软件从事任何违反当地法律法规的行为（如非法传播侵权盗版内容），一切因不当使用造成的法律后果均由使用者自行承担。
EOT,
    'env' => [
        'php' => '8.3.0',
        'extensions' => ['curl', 'gd', 'mbstring', 'pdo'],
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
                    'default' => 'localhost',
                    'required' => true,
                    'desc' => 'MySQL 主机地址 , SQLite忽略',
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
