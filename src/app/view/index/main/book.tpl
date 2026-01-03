<title id="title">书籍列表 - {$title}</title>
<style id="style">
    /* 书籍封面缩略图 */
    .book-cover-thumb {
        width: 40px;
        height: 56px;
        object-fit: cover;
        border-radius: 4px;
        display: block;
    }
    
    .book-cover-placeholder {
        width: 40px;
        height: 56px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 18px;
        font-weight: bold;
        border-radius: 4px;
    }
    
    /* 自动完成下拉列表 */
    mdui-dropdown mdui-list {
        max-height: 240px;
        overflow-y: auto;
    }
    
    mdui-dropdown mdui-list-item {
        cursor: pointer;
    }
    
    /* 拖拽上传覆盖层 */
    .drag-overlay {
        position: fixed;
        inset: 0;
        background: rgba(var(--mdui-color-primary), 0.1);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        pointer-events: none;
    }
    
    .drag-overlay.active {
        display: flex;
    }
    
    .drag-overlay-content {
        background: rgba(var(--mdui-color-surface-container-highest));
        border: 3px dashed rgba(var(--mdui-color-primary));
        border-radius: 16px;
        padding: 3rem;
        text-align: center;
        box-shadow: var(--mdui-elevation-level3);
    }
    
    /* DataTable 样式优化 */
    .book-title {
        font-weight: 500;
        color: rgba(var(--mdui-color-on-surface));
    }
    
    .book-description {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 3;
        overflow: hidden;
        word-break: break-word;
        white-space: normal;
        line-height: 1.5;
        max-width: 300px;
        max-height: 4.5em;
        color: rgba(var(--mdui-color-on-surface-variant));
        font-size: .875rem;
    }
</style>

<div id="container" class="container py-4">
    <!-- 头部：标题和操作按钮 -->
    <div class="d-flex justify-between items-center mb-4 flex-wrap gap-3">
        <div class="title-large d-flex items-center">
            <mdui-icon name="book" class="mr-2"></mdui-icon>
            <span>书籍列表</span>
        </div>
        <div class="d-flex gap-2">
            <mdui-button id="btnAdd" icon="add" variant="filled">导入</mdui-button>
            <mdui-button id="btnSync" icon="sync" variant="filled">同步</mdui-button>
        </div>
    </div>
    
    <!-- 快速筛选 -->
    <div class="mb-4">
        <div class="d-flex items-center gap-2 mb-2">
            <mdui-icon name="filter_alt" class="text-on-surface-variant"></mdui-icon>
            <span class="body-medium text-on-surface-variant">快速筛选</span>
            <mdui-button-icon id="btnResetFilters" icon="refresh" title="清除所有筛选"></mdui-button-icon>
        </div>
        
        <div class="d-flex gap-3 flex-wrap">
            <!-- 系列筛选 -->
            <div class="d-flex flex-col gap-1" style="min-width: 180px;">
                <mdui-select id="filterSeries" clearable label="系列">
                    <mdui-menu-item value="">全部系列</mdui-menu-item>
                    <!-- 动态填充 -->
                </mdui-select>
            </div>
            
            <!-- 分类筛选 -->
            <div class="d-flex flex-col gap-1" style="min-width: 180px;">
                <mdui-select id="filterCategory" clearable label="分类">
                    <mdui-menu-item value="">全部分类</mdui-menu-item>
                    <!-- 动态填充 -->
                </mdui-select>
            </div>
            
            <!-- 收藏夹筛选 -->
            <div class="d-flex flex-col gap-1" style="min-width: 180px;">
                <mdui-select id="filterFavorite" clearable label="收藏">
                    <mdui-menu-item value="">全部收藏</mdui-menu-item>
                    <!-- 动态填充 -->
                </mdui-select>
            </div>
        </div>
        
        <!-- 当前筛选条件显示 -->
        <div id="activeFilters" class="d-flex gap-2 mt-3 flex-wrap"></div>
    </div>
    
    <!-- 书籍列表表格 -->
    <div id="bookTable"></div>

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
                <mdui-dropdown id="categoryDropdown">
                    <mdui-text-field
                        slot="trigger"
                        id="editCategory"
                        label="分类"
                        name="category"
                        helper="可选择或输入新值"
                        clearable
                        icon="arrow_drop_down"
                        end-icon
                    ></mdui-text-field>
                    <mdui-list id="categoryList"></mdui-list>
                </mdui-dropdown>
            </div>

            <div class="col-xs12 col-sm6">
                <mdui-dropdown id="favoriteDropdown">
                    <mdui-text-field
                        slot="trigger"
                        id="editFavorite"
                        label="收藏夹"
                        name="favorite"
                        helper="可选择或输入新值"
                        clearable
                        icon="arrow_drop_down"
                        end-icon
                    ></mdui-text-field>
                    <mdui-list id="favoriteList"></mdui-list>
                </mdui-dropdown>
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

    <mdui-dialog 
        id="searchDialog" 
        close-on-overlay-click
    >
    </mdui-dialog>
</div>

<!-- 拖拽上传覆盖层 -->
<div id="dragOverlay" class="drag-overlay">
    <div class="drag-overlay-content">
        <mdui-icon name="upload" style="font-size: 64px; color: rgba(var(--mdui-color-primary));"></mdui-icon>
        <div class="headline-medium mt-3">拖放文件到此处上传</div>
        <div class="body-medium text-on-surface-variant mt-2">支持格式: EPUB, MOBI, AZW, AZW3, PDF, TXT</div>
    </div>
</div>

<script id="script" src="/static/js/book.js"></script>