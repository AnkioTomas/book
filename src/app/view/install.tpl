<!DOCTYPE html>
<html lang="zh-CN" class="mdui-theme-light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no"/>
    <meta name="renderer" content="webkit"/>
    <title>{$title} - 安装向导</title>

    <link rel="preconnect" href="https://fonts.loli.net">
    <link rel="preconnect" href="https://gstatic.loli.net" crossorigin>
    <link href="https://fonts.loli.net/css2?family=Material+Icons&family=Material+Icons+Outlined&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/static/bundle?file=
    framework/libs/mdui.css,
    framework/base.css,
    framework/utils/Loading.css
    &type=css&v={$__v}">

    <style>
        body {
            background-image: url('https://api.ankio.net/bing');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            min-height: 100vh;
            position: relative;
            margin: 0;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background: var(--overlay-color);
            pointer-events: none;
        }

        :root {
            --overlay-color: rgba(0, 0, 0, 0.5);
        }

        .mdui-theme-light {
            --overlay-color: rgba(191, 191, 191, 0.3);
        }

        @media (prefers-color-scheme: light) {
            .mdui-theme-auto {
                --overlay-color: rgba(191, 191, 191, 0.3);
            }
        }

        .install-wrap {
            box-sizing: border-box;
        }

        .install-card {
            max-width: 720px;
        }

        .install-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .install-header .headline-medium {
            font-weight: 700;
        }

        .install-section {
            margin-bottom: 1.25rem;
        }

        .install-section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 0.75rem 0;
            font-weight: 600;
            color: rgb(var(--mdui-color-on-surface));
        }

        .install-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px 16px;
            align-items: start;
        }

        .install-grid mdui-text-field,
        .install-section > mdui-text-field {
            display: block;
            width: 100%;
            box-sizing: border-box;
        }

        @media (max-width: 600px) {
            .install-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        .copyright {
            margin-top: 1rem;
            font-size: 0.875rem;
            color: rgba(var(--mdui-color-on-background), 0.8);
            text-align: center;
        }

        .copyright a {
            color: inherit;
            text-decoration: none;
        }

        .copyright a:hover {
            text-decoration: underline;
        }

        .settings-fab {
            right: 1rem;
            bottom: 1rem;
        }

        .settings-fab mdui-menu {
            background: transparent;
            border: 0;
            box-shadow: none;
            width: unset;
            max-width: unset;
            min-width: unset;
        }

        .password-box {
            background: rgba(var(--mdui-color-primary-container));
            color: rgb(var(--mdui-color-on-primary-container));
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .password-box code {
            flex: 1;
            font-size: 1.05rem;
            letter-spacing: 0.05em;
            user-select: all;
            word-break: break-all;
        }
    </style>
</head>
<body>

<div class="install-wrap position-relative z-1 d-flex flex-col items-center justify-center min-h-screen py-4 px-3">
    <mdui-card variant="filled" class="install-card p-4 w-full">
        <div class="install-header">
            <mdui-icon name="auto_fix_high" style="font-size: 40px;color: rgb(var(--mdui-color-primary));"></mdui-icon>
            <div class="headline-medium mt-1">{$title} 安装向导</div>
            <div class="body-small text-on-surface-variant mt-2">
                第一次部署？这里只需要填三件事：数据库、WebDAV、系统名称
            </div>
        </div>

        <form id="installForm">
            <!-- 1. 数据库 -->
            <div class="install-section">
                <h3 class="install-section-title title-medium">
                    <mdui-icon name="storage"></mdui-icon>
                    数据库（MySQL / MariaDB）
                </h3>
                <div class="install-grid">
                    <mdui-text-field
                        name="db_host"
                        label="主机"
                        value="127.0.0.1"
                        helper="Docker 部署填容器名，例如 mysql"
                        required>
                    </mdui-text-field>
                    <mdui-text-field
                        name="db_port"
                        label="端口"
                        type="number"
                        value="3306"
                        required>
                    </mdui-text-field>
                    <mdui-text-field
                        name="db_username"
                        label="账号"
                        required>
                    </mdui-text-field>
                    <mdui-text-field
                        name="db_password"
                        label="密码"
                        type="password"
                        toggle-password>
                    </mdui-text-field>
                    <mdui-text-field
                        name="db_name"
                        label="库名"
                        value="book"
                        helper="需提前创建空库（utf8mb4）"
                        required
                        class="col-span-2">
                    </mdui-text-field>
                </div>
            </div>

            <mdui-divider class="mt-2 mb-3"></mdui-divider>

            <!-- 2. WebDAV -->
            <div class="install-section">
                <h3 class="install-section-title title-medium">
                    <mdui-icon name="cloud_sync"></mdui-icon>
                    WebDAV（必须与静读天下 App 一致）
                </h3>
                <div class="install-grid">
                    <mdui-text-field
                        class="col-span-2"
                        name="webdav_url"
                        label="WebDAV 地址"
                        placeholder="https://dav.jianguoyun.com/dav/"
                        helper="坚果云示例，地址结尾保留 /dav/"
                        required>
                    </mdui-text-field>
                    <mdui-text-field
                        name="webdav_username"
                        label="账号 / 邮箱"
                        required>
                    </mdui-text-field>
                    <mdui-text-field
                        name="webdav_password"
                        label="密码 / 应用密码"
                        type="password"
                        toggle-password
                        helper="坚果云请填应用密码">
                    </mdui-text-field>
                </div>
            </div>

            <mdui-divider class="mt-2 mb-3"></mdui-divider>

            <!-- 3. 系统 -->
            <div class="install-section">
                <h3 class="install-section-title title-medium">
                    <mdui-icon name="tune"></mdui-icon>
                    站点信息
                </h3>
                <mdui-text-field
                    name="system_name"
                    label="系统名称"
                    value="{$title}"
                    helper="登录页和顶栏显示的名字">
                </mdui-text-field>
            </div>

            <div class="d-flex justify-end gap-2 mt-3">
                <mdui-button form="installForm" type="submit" variant="filled" icon="rocket_launch" full-width>
                    开始安装
                </mdui-button>
            </div>
        </form>
    </mdui-card>

    <div class="copyright">
        <p>© {date('Y')} <a href="https://ankio.net" target="_blank">Ankio</a>. All rights reserved.</p>
    </div>
</div>

<div class="settings-fab position-fixed z-100 d-flex flex-col">
    <mdui-dropdown>
        <mdui-fab icon="settings" slot="trigger"></mdui-fab>
        <mdui-menu>
            <theme-switcher class="mb-2"></theme-switcher>
        </mdui-menu>
    </mdui-dropdown>
</div>

<script src="/static/bundle?file=
framework/libs/vhcheck.min.js,
framework/libs/mdui.global.min.js,
framework/bootloader.js,
framework/utils/Loading.js,
framework/utils/Logger.js,
framework/utils/Loader.js,
framework/utils/Event.js,
framework/utils/Toaster.js,
framework/utils/Form.js,
framework/utils/Request.js,
framework/theme/ThemeSwitcher.js,
framework/language/NodeUtils.js,
framework/language/TranslateUtils.js,
framework/language/Language.js
&type=js&v={$__v}"></script>
<script>
    (function () {
        window.mainAppLoading.close();

        const form = document.getElementById('installForm');


        $.form.submit('#installForm', {
            callback: function (data) {


                $(form).showLoading('正在写入配置并初始化数据库...');

                $.request.postForm('/install/submit', data,
                    function (res) {
                        $(form).closeLoading();
                        if (res.code !== 200) {
                            $.toaster.error(res.msg || '安装失败');
                            return;
                        }
                        const info = res.data;
                        const lines = ['安装完成'];
                        lines.push('管理员账号: ' + info.username);
                        const adminPassword = info.password || '（未读取到初始密码，请查看 runtime/admin_password.txt）';
                        lines.push('管理员密码: ' + adminPassword);
                        $.layer.alert({
                            title: '安装完成',
                            msg: lines.join('<br>'),
                            yes: function () {
                                location.href = info.redirect || '/login';
                            }
                        });
                    },
                    function () {
                        $(form).closeLoading();
                    }
                );

                return false;
            }
        });

    })();
</script>
</body>
</html>
