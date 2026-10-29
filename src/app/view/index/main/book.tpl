<title id="title">书籍列表 - {$title}</title>
<style id="style">
    /* 自定义元素升级前隐藏对话框表单，避免进页 FOUC 闪一下整页表单 */
    mdui-dialog-form:not(:defined),
    meta-fill-picker:not(:defined),
    zlib-picker:not(:defined),
    drag-upload:not(:defined) {
        display: none !important;
    }

    /* 宽弹窗：必须写在页面 style 里；组件内 style 打不穿 mdui 默认 560px */
    mdui-dialog.zlib-dialog::part(panel),
    mdui-dialog.meta-fill-dialog::part(panel) {
        width: min(960px, 96vw) !important;
        max-width: 960px !important;
        height: min(var(--80vh, 80vh), 720px) !important;
        max-height: var(--80vh, 80vh) !important;
    }
    mdui-dialog.zlib-dialog::part(body),
    mdui-dialog.meta-fill-dialog::part(body) {
        display: flex !important;
        flex-direction: column !important;
        min-height: 0 !important;
        overflow: hidden !important;
    }

    .book-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .book-toolbar-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .book-filter-panel {
        padding: 0;
        margin-bottom: 14px;
    }

    
    /* 自动完成下拉列表 */
    mdui-dropdown {
        --mdui-comp-dropdown-z-index: 2000;
    }
    
    mdui-dropdown mdui-list {
        max-height: 240px;
        overflow-y: auto;
        background: rgb(var(--mdui-color-surface-container));
        box-shadow: var(--mdui-elevation-level2);
        border-radius: 4px;
    }
    
    mdui-dropdown mdui-list-item {
        cursor: pointer;
    }
    
   #searchForm mdui-select::part(menu){
       max-height: 50vh;
       width: fit-content;
       overflow-y: scroll;
   }

    #bookTable book-card {
        display: block;
        width: 100%;
    }

    /* 卡片内容区域 */
    #bookTable .book-card-content {
        display: block;
    }

    /* 操作按钮 - 块级布局，右对齐 */
    #bookTable .book-actions {
        display: block;
        text-align: right;
        margin-top: 8px;
    }

    /* 卡片悬停时显示操作按钮 */
    #bookTable .card-view-item:hover .book-actions,
    #bookTable .card-view-item.selected .book-actions {
        display: block;
    }

    /* 移动端：操作按钮始终显示且居中 */
    @media (hover: none), (pointer: coarse) {
        #bookTable .book-actions {
            display: block;
            text-align: center;
        }
    }

    @media (max-width: 768px) {
        #bookTable .card-view-container {
            --card-min-width: 130px;
        }
        #bookTable .card-view-item {
            padding: 4px;
        }
        .btn-download {
            display: none;
        }
    }

    /* 手机：工具栏只留图标、隐藏全选栏、三列卡片 */
    @media (max-width: 600px) {
        .book-toolbar {
            gap: 8px;
            margin-bottom: 4px;
        }
        .book-toolbar > .title-large,
        .book-toolbar-actions {
            width: 100%;
        }
        .book-toolbar-actions {
            justify-content: flex-end;
        }
        .book-toolbar-actions mdui-button::part(label),
        .book-toolbar-actions mdui-button::part(end-icon) {
            display: none;
        }
        .book-toolbar-actions mdui-button::part(button) {
            padding-inline: 0;
            width: 40px;
            justify-content: center;
        }
        .book-toolbar-actions mdui-button::part(icon) {
            margin: 0;
        }
        /* 手机：藏选择栏，三列封面卡 */
        #bookTable .card-view-selection-bar {
            display: none;
        }
        #bookTable .card-view-container {
            --card-min-width: 100px;
            gap: 8px;
            padding-top: 8px;
        }
        #bookTable .card-view-item {
            padding: 2px;
        }
        #bookTable .book-card-content {
            padding: 4px;
        }
    }

    /* —— 编辑书籍弹窗 —— */
    .book-edit-form {
        display: flex;
        flex-direction: column;
        gap: 1.15rem;
    }
    .book-edit-hero {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
    }
    .book-edit-cover {
        flex: 0 0 6.75rem;
        width: 6.75rem;
        aspect-ratio: 2 / 3;
        height: auto;
        border-radius: 0.75rem;
        overflow: hidden;
        background: rgba(var(--mdui-color-surface-container-highest), 1);
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        align-self: flex-start;
    }
    .book-edit-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center top;
        display: none;
    }
    .book-edit-cover.has-cover img {
        display: block;
    }
    .book-edit-cover-empty {
        color: rgba(var(--mdui-color-on-surface-variant), 0.7);
        font-size: 0.75rem;
        text-align: center;
        padding: 0.5rem;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 0.35rem;
    }
    .book-edit-cover.has-cover .book-edit-cover-empty {
        display: none;
    }
    .book-edit-main {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
    }
    /* end-icon 默认 40px 会撑出 filled 背景；压到跟清空按钮同量级 */
    .book-edit-form mdui-text-field mdui-button-icon[slot="end-icon"] {
        width: 2rem;
        height: 2rem;
        font-size: 1.25rem;
    }
    .book-edit-form mdui-text-field mdui-button-icon[slot="end-icon"]::part(button) {
        width: 2rem;
        height: 2rem;
    }
    .book-edit-rate {
        display: flex;
        align-items: center;
        gap: 0.25rem;
        margin-top: 0.25rem;
        min-height: 2.5rem;
    }
    .book-edit-rate-label {
        font-size: var(--mdui-typescale-body-small-size, 0.75rem);
        color: rgb(var(--mdui-color-on-surface-variant));
        margin-right: 0.35rem;
        flex-shrink: 0;
    }
    .book-edit-rate mdui-button-icon {
        color: rgb(var(--mdui-color-primary));
    }
    .book-edit-tags {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }
    .book-edit-tags-label {
        font-size: var(--mdui-typescale-body-small-size, 0.75rem);
        color: rgb(var(--mdui-color-on-surface-variant));
    }
    .book-edit-tag-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        min-height: 1.75rem;
    }
    .book-edit-tag-chips:empty::before {
        content: '暂无标签';
        font-size: 0.75rem;
        color: rgba(var(--mdui-color-on-surface-variant), 0.55);
    }
    @media (max-width: 600px) {
        .book-edit-hero {
            flex-direction: column;
            align-items: center;
        }
        .book-edit-cover {
            flex-basis: 7.5rem;
            width: 7.5rem;
        }
        .book-edit-main {
            width: 100%;
        }
    }
