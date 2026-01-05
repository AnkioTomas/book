<title id="title">青年文摘采集 - {$title}</title>
<style id="style">
    .action-buttons {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .cron-preview {
        font-family: monospace;
        color: var(--mdui-color-primary);
        font-size: 14px;
        margin-top: 8px;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="schedule" class="refresh mr-2"></mdui-icon>
            <span>青年文摘定时采集</span>
        </div>

        <div class="col-xs-12">
            <div class="title-medium mb-3">定时采集配置</div>
            <p class="body-medium mb-3" style="color: var(--mdui-color-on-surface-variant);">
                配置定时任务自动采集青年文摘最新发布内容
            </p>

            <mdui-cron name="cron"></mdui-cron>

            <div style="margin-top: 16px; text-align: right;">
                <mdui-button variant="filled" id="saveCronBtn">
                    <mdui-icon slot="icon" name="save"></mdui-icon>
                    保存配置
                </mdui-button>
            </div>
        </div>
    </div>
</div>

<script id="script" src="/static/js/qing.js"></script>
