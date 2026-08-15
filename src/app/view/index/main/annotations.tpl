<title id="title">笔记与高亮 - {$title}</title>
<style id="style">
    .annotation-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .annotation-view-switch { display:flex; gap:4px; padding:4px; border-radius:12px; background:rgba(var(--mdui-color-outline),.08); }
    .annotation-view-switch button { border:0; cursor:pointer; padding:7px 12px; border-radius:9px; color:inherit; background:transparent; }
    .annotation-view-switch button.active { color:rgb(var(--mdui-color-on-primary)); background:rgb(var(--mdui-color-primary)); }
    .annotation-book { overflow:hidden; }
    .annotation-book-head { cursor:pointer; }
    .annotation-cover { width:48px; height:66px; border-radius:6px; overflow:hidden; flex:none; }
    .annotation-items { border-top:1px solid rgba(var(--mdui-color-outline),.14); }
    .annotation-item { padding:14px 16px; border-bottom:1px solid rgba(var(--mdui-color-outline),.1); }
    .annotation-item:last-child { border-bottom:0; }
    .annotation-mark { width:4px; align-self:stretch; border-radius:4px; background:rgb(var(--mdui-color-primary)); flex:none; }
    .annotation-note { margin-top:8px; padding:8px 10px; border-radius:8px; background:rgba(var(--mdui-color-tertiary),.1); }
    .annotation-meta { font-size:.75rem; color:rgb(var(--mdui-color-on-surface-variant)); }
    .annotation-cloud { min-height:360px; display:flex; flex-wrap:wrap; align-content:center; justify-content:center; gap:12px 16px; padding:28px; }
    .annotation-cloud button { border:0; cursor:pointer; line-height:1.15; color:rgb(var(--mdui-color-primary)); background:transparent; }
    .annotation-cloud button:hover { color:rgb(var(--mdui-color-tertiary)); transform:scale(1.06); }
    .annotation-cloud-results { display:grid; gap:10px; }
    .annotation-cloud-hit { padding:12px 14px; border-radius:10px; background:rgba(var(--mdui-color-outline),.07); }
    @media (max-width:600px) {
        .annotation-toolbar mdui-text-field { width:100%; max-width:none!important; }
        .annotation-view-switch { width:100%; }
        .annotation-view-switch button { flex:1; }
        .annotation-cloud { padding:18px 10px; gap:10px; }
    }
</style>

<div id="container" class="container h-fit py-3">
    <div class="annotation-toolbar mb-3">
        <mdui-text-field id="annotation-search" label="搜索书籍、高亮或笔记" variant="outlined" icon="search" style="max-width:420px;flex:1"></mdui-text-field>
        <div class="annotation-view-switch" role="tablist" aria-label="展示方式">
            <button type="button" class="active" data-view="list"><mdui-icon name="view_list"></mdui-icon> 书籍列表</button>
            <button type="button" data-view="cloud"><mdui-icon name="cloud"></mdui-icon> 词云</button>
        </div>
    </div>

    <div id="annotation-summary" class="body-small text-on-surface-variant mb-3">加载中…</div>
    <div id="annotation-list"></div>
    <div id="annotation-cloud-view" class="d-none">
        <mdui-card class="rounded-lg w-100 mb-3">
            <div id="annotation-cloud" class="annotation-cloud"></div>
        </mdui-card>
        <div id="annotation-cloud-title" class="title-small font-semibold mb-2 d-none"></div>
        <div id="annotation-cloud-results" class="annotation-cloud-results"></div>
    </div>
</div>

<script id="script" src="/static/js/annotations.js?v={$__v}"></script>
