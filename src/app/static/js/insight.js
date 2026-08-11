/**
 * 多维统计：拉取 /index/stats/insight，渲染阅读 KPI + 日历 + 静读天下导入
 * @file insight.js
 */

window.pageLoadFiles = ['Toaster'];

window.pageOnLoad = () => {
    bindMoonImport();
    loadInsight();
};

function loadInsight() {
    $.request.get('/index/stats/insight', {}, function (res) {
        if (!res || res.code !== 200) {
            $.toaster.error((res && res.msg) || '加载失败');
            renderReadingEmpty('加载失败');
            return;
        }
        applyInsight(res.data || {});
    }, function () {
        $.toaster.error('加载失败');
        renderReadingEmpty('加载失败');
    });
}

function applyInsight(data) {
    const emptyEl = document.getElementById('insight-empty');
    if (emptyEl) {
        emptyEl.classList.toggle('d-none', !!data.hasData);
    }
    renderReading(data.readingActivity || {});
    initCalendar(data.perDay || {}, data.initialYm || '');
}

function renderReadingEmpty(msg) {
    const root = document.getElementById('insight-reading');
    if (!root) {
        return;
    }
    root.innerHTML = '<mdui-card class="p-3 rounded-lg">'
        + '<div class="body-small text-on-surface-variant">' + $.escapeHtml(msg) + '</div>'
        + '</mdui-card>';
}

function renderReading(activity) {
    const root = document.getElementById('insight-reading');
    if (!root) {
        return;
    }
    if (!activity.hasData) {
        renderReadingEmpty('暂无阅读活动数据');
        return;
    }

    const kpi = activity.kpi || {};
    const kpiItems = [
        { cls: 'kpi-total', icon: 'schedule', value: kpi.totalReadingTime, label: '总阅读时长', small: true },
        { cls: 'kpi-reading', icon: 'date_range', value: kpi.last7DaysReadTime, label: '近 7 天', small: true },
        { cls: 'kpi-rate', icon: 'timelapse', value: kpi.longestDay, label: '最长单日', small: true },
        { cls: 'kpi-finished', icon: 'menu_book', value: kpi.mostPagesInADay, label: '单日最多页', small: false },
        { cls: 'kpi-dusty', icon: 'auto_stories', value: kpi.totalPagesRead, label: '累计阅读页', small: false },
    ];

    let html = '<div class="stats-kpi-grid d-grid gap-3 mb-3">';
    kpiItems.forEach((item) => {
        html += '<mdui-card class="stats-kpi ' + item.cls + ' bg-surface-container-low d-flex items-center gap-3 p-3">'
            + '<div class="stats-kpi-icon center-both"><mdui-icon name="' + item.icon + '"></mdui-icon></div>'
            + '<div class="d-flex flex-col min-w-0">'
            + '<div class="stats-kpi-value"' + (item.small ? ' style="font-size:1.25rem"' : '') + '>'
            + $.escapeHtml(item.value) + '</div>'
            + '<div class="body-small text-on-surface-variant">' + $.escapeHtml(item.label) + '</div>'
            + '</div></mdui-card>';
    });
    html += '</div>';

    html += '<div class="stats-two-grid d-grid gap-4">';
    html += '<mdui-card class="p-3 rounded-lg">'
        + '<div class="title-small mb-2 font-semibold">月度阅读时长</div>'
        + '<div class="trend d-flex items-end justify-between gap-2 w-full pt-2">';
    (activity.perMonth || []).forEach((m) => {
        html += '<div class="trend-col flex-1 d-flex flex-col items-center justify-end h-full gap-1">'
            + '<div class="trend-count">' + $.escapeHtml(m.count) + '</div>'
            + '<div class="flex-1 w-full d-flex items-end justify-center">'
            + '<div class="trend-bar" style="height:' + (m.pct || 0) + '%"></div></div>'
            + '<div class="trend-label">' + $.escapeHtml(m.label) + '</div>'
            + '</div>';
    });
    html += '</div></mdui-card>';

    html += '<mdui-card class="p-3 rounded-lg">'
        + '<div class="title-small mb-2 font-semibold">星期分布</div>';
    (activity.perWeekday || []).forEach((d) => {
        html += '<div class="d-flex items-center gap-2 my-2">'
            + '<div class="bar-label text-on-surface-variant">' + $.escapeHtml(d.name) + '</div>'
            + '<div class="bar-track"><div class="bar-fill" style="width:' + (d.pct || 0) + '%"></div></div>'
            + '<div class="bar-count" style="flex-basis:auto;min-width:4.5rem">' + $.escapeHtml(d.count) + '</div>'
            + '</div>';
    });
    html += '</mdui-card></div>';

    root.innerHTML = html;
}

