/**
 * 青年文摘定时采集页面
 * @file qing.js
 */

window.pageLoadFiles = ['Form'];

window.pageOnLoad = function (loading) {
    
    /**
     * Cron 表达式管理类
     */
    const CronManager = {
        
        /**
         * 生成 cron 表达式
         */
        generate: {
            day: (hour, minute) => `${minute} ${hour} * * *`,
            nDay: (day, hour, minute) => `${minute} ${hour} */${day} * *`,
            nHour: (hour, minute) => `${minute} */${hour} * * *`,
            hour: (minute) => `${minute} */1 * * *`,
            nMinute: (minute) => minute === 0 ? '' : `*/${minute} * * * *`
        },
        
        /**
         * 生成 cron 描述
         */
        describe: {
            day: (h, m) => `每天 ${h}:${String(m).padStart(2, '0')} 执行`,
            nDay: (d, h, m) => `每隔 ${d} 天的 ${h}:${String(m).padStart(2, '0')} 执行`,
            nHour: (h, m) => `每隔 ${h} 小时的第 ${m} 分钟执行`,
            hour: (m) => `每小时的第 ${m} 分钟执行`,
            nMinute: (m) => `每隔 ${m} 分钟执行`
        },
        
        /**
         * 反向解析 cron 表达式
         * @param {string} cron - cron 表达式
         * @returns {Object|null} - {mode, params} 或 null
         */
        parse(cron) {
            if (!cron || cron.trim() === '') return null;
            
            const parts = cron.trim().split(/\s+/);
            if (parts.length < 5) return null;
            
            const [minute, hour, dayOfMonth, month, dayOfWeek] = parts;
            
            // 每隔N分钟执行: */N * * * *
            if (minute.startsWith('*/') && hour === '*' && dayOfMonth === '*') {
                const m = parseInt(minute.substring(2));
                return { mode: 'nMinute', params: { minute: m } };
            }
            
            // 每小时执行: M */1 * * *
            if (hour === '*/1' && dayOfMonth === '*') {
                const m = parseInt(minute);
                return { mode: 'hour', params: { minute: m } };
            }
            
            // 每隔N小时执行: M */N * * *
            if (hour.startsWith('*/') && dayOfMonth === '*') {
                const m = parseInt(minute);
                const h = parseInt(hour.substring(2));
                return { mode: 'nHour', params: { hour: h, minute: m } };
            }
            
            // 每隔N天执行: M H */N * *
            if (dayOfMonth.startsWith('*/')) {
                const m = parseInt(minute);
                const h = parseInt(hour);
                const d = parseInt(dayOfMonth.substring(2));
                return { mode: 'nDay', params: { day: d, hour: h, minute: m } };
            }
            
            // 每天执行: M H * * *
            if (dayOfMonth === '*' && month === '*') {
                const m = parseInt(minute);
                const h = parseInt(hour);
                return { mode: 'day', params: { hour: h, minute: m } };
            }
            
            return null;
        }
    };
    
    /**
     * UI 控制器
     */
    const UI = {
        $mode: $('#mode'),
        $cronValue: $('#cron-value'),
        $cronDisplay: $('#cron-display'),
        $form: $('#form'),
        
        containers: {
            day: $('#day-inputs'),
            nDay: $('#nDay-inputs'),
            nHour: $('#nHour-inputs'),
            hour: $('#hour-inputs'),
            nMinute: $('#nMinute-inputs')
        },
        
        /**
         * 隐藏所有输入容器
         */
        hideAllInputs() {
            $.each(this.containers, (key, $container) => {
                $container.addClass('hidden');
            });
        },
        
        /**
         * 显示指定模式的输入容器
         */
        showInputs(mode) {
            this.hideAllInputs();
            if (mode && this.containers[mode]) {
                this.containers[mode].removeClass('hidden');
            }
        },
        
        /**
         * 更新 cron 表达式显示
         */
        updateCron() {
            const mode = this.$mode.val();
            
            if (!mode) {
                this.$cronValue.val('');
                this.$cronDisplay
                    .text('已禁用定时任务')
                    .css('color', 'var(--mdui-color-error)');
                return;
            }
            
            let cron = '';
            let desc = '';
            
            switch (mode) {
                case 'day': {
                    const h = parseInt($('#day-hour').val()) || 0;
                    const m = parseInt($('#day-minute').val()) || 0;
                    cron = CronManager.generate.day(h, m);
                    desc = CronManager.describe.day(h, m);
                    break;
                }
                
                case 'nDay': {
                    const d = parseInt($('#nDay-day').val()) || 1;
                    const h = parseInt($('#nDay-hour').val()) || 0;
                    const m = parseInt($('#nDay-minute').val()) || 0;
                    cron = CronManager.generate.nDay(d, h, m);
                    desc = CronManager.describe.nDay(d, h, m);
                    break;
                }
                
                case 'nHour': {
                    const h = parseInt($('#nHour-hour').val()) || 1;
                    const m = parseInt($('#nHour-minute').val()) || 0;
                    cron = CronManager.generate.nHour(h, m);
                    desc = CronManager.describe.nHour(h, m);
                    break;
                }
                
                case 'hour': {
                    const m = parseInt($('#hour-minute').val()) || 0;
                    cron = CronManager.generate.hour(m);
                    desc = CronManager.describe.hour(m);
                    break;
                }
                
                case 'nMinute': {
                    const m = parseInt($('#nMinute-minute').val()) || 1;
                    cron = CronManager.generate.nMinute(m);
                    desc = CronManager.describe.nMinute(m);
                    break;
                }
            }
            
            this.$cronValue.val(cron);
            this.$cronDisplay
                .text(`${desc} (${cron})`)
                .css('color', 'var(--mdui-color-primary)');
        },
        
        /**
         * 从 cron 表达式设置 UI
         */
        setCronToUI(cron) {
            const parsed = CronManager.parse(cron);
            
            if (!parsed) {
                console.warn('无法解析 cron 表达式:', cron);
                this.$mode.val('');
                this.hideAllInputs();
                return;
            }
            
            const { mode, params } = parsed;
            
            // 设置模式
            this.$mode.val(mode);
            this.showInputs(mode);
            
            // 设置对应的参数
            switch (mode) {
                case 'day':
                    $('#day-hour').val(params.hour);
                    $('#day-minute').val(params.minute);
                    break;
                    
                case 'nDay':
                    $('#nDay-day').val(params.day);
                    $('#nDay-hour').val(params.hour);
                    $('#nDay-minute').val(params.minute);
                    break;
                    
                case 'nHour':
                    $('#nHour-hour').val(params.hour);
                    $('#nHour-minute').val(params.minute);
                    break;
                    
                case 'hour':
                    $('#hour-minute').val(params.minute);
                    break;
                    
                case 'nMinute':
                    $('#nMinute-minute').val(params.minute);
                    break;
            }
            
            // 更新显示
            this.updateCron();
        },
        
        /**
         * 初始化事件监听
         */
        bindEvents() {
            // 模式切换
            this.$mode.on('change', () => {
                const mode = this.$mode.val();
                this.showInputs(mode);
                this.updateCron();
            });
            
            // 所有数字输入框变化
            $('mdui-text-field[type="number"]').on('input change', () => {
                this.updateCron();
            });
            
            // 表单提交验证
            this.$form.on('submit', (e) => {
                const mode = this.$mode.val();
                
                if (!mode) {
                    // 允许提交空值来禁用任务
                    this.$cronValue.val('');
                    return true;
                }
                
                // 确保 cron 表达式已生成
                this.updateCron();
                
                if (!this.$cronValue.val()) {
                    e.preventDefault();
                    $.toaster.error('请正确配置定时任务参数');
                    return false;
                }
            });
        }
    };
    
    /**
     * 初始化页面
     */
    function init() {
        // 绑定事件
        UI.bindEvents();
        
        // 使用 $.form.manage 自动管理表单
        $.form.manage("/admin/api/qing/cron", "#form", {
            afterSet: (response) => {
                // 获取到现有配置后，解析 cron 并设置到 UI
                if (response.data && response.data.cron) {
                    const cron = response.data.cron;
                    UI.setCronToUI(cron);
                    
                    // 显示任务信息
                    if (response.data.nextRun) {
                        $.toaster.info(`当前定时任务将于 ${response.data.nextRun} 执行`);
                    }
                } else {
                    // 没有配置时，设置默认值
                    UI.$mode.val('');
                    UI.hideAllInputs();
                    UI.updateCron();
                }
            },
            
            beforeSubmit: (data) => {
                // 提交前再次验证
                const mode = UI.$mode.val();
                if (mode && !data.cron) {
                    $.toaster.error('请正确配置定时任务参数');
                    return false;
                }
                return true;
            },
            
            afterSubmit: (response) => {
                // 提交成功后刷新状态
                if (response.code === 200) {
                    setTimeout(() => {
                        $.request.get("/admin/api/qing/status", {}, (res) => {
                            if (res.code === 200 && res.data.nextRun) {
                                $.toaster.info(`下次执行时间: ${res.data.nextRun}`);
                            }
                        });
                    }, 500);
                }
            }
        });
    }
    
    // 初始化
    init();
    
    // 页面卸载清理
    window.pageOnUnLoad = function () {
        // 清理事件监听
        UI.$mode.off('change');
        $('mdui-text-field[type="number"]').off('input change');
    };
};
