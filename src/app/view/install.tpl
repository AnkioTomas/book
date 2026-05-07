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
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 2rem 1rem;
            box-sizing: border-box;
        }

        .install-card {
            width: 100%;
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

        .install-grid-full {
            grid-column: 1 / -1;
        }

        @media (max-width: 600px) {
            .install-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        .install-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 1rem;
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
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 100;
            display: flex;
            flex-direction: column;
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

<div class="install-wrap">
    <mdui-card variant="filled" class="install-card p-4">
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
                        class="install-grid-full"
                        style="grid-column: 1 / -1;">
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
                        class="install-grid-full"
                        style="grid-column: 1 / -1;"
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

            <div class="install-actions">
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

<div class="settings-fab">
    <mdui-dropdown>
        <mdui-fab icon="settings" slot="trigger"></mdui-fab>
        <mdui-menu>
            <theme-switcher class="mb-2"></theme-switcher>
        </mdui-menu>
    </mdui-dropdown>
</div>

<!-- 安装完成对话框：展示一次性 admin 密码 -->
<mdui-dialog id="doneDialog" close-on-overlay-click="false" close-on-esc="false">
    <div slot="header" class="d-flex items-center gap-2">
        <mdui-icon name="check_circle" style="color: rgb(var(--mdui-color-primary));"></mdui-icon>
        <span>安装完成</span>
    </div>
    <div class="body-medium mb-2">
        系统已自动生成超级管理员账户，请<strong>立即记录</strong>下面的初始密码（仅显示一次）：
    </div>
    <div class="password-box">
        <div class="d-flex flex-col" style="flex:1;min-width:0;">
            <div class="body-small text-on-surface-variant">账号</div>
            <code id="adminUser">admin</code>
            <div class="body-small text-on-surface-variant mt-2">初始密码</div>
            <code id="adminPwd">--</code>
        </div>
        <mdui-button-icon id="copyPwd" icon="content_copy" variant="tonal" title="复制密码"></mdui-button-icon>
    </div>
    <div class="body-small text-on-surface-variant mt-3">
        登录后请立即在「系统设置 → 账户安全」中修改密码与用户名。
    </div>
    <mdui-button slot="action" variant="filled" id="goLogin">前往登录</mdui-button>
</mdui-dialog>

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
        // 独立页面（非 PJAX）必须自己关掉 Loading.js 自动启动的全屏蒙层，
        // 否则蒙层（z-index:2000，不透明背景）会盖住整个安装页。
        // 跳过淡出动画直接移除节点，避免 500ms 闪烁。
        function dismissBootLoading() {
            const ml = window.mainAppLoading;
            if (ml && ml.overlayElement && ml.overlayElement.parentNode) {
                ml.overlayElement.parentNode.removeChild(ml.overlayElement);
            }
            window.mainAppLoading = null;
        }
        dismissBootLoading();
        // 兜底：极端情况下 Loading.js 在我们之后才执行 show()
        window.addEventListener('load', dismissBootLoading);

        const form = document.getElementById('installForm');
        const dialog = document.getElementById('doneDialog');
        const userEl = document.getElementById('adminUser');
        const pwdEl = document.getElementById('adminPwd');

        const required = ['db_host', 'db_port', 'db_username', 'db_name', 'webdav_url', 'webdav_username'];

        $.form.submit('#installForm', {
            callback: function (data) {
                for (const name of required) {
                    if (!String(data[name] ?? '').trim()) {
                        $.toaster.error('请完整填写带 * 的必填项');
                        return false;
                    }
                }

                $(form).showLoading('正在写入配置并初始化数据库...');

                $.request.postForm('/install/submit', data,
                    function (res) {
                        $(form).closeLoading();
                        if (res.code !== 200) {
                            $.toaster.error(res.msg || '安装失败');
                            return;
                        }
                        const info = res.data || {};
                        userEl.textContent = info.username || 'admin';
                        pwdEl.textContent = info.password || '（未读取到初始密码，请查看 runtime/admin_password.txt）';
                        dialog.open = true;
                    },
                    function () {
                        $(form).closeLoading();
                    }
                );

                return false;
            }
        });

        document.getElementById('copyPwd').addEventListener('click', function () {
            const text = pwdEl.textContent || '';
            if (!text) return;
            navigator.clipboard.writeText(text).then(function () {
                $.toaster.success('已复制到剪贴板');
            }).catch(function () {
                $.toaster.error('复制失败，请手动选中');
            });
        });

        document.getElementById('goLogin').addEventListener('click', function () {
            location.href = '/login';
        });
    })();
</script>
</body>
</html>
