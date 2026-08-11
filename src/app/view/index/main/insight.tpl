<title id="title">多维统计 - {$title}</title>
<style id="style">
{include file="statsWidgets.css.tpl"}
    .cal-layout {
        display: grid;
        gap: 1rem;
        grid-template-columns: minmax(280px, 1.1fr) minmax(260px, 1fr);
    }
    @media (max-width: 860px) {
        .cal-layout { grid-template-columns: 1fr; }
    }
    .cal-nav button {
        border: 0; cursor: pointer; color: rgb(var(--mdui-color-primary));
        padding: .25rem .6rem; border-radius: 8px;
        background: rgba(var(--mdui-color-primary), .08);
    }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px; }
    .cal-dow { text-align: center; font-size: .75rem; color: rgb(var(--mdui-color-on-surface-variant)); padding: .25rem 0; }
    .cal-cell {
        aspect-ratio: 1; border-radius: 10px; display: flex; flex-direction: column;
        align-items: center; justify-content: center; border: 0; cursor: pointer;
        color: inherit; background: rgba(var(--mdui-color-outline), .08);
        font-size: .8rem; font-variant-numeric: tabular-nums;
        outline: 2px solid transparent;
    }
    .cal-cell.out { opacity: .35; }
    .cal-cell.lv1 { background: rgba(var(--mdui-color-primary), .18); }
    .cal-cell.lv2 { background: rgba(var(--mdui-color-primary), .35); }
    .cal-cell.lv3 { background: rgba(var(--mdui-color-primary), .55); color: rgb(var(--mdui-color-on-primary)); }
    .cal-cell.lv4 { background: rgb(var(--mdui-color-primary)); color: rgb(var(--mdui-color-on-primary)); }
    .cal-cell.is-selected { outline-color: rgb(var(--mdui-color-secondary)); }
    .cal-cell .meta { font-size: .65rem; opacity: .85; line-height: 1.1; }
    .day-meta { white-space: nowrap; text-align: right; font-variant-numeric: tabular-nums; }
    .insight-book-cover { width: 32px; height: 44px; border-radius: 4px; overflow: hidden; vertical-align: middle; }
    .remap-pick-row { cursor: pointer; border-bottom: 1px solid rgba(var(--mdui-color-outline), .12); }
    .remap-pick-row:hover { background: rgba(var(--mdui-color-primary), .06); }
    .remap-pick-list { max-height: 360px; overflow: auto; }
</style>

<div id="container" class="container h-fit py-3">
    <div id="insight-empty" class="d-none">
        <mdui-card class="p-3 rounded-lg mb-3">
            <div class="body-small text-on-surface-variant">暂无多维统计数据。请用 KOReader Book 插件上报或以表格右上角导入静读天下备份。</div>
        </mdui-card>
    </div>

    <section class="mb-4">
        <div id="insight-reading">
            <mdui-card class="p-3 rounded-lg">
                <div class="body-small text-on-surface-variant">加载中…</div>
            </mdui-card>
        </div>
    </section>

    <section class="mb-4">
        <div class="cal-layout">
            <mdui-card class="p-3 rounded-lg">
                <div class="cal-nav d-flex items-center justify-between mb-3">
                    <button type="button" id="cal-prev">‹ 上月</button>
                    <div class="title-small font-semibold" id="cal-label">—</div>
                    <button type="button" id="cal-next">下月 ›</button>
                </div>
                <div class="cal-grid mb-1">
                    <div class="cal-dow">日</div><div class="cal-dow">一</div><div class="cal-dow">二</div>
                    <div class="cal-dow">三</div><div class="cal-dow">四</div><div class="cal-dow">五</div><div class="cal-dow">六</div>
                </div>
                <div class="cal-grid" id="cal-grid"></div>
            </mdui-card>
            <mdui-card class="p-3 rounded-lg">
                <div class="title-small font-semibold mb-2" id="cal-day-title">选择日期</div>
                <div id="cal-day-list">
                    <div class="body-small text-on-surface-variant">点击日历中的日期查看当日书籍</div>
                </div>
            </mdui-card>
        </div>
    </section>

    <section>
        <div class="d-flex items-center justify-between gap-2 mb-2 flex-wrap">
            <div class="d-flex items-center gap-2 flex-1 flex-wrap" style="min-width:180px">
                <mdui-text-field id="insight-book-search" label="搜索阅读书籍" variant="outlined" icon="search" class="flex-1" style="min-width:180px;max-width:320px"></mdui-text-field>
                <mdui-checkbox id="insight-unmatched">仅未匹配</mdui-checkbox>
            </div>
            <div class="d-flex items-center gap-1">
                <input type="file" id="moon-import-file" accept=".mrpro,application/zip" hidden>
                <mdui-button id="moon-import-btn" variant="tonal" icon="upload_file">导入静读天下</mdui-button>
            </div>
        </div>
        <div id="dataTable" class="table-card w-100"></div>
    </section>
</div>

<script id="script" src="/static/js/insight.js?v={$__v}"></script>
