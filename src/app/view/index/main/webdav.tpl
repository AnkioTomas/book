<title id="title">Webdav配置 - {$title}</title>
<style id="style">
    .action-buttons {
        display: flex;
        justify-content: flex-end;
    }
    mdui-card {
        width: 100%;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="badge" class="refresh mr-2"></mdui-icon>
            <span>WebDAV 配置</span>
        </div>

        <div class="col-xs-12">
            <form class="row col-space16" id="form">

                <div class="col-xs-12">
                    <mdui-text-field
                            label="主机域"
                            name="url"
                            type="text"
                            variant="outlined"
                            required
                            helper="类似于https://xx.xx.xx"
                    ></mdui-text-field>
                </div>

                <div class="col-xs-12 col-md-6">
                    <mdui-text-field
                            label="账户"
                            name="username"
                            type="text"
                            variant="outlined"
                    ></mdui-text-field>
                </div>

                <div class="col-xs-12 col-md-6">
                    <mdui-text-field
                            label="密码"
                            name="password"
                            type="password"
                            variant="outlined"
                    ></mdui-text-field>
                </div>

                <div class="col-xs-12 action-buttons">
                    <mdui-button id="save_oidc" icon="save" type="submit">
                        保存修改
                    </mdui-button>
                </div>
            </form>
        </div>
    </div>
</div>

<script id="script" src="/static/js/webdav.js"></script>