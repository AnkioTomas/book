/**
 * 青年文摘定时采集页面
 * @file qing.js
 */

window.pageLoadFiles = ['DialogForm', 'Cron'];

window.pageOnLoad = function (loading) {

 
    /**
     * 保存 Cron 配置
     */
    function saveCron(book) {
        let cronComponent = document.querySelector('mdui-cron[data-book="'+book+'"]');
        let cron = cronComponent.getValue();
        let saveBtn =  document.querySelector('mdui-button[data-book="'+book+'"]');
        saveBtn.loading = true;
        
        $.request.postForm("/admin/api/dzg/cron", { cron,book }, (response) => {
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
        $.request.get("/admin/api/dzg/cron", {}, (response) => {
            if (response.code === 200 && response.data) {
                for (let i = 0; i < response.data.length; i++) {
                    let book = encodeURIComponent(response.data[i].book);
                    let cron = response.data[i].cron;
                   let cronComponent = document.querySelector('mdui-cron[data-book="'+book+'"]');
                    cronComponent.setValue(cron);

                }
              //  cronComponent.setValue(response.data);
            }
        });
    }

    $("#container").on("click",'.saveCronBtn',function () {
        let book = $(this).data('book');
        saveCron(book)

    })


    // 加载初始配置
    loadConfig();

    /**
     * 页面卸载清理
     */
    window.pageOnUnLoad = function () {

    };
};
