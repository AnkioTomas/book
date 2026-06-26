<title id="title">书籍列表 - {$title}</title>
<style id="style">
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
        .card-view-container {
            --card-min-width: 130px !important;
        }
        .card-view-item {
            padding: 4px;
        }
        .btn-download {
            display: none;
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
            <mdui-button id="btnSync" icon="sync" variant="tonal">同步</mdui-button>
            <mdui-dropdown>
                <mdui-button slot="trigger" icon="more_vert" variant="outlined" end-icon="arrow_drop_down">批量</mdui-button>
                <mdui-menu>
                    <mdui-menu-item id="btnBatchEdit" icon="edit_note">批量编辑</mdui-menu-item>
                    <mdui-menu-item id="btnBatchAiIdentify" icon="auto_awesome">AI 识别</mdui-menu-item>
                    <mdui-menu-item id="btnBatchDelete" icon="delete_sweep">批量删除</mdui-menu-item>
                    <mdui-menu-item id="btnBatchScrape" icon="image">批量刮削封面</mdui-menu-item>
                    <mdui-menu-item id="btnBatchMarkRead" icon="task_alt">批量标记已读</mdui-menu-item>
                    <mdui-menu-item id="btnBatchMarkUnread" icon="radio_button_unchecked">批量标记未读</mdui-menu-item>
                    <mdui-divider></mdui-divider>
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
        <form id="bookEditForm">
            <mdui-text-field type="hidden" name="id" ></mdui-text-field>

            <div class="row col-space12">
                <div class="col-xs12 d-flex items-end gap-2">
                    <mdui-text-field

                            label="书名"
                            name="bookName"
                            id="bookName"
                            required
                            class="flex-1"
                    ></mdui-text-field>
                    <mdui-button-icon variant="filled" icon="search" id="douban"></mdui-button-icon>
                    <mdui-button-icon variant="tonal" icon="auto_awesome" id="aiFill"></mdui-button-icon>
                </div>

                <div class="col-xs12">
                    <mdui-text-field

                            label="作者"
                            name="author"
                    ></mdui-text-field>
                </div>

                <div class="col-xs12">
                    <mdui-text-field

                            label="封面"
                            name="coverUrl"
                    ></mdui-text-field>
                </div>

                <div class="col-xs12">
                    <mdui-text-field

                            label="简介"
                            name="description"
                            rows="3"
                            type="textarea"
                    ></mdui-text-field>
                </div>

            <div class="col-xs12 col-sm6">
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

            <div class="col-xs12 col-sm6">
                <mdui-text-field
                        id="editCategory"
                        label="标签"
                        name="category"
                        type="textarea"
                        rows="3"
                        helper="多个标签用换行分隔"
                        clearable
                ></mdui-text-field>
            </div>

            <div class="col-xs12 col-sm8">
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

                <div class="col-xs12 col-sm4">
                    <mdui-text-field

                            label="系列编号"
                            name="seriesNum"
                            type="number"
                            min="0"
                    ></mdui-text-field>
                </div>

                <div class="col-xs12">
                    <mdui-text-field

                            label="评分"
                            name="rate"
                            type="number"
                            min="0"
                            max="5"
                            step="1"
                    ></mdui-text-field>
                </div>
            </div>
        </form>
    </mdui-dialog-form>

    <douban-book-picker id="doubanBookPicker"></douban-book-picker>

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

    <book-context-menu id="bookContextMenu"></book-context-menu>

    <drag-upload
        accept=".epub,.mobi,.azw,.azw3,.pdf,.txt"
        hint="EPUB, MOBI, AZW, AZW3, PDF, TXT"
    ></drag-upload>
</div>



<script id="script" src="/static/js/book.js"></script>