<title id="title">设备令牌 - {$title}</title>
<style id="style">
    .token-tip {
        background: rgba(var(--mdui-color-surface-container));
        border-radius: 12px;
        padding: 12px 16px;
        color: rgb(var(--mdui-color-on-surface-variant));
        font-size: 0.875rem;
        line-height: 1.6;
        margin-bottom: 16px;
    }
    .token-tip code {
        background: rgba(var(--mdui-color-surface-container-highest));
        padding: 1px 6px;
        border-radius: 4px;
        word-break: break-all;
    }
    .token-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid rgba(var(--mdui-color-outline-variant), 0.4);
    }
    .token-meta {
        color: rgb(var(--mdui-color-on-surface-variant));
        font-size: 0.8rem;
    }
    .action-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }
    #createdTokenBox {
        display: none;
        margin-top: 12px;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="vpn_key" class="mr-2"></mdui-icon>
            <span>设备令牌</span>
        </div>

        <div class="col-xs-12">
            <div class="token-tip">
                给 KOReader 等客户端使用的长期凭证，格式 <code>bk_</code> + 8 位。请求头：
                <code>Authorization: Bearer bk_XXXXXXXX</code>
                <br/>
                明文令牌<strong>只在创建时显示一次</strong>，丢失请撤销后重建。
                探测 <code>GET /index/auth/ping</code>，封面
                <code>GET /webdav/{filename}</code>
            </div>
        </div>

        <div class="col-xs-12">
            <form class="row col-space16" id="createForm">
                <div class="col-xs-12 col-md-8">
                    <mdui-text-field
                        label="设备名称"
                        name="name"
                        type="text"
                        variant="outlined"
                        value="KOReader"
                        helper="例如 KOReader Kobo / Kindle"
                    ></mdui-text-field>
                </div>
                <div class="col-xs-12 col-md-4 action-buttons center-vertical">
                    <mdui-button id="btnCreate" icon="add" type="submit">创建令牌</mdui-button>
                </div>
            </form>
            <div id="createdTokenBox" class="token-tip">
                <div>请立即复制保存：</div>
                <code id="createdTokenValue"></code>
                <div class="action-buttons mt-2">
                    <mdui-button id="btnCopy" icon="content_copy" variant="tonal" type="button">复制</mdui-button>
                </div>
            </div>
        </div>

        <div class="col-xs-12 mt-4">
            <div class="title-medium mb-2">已创建令牌</div>
            <div id="tokenList"></div>
        </div>
    </div>
</div>

<script id="script" src="/static/js/token.js?v={$__v}"></script>
