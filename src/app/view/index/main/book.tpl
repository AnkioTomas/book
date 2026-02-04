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
    
    /* 书籍卡片 - 仅保留必要样式 */
    .book-cover {
        aspect-ratio: 3/4;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

   #searchForm mdui-select::part(menu){
       max-height: 50vh;
       width: fit-content;
       overflow-y: scroll;
   }
   .card-view-item{
       padding: 0!important;
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
            <mdui-button id="btnSync" icon="sync" variant="tonal">同步</mdui-button>
            <mdui-dropdown>
                <mdui-button slot="trigger" icon="more_vert" variant="outlined" end-icon="arrow_drop_down">批量操作</mdui-button>
                <mdui-menu>
                    <mdui-menu-item id="btnBatchEdit" icon="edit_note">批量编辑</mdui-menu-item>
                    <mdui-menu-item id="btnBatchDelete" icon="delete_sweep">批量删除</mdui-menu-item>
                    <mdui-menu-item id="btnBatchScrape" icon="image">批量刮削封面</mdui-menu-item>
                    <mdui-divider></mdui-divider>
                    <mdui-menu-item id="btnRemoveDuplicates" icon="content_copy">删除重复</mdui-menu-item>
                </mdui-menu>
            </mdui-dropdown>
        </div>
    </div>
    
    <!-- 搜索筛选表单 -->
    <form id="searchForm" class="mb-4">
        <div class="row col-space12">
            <!-- 搜索 -->
            <div class="col-xs12 col-sm6 col-md3">
                <mdui-text-field
                    name="search"
                    label="搜索书名或作者"
                    icon="search"
                    clearable
                ></mdui-text-field>
            </div>
            
            <!-- 系列筛选 -->
            <div class="col-xs6 col-sm3 col-md2">
                <mdui-select style="max-height: 50vh;min-width: fit-content" name="series" clearable label="系列">
                    <mdui-menu-item value="">全部</mdui-menu-item>
                    <!-- 动态填充 -->
                </mdui-select>
            </div>
            
            <!-- 分类筛选 -->
            <div class="col-xs6 col-sm3 col-md2">
                <mdui-select name="category" clearable label="分类">
                    <mdui-menu-item value="">全部</mdui-menu-item>
                    <!-- 动态填充 -->
                </mdui-select>
            </div>
            
            <!-- 收藏筛选 -->
            <div class="col-xs6 col-sm4 col-md2">
                <mdui-select name="favorite" clearable label="收藏">
                    <mdui-menu-item value="">全部</mdui-menu-item>
                    <!-- 动态填充 -->
                </mdui-select>
            </div>

            <!-- 已读完筛选 -->
            <div class="col-xs6 col-sm4 col-md2">
                <mdui-select name="finished" clearable label="已读完">
                    <mdui-menu-item value="">全部</mdui-menu-item>
                    <mdui-menu-item value="1">已读完</mdui-menu-item>
                    <mdui-menu-item value="0">未读完</mdui-menu-item>
                </mdui-select>
            </div>
            
            <!-- 按钮 -->
            <div class="col-xs6 col-sm4 col-md3 d-flex gap-2">
                <mdui-button type="submit" icon="search" variant="filled" class="flex-1">搜索</mdui-button>
                <mdui-button type="reset" icon="refresh" variant="outlined" class="flex-1">重置</mdui-button>
            </div>
        </div>
    </form>
    
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
                    name="category"
                    id="batchCategory"
                    clearable
                    helper="将选中的所有书籍设置为该分类"
                ></mdui-text-field>
            </div>
            
            <div class="mb-3">
                <mdui-text-field
                    label="批量设置收藏夹"
                    name="favorite"
                    id="batchFavorite"
                    clearable
                    helper="将选中的所有书籍设置为该收藏夹"
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

    <!-- 拖拽上传覆盖层 -->
    <div id="dragOverlay" class="drag-overlay">
        <div class="drag-overlay-content">
            <mdui-icon name="upload" style="font-size: 64px; color: rgba(var(--mdui-color-primary));"></mdui-icon>
            <div class="headline-medium mt-3">拖放文件到此处上传</div>
            <div class="body-medium text-on-surface-variant mt-2">支持格式: EPUB, MOBI, AZW, AZW3, PDF, TXT</div>
        </div>
    </div>
</div>



<script id="script" src="/static/js/book.js"></script>