/**
 * 书籍列表页面交互逻辑
 * @file book.js
 */

window.pageLoadFiles = [
    'Toaster',
    'Pagination',
    "FileUploader",
];

window.pageOnLoad = function (loading) {

    /**
     * 书籍页面管理类
     */
    class BookPage {
        constructor() {
            // 分页状态
            this.currentPage = 1;
            this.pageSize = 20;
            this.totalCount = 0;

            // 搜索和筛选
            this.searchKeyword = '';
            this.filterType = '';
            this.filterValue = '';

            // 筛选选项缓存
            this.filterOptions = {
                groupNames: [],
                categories: [],
                favorites: []
            };

            // 文件上传实例
            this.fileUpload = null;
        }

        /**
         * 初始化
         */
        init() {
            this.bindElements();
            this.bindEvents();
            this.loadFilters();
            this.loadBooks();
        }

        /**
         * 绑定DOM元素（使用 jQuery）
         */
        bindElements() {
            this.$bookList = $('#bookList');
            this.$pagination = $('#pagination');
            this.$searchInput = $('#searchInput');
            this.$filterType = $('#filterType');
            this.$filterValue = $('#filterValue');
            this.$btnFilter = $('#btnFilter');
            this.$btnReset = $('#btnReset');
            this.$btnAdd = $('#btnAdd');
            this.$btnSync = $('#btnSync');
        }

        /**
         * 绑定事件（使用 jQuery）
         */
        bindEvents() {
            // 搜索（输入时实时搜索）
            this.$searchInput.on('input', (e) => {
                this.searchKeyword = $(e.target).val().trim();
                this.currentPage = 1;
                this.loadBooks();
            });

            // 筛选类型变化
            this.$filterType.on('change', (e) => {
                this.filterType = $(e.target).val();
                this.updateFilterValueOptions();
                this.$filterValue.prop('disabled', !this.filterType);
            });

            // 执行筛选
            this.$btnFilter.on('click', () => {
                this.filterValue = this.$filterValue.val();
                this.currentPage = 1;
                this.loadBooks();
            });

            // 重置筛选
            this.$btnReset.on('click', () => {
                this.searchKeyword = '';
                this.filterType = '';
                this.filterValue = '';
                this.$searchInput.val('');
                this.$filterType.val('');
                this.$filterValue.val('');
                this.$filterValue.prop('disabled', true);
                this.currentPage = 1;
                this.loadBooks();
            });

            // 添加书籍（文件上传）
            this.$btnAdd.on('click', () => {
                $.file.upload({
                    accept: '.epub,.mobi,.azw,.azw3,.pdf,.txt',
                    uploadEndpoint: '/admin/api/upload',
                    onSuccess: (res) => {
                        $.toaster.success('上传成功');
                        $.request.postForm("/admin/api/publish",{name:res.data},function (res){
                            $.toaster.success(res.msg);
                        });
                    },
                    onError: (msg) => {
                        $.toaster.error(msg || '上传失败');
                    }
                })
            });

            // 同步 WebDAV
            this.$btnSync.on('click', () => {
                this.syncWebDAV();
            });

            // 初始化分页组件
            this.initPagination();
        }


        /**
         * 初始化分页组件
         */
        initPagination() {
            this.$pagination[0].init({
                pageIndex: this.currentPage,
                pageSize: this.pageSize,
                total: this.totalCount,
                layout: 'first, prev, pager, next, last',
                showCount: true,
                showLimits: true,
                onPageChange: (index, pageSize) => {
                    this.currentPage = index;
                    this.pageSize = pageSize;
                    this.loadBooks();
                }
            });
        }

        /**
         * 加载筛选选项
         */
        loadFilters() {
            $.request.get('/admin/api/book/filters', {}, (res) => {
                if (res.code === 200) {
                    this.filterOptions = res.data;
                }
            });
        }

        /**
         * 更新筛选值下拉选项
         */
        updateFilterValueOptions() {
            this.$filterValue.html('<mdui-menu-item value="">请选择</mdui-menu-item>');

            let options = [];
            switch (this.filterType) {
                case 'groupName':
                    options = this.filterOptions.groupNames || [];
                    break;
                case 'category':
                    options = this.filterOptions.categories || [];
                    break;
                case 'favorite':
                    options = this.filterOptions.favorites || [];
                    break;
            }

            options.forEach(opt => {
                this.$filterValue.append(`<mdui-menu-item value="${opt}">${opt}</mdui-menu-item>`);
            });
        }

        /**
         * 加载书籍列表
         */
        loadBooks() {
            const params = {
                page: this.currentPage,
                limit: this.pageSize,
                search: this.searchKeyword,
                filterType: this.filterType,
                filterValue: this.filterValue
            };

            $.request.get('/admin/api/book/list', params, (res) => {
                if (res.code === 200) {
                    this.totalCount = res.data.total;
                    this.renderBooks(res.data.list);
                    this.updatePagination();
                } else {
                    $.toaster.error(res.msg || '加载失败');
                }
            });
        }

        /**
         * 渲染书籍列表
         */
        renderBooks(books) {
            if (!books || books.length === 0) {
                this.$bookList.html(`
                    <div class="col-xs12 text-center py-5 text-on-surface-variant">
                        <mdui-icon name="collections_bookmark" style="font-size: 64px; opacity: 0.3;"></mdui-icon>
                        <p class="mt-3 body-large">暂无书籍</p>
                    </div>
                `);
                return;
            }

            this.$bookList.html(books.map(book => this.renderBookCard(book)).join(''));
            this.bindCardEvents();
        }

        /**
         * 渲染单个书籍卡片
         */
        renderBookCard(book) {
            const coverHtml = book.coverUrl
                ? `<img src="${book.coverUrl}" alt="${book.bookName}">`
                : `<span>${book.bookName.charAt(0)}</span>`;

            const description = book.description || '暂无简介';

            return `
                <div class="col-xs12 col-sm6 col-md4 col-lg3">
                    <mdui-card class="book-card h-full w-100" style="max-width: 12rem;" data-book-id="${book.id}">
                        <div class="book-cover">${coverHtml}</div>
                        <div class="p-3">
                            <div class="title-medium text-ellipsis mb-1" title="${book.bookName}">${book.bookName}</div>
                            <div class="body-small text-on-surface-variant mb-2">${book.author || '未知作者'}</div>
                            <div class="body-small text-on-surface-variant line-clamp-2" style="min-height: 2.5rem;">${description}</div>
                        </div>
                        <div class="d-flex justify-end gap-2 px-3 pb-3">
                            <mdui-button-icon class="btn-delete" data-book-id="${book.id}" title="删除">
                                <mdui-icon name="delete"></mdui-icon>
                            </mdui-button-icon>
                        </div>
                    </mdui-card>
                </div>
            `;
        }

        /**
         * 绑定卡片事件
         */
        bindCardEvents() {
            this.$bookList.find('.btn-delete').on('click', (e) => {
                e.stopPropagation();
                const bookId = parseInt($(e.currentTarget).data('book-id'));
                this.deleteBook(bookId);
            });
        }

        /**
         * 更新分页信息
         */
        updatePagination() {
            this.$pagination[0].init({
                pageIndex: this.currentPage,
                pageSize: this.pageSize,
                total: this.totalCount,
                layout: 'first, prev, pager, next, last',
                showCount: true,
                showLimits: true,
                onPageChange: (index, pageSize) => {
                    this.currentPage = index;
                    this.pageSize = pageSize;
                    this.loadBooks();
                }
            });
        }

        /**
         * 同步 WebDAV
         */
        syncWebDAV() {
            $.toaster.info('正在同步 WebDAV...');
            
            $.request.post('/admin/api/book/sync', {}, (res) => {
                if (res.code === 200) {
                    $.toaster.success(res.msg || '同步成功');
                    this.loadBooks();
                } else {
                    $.toaster.error(res.msg || '同步失败');
                }
            });
        }

        /**
         * 删除书籍
         */
        deleteBook(bookId) {
            if (!confirm('确定要删除这本书籍吗？')) {
                return;
            }

            $.request.post('/admin/api/book/delete', { id: bookId }, (res) => {
                if (res.code === 200) {
                    $.toaster.success(res.msg || '删除成功');
                    this.loadBooks();
                } else {
                    $.toaster.error(res.msg || '删除失败');
                }
            });
        }

        /**
         * 销毁
         */
        destroy() {
            // 解绑事件
            this.$searchInput.off();
            this.$filterType.off();
            this.$btnFilter.off();
            this.$btnReset.off();
            this.$btnAdd.off();
            this.$btnSync.off();
            this.$bookList.off();
        }
    }

    const bookPage = new BookPage();
    bookPage.init();

    window.pageOnUnLoad = function () {
        bookPage.destroy();
    };
};
