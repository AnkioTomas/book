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
    .remap-pick-list { max-height: 280px; overflow: auto; }
    .create-stat-form mdui-text-field { width: 100%; margin-bottom: .5rem; }
    .create-book-label { font-size: .85rem; color: rgb(var(--mdui-color-on-surface-variant)); }
    #insight-create-dialog::part(panel),
    #insight-remap-dialog::part(panel) { width: min(520px, 94vw); max-width: 94vw; }
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
                <mdui-button id="insight-create-btn" variant="tonal" icon="add">新建</mdui-button>
                <mdui-button id="moon-import-btn" variant="tonal" icon="upload_file">导入静读天下</mdui-button>
            </div>
        </div>
        <div id="dataTable" class="table-card w-100"></div>
    </section>

    <mdui-dialog id="insight-create-dialog" close-on-overlay-click close-on-esc headline="新建阅读记录">
        <div class="create-stat-form">
            <div class="create-book-label mb-1">书籍</div>
            <mdui-text-field id="create-book-search" label="搜索书库" variant="outlined" icon="search"></mdui-text-field>
            <div id="create-book-picked" class="body-small text-on-surface-variant mb-2">未选择</div>
            <div id="create-book-list" class="remap-pick-list mb-2 body-small text-on-surface-variant">输入关键词搜索…</div>
            <mdui-checkbox id="create-batch-mode">批量（按日期范围每天随机时长）</mdui-checkbox>
            <div id="create-single-fields" class="mt-2">
                <mdui-text-field id="create-date" label="日期" type="date" variant="outlined"></mdui-text-field>
                <mdui-text-field id="create-minutes" label="阅读时长（分钟）" type="number" variant="outlined" value="30"></mdui-text-field>
            </div>
            <div id="create-batch-fields" class="mt-2 d-none">
                <mdui-text-field id="create-date-from" label="开始日期" type="date" variant="outlined"></mdui-text-field>
                <mdui-text-field id="create-date-to" label="结束日期" type="date" variant="outlined"></mdui-text-field>
                <mdui-text-field id="create-minutes-min" label="每日时长下限（分钟）" type="number" variant="outlined" value="20"></mdui-text-field>
                <mdui-text-field id="create-minutes-max" label="每日时长上限（分钟）" type="number" variant="outlined" value="60"></mdui-text-field>
                <div class="body-small text-on-surface-variant mb-2">范围内每一天写入一条，时长在上下限间随机（最多 366 天）。</div>
            </div>
            <mdui-text-field id="create-progress" label="进度%（可选）" type="number" variant="outlined"></mdui-text-field>
        </div>
        <mdui-button slot="action" variant="text" id="create-cancel-btn">取消</mdui-button>
        <mdui-button slot="action" variant="filled" icon="check" id="create-stat-submit">保存</mdui-button>
    </mdui-dialog>

    <mdui-dialog id="insight-remap-dialog" close-on-overlay-click close-on-esc>
        <span slot="headline" id="remap-dialog-title">改绑到书库</span>
        <mdui-text-field id="remap-search" label="搜索书库" variant="outlined" icon="search" class="w-100 mb-2"></mdui-text-field>
        <div id="remap-pick-list" class="remap-pick-list body-small text-on-surface-variant">搜索中…</div>
        <mdui-button slot="action" variant="text" id="remap-cancel-btn">取消</mdui-button>
    </mdui-dialog>
</div>

<script id="script" src="/static/js/insight.js?v={$__v}"></script>
