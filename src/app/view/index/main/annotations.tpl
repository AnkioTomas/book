<title id="title">笔记与高亮 - {$title}</title>
<style id="style">
    .annotation-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
    .annotation-type-switch { display:flex; gap:4px; padding:4px; border-radius:12px; background:rgba(var(--mdui-color-outline),.08); }
    .annotation-type-switch button { border:0; cursor:pointer; padding:7px 12px; border-radius:9px; color:inherit; background:transparent; }
    .annotation-type-switch button.active { color:rgb(var(--mdui-color-on-primary)); background:rgb(var(--mdui-color-primary)); }
    #annotationTable .card-view-item { cursor:default; padding:0; }
    .annotation-book { overflow:hidden; }
    .annotation-book-head { cursor:pointer; }
    .annotation-cover { width:48px; height:66px; border-radius:6px; overflow:hidden; flex:none; }
    .annotation-items { border-top:1px solid rgba(var(--mdui-color-outline),.14); }
    .annotation-item { padding:14px 16px; border-bottom:1px solid rgba(var(--mdui-color-outline),.1); }
    .annotation-item:last-child { border-bottom:0; }
    .annotation-mark { width:4px; align-self:stretch; border-radius:4px; flex:none; }
    .annotation-note { margin-top:8px; padding:8px 10px; border-radius:8px; white-space:pre-wrap; background:rgba(var(--mdui-color-tertiary),.1); }
    .annotation-meta { font-size:.75rem; color:rgb(var(--mdui-color-on-surface-variant)); }
    @media (max-width:600px) {
        .annotation-toolbar mdui-text-field { width:100%; max-width:none!important; }
        .annotation-type-switch { width:100%; }
        .annotation-type-switch button { flex:1; }
    }
</style>

<div id="container" class="container h-fit py-3">
    <div class="annotation-toolbar">
        <mdui-text-field id="annotation-search" label="搜索书籍、章节、高亮或笔记" variant="outlined" icon="search" clearable style="max-width:420px;flex:1"></mdui-text-field>
        <div class="annotation-type-switch" role="tablist" aria-label="类型">
            <button type="button" class="active" data-type="">全部</button>
            <button type="button" data-type="highlight">高亮</button>
            <button type="button" data-type="note">笔记</button>
            <button type="button" data-type="bookmark">书签</button>
        </div>
    </div>
    <div id="annotationTable" class="w-100"></div>
</div>

<script id="script" src="/static/js/annotations.js?v={$__v}"></script>
