<title id="title">Calibre - {$title}</title>
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
            <mdui-icon name="electrical_services" class="mr-2"></mdui-icon>
            <span>Calibre</span>
        </div>

        <div class="col-xs-12">
            <form class="row col-space16" id="form">
                <div class="col-xs-12">
                    <mdui-text-field
                        label="服务地址"
                        name="url"
                        type="text"
                        variant="outlined"
                        helper="如 http://127.0.0.1:8080，留空关闭"
                    ></mdui-text-field>
                </div>
                <div class="col-xs-12 action-buttons">
                    <mdui-button id="btnTest" icon="wifi_tethering" variant="tonal" type="button">测试连接</mdui-button>
                    <mdui-button icon="save" type="submit">保存修改</mdui-button>
                </div>
            </form>
        </div>
    </div>
</div>

<script id="script" src="/static/js/calibre.js?v={$__v}"></script>
