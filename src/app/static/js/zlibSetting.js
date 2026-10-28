window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function () {
    $.form.manage('/index/zlib/config', '#zlibForm');

    $('#btnZlibTest').on('click', function () {
        var data = $.form.get('#zlibForm');
        $('#zlibForm').showLoading('登录中…');
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
        $('#btnZlibTest').off();
    };
};
