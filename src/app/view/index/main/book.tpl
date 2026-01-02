<title id="title">书籍列表 - {$title}</title>
<style id="style">
    /* 书籍卡片交互 */
    .book-card {
        transition: transform 0.2s, box-shadow 0.2s;
        cursor: pointer;
        overflow: hidden;
    }
    
    .book-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0,0,0,0.15) !important;
    }
    
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
    
    <!-- 搜索和筛选 -->
    <div class="d-flex gap-3 items-center flex-wrap mb-4">
        <mdui-text-field
            id="searchInput"
            label="搜索书名或作者"
            icon="search"
            clearable
            style="flex: 1; max-width: 400px;"
        ></mdui-text-field>
        
        <mdui-select id="filterType" label="筛选类型" style="width: 150px;">
            <mdui-menu-item value="">全部</mdui-menu-item>
            <mdui-menu-item value="groupName">系列</mdui-menu-item>
            <mdui-menu-item value="category">分类</mdui-menu-item>
            <mdui-menu-item value="favorite">收藏夹</mdui-menu-item>
        </mdui-select>
        
        <mdui-select id="filterValue" label="筛选值" style="width: 200px;" disabled>
            <mdui-menu-item value="">请选择</mdui-menu-item>
        </mdui-select>
        
        <mdui-button id="btnFilter" icon="filter_alt" variant="outlined">筛选</mdui-button>
        <mdui-button id="btnReset" icon="refresh" variant="outlined">重置</mdui-button>
    </div>
    
    <!-- 书籍列表 -->
    <div id="bookList" class="row col-space16"></div>
    
    <!-- 分页 -->
    <div class="mt-5">
        <mdui-page-btn id="pagination"></mdui-page-btn>
    </div>
</div>

<!-- 添加/编辑对话框 -->
<mdui-dialog id="bookDialog" close-on-esc close-on-overlay-click>
    <span slot="headline" id="dialogTitle">添加书籍</span>
    
    <form id="bookForm" class="px-4 py-3" style="min-width: 500px;">
        <input type="hidden" id="bookId" name="id">
        
        <div class="row col-space12">
            <div class="col-xs12">
                <mdui-text-field
                    label="书名"
                    name="bookName"
                    required
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12">
                <mdui-text-field
                    label="作者"
                    name="author"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12">
                <mdui-text-field
                    label="简介"
                    name="description"
                    rows="3"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12 col-sm6">
                <mdui-text-field
                    label="分类"
                    name="category"
                    helper="多个分类用换行或空格分隔"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12 col-sm6">
                <mdui-text-field
                    label="系列名称"
                    name="groupName"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12 col-sm6">
                <mdui-text-field
                    label="收藏夹标签"
                    name="favorite"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12 col-sm6">
                <mdui-text-field
                    label="文件名"
                    name="filename"
                    helper="例如: 巷子里的野猫.epub"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12">
                <mdui-text-field
                    label="下载地址"
                    name="downloadUrl"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12 col-sm8">
                <mdui-text-field
                    label="封面图片URL"
                    name="coverUrl"
                ></mdui-text-field>
            </div>
            
            <div class="col-xs12 col-sm4">
                <mdui-text-field
                    label="评分"
                    name="rate"
                    type="number"
                    min="0"
                    max="5"
                    step="0.5"
                    value="0"
                ></mdui-text-field>
            </div>
        </div>
    </form>
    
    <mdui-button slot="action" variant="text" id="btnCancelDialog">取消</mdui-button>
    <mdui-button slot="action" variant="filled" id="btnSaveDialog">保存</mdui-button>
</mdui-dialog>

<script id="script" src="/static/js/book.js"></script>