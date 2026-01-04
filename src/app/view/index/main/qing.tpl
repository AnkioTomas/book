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
    }
    
    .hidden {
        display: none !important;
    }
</style>

<div id="container" class="container p-4">
    <div class="row col-space16">
        <div class="col-xs-12 title-large center-vertical mb-4">
            <mdui-icon name="schedule" class="refresh mr-2"></mdui-icon>
            <span>青年文摘定时采集</span>
        </div>

        <div class="col-xs-12">
            <form class="row col-space16" id="form">
                
                <!-- 定时模式选择 -->
                <div class="col-xs-12">
                    <mdui-select
                        label="执行频率"
                        id="mode"
                        variant="outlined"
                        required
                    >
                        <mdui-menu-item value="">不启用</mdui-menu-item>
                        <mdui-menu-item value="day">每天执行</mdui-menu-item>
                        <mdui-menu-item value="nDay">每隔N天执行</mdui-menu-item>
                        <mdui-menu-item value="nHour">每隔N小时执行</mdui-menu-item>
                        <mdui-menu-item value="hour">每小时执行</mdui-menu-item>
                        <mdui-menu-item value="nMinute">每隔N分钟执行</mdui-menu-item>
                    </mdui-select>
                </div>

                <!-- 每天执行：时和分 -->
                <div id="day-inputs" class="col-xs-12 row col-space16 hidden">
                    <div class="col-xs-6">
                        <mdui-text-field
                            label="小时 (0-23)"
                            id="day-hour"
                            type="number"
                            min="0"
                            max="23"
                            value="2"
                            variant="outlined"
                            helper="每天的几点执行"
                        ></mdui-text-field>
                    </div>
                    <div class="col-xs-6">
                        <mdui-text-field
                            label="分钟 (0-59)"
                            id="day-minute"
                            type="number"
                            min="0"
                            max="59"
                            value="0"
                            variant="outlined"
                            helper="每小时的第几分钟"
                        ></mdui-text-field>
                    </div>
                </div>

                <!-- 每隔N天执行：天、时、分 -->
                <div id="nDay-inputs" class="col-xs-12 row col-space16 hidden">
                    <div class="col-xs-4">
                        <mdui-text-field
                            label="间隔天数"
                            id="nDay-day"
                            type="number"
                            min="1"
                            max="31"
                            value="1"
                            variant="outlined"
                            helper="每隔几天"
                        ></mdui-text-field>
                    </div>
                    <div class="col-xs-4">
                        <mdui-text-field
                            label="小时 (0-23)"
                            id="nDay-hour"
                            type="number"
                            min="0"
                            max="23"
                            value="2"
                            variant="outlined"
                        ></mdui-text-field>
                    </div>
                    <div class="col-xs-4">
                        <mdui-text-field
                            label="分钟 (0-59)"
                            id="nDay-minute"
                            type="number"
                            min="0"
                            max="59"
                            value="0"
                            variant="outlined"
                        ></mdui-text-field>
                    </div>
                </div>

                <!-- 每隔N小时执行：小时、分 -->
                <div id="nHour-inputs" class="col-xs-12 row col-space16 hidden">
                    <div class="col-xs-6">
                        <mdui-text-field
                            label="间隔小时"
                            id="nHour-hour"
                            type="number"
                            min="1"
                            max="23"
                            value="6"
                            variant="outlined"
                            helper="每隔几小时"
                        ></mdui-text-field>
                    </div>
                    <div class="col-xs-6">
                        <mdui-text-field
                            label="分钟 (0-59)"
                            id="nHour-minute"
                            type="number"
                            min="0"
                            max="59"
                            value="0"
                            variant="outlined"
                            helper="每小时的第几分钟"
                        ></mdui-text-field>
                    </div>
                </div>

                <!-- 每小时执行：分 -->
                <div id="hour-inputs" class="col-xs-12 hidden">
                    <mdui-text-field
                        label="分钟 (0-59)"
                        id="hour-minute"
                        type="number"
                        min="0"
                        max="59"
                        value="0"
                        variant="outlined"
                        helper="每小时的第几分钟执行"
                    ></mdui-text-field>
                </div>

                <!-- 每隔N分钟执行：分钟 -->
                <div id="nMinute-inputs" class="col-xs-12 hidden">
                    <mdui-text-field
                        label="间隔分钟"
                        id="nMinute-minute"
                        type="number"
                        min="1"
                        max="59"
                        value="30"
                        variant="outlined"
                        helper="每隔几分钟执行一次"
                    ></mdui-text-field>
                </div>

                <!-- 隐藏的 cron 表达式字段 -->
                <input type="hidden" name="cron" id="cron-value">

                <!-- Cron 表达式预览和操作按钮 -->
                <div class="col-xs-12 action-buttons">
                    <div class="cron-preview">
                        <span id="cron-display">请选择执行频率</span>
                    </div>
                    <mdui-button icon="save" type="submit">
                        保存配置
                    </mdui-button>
                </div>
            </form>
        </div>
    </div>
</div>

<script id="script" src="/static/js/qing.js"></script>