window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function (loading) {
    $.form.manage("/admin/api/webdav","#form");

    window.pageOnUnLoad = function () {
        // 页面卸载时的清理工作
    };
};