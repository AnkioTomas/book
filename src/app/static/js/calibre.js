window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function () {
    $.form.manage('/index/calibre/config', '#form');

    $('#btnTest').on('click', function () {
        var data = $.form.get('#form');
        $('#form').showLoading('连接中…');
        $.request.postForm('/index/calibre/test', data,
            function (res) {
                $('#form').closeLoading();
                if (res.code === 200) $.toaster.success(res.msg);
                else $.toaster.error(res.msg || '连接失败');
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
