<title id="title">书籍列表 - {$title}</title>
<style id="style">
    /* 书籍封面 */
    .book-cover {
        width: 100%;
        height: 240px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 48px;
        font-weight: bold;
        overflow: hidden;
    }
    
    .book-cover img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    /* 自动完成下拉列表 */
    mdui-dropdown mdui-list {
        max-height: 240px;
        overflow-y: auto;
    }
    
    mdui-dropdown mdui-list-item {
        cursor: pointer;
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
    
    <!-- 搜索栏 -->
    <div class="mb-3">
        <mdui-text-field
            id="searchInput"
            label="搜索书名或作者"
            icon="search"
            clearable
            style="width: 100%; max-width: 500px;"
        ></mdui-text-field>
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
    
    <!-- 书籍列表 -->
    <div id="bookList" class="row col-space16"></div>
    
    <!-- 分页 -->
    <div class="mt-5">
        <mdui-page-btn id="pagination"></mdui-page-btn>
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
        style="width: min(90vw, 800px);">
    </mdui-dialog>
</div>


<script id="script" src="/static/js/book.js"></script>