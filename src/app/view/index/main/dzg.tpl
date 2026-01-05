<title id="title">读者阁采集 - {$title}</title>
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
            <span>读者阁定时采集</span>
        </div>


        {foreach $books as $book => $value}
            <div class="col-xs-12">
                <div class="title-medium mb-3">{$book}采集配置</div>
                <p class="body-medium mb-3" style="color: var(--mdui-color-on-surface-variant);">
                    配置定时任务自动采集{$book}最新发布内容
                </p>

                <mdui-cron name="cron" data-book="{rawurlencode($book)}"></mdui-cron>

                <div style="margin-top: 16px; text-align: right;">
                    <mdui-button icon="save" class="saveCronBtn mr-2" data-book="{rawurlencode($book)}" variant="filled" >
                        保存配置
                    </mdui-button>
                </div>
            </div>
    {/foreach}
    </div>
</div>

<script id="script" src="/static/js/dzg.js"></script>
