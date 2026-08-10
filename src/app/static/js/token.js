/**
 * 设备令牌管理
 */
window.pageLoadFiles = ['Toaster', 'Form'];

window.pageOnLoad = function () {
    var $list = $('#tokenList');
    var $createdBox = $('#createdTokenBox');
    var $createdVal = $('#createdTokenValue');

    function fmtTime(ts) {
        if (!ts) return '—';
        var d = new Date(ts * 1000);
        if (isNaN(d.getTime())) return '—';
        return d.toLocaleString();
    }

    function renderList(rows) {
        if (!rows || !rows.length) {
            $list.html('<div class="token-meta">暂无令牌</div>');
            return;
        }
        var html = '';
        rows.forEach(function (row) {
            html += '<div class="token-row" data-id="' + row.id + '">'
                + '<div>'
                + '<div><strong>' + (row.name || '未命名') + '</strong> '
                + '<code>bk_••••••••</code></div>'
                + '<div class="token-meta">创建 ' + fmtTime(row.created_at)
                + ' · 最近使用 ' + fmtTime(row.last_used_at) + '</div>'
                + '</div>'
                + '<mdui-button class="btn-revoke" variant="text" icon="delete" data-id="' + row.id + '">撤销</mdui-button>'
                + '</div>';
        });
        $list.html(html);
    }

    function loadList() {
        $.request.get('/index/token/list', {}, function (res) {
            if (res.code === 200) {
                renderList(res.data || []);
            } else {
                $.toaster.error(res.msg || '加载失败');
            }
        }, function () {
            $.toaster.error('加载失败');
        });
    }

    $('#createForm').on('submit', function (e) {
        e.preventDefault();
        var data = $.form.get('#createForm');
        $.request.postForm('/index/token/create', data, function (res) {
            if (res.code !== 200) {
                $.toaster.error(res.msg || '创建失败');
                return;
            }
            var token = (res.data && res.data.token) || '';
            $createdVal.text(token);
            $createdBox.show();
            $.toaster.success(res.msg || '已创建');
            loadList();
        }, function () {
            $.toaster.error('创建失败');
        });
    });

    $('#btnCopy').on('click', function () {
        var text = $createdVal.text();
        if (!text) return;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                $.toaster.success('已复制');
            });
        } else {
            $.toaster.info('请手动选中复制');
        }
    });

    $list.on('click', '.btn-revoke', function () {
        var id = $(this).attr('data-id');
        if (!id || !confirm('确定撤销该令牌？使用它的设备将立即失效。')) return;
        $.request.postForm('/index/token/revoke', { id: id }, function (res) {
            if (res.code === 200) {
                $.toaster.success('已撤销');
                loadList();
            } else {
                $.toaster.error(res.msg || '撤销失败');
            }
        });
    });

    loadList();

    window.pageOnUnLoad = function () {
        $('#createForm').off();
        $('#btnCopy').off();
        $list.off();
    };
};
