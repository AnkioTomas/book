/**
 * 青年文摘定时采集页面
 * @file qing.js
 */

window.pageLoadFiles = ['DialogForm', 'Cron'];

window.pageOnLoad = function (loading) {
    
    const cronComponent = document.querySelector('mdui-cron[name="cron"]');
    const saveBtn = document.getElementById('saveCronBtn');
 
    /**
     * 保存 Cron 配置
     */
    function saveCron() {
        const cron = cronComponent.getValue();
        
        saveBtn.loading = true;
        
        $.request.postForm("/admin/api/qing/cron", { cron }, (response) => {
            saveBtn.loading = false;
            
            if (response.code === 200) {
                $.toaster.success('定时任务配置已保存');
            }
        });
    }

    /**
     * 加载当前配置
     */
    function loadConfig() {
        $.request.get("/admin/api/qing/cron", {}, (response) => {
            if (response.code === 200 && response.data) {
                cronComponent.setValue(response.data);
            }
        });
    }

    // 点击保存按钮
    saveBtn.addEventListener('click', saveCron);

    // 加载初始配置
    loadConfig();

    /**
     * 页面卸载清理
     */
    window.pageOnUnLoad = function () {
        saveBtn.removeEventListener('click', saveCron);
    };
};