</style>

<div id="container" class="container book-page">
    <div class="book-toolbar">
        <div class="title-large d-flex items-center">
            <form id="searchForm" class="d-flex flex-wrap items-end gap-2" style="flex:1;min-width:0">
                <mdui-text-field
                        class="flex-1"
                        style="min-width: 12rem"
                        name="search"
                        label="搜索书名或作者"
                        icon="search"
                        clearable
                ></mdui-text-field>

            </form>
        </div>
        <div class="book-toolbar-actions">
            <mdui-button id="btnAdd" icon="add" variant="filled">导入</mdui-button>
            <mdui-button id="btnZlib" icon="cloud_download" variant="tonal">Z站</mdui-button>
            <mdui-button id="btnSync" icon="sync" variant="tonal">同步</mdui-button>
            <mdui-button id="btnToggleCategoryHidden" icon="visibility_off" variant="outlined" style="display:none">隐藏此分类</mdui-button>
            <mdui-dropdown>
                <mdui-button slot="trigger" icon="more_vert" variant="outlined" end-icon="arrow_drop_down">批量</mdui-button>
                <mdui-menu>
                    <mdui-menu-item id="btnBatchEdit" icon="edit_note">批量编辑</mdui-menu-item>
                    <mdui-menu-item id="btnBatchSetCategory" icon="topic">设置分类</mdui-menu-item>
                    <mdui-menu-item id="btnBatchAiIdentify" icon="auto_awesome">AI 识别</mdui-menu-item>
                    <mdui-menu-item id="btnBatchAiFillMissing" icon="playlist_add_check">AI 填充缺失</mdui-menu-item>
                    <mdui-menu-item id="btnBatchAiClassify" icon="label">AI 分类</mdui-menu-item>
                    <mdui-menu-item id="btnBatchDelete" icon="delete_sweep">批量删除</mdui-menu-item>
                    <mdui-menu-item id="btnBatchScrape" icon="image">批量刮削封面</mdui-menu-item>
                    <mdui-menu-item id="btnBatchMarkRead" icon="task_alt">批量标记已读</mdui-menu-item>
                    <mdui-menu-item id="btnBatchMarkUnread" icon="radio_button_unchecked">批量标记未读</mdui-menu-item>
                    <mdui-divider></mdui-divider>
                    <mdui-menu-item id="btnOrganize" icon="folder_copy">整理源文件</mdui-menu-item>
                    <mdui-menu-item id="btnRemoveDuplicates" icon="content_copy">删除重复</mdui-menu-item>
                </mdui-menu>
            </mdui-dropdown>
        </div>
    </div>


    <div class="book-list-panel">
        <div id="bookTable"></div>
    </div>

    <!-- 编辑对话框 -->
    <mdui-dialog-form id="bookEditDialog" label="编辑书籍" saveName="保存" >
        <form id="bookEditForm" class="book-edit-form">
            <mdui-text-field type="hidden" name="id"></mdui-text-field>
            <!-- 实际提交字段，由 chip / 星级 UI 同步；视觉隐藏，保留 Form.js 读写 -->
            <mdui-text-field class="d-none" name="category" id="editCategory"></mdui-text-field>
            <mdui-text-field class="d-none" name="rate" id="editRate" type="number" min="0" max="5"></mdui-text-field>

            <div class="book-edit-hero">
                <div class="book-edit-cover" id="editCoverBox">
                    <img id="editCoverPreview" alt="封面预览" referrerpolicy="no-referrer">
                    <div class="book-edit-cover-empty">
                        <mdui-icon name="image"></mdui-icon>
                        无封面
                    </div>
                </div>
                <div class="book-edit-main">
                    <mdui-text-field
                            label="书名"
                            name="bookName"
                            id="bookName"
                            required
                    >
                        <mdui-button-icon
                                slot="end-icon"
                                icon="manage_search"
                                id="metaFillBtn"
                                title="元数据补全"
                        ></mdui-button-icon>
                    </mdui-text-field>
                    <mdui-text-field label="作者" name="author"></mdui-text-field>
                    <mdui-text-field label="封面 URL" name="coverUrl" id="editCoverUrl" clearable></mdui-text-field>
                    <div class="book-edit-rate" id="editRateStars" role="group" aria-label="评分">
                        <span class="book-edit-rate-label">评分</span>
                    </div>
                </div>
            </div>

            <mdui-text-field
                    label="简介"
                    name="description"
                    rows="3"
                    type="textarea"
            ></mdui-text-field>

            <div class="row col-space12">
                <div class="col-xs12 col-sm4">
                    <mdui-search-input
                            id="editFavorite"
                            name="favorite"
                            label="分类"
                            placeholder="可选择或输入新值"
                            search-uri="/index/book/searchFavorite"
                            min-length="1"
                            clearable
                    ></mdui-search-input>
                </div>
                <div class="col-xs12 col-sm5">
                    <mdui-dropdown id="seriesDropdown">
                        <mdui-text-field
                            slot="trigger"
                            id="editSeries"
                            label="系列名称"
                            name="series"
                            helper="可选择或输入新值"
                            clearable
                            icon="arrow_drop_down"
                            end-icon
                        ></mdui-text-field>
                        <mdui-list id="seriesList"></mdui-list>
                    </mdui-dropdown>
                </div>
                <div class="col-xs12 col-sm3">
                    <mdui-text-field
                            label="系列编号"
                            name="seriesNum"
                            type="number"
                            min="0"
                    ></mdui-text-field>
                </div>
            </div>

            <div class="book-edit-tags">
                <div class="book-edit-tags-label">标签</div>
                <div class="book-edit-tag-chips" id="editTagChips"></div>
                <mdui-text-field
                        id="editTagInput"
                        label="添加标签"
                        placeholder="回车添加"
                >
                    <mdui-button-icon
                            slot="end-icon"
                            icon="add"
                            id="editTagAddBtn"
                            title="添加"
                    ></mdui-button-icon>
                </mdui-text-field>
            </div>
        </form>
    </mdui-dialog-form>

    <meta-fill-picker id="metaFillPicker"></meta-fill-picker>
    <zlib-picker id="zlibPicker"></zlib-picker>

    <!-- 批量编辑对话框 -->
    <mdui-dialog-form id="batchEditDialog" label="批量编辑书籍">
        <form id="batchEditForm">
            <div class="mb-3">
                <mdui-text-field

                    label="批量设置作者"
                    name="author"
                    id="batchAuthor"
                    clearable
                    helper="将选中的所有书籍设置为该作者"
                ></mdui-text-field>
            </div>

            <div class="mb-3">
                <mdui-text-field

                    label="批量设置分类"
                    name="favorite"
                    id="batchFavorite"
                    clearable
                    helper="将选中的所有书籍设置为该分类（单值）"
                ></mdui-text-field>
            </div>

            <div class="mb-3">
                <mdui-text-field

                    label="批量设置标签"
                    name="category"
                    id="batchCategory"
                    clearable
                    helper="多个标签用换行分隔，将覆盖原有标签"
                ></mdui-text-field>
            </div>

            <div class="mb-3">
                <mdui-text-field

                    label="批量设置系列"
                    name="series"
                    id="batchSeries"
                    clearable
                    helper="将选中的所有书籍设置为该系列"
                ></mdui-text-field>
            </div>

            <div class="body-small text-on-surface-variant">
                提示：只填写需要批量设置的字段，留空的字段不会被修改
            </div>
        </form>

        <mdui-button slot="action" variant="text" onclick="this.closest('mdui-dialog').open = false">取消</mdui-button>
        <mdui-button slot="action" variant="filled" id="btnBatchSubmit">批量更新</mdui-button>
    </mdui-dialog-form>

    <!-- 设置分类对话框（单本 / 批量共用） -->
    <mdui-dialog-form id="setCategoryDialog" label="设置分类" saveName="确定">
        <form id="setCategoryForm">
            <div class="body-medium mb-3" id="setCategoryTip"></div>
            <div class="mb-3">
                <mdui-search-input
                        id="setCategoryFavorite"
                        name="favorite"
                        label="分类"
                        placeholder="可选择已有分类或输入新分类"
                        search-uri="/index/book/searchFavorite"
                        min-length="1"
                        clearable
                ></mdui-search-input>
            </div>
            <div class="body-small text-on-surface-variant">
                提示：只修改分类，不影响标签；如需移除分类请点「清空分类」
            </div>
        </form>

        <mdui-button slot="action" variant="text" onclick="this.closest('mdui-dialog').open = false">取消</mdui-button>
        <mdui-button slot="action" variant="tonal" id="btnClearCategory">清空分类</mdui-button>
    </mdui-dialog-form>

    <drag-upload
        accept=".epub,.mobi,.azw,.azw3,.pdf,.txt"
        hint="EPUB, MOBI, AZW, AZW3, PDF, TXT"
    ></drag-upload>
</div>



<script id="script" src="/static/js/book.js?v={$__v}"></script>