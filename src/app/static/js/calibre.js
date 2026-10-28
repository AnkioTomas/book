window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function (loading) {
    $.form.manage("/index/calibre/config", "#form");
    $.form.manage("/index/calibre/bookConfig", "#bookForm");
    $.form.manage("/index/zlib/config", "#zlibForm");

    $('#btnTest').on('click', function () {
        const data = $.form.get('#form');
        $('#form').showLoading('正在连接 Calibre 服务...');
        $.request.postForm('/index/calibre/test', data,
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

    $('#btnZlibTest').on('click', function () {
        const data = $.form.get('#zlibForm');
        $('#zlibForm').showLoading('正在登录 Z站…');
        $.request.postForm('/index/zlib/config', data, function (saveRes) {
            if (saveRes.code !== 200) {
                $('#zlibForm').closeLoading();
                return $.toaster.error(saveRes.msg || '保存失败');
            }
            $.request.postForm('/index/zlib/testLogin', {},
                function (res) {
                    $('#zlibForm').closeLoading();
                    if (res.code === 200) $.toaster.success(res.msg);
                    else $.toaster.error(res.msg || '登录失败');
                },
                function () {
                    $('#zlibForm').closeLoading();
                    $.toaster.error('请求失败');
                }
            );
        }, function () {
            $('#zlibForm').closeLoading();
            $.toaster.error('保存失败');
        });
    });

    window.pageOnUnLoad = function () {
        $('#btnTest').off();
        $('#btnZlibTest').off();
    };
};
