<title id="title">统计 - {$title}</title>
<style id="style">
    /* base.css 无 auto-fit 网格工具类，仅保留列模板 */
    .stats-kpi-grid { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    .stats-two-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
    .stats-list-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }

    /* KPI 卡视觉（布局走工具类） */
    .stats-kpi { border-radius: 14px; box-shadow: none !important; }
    .stats-kpi-icon { flex: 0 0 48px; height: 48px; border-radius: 12px; }
    .stats-kpi-icon mdui-icon { font-size: 26px; }
    .stats-kpi-value { font-size: 1.7rem; font-weight: 700; line-height: 1.15; font-variant-numeric: tabular-nums; }

    /* 图标配色：复用 base.css 的 tag-* 色板，日夜间自适应 */
    .kpi-total .stats-kpi-icon { background: rgba(var(--mdui-color-primary-container), .55); color: rgb(var(--mdui-color-primary)); }
    .kpi-finished .stats-kpi-icon { background: rgb(var(--tag-success-bg)); color: rgb(var(--tag-success-fg)); }
    .kpi-reading .stats-kpi-icon { background: rgb(var(--tag-info-bg)); color: rgb(var(--tag-info-fg)); }
    .kpi-rate .stats-kpi-icon { background: rgb(var(--tag-gold-bg)); color: rgb(var(--tag-gold-fg)); }
    .kpi-dusty .stats-kpi-icon { background: rgb(var(--tag-stone-bg)); color: rgb(var(--tag-stone-fg)); }

    /* 横向条形（几何，无工具类对应） */
    .bar-label { flex: 0 0 84px; font-size: .82rem; text-align: right; }
    .bar-track { flex: 1; height: 14px; border-radius: 99px; background: rgba(var(--mdui-color-outline), .18); overflow: hidden; }
    .bar-fill { height: 100%; min-width: 2px; border-radius: 99px; background: rgb(var(--mdui-color-primary)); }
    .bar-count { flex: 0 0 36px; font-size: .8rem; font-variant-numeric: tabular-nums; }

    /* 纵向条形：入库趋势 */
    .trend { height: 160px; }
    .trend-bar { width: 60%; min-height: 2px; border-radius: 6px 6px 0 0; background: rgb(var(--mdui-color-primary)); }
    .trend-label { font-size: .65rem; white-space: nowrap; }
    .trend-count { font-size: .68rem; font-variant-numeric: tabular-nums; }

    /* 清单 */
    .list-cover { flex: 0 0 40px; width: 40px; height: 56px; border-radius: 6px; overflow: hidden; }
    .list-row { border-bottom: 1px solid rgba(var(--mdui-color-outline), .12); }
    .list-row:last-child { border-bottom: none; }
</style>

