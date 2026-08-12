<title id="title">系统设置 - {$title}</title>
<style id="style">
    .action-buttons {
        display: flex;
        justify-content: flex-end;
        gap: 8px;
    }

    mdui-card {
        width: 100%;
    }

    .calibre-tip {
        background: rgba(var(--mdui-color-surface-container));
        border-radius: 12px;
        padding: 12px 16px;
        color: rgb(var(--mdui-color-on-surface-variant));
        font-size: 0.875rem;
        line-height: 1.6;
    }

    .calibre-tip code {
        background: rgba(var(--mdui-color-surface-container-highest));
        padding: 1px 6px;
        border-radius: 4px;
    }

    .setting-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 8px 0;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="settings" class="mr-2"></mdui-icon>
            <span>系统设置</span>
        </div>

        <div class="col-xs-12 mb-2">
            <div class="title-medium mb-2">书库</div>
            <div class="calibre-tip mb-3">
                上传后自动识别依赖已配置的 AI 服务商（在 <code>config.php</code> 的 <code>ai</code> 段），会提交后台任务且仅补全空字段。
            </div>
            <form class="row col-space16" id="bookForm">
                <div class="col-xs-12">
                    <div class="setting-row">
                        <div>
                            <div class="title-small">上传后自动 AI 识别</div>
                            <div class="body-small text-on-surface-variant">新书入库后自动刮削作者、简介、分类、标签、封面等</div>
                        </div>
                        <mdui-switch name="autoFillOnUpload"></mdui-switch>
                    </div>
                </div>
                <div class="col-xs-12 action-buttons">
                    <mdui-button id="btnBookSave" icon="save" type="submit">保存书库设置</mdui-button>
                </div>
            </form>
        </div>

        <div class="col-xs-12 title-medium mt-4 mb-2">Calibre 微服务</div>
        <div class="col-xs-12 mb-3">
            <div class="calibre-tip">
                Calibre 微服务用于 <strong>非 EPUB 格式的封面提取、元数据读取、格式转换</strong>。
                如果不需要 MOBI / AZW / PDF 等格式的相关能力，可以留空。
                服务部署见 <code>src/calibre/ebook-service/</code>，默认端口 <code>8080</code>。
            </div>
        </div>

        <div class="col-xs-12">
            <form class="row col-space16" id="form">

                <div class="col-xs-12">
                    <mdui-text-field
                        label="服务地址"
                        name="url"
                        type="text"
                        variant="outlined"
                        helper="例如 http://127.0.0.1:8080；留空则不启用 Calibre 能力"
                    ></mdui-text-field>
                </div>

                <div class="col-xs-12 action-buttons">
                    <mdui-button id="btnTest" icon="wifi_tethering" variant="tonal" type="button">
                        测试连接
                    </mdui-button>
                    <mdui-button id="btnSave" icon="save" type="submit">
                        保存修改
                    </mdui-button>
                </div>
            </form>
        </div>
    </div>
</div>

<script id="script" src="/static/js/calibre.js?v={$__v}"></script>
