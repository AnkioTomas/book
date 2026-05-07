window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function (loading) {
    $.form.manage("/admin/api/calibre", "#form");

    $('#btnTest').on('click', function () {
        const data = $.form.get('#form');
        $('#form').showLoading('正在连接 Calibre 服务...');
        $.request.postForm('/admin/api/calibre/test', data,
            function (res) {
                $('#form').closeLoading();
                if (res.code === 200) {
                    $.toaster.success(res.msg);
                } else {
                    $.toaster.error(res.msg || '连接失败');
                }
            },
            function () {
                $('#form').closeLoading();
            }
        );
    });

    window.pageOnUnLoad = function () {
        $('#btnTest').off();
    };
};