<div id="container" class="container h-fit py-3">
    <h2 class="title-medium mb-2 font-bold">藏书概览</h2>
    <div class="stats-kpi-grid d-grid gap-3 mb-3">
        <mdui-card class="stats-kpi kpi-total bg-surface-container-low d-flex items-center gap-3 p-3">
            <div class="stats-kpi-icon center-both"><mdui-icon name="library_books"></mdui-icon></div>
            <div class="d-flex flex-col min-w-0">
                <div class="stats-kpi-value">{$kpi.total}</div>
                <div class="body-small text-on-surface-variant">总藏书</div>
            </div>
        </mdui-card>
        <mdui-card class="stats-kpi kpi-finished bg-surface-container-low d-flex items-center gap-3 p-3">
            <div class="stats-kpi-icon center-both"><mdui-icon name="task_alt"></mdui-icon></div>
            <div class="d-flex flex-col min-w-0">
                <div class="stats-kpi-value">{$kpi.finished}</div>
                <div class="body-small text-on-surface-variant">已读</div>
            </div>
        </mdui-card>
        <mdui-card class="stats-kpi kpi-reading bg-surface-container-low d-flex items-center gap-3 p-3">
            <div class="stats-kpi-icon center-both"><mdui-icon name="auto_stories"></mdui-icon></div>
            <div class="d-flex flex-col min-w-0">
                <div class="stats-kpi-value">{$kpi.reading}</div>
                <div class="body-small text-on-surface-variant">在读</div>
            </div>
        </mdui-card>
        <mdui-card class="stats-kpi kpi-rate bg-surface-container-low d-flex items-center gap-3 p-3">
            <div class="stats-kpi-icon center-both"><mdui-icon name="grade"></mdui-icon></div>
            <div class="d-flex flex-col min-w-0">
                <div class="stats-kpi-value">{$kpi.avgRate}</div>
                <div class="body-small text-on-surface-variant">平均评分</div>
            </div>
        </mdui-card>
        <mdui-card class="stats-kpi kpi-dusty bg-surface-container-low d-flex items-center gap-3 p-3">
            <div class="stats-kpi-icon center-both"><mdui-icon name="hourglass_empty"></mdui-icon></div>
            <div class="d-flex flex-col min-w-0">
                <div class="stats-kpi-value">{$kpi.neverRead}</div>
                <div class="body-small text-on-surface-variant">从未翻开</div>
            </div>
        </mdui-card>
    </div>

    <div class="stats-two-grid d-grid gap-4 mb-3">
        <mdui-card class="p-3 rounded-lg">
            <div class="title-small mb-2 font-semibold">分类分布</div>
            {if $categories}
            {foreach $categories as $c}
            <div class="d-flex items-center gap-2 my-2">
                <div class="bar-label text-ellipsis text-on-surface-variant" title="{$c.name}">{$c.name}</div>
                <div class="bar-track"><div class="bar-fill" style="width:{$c.pct}%"></div></div>
                <div class="bar-count">{$c.count}</div>
            </div>
            {/foreach}
            {else}
            <div class="body-small text-on-surface-variant">暂无数据</div>
            {/if}
        </mdui-card>

        <mdui-card class="p-3 rounded-lg">
            <div class="title-small mb-2 font-semibold">评分分布</div>
            {foreach $ratings as $r}
            <div class="d-flex items-center gap-2 my-2">
                <div class="bar-label text-on-surface-variant">{$r.label}</div>
                <div class="bar-track"><div class="bar-fill" style="width:{$r.pct}%"></div></div>
                <div class="bar-count">{$r.count}</div>
            </div>
            {/foreach}
        </mdui-card>
    </div>

    <h2 class="title-medium mb-2 font-bold">入库趋势（近 12 个月）</h2>
    <mdui-card class="p-3 rounded-lg mb-3 w-100">
        <div class="trend d-flex items-end justify-between gap-2 w-full pt-2">
            {foreach $months as $m}
            <div class="trend-col flex-1 d-flex flex-col items-center justify-end h-full gap-1">
                <div class="trend-count">{$m.count}</div>
                <div class="flex-1 w-full d-flex items-end justify-center"><div class="trend-bar" style="height:{$m.pct}%"></div></div>
                <div class="trend-label">{$m.label}</div>
            </div>
            {/foreach}
        </div>
    </mdui-card>

    <h2 class="title-medium mb-2 font-bold">书籍动态</h2>
    <div class="stats-list-grid d-grid gap-4">
        <mdui-card class="p-3 rounded-lg">
            <div class="title-small mb-2 font-semibold">最近添加</div>
            {if $recentAdded}
            {foreach $recentAdded as $b}
            <div class="list-row js-open-reader d-flex items-center gap-3 py-2 cursor-pointer" data-file="{$b.filename}" data-title="{$b.bookName}">
                <image-loader src="{$b.coverUrl}" class="list-cover"></image-loader>
                <div class="d-flex flex-col flex-1 min-w-0">
                    <div class="title-small text-ellipsis" title="{$b.bookName}">{$b.bookName}</div>
                    <div class="body-small text-on-surface-variant text-ellipsis">{$b.author}</div>
                </div>
                <div class="body-small text-on-surface-variant">{$b.meta}</div>
            </div>
            {/foreach}
            {else}
            <div class="body-small text-on-surface-variant">暂无数据</div>
            {/if}
        </mdui-card>

        <mdui-card class="p-3 rounded-lg">
            <div class="title-small mb-2 font-semibold">最近阅读</div>
            {if $recentRead}
            {foreach $recentRead as $b}
            <div class="list-row js-open-reader d-flex items-center gap-3 py-2 cursor-pointer" data-file="{$b.filename}" data-title="{$b.bookName}">
                <image-loader src="{$b.coverUrl}" class="list-cover"></image-loader>
                <div class="d-flex flex-col flex-1 min-w-0">
                    <div class="title-small text-ellipsis" title="{$b.bookName}">{$b.bookName}</div>
                    <div class="body-small text-on-surface-variant text-ellipsis">{$b.author}</div>
                </div>
                <div class="body-small text-on-surface-variant">{$b.meta}</div>
            </div>
            {/foreach}
            {else}
            <div class="body-small text-on-surface-variant">暂无数据</div>
            {/if}
        </mdui-card>

        <mdui-card class="p-3 rounded-lg">
            <div class="title-small mb-2 font-semibold">从未翻开</div>
            {if $dusty}
            {foreach $dusty as $b}
            <div class="list-row js-open-reader d-flex items-center gap-3 py-2 cursor-pointer" data-file="{$b.filename}" data-title="{$b.bookName}">
                <image-loader src="{$b.coverUrl}" class="list-cover"></image-loader>
                <div class="d-flex flex-col flex-1 min-w-0">
                    <div class="title-small text-ellipsis" title="{$b.bookName}">{$b.bookName}</div>
                    <div class="body-small text-on-surface-variant text-ellipsis">{$b.author}</div>
                </div>
                <div class="body-small text-on-surface-variant">{$b.meta}</div>
            </div>
            {/foreach}
            {else}
            <div class="body-small text-on-surface-variant">都翻过了</div>
            {/if}
        </mdui-card>
    </div>
</div>

<script id="script" src="/static/js/stats.js?v={$__v}"></script>