function initCalendar(perDay, initialYm) {
    const grid = document.getElementById('cal-grid');
    const label = document.getElementById('cal-label');
    const dayTitle = document.getElementById('cal-day-title');
    const dayList = document.getElementById('cal-day-list');
    if (!grid || !label || !dayTitle || !dayList) {
        return;
    }

    let ym = initialYm || '2020-01';
    let selected = '';

    const pad = (n) => (n < 10 ? '0' + n : '' + n);
    const shiftYm = (y, m, delta) => {
        const d = new Date(y, m - 1 + delta, 1);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1);
    };

    const renderDayDetail = (date) => {
        selected = date;
        const info = perDay[date];
        if (!info || !info.books || !info.books.length) {
            dayTitle.textContent = date || '选择日期';
            dayList.innerHTML = '<div class="body-small text-on-surface-variant">这一天没有阅读记录</div>';
            return;
        }
        dayTitle.textContent = date + ' · ' + info.durationText;
        dayList.innerHTML = info.books.map((b) => (
            '<div class="list-row d-flex items-center gap-3 py-2">'
            + '<image-loader src="' + $.escapeHtml(b.coverUrl || '') + '" class="list-cover"></image-loader>'
            + '<div class="d-flex flex-col flex-1 min-w-0">'
            + '<div class="title-small text-ellipsis" title="' + $.escapeHtml(b.title || '') + '">'
            + $.escapeHtml(b.title || '') + '</div>'
            + '<div class="body-small text-on-surface-variant text-ellipsis">'
            + $.escapeHtml(b.authors || '未知作者') + '</div>'
            + '</div>'
            + '<div class="day-meta d-flex flex-col items-end gap-1 body-small text-on-surface-variant">'
            + '<div>' + $.escapeHtml(b.durationText || '') + '</div>'
            + '<div>' + $.escapeHtml(b.progressText || '0%') + '</div>'
            + '</div>'
            + '</div>'
        )).join('');
    };

    const renderMonth = () => {
        const [y, m] = ym.split('-').map(Number);
        label.textContent = y + '年' + m + '月';
        const first = new Date(y, m - 1, 1);
        const startPad = first.getDay();
        const daysInMonth = new Date(y, m, 0).getDate();
        const gridStart = new Date(y, m - 1, 1 - startPad);
        const total = Math.ceil((startPad + daysInMonth) / 7) * 7;

        let maxDur = 1;
        for (let d = 1; d <= daysInMonth; d++) {
            const key = ym + '-' + pad(d);
            maxDur = Math.max(maxDur, (perDay[key] && perDay[key].duration) || 0);
        }

        const frag = document.createDocumentFragment();
        for (let i = 0; i < total; i++) {
            const day = new Date(gridStart.getFullYear(), gridStart.getMonth(), gridStart.getDate() + i);
            const key = day.getFullYear() + '-' + pad(day.getMonth() + 1) + '-' + pad(day.getDate());
            const inMonth = day.getMonth() === m - 1;
            const info = perDay[key];
            const dur = (info && info.duration) || 0;
            const bookCount = (info && info.books && info.books.length) || 0;
            let level = 0;
            if (dur > 0) {
                const ratio = dur / maxDur;
                level = ratio >= 0.75 ? 4 : (ratio >= 0.5 ? 3 : (ratio >= 0.25 ? 2 : 1));
            }
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cal-cell lv' + level
                + (inMonth ? '' : ' out')
                + (key === selected ? ' is-selected' : '');
            btn.dataset.date = key;
            btn.title = key + (dur ? (' · ' + ((info && info.durationText) || '')) : '');
            btn.innerHTML = '<span>' + day.getDate() + '</span>'
                + (inMonth && dur > 0 ? '<span class="meta">' + bookCount + '本</span>' : '');
            btn.addEventListener('click', () => {
                renderDayDetail(key);
                renderMonth();
            });
            frag.appendChild(btn);
        }
        grid.innerHTML = '';
        grid.appendChild(frag);
    };

    const days = Object.keys(perDay).sort();
    if (days.length) {
        selected = days[days.length - 1];
        const parts = selected.split('-');
        if (parts.length === 3) {
            ym = parts[0] + '-' + parts[1];
        }
        renderDayDetail(selected);
    }
    renderMonth();

    const prev = document.getElementById('cal-prev');
    const next = document.getElementById('cal-next');
    if (prev && !prev.dataset.bound) {
        prev.dataset.bound = '1';
        prev.addEventListener('click', () => {
            const [y, m] = ym.split('-').map(Number);
            ym = shiftYm(y, m, -1);
            renderMonth();
        });
    }
    if (next && !next.dataset.bound) {
        next.dataset.bound = '1';
        next.addEventListener('click', () => {
            const [y, m] = ym.split('-').map(Number);
            ym = shiftYm(y, m, 1);
            renderMonth();
        });
    }
}

function bindMoonImport() {
    const btn = document.getElementById('moon-import-btn');
    const input = document.getElementById('moon-import-file');
    if (!btn || !input || btn.dataset.bound) {
        return;
    }
    btn.dataset.bound = '1';

    btn.addEventListener('click', () => input.click());
    input.addEventListener('change', () => {
        const file = input.files && input.files[0];
        input.value = '';
        if (!file) {
            return;
        }
        const name = (file.name || '').toLowerCase();
        if (!name.endsWith('.mrpro') && !name.endsWith('.zip')) {
            $.toaster.error('请选择 .mrpro 备份文件');
            return;
        }

        btn.disabled = true;
        btn.loading = true;
        const fd = new FormData();
        fd.append('file', file);

        // FormData 只能走原生 fetch；$.request.postForm 是 urlencoded
        fetch('/index/stats/importMoon', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
            .then((r) => r.json())
            .then((res) => {
                if (!res || res.code !== 200) {
                    throw new Error((res && res.msg) || '导入失败');
                }
                $.toaster.success(res.msg || '导入成功');
                loadInsight();
            })
            .catch((err) => {
                $.toaster.error(err.message || '导入失败');
            })
            .finally(() => {
                btn.disabled = false;
                btn.loading = false;
            });
    });
}
