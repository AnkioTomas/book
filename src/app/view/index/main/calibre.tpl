<title id="title">Calibre 配置 - {$title}</title>
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
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="auto_stories" class="mr-2"></mdui-icon>
            <span>Calibre 微服务配置</span>
        </div>

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

<script id="script" src="/static/js/calibre.js"></script>
