<title id="title">上传识别 - {$title}</title>
<style id="style">
    .action-buttons {
        display: flex;
        justify-content: flex-end;
    }
    .setting-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 4px 0;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="auto_awesome" class="mr-2"></mdui-icon>
            <span>上传识别</span>
        </div>

        <div class="col-xs-12">
            <form class="row col-space16" id="bookForm">
                <div class="col-xs-12">
                    <div class="setting-row">
                        <div class="title-small">上传后自动 AI 识别</div>
                        <mdui-switch name="autoFillOnUpload"></mdui-switch>
                    </div>
                </div>
                <div class="col-xs-12 action-buttons">
                    <mdui-button icon="save" type="submit">保存修改</mdui-button>
                </div>
            </form>
        </div>
    </div>
</div>

<script id="script" src="/static/js/bookSetting.js?v={$__v}"></script>
