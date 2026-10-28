<title id="title">Z站 - {$title}</title>
<style id="style">
    .action-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="cloud_download" class="mr-2"></mdui-icon>
            <span>Z站</span>
        </div>

        <div class="col-xs-12">
            <form class="row col-space16" id="zlibForm">
                <div class="col-xs-12 col-md-6">
                    <mdui-text-field
                        label="邮箱"
                        name="email"
                        type="email"
                        variant="outlined"
                        autocomplete="username"
                    ></mdui-text-field>
                </div>
                <div class="col-xs-12 col-md-6">
                    <mdui-text-field
                        label="密码"
                        name="password"
                        type="password"
                        variant="outlined"
                        autocomplete="current-password"
                    ></mdui-text-field>
                </div>
                <div class="col-xs-12">
                    <mdui-text-field
                        label="镜像地址"
                        name="base_url"
                        type="text"
                        variant="outlined"
                        helper="可选，留空自动竞速"
                    ></mdui-text-field>
                </div>
                <div class="col-xs-12 action-buttons">
                    <mdui-button id="btnZlibTest" icon="login" variant="tonal" type="button">测试登录</mdui-button>
                    <mdui-button icon="save" type="submit">保存修改</mdui-button>
                </div>
            </form>
        </div>
    </div>
</div>

<script id="script" src="/static/js/zlibSetting.js?v={$__v}"></script>
