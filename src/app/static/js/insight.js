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

    var remapFrom = null;
    var remapTimer = null;

    $('#dataTable').on('click', '.action-remap', function () {
        var fromRow = table.getRow($(this).data('index'));
        if (!fromRow) return;
        remapFrom = fromRow;
        $('#remap-dialog-title').text('改绑到书库 · ' + (fromRow.title || fromRow.filename));
        $('#remap-search')[0].value = fromRow.title || '';
        $('#remap-pick-list').html('搜索中…');
        $('#insight-remap-dialog')[0].open = true;
        searchRemapLib(fromRow.title || '');
    });

    function searchRemapLib(q) {
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
        clearTimeout(remapTimer);
        var q = this.value || '';
        remapTimer = setTimeout(function () { searchRemapLib(q); }, 350);
    });
    $('#remap-pick-list').on('click', '.remap-pick-row', function () {
        var to = $(this).attr('data-file') || '';
        if (!to || !remapFrom) return;
        $.request.postForm('/index/stats/remap', { from: remapFrom.filename, to: to }, function (res) {
            if (!res || res.code !== 200) {
                $.toaster.error((res && res.msg) || '改绑失败');
                return;
            }
            $.toaster.success(res.msg || '已改绑');
            $('#insight-remap-dialog')[0].open = false;
            reloadAll();
        });
    });
    $('#remap-cancel-btn').on('click', function () {
        $('#insight-remap-dialog')[0].open = false;
    });

    // —— 新建 / 批量阅读记录（mdui-dialog）——
    var createPickedFile = '';
    var createTimer = null;

    function todayYmd() {
        var today = new Date();
        return today.getFullYear() + '-'
            + String(today.getMonth() + 1).padStart(2, '0') + '-'
            + String(today.getDate()).padStart(2, '0');
    }

    function searchCreateLib(q) {
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

    function syncCreateMode() {
        var batch = $('#create-batch-mode')[0].checked;
        if (batch) {
            $('#create-single-fields').addClass('d-none');
            $('#create-batch-fields').removeClass('d-none');
        } else {
            $('#create-batch-fields').addClass('d-none');
            $('#create-single-fields').removeClass('d-none');
        }
    }

    function closeCreateDialog() {
        $('#insight-create-dialog')[0].open = false;
    }

    $('#insight-create-btn').on('click', function () {
        var ymd = todayYmd();
        createPickedFile = '';
        $('#create-book-picked').text('未选择');
        $('#create-book-search')[0].value = '';
        $('#create-batch-mode')[0].checked = false;
        $('#create-date')[0].value = ymd;
        $('#create-date-from')[0].value = ymd;
        $('#create-date-to')[0].value = ymd;
        $('#create-minutes')[0].value = '30';
        $('#create-minutes-min')[0].value = '20';
        $('#create-minutes-max')[0].value = '60';
        $('#create-progress')[0].value = '';
        syncCreateMode();
        $('#create-book-list').html('输入关键词搜索…');
        $('#insight-create-dialog')[0].open = true;
        searchCreateLib('');
    });

    $('#create-book-search').on('input', function () {
        clearTimeout(createTimer);
        var q = this.value || '';
        createTimer = setTimeout(function () { searchCreateLib(q); }, 350);
    });
    $('#create-book-list').on('click', '.remap-pick-row', function () {
        createPickedFile = $(this).attr('data-file') || '';
        var title = $(this).attr('data-title') || createPickedFile;
        $('#create-book-picked').html('已选：<strong>' + $.escapeHtml(title) + '</strong>');
    });
    $('#create-batch-mode').on('change', syncCreateMode);
    $('#create-cancel-btn').on('click', closeCreateDialog);
    $('#create-stat-submit').on('click', function () {
        if (!createPickedFile) {
            $.toaster.error('请先选择书籍');
            return;
        }
        var progress = ($('#create-progress')[0].value || '').trim();
        var batch = $('#create-batch-mode')[0].checked;
        if (batch) {
            var dateFrom = ($('#create-date-from')[0].value || '').trim();
            var dateTo = ($('#create-date-to')[0].value || '').trim();
            var minutesMin = parseInt($('#create-minutes-min')[0].value, 10) || 0;
            var minutesMax = parseInt($('#create-minutes-max')[0].value, 10) || 0;
            if (!dateFrom || !dateTo) {
                $.toaster.error('请填写日期范围');
                return;
            }
            if (minutesMin <= 0 || minutesMax <= 0) {
                $.toaster.error('请填写每日时长上下限');
                return;
            }
            if (minutesMin > minutesMax) {
                $.toaster.error('时长下限不能大于上限');
                return;
            }
            var batchPayload = {
                filename: createPickedFile,
                date_from: dateFrom,
                date_to: dateTo,
                minutes_min: minutesMin,
                minutes_max: minutesMax,
            };
            if (progress !== '') batchPayload.progress = progress;
            $.request.postForm('/index/stats/createBatch', batchPayload, function (res) {
                if (!res || res.code !== 200) {
                    $.toaster.error((res && res.msg) || '保存失败');
                    return;
                }
                $.toaster.success(res.msg || '已添加');
                closeCreateDialog();
                reloadAll();
            }, function () {
                $.toaster.error('保存失败');
            });
            return;
        }

        var date = ($('#create-date')[0].value || '').trim();
        var minutes = parseInt($('#create-minutes')[0].value, 10) || 0;
        if (!date) {
            $.toaster.error('请填写日期');
            return;
        }
        if (minutes <= 0) {
            $.toaster.error('请填写阅读时长（分钟）');
            return;
        }
        var payload = { filename: createPickedFile, date: date, minutes: minutes };
        if (progress !== '') payload.progress = progress;
        $.request.postForm('/index/stats/create', payload, function (res) {
            if (!res || res.code !== 200) {
                $.toaster.error((res && res.msg) || '保存失败');
                return;
            }
            $.toaster.success(res.msg || '已添加');
            closeCreateDialog();
            reloadAll();
        }, function () {
            $.toaster.error('保存失败');
        });
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
        $('#cal-grid, #cal-prev, #cal-next, #dataTable, #insight-book-search, #insight-unmatched, #insight-create-btn, #moon-import-btn, #moon-import-file, #create-book-search, #create-book-list, #create-batch-mode, #create-cancel-btn, #create-stat-submit, #remap-search, #remap-pick-list, #remap-cancel-btn').off();
    };
};
