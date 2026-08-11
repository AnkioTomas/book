/**
 * 多维统计
 * @file insight.js
 */
window.pageLoadFiles = ['Toaster', 'DataTable', 'Layer', 'Request'];

window.pageOnLoad = function () {
    var table = new DataTable('#dataTable');
    var perDay = {};
    var ym = '';
    var selected = '';

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function tableParams() {
        return {
            search: $('#insight-book-search')[0].value || '',
            unmatched: $('#insight-unmatched')[0].checked ? '1' : '',
        };
    }

    function reloadAll() {
        table.reload(tableParams(),false);
        loadInsight();
    }

    // —— KPI / 图表 ——
    function loadInsight() {
        $.request.get('/index/stats/insight', {}, function (res) {
            if (!res || res.code !== 200) {
                $.toaster.error((res && res.msg) || '加载失败');
                $('#insight-reading').html(
                    '<mdui-card class="p-3 rounded-lg"><div class="body-small text-on-surface-variant">加载失败</div></mdui-card>'
                );
                return;
            }
            var data = res.data || {};
            perDay = data.perDay || {};
            // mdui.$ 无 toggleClass；有数据必须藏空状态提示
            var hasData = !!(data.hasData
                || (data.readingActivity && data.readingActivity.hasData)
                || Object.keys(perDay).length);
            if (hasData) {
                $('#insight-empty').addClass('d-none');
                renderReading(data.readingActivity || {});
            } else {
                $('#insight-empty').removeClass('d-none');
                $('#insight-reading').empty();
            }
            ym = data.initialYm || '';
            selected = '';
            var days = Object.keys(perDay).sort();
            if (days.length) {
                selected = days[days.length - 1];
                ym = selected.slice(0, 7);
                renderDay(selected);
            }
            renderMonth();
        }, function () {
            $.toaster.error('加载失败');
        });
    }

    function renderReading(activity) {
        if (!activity.hasData) {
            $('#insight-reading').html(
                '<mdui-card class="p-3 rounded-lg"><div class="body-small text-on-surface-variant">暂无阅读活动数据</div></mdui-card>'
            );
            return;
        }
        var kpi = activity.kpi || {};
        var items = [
            ['kpi-total', 'schedule', kpi.totalReadingTime, '总阅读时长', true],
            ['kpi-reading', 'date_range', kpi.last7DaysReadTime, '近 7 天', true],
            ['kpi-rate', 'timelapse', kpi.longestDay, '最长单日', true],
            ['kpi-finished', 'menu_book', kpi.mostPagesInADay, '单日最多页', false],
            ['kpi-dusty', 'auto_stories', kpi.totalPagesRead, '累计阅读页', false],
        ];
        var html = '<div class="stats-kpi-grid d-grid gap-3 mb-3">';
        items.forEach(function (it) {
            html += '<mdui-card class="stats-kpi ' + it[0] + ' bg-surface-container-low d-flex items-center gap-3 p-3">'
                + '<div class="stats-kpi-icon center-both"><mdui-icon name="' + it[1] + '"></mdui-icon></div>'
                + '<div class="d-flex flex-col min-w-0">'
                + '<div class="stats-kpi-value"' + (it[4] ? ' style="font-size:1.25rem"' : '') + '>'
                + $.escapeHtml(it[2]) + '</div>'
                + '<div class="body-small text-on-surface-variant">' + $.escapeHtml(it[3]) + '</div>'
                + '</div></mdui-card>';
        });
        html += '</div><div class="stats-two-grid d-grid gap-4">';
        html += '<mdui-card class="p-3 rounded-lg"><div class="title-small mb-2 font-semibold">月度阅读时长</div>'
            + '<div class="trend d-flex items-end justify-between gap-2 w-full pt-2">';
        (activity.perMonth || []).forEach(function (m) {
            html += '<div class="trend-col flex-1 d-flex flex-col items-center justify-end h-full gap-1">'
                + '<div class="trend-count">' + $.escapeHtml(m.count) + '</div>'
                + '<div class="flex-1 w-full d-flex items-end justify-center">'
                + '<div class="trend-bar" style="height:' + (m.pct || 0) + '%"></div></div>'
                + '<div class="trend-label">' + $.escapeHtml(m.label) + '</div></div>';
        });
        html += '</div></mdui-card><mdui-card class="p-3 rounded-lg">'
            + '<div class="title-small mb-2 font-semibold">星期分布</div>';
        (activity.perWeekday || []).forEach(function (d) {
            html += '<div class="d-flex items-center gap-2 my-2">'
                + '<div class="bar-label text-on-surface-variant">' + $.escapeHtml(d.name) + '</div>'
                + '<div class="bar-track"><div class="bar-fill" style="width:' + (d.pct || 0) + '%"></div></div>'
                + '<div class="bar-count" style="flex-basis:auto;min-width:4.5rem">' + $.escapeHtml(d.count) + '</div></div>';
        });
        html += '</mdui-card></div>';
        $('#insight-reading').html(html);
    }

    // —— 日历 ——
    function renderDay(date) {
        selected = date;
        var info = perDay[date];
        if (!info || !info.books || !info.books.length) {
            $('#cal-day-title').text(date || '选择日期');
            $('#cal-day-list').html('<div class="body-small text-on-surface-variant">这一天没有阅读记录</div>');
            return;
        }
        $('#cal-day-title').text(date + ' · ' + info.durationText);
        $('#cal-day-list').html(info.books.map(function (b) {
            return '<div class="list-row d-flex items-center gap-3 py-2">'
                + '<image-loader src="' + $.escapeHtml(b.coverUrl || '') + '" class="list-cover"></image-loader>'
                + '<div class="d-flex flex-col flex-1 min-w-0">'
                + '<div class="title-small text-ellipsis" title="' + $.escapeHtml(b.title || '') + '">'
                + $.escapeHtml(b.title || '') + '</div>'
                + '<div class="body-small text-on-surface-variant text-ellipsis">'
                + $.escapeHtml(b.authors || '未知作者') + '</div></div>'
                + '<div class="day-meta d-flex flex-col items-end gap-1 body-small text-on-surface-variant">'
                + '<div>' + $.escapeHtml(b.durationText || '') + '</div>'
                + '<div>' + $.escapeHtml(b.progressText || '0%') + '</div></div></div>';
        }).join(''));
    }

    function renderMonth() {
        var parts = ym.split('-').map(Number);
        var y = parts[0];
        var m = parts[1];
        $('#cal-label').text(y + '年' + m + '月');

        var startPad = new Date(y, m - 1, 1).getDay();
        var daysInMonth = new Date(y, m, 0).getDate();
        var gridStart = new Date(y, m - 1, 1 - startPad);
        var total = Math.ceil((startPad + daysInMonth) / 7) * 7;
        var maxDur = 1;
        for (var d = 1; d <= daysInMonth; d++) {
            var k = ym + '-' + pad(d);
            maxDur = Math.max(maxDur, (perDay[k] && perDay[k].duration) || 0);
        }

        var html = '';
        for (var i = 0; i < total; i++) {
            var day = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
            var key = day.getFullYear() + '-' + pad(day.getMonth() + 1) + '-' + pad(day.getDate());
            var inMonth = day.getMonth() === m - 1;
            var info = perDay[key];
            var dur = (info && info.duration) || 0;
            var level = 0;
            if (dur > 0) {
                var ratio = dur / maxDur;
                level = ratio >= 0.75 ? 4 : (ratio >= 0.5 ? 3 : (ratio >= 0.25 ? 2 : 1));
            }
            html += '<button type="button" class="cal-cell lv' + level
                + (inMonth ? '' : ' out')
                + (key === selected ? ' is-selected' : '')
                + '" data-date="' + key + '" title="' + key
                + (dur ? (' · ' + ((info && info.durationText) || '')) : '') + '">'
                + '<span>' + day.getDate() + '</span>'
                + (inMonth && dur > 0 ? '<span class="meta">' + ((info.books && info.books.length) || 0) + '本</span>' : '')
                + '</button>';
        }
        $('#cal-grid').html(html);
    }

    $('#cal-grid').on('click', '.cal-cell', function () {
        renderDay($(this).data('date'));
        renderMonth();
    });
    $('#cal-prev').on('click', function () {
        var p = ym.split('-').map(Number);
        var d = new Date(p[0], p[1] - 2, 1);
        ym = d.getFullYear() + '-' + pad(d.getMonth() + 1);
        renderMonth();
    });
    $('#cal-next').on('click', function () {
        var p = ym.split('-').map(Number);
        var d = new Date(p[0], p[1], 1);
        ym = d.getFullYear() + '-' + pad(d.getMonth() + 1);
        renderMonth();
    });

    // —— DataTable ——
    table.load({
        uri: '/index/stats/books',
        height: 'auto',
        lineHeight: 'auto',
        mobile: true,
        page: true,
        selectable: false,
        empty_msg: '暂无阅读记录',
        columns: [
            {
                field: 'coverUrl', name: '封面', align: 'center', width: 60,
                formatter: function (v) {
                    return '<image-loader src="' + $.escapeHtml(v || '') + '" class="insight-book-cover"></image-loader>';
                },
            },
            {
                field: 'title', name: '书名', align: 'left', width: 'auto',
                formatter: function (v, row) {
                    return '<div class="text-ellipsis" title="' + $.escapeHtml(v || '') + '">'
                        + $.escapeHtml(v || '')
                        + (row.inLibrary
                            ? '<span class="badge badge-sm badge-primary ml-1">已入库</span>'
                            : '<span class="badge badge-sm badge-error ml-1">未匹配</span>')
                        + '</div><div class="body-small text-on-surface-variant text-ellipsis">'
                        + $.escapeHtml(row.filename || '') + '</div>';
                },
            },
            {
                field: 'authors', name: '作者', align: 'left', width: 120,
                formatter: function (v) { return $.escapeHtml(v || '—'); },
            },
            { field: 'durationText', name: '时长', align: 'center', width: 100 },
            { field: 'records', name: '记录', align: 'center', width: 70 },
            { field: 'lastReadText', name: '最近阅读', align: 'center', width: 140 },
            {
                field: 'filename', name: '操作', align: 'center', width: 140, fixed: 'right',
                formatter: function (_v, _row, index) {
                    return '<mdui-button-icon data-index="' + index + '" icon="swap_horiz" class="action-remap" title="改绑书库"></mdui-button-icon>'
                        + '<mdui-button-icon data-index="' + index + '" icon="delete" class="action-delete" title="删除记录" color="error"></mdui-button-icon>';
                },
            },
        ],
    });

    var searchTimer = null;
    $('#insight-book-search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            table.reload(tableParams());
        }, 400);
    });
    $('#insight-unmatched').on('change', function () {
        table.reload(tableParams());
    });

    $('#dataTable').on('click', '.action-delete', function () {
        var row = table.getRow($(this).data('index'));
        if (!row) return;
        $.layer.confirm({
            msg: '确定删除「' + (row.title || row.filename) + '」的全部阅读记录？',
            yes: function () {
                $.request.postForm('/index/stats/removeBook', { filename: row.filename }, function (res) {
                    if (!res || res.code !== 200) {
                        $.toaster.error((res && res.msg) || '删除失败');
                        return;
                    }
                    $.toaster.success(res.msg || '已删除');
                    reloadAll();
                });
            },
        });
    });

    $('#dataTable').on('click', '.action-remap', function () {
        var fromRow = table.getRow($(this).data('index'));
        if (!fromRow) return;

        $.layer.html({
            title: '改绑到书库 · ' + (fromRow.title || fromRow.filename),
            content: '<mdui-text-field id="remap-search" label="搜索书库" variant="outlined" icon="search" class="w-100 mb-2"></mdui-text-field>'
                + '<div id="remap-pick-list" class="remap-pick-list body-small text-on-surface-variant">搜索中…</div>',
            style: 'width:min(520px,94vw);',
            closeOnOverlayClick: true,
        });

        var timer = null;
        function searchLib(q) {
            $.request.get('/index/book/list', { page: 1, pageSize: 20, search: q || '' }, function (res) {
                var books = (res && res.code === 200 && res.data) || [];
                if (!books.length) {
                    $('#remap-pick-list').html('无匹配书籍');
                    return;
                }
                $('#remap-pick-list').html(books.map(function (b) {
                    return '<div class="remap-pick-row d-flex items-center gap-3 py-2" data-file="' + $.escapeHtml(b.filename || '') + '">'
                        + '<image-loader src="/webdav/' + encodeURIComponent(b.filename || '') + '" class="insight-book-cover"></image-loader>'
                        + '<div class="d-flex flex-col flex-1 min-w-0">'
                        + '<div class="title-small text-ellipsis">' + $.escapeHtml(b.bookName || b.filename || '') + '</div>'
                        + '<div class="body-small text-on-surface-variant text-ellipsis">'
                        + $.escapeHtml(b.author || '') + ' · ' + $.escapeHtml(b.filename || '') + '</div></div></div>';
                }).join(''));
            });
        }

        $('#remap-search').on('input', function () {
            clearTimeout(timer);
            var q = this.value || '';
            timer = setTimeout(function () { searchLib(q); }, 350);
        });
        $('#remap-pick-list').on('click', '.remap-pick-row', function () {
            var to = $(this).attr('data-file') || '';
            if (!to) return;
            $.request.postForm('/index/stats/remap', { from: fromRow.filename, to: to }, function (res) {
                if (!res || res.code !== 200) {
                    $.toaster.error((res && res.msg) || '改绑失败');
                    return;
                }
                $.toaster.success(res.msg || '已改绑');
                $.layer.closeAll();
                reloadAll();
            });
        });
        searchLib(fromRow.title || '');
    });

    // —— 新建阅读记录 ——
    $('#insight-create-btn').on('click', function () {
        var today = new Date();
        var ymd = today.getFullYear() + '-'
            + String(today.getMonth() + 1).padStart(2, '0') + '-'
            + String(today.getDate()).padStart(2, '0');
        var pickedFile = '';
        var pickedTitle = '';

        $.layer.html({
            title: '新建阅读记录',
            content: '<div class="create-stat-form">'
                + '<div class="create-book-label mb-1">书籍</div>'
                + '<mdui-text-field id="create-book-search" label="搜索书库" variant="outlined" icon="search"></mdui-text-field>'
                + '<div id="create-book-picked" class="body-small text-on-surface-variant mb-2">未选择</div>'
                + '<div id="create-book-list" class="remap-pick-list mb-2 body-small text-on-surface-variant">输入关键词搜索…</div>'
                + '<mdui-text-field id="create-date" label="日期" type="date" variant="outlined" value="' + ymd + '"></mdui-text-field>'
                + '<mdui-text-field id="create-minutes" label="阅读时长（分钟）" type="number" variant="outlined" value="30"></mdui-text-field>'
                + '<mdui-text-field id="create-progress" label="进度%（可选）" type="number" variant="outlined"></mdui-text-field>'
                + '<mdui-button id="create-stat-submit" variant="filled" icon="check" class="mt-2">保存</mdui-button>'
                + '</div>',
            style: 'width:min(520px,94vw);',
            closeOnOverlayClick: true,
        });

        var timer = null;
        function searchLib(q) {
            $.request.get('/index/book/list', { page: 1, pageSize: 20, search: q || '' }, function (res) {
                var books = (res && res.code === 200 && res.data) || [];
                if (!books.length) {
                    $('#create-book-list').html('无匹配书籍');
                    return;
                }
                $('#create-book-list').html(books.map(function (b) {
                    return '<div class="remap-pick-row d-flex items-center gap-3 py-2" data-file="'
                        + $.escapeHtml(b.filename || '') + '" data-title="'
                        + $.escapeHtml(b.bookName || b.filename || '') + '">'
                        + '<image-loader src="/webdav/' + encodeURIComponent(b.filename || '') + '" class="insight-book-cover"></image-loader>'
                        + '<div class="d-flex flex-col flex-1 min-w-0">'
                        + '<div class="title-small text-ellipsis">' + $.escapeHtml(b.bookName || b.filename || '') + '</div>'
                        + '<div class="body-small text-on-surface-variant text-ellipsis">'
                        + $.escapeHtml(b.author || '') + '</div></div></div>';
                }).join(''));
            });
        }

        $('#create-book-search').on('input', function () {
            clearTimeout(timer);
            var q = this.value || '';
            timer = setTimeout(function () { searchLib(q); }, 350);
        });
        $('#create-book-list').on('click', '.remap-pick-row', function () {
            pickedFile = $(this).attr('data-file') || '';
            pickedTitle = $(this).attr('data-title') || pickedFile;
            $('#create-book-picked').html('已选：<strong>' + $.escapeHtml(pickedTitle) + '</strong>');
        });
        $('#create-stat-submit').on('click', function () {
            if (!pickedFile) {
                $.toaster.error('请先选择书籍');
                return;
            }
            var date = ($('#create-date')[0].value || '').trim();
            var minutes = parseInt($('#create-minutes')[0].value, 10) || 0;
            var progress = ($('#create-progress')[0].value || '').trim();
            if (!date) {
                $.toaster.error('请填写日期');
                return;
            }
            if (minutes <= 0) {
                $.toaster.error('请填写阅读时长（分钟）');
                return;
            }
            var payload = { filename: pickedFile, date: date, minutes: minutes };
            if (progress !== '') payload.progress = progress;
            $.request.postForm('/index/stats/create', payload, function (res) {
                if (!res || res.code !== 200) {
                    $.toaster.error((res && res.msg) || '保存失败');
                    return;
                }
                $.toaster.success(res.msg || '已添加');
                $.layer.closeAll();
                reloadAll();
            }, function () {
                $.toaster.error('保存失败');
            });
        });
        searchLib('');
    });

    // —— 静读天下导入（FormData 只能用 fetch）——
    $('#moon-import-btn').on('click', function () {
        $('#moon-import-file')[0].click();
    });
    $('#moon-import-file').on('change', function () {
        var file = this.files && this.files[0];
        this.value = '';
        if (!file) return;
        var name = (file.name || '').toLowerCase();
        if (!name.endsWith('.mrpro') && !name.endsWith('.zip')) {
            $.toaster.error('请选择 .mrpro 备份文件');
            return;
        }
        var btn = $('#moon-import-btn')[0];
        btn.disabled = true;
        btn.loading = true;
        var fd = new FormData();
        fd.append('file', file);
        fetch('/index/stats/importMoon', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || res.code !== 200) throw new Error((res && res.msg) || '导入失败');
                $.toaster.success(res.msg || '导入成功');
                reloadAll();
            })
            .catch(function (err) {
                $.toaster.error(err.message || '导入失败');
            })
            .finally(function () {
                btn.disabled = false;
                btn.loading = false;
            });
    });

    loadInsight();

    window.pageOnUnLoad = function () {
        $('#cal-grid, #cal-prev, #cal-next, #dataTable, #insight-book-search, #insight-unmatched, #insight-create-btn, #moon-import-btn, #moon-import-file').off();
    };
};
