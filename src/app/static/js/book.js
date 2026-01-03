/**
 * 书籍列表页面交互逻辑
 * @file book.js
 */

window.pageLoadFiles = [
    'Toaster',
    'Pagination',
    "FileUploader",
    "DialogForm",
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

            this.books = [];
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
            this.$filterSeries = $('#filterSeries');
            this.$filterCategory = $('#filterCategory');
            this.$filterFavorite = $('#filterFavorite');
            this.$activeFilters = $('#activeFilters');
            this.$btnResetFilters = $('#btnResetFilters');
            this.$btnAdd = $('#btnAdd');
            this.$btnSync = $('#btnSync');
            this.editDialog = document.getElementById('bookEditDialog');
        }

        /**
         * 绑定事件（使用 jQuery）
         */
        bindEvents() {
            // 搜索（实时搜索，带防抖）
            let searchTimer = null;
            this.$searchInput.on('input', (e) => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    this.searchKeyword = $(e.target).val().trim();
                    this.currentPage = 1;
                    this.loadBooks();
                }, 300); // 300ms 防抖
            });

            // 系列筛选（即时生效，清除其他筛选）
            this.$filterSeries.on('change', (e) => {
                const val = $(e.target).val();
                if (val) {
                    this.filterType = 'groupName';
                    this.filterValue = val;
                    this.$filterCategory.val('');
                    this.$filterFavorite.val('');
                } else {
                    this.filterType = '';
                    this.filterValue = '';
                }
                this.currentPage = 1;
                this.updateActiveFilters();
                this.loadBooks();
            });

            // 分类筛选（即时生效，清除其他筛选）
            this.$filterCategory.on('change', (e) => {
                const val = $(e.target).val();
                if (val) {
                    this.filterType = 'category';
                    this.filterValue = val;
                    this.$filterSeries.val('');
                    this.$filterFavorite.val('');
                } else {
                    this.filterType = '';
                    this.filterValue = '';
                }
                this.currentPage = 1;
                this.updateActiveFilters();
                this.loadBooks();
            });

            // 收藏夹筛选（即时生效，清除其他筛选）
            this.$filterFavorite.on('change', (e) => {
                const val = $(e.target).val();
                if (val) {
                    this.filterType = 'favorite';
                    this.filterValue = val;
                    this.$filterSeries.val('');
                    this.$filterCategory.val('');
                } else {
                    this.filterType = '';
                    this.filterValue = '';
                }
                this.currentPage = 1;
                this.updateActiveFilters();
                this.loadBooks();
            });

            // 重置所有筛选
            this.$btnResetFilters.on('click', () => {
                this.searchKeyword = '';
                this.filterType = '';
                this.filterValue = '';
                this.$searchInput.val('');
                this.$filterSeries.val('');
                this.$filterCategory.val('');
                this.$filterFavorite.val('');
                this.currentPage = 1;
                this.updateActiveFilters();
                this.loadBooks();
            });

            // 添加书籍（文件上传）
            this.$btnAdd.on('click', () => {
                $.file.upload({
                    accept: '.epub,.mobi,.azw,.azw3,.pdf,.txt',
                    uploadEndpoint: '/admin/api/upload',
                    onSuccess: (res) => {
                        $.toaster.success('上传成功');
                        $.request.postForm("/admin/api/publish", {name: res.data}, (res) => {
                            $.toaster.success(res.msg);
                            this.loadBooks();
                        });
                    },
                    onError: (msg) => {
                        $.toaster.error(msg || '上传失败');
                    }
                });
            });
            let that = this;

            // 编辑书籍（事件委托）
            this.$bookList.on('click', '.btn-edit', function (e) {
                e.stopPropagation();
                const index = parseInt($(this).data('index'));
                const book = that.books[index];
                that.editBook(book);
            });

            // 下载书籍（事件委托）
            this.$bookList.on('click', '.btn-download', (e) => {
                e.stopPropagation();
                const bookId = parseInt($(e.currentTarget).data('book-id'));
                const downloadUrl = $(e.currentTarget).data('url');
                this.downloadBook(bookId, downloadUrl);
            });

            // 删除书籍（事件委托）
            this.$bookList.on('click', '.btn-delete', (e) => {
                e.stopPropagation();
                const bookId = parseInt($(e.currentTarget).data('book-id'));
                this.deleteBook(bookId);
            });

            // 封面点击（打开阅读器）
            this.$bookList.on('click', '.book-cover', (e) => {
                e.stopPropagation();
                const bookId = parseInt($(e.currentTarget).data('book-id'));
                this.openReader(bookId);
            });

            // 同步 WebDAV
            this.$btnSync.on('click', () => {
                this.syncWebDAV();
            });

            // 设置提交回调
            this.editDialog.submit('/admin/api/book/update', (formData, response) => {
                this.loadBooks();
            });

            $(this.editDialog).on("click", "#douban", function () {
                let bookName = $("#bookName").val().trim();
                
                if (!bookName) {
                    $.toaster.error('请先输入书名');
                    return;
                }
                
                $(that.editDialog).showLoading();
                $.request.postForm("/admin/api/douban", {
                    q: bookName
                }, function (data) {
                    $(that.editDialog).closeLoading();
                    
                    if (data.code !== 200) {
                        $.toaster.error(data.msg);
                    } else {
                        that.showBookSelector(data.data);
                    }
                });
            });

            // 初始化分页组件
            this.initPagination();
        }
        
        /**
         * 显示豆瓣书籍选择对话框
         * @param {Array} books - 书籍数组
         */
        showBookSelector(books) {
            if (!books || books.length === 0) {
                $.toaster.warning('未找到匹配的书籍');
                return;
            }
            
            const dialog = document.querySelector('#searchDialog');
            
            // 生成书籍列表 HTML
            const booksHTML = books.map((book, index) => {
                const cover =  encodeURIComponent(book.cover_url);
                const title = book.title || '未知书名';
                const author = book.author || '未知作者';
                const publisher = book.publisher || '';
                const year = book.year || '';
                const rating = book.rating ? `⭐ ${book.rating}` : '';
                const intro = book.intro ? book.intro.substring(0, 100) + '...' : '';
                
                return `
                    <mdui-list-item data-index="${index}" style="cursor: pointer; --mdui-comp-list-item-one-line-height: auto; padding: 12px 16px;">
                        <div class="d-flex gap-3" style="width: 100%;">
                            <img src="/proxy/${cover}" 
                                 style="width: 60px; height: 80px; object-fit: cover; border-radius: 4px; flex-shrink: 0;" 
                                >
                            <div class="flex-1" style="min-width: 0;">
                                <div class="font-semibold mb-1" style="font-size: 1rem;">${title}</div>
                                <div class="text-on-surface-variant mb-1" style="font-size: 0.875rem;">
                                    ${author}
                                    ${publisher ? ` / ${publisher}` : ''}
                                    ${year ? ` / ${year}` : ''}
                                </div>
                                ${rating ? `<div class="mb-1" style="font-size: 0.875rem;">${rating}</div>` : ''}
                                ${intro ? `<div class="text-on-surface-variant" style="font-size: 0.8125rem; line-height: 1.4;">${intro}</div>` : ''}
                            </div>
                        </div>
                    </mdui-list-item>
                    <mdui-divider></mdui-divider>
                `;
            }).join('');
            
            // 填充内容
            dialog.innerHTML = `
                <mdui-top-app-bar slot="header">
                    <mdui-top-app-bar-title>选择书籍 (共 ${books.length} 条结果)</mdui-top-app-bar-title>
                    <mdui-button-icon icon="close" slot="end-icon" onclick="this.closest('mdui-dialog').open = false"></mdui-button-icon>
                </mdui-top-app-bar>
                <div style="max-height: 60vh; overflow-y: auto;">
                    <mdui-list>
                        ${booksHTML}
                    </mdui-list>
                </div>
            `;
            
            // 绑定选择事件
            dialog.querySelectorAll('mdui-list-item[data-index]').forEach(item => {
                item.addEventListener('click', () => {
                    const index = parseInt(item.getAttribute('data-index'));
                    this.fillBookData(books[index]);
                    dialog.open = false;
                });
            });
            
            dialog.open = true;
        }
        
        /**
         * 填充书籍数据到表单
         * @param {Object} book - 书籍数据对象
         */
        fillBookData(book) {
            const form = document.querySelector('#bookEditForm');
            if (!form) return;
            
            // 填充基本信息
            const setFieldValue = (name, value) => {
                const field = form.querySelector(`[name="${name}"]`);
                if (field && value) {
                    field.value = value;
                }
            };
            
            setFieldValue('bookName', book.title);
            setFieldValue('author', book.author);
            setFieldValue('description', book.full_intro || book.intro);
            setFieldValue('publisher', book.publisher);
            setFieldValue('publishYear', book.year);
            setFieldValue('isbn', book.isbn);
            setFieldValue('pages', book.pages);
            setFieldValue('price', book.price);
            
            // 填充标签
            if (book.tags && book.tags.length > 0) {
                setFieldValue('tags', book.tags.join(', '));
            }
            
            // 填充封面 URL
            if (book.cover_url) {
                setFieldValue('coverUrl', book.cover_url);
            }
            
            // 显示成功提示
            $.toaster.success('已填充书籍信息');
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
                    this.populateFilterDropdowns();
                }
            });
        }

        /**
         * 填充筛选下拉框
         */
        populateFilterDropdowns() {
            // 填充系列下拉
            if (this.filterOptions.groupNames && this.filterOptions.groupNames.length > 0) {
                this.filterOptions.groupNames.forEach(name => {
                    this.$filterSeries.append(`<mdui-menu-item value="${name}">${name}</mdui-menu-item>`);
                });
            }

            // 填充分类下拉
            if (this.filterOptions.categories && this.filterOptions.categories.length > 0) {
                this.filterOptions.categories.forEach(cat => {
                    this.$filterCategory.append(`<mdui-menu-item value="${cat}">${cat}</mdui-menu-item>`);
                });
            }

            // 填充收藏夹下拉
            if (this.filterOptions.favorites && this.filterOptions.favorites.length > 0) {
                this.filterOptions.favorites.forEach(fav => {
                    this.$filterFavorite.append(`<mdui-menu-item value="${fav}">${fav}</mdui-menu-item>`);
                });
            }
        }

        /**
         * 更新当前激活的筛选条件显示
         */
        updateActiveFilters() {
            const filters = [];

            // 搜索关键词（始终显示）
            if (this.searchKeyword) {
                filters.push({
                    label: `搜索: ${this.searchKeyword}`,
                    type: 'search',
                    icon: 'search'
                });
            }

            // 当前激活的筛选（只会有一个）
            if (this.filterType && this.filterValue) {
                let label = '';
                switch (this.filterType) {
                    case 'series':
                        label = `系列: ${this.filterValue}`;
                        break;
                    case 'category':
                        label = `分类: ${this.filterValue}`;
                        break;
                    case 'favorite':
                        label = `收藏: ${this.filterValue}`;
                        break;
                }
                if (label) {
                    filters.push({
                        label: label,
                        type: 'filter',
                        icon: 'filter_alt'
                    });
                }
            }

            // 渲染筛选标签
            if (filters.length === 0) {
                this.$activeFilters.html('');
                return;
            }

            const chipsHtml = filters.map(f =>
                `<mdui-chip deletable data-filter-type="${f.type}" icon="${f.icon || ''}">${f.label}</mdui-chip>`
            ).join('');

            this.$activeFilters.html(chipsHtml);

            // 绑定删除事件
            this.$activeFilters.find('mdui-chip').on('delete', (e) => {
                const type = $(e.target).data('filter-type');
                this.removeFilter(type);
            });
        }

        /**
         * 移除指定筛选条件
         */
        removeFilter(type) {
            if (type === 'search') {
                this.searchKeyword = '';
                this.$searchInput.val('');
            } else if (type === 'filter') {
                // 清除所有筛选下拉框
                this.filterType = '';
                this.filterValue = '';
                this.$filterSeries.val('');
                this.$filterCategory.val('');
                this.$filterFavorite.val('');
            }

            this.currentPage = 1;
            this.updateActiveFilters();
            this.loadBooks();
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
                    this.books = res.data.list;
                    this.renderBooks();
                    this.updatePagination();
                } else {
                    $.toaster.error(res.msg || '加载失败');
                }
            });
        }

        /**
         * 渲染书籍列表
         */
        renderBooks() {
            if (this.books.length === 0) {
                this.$bookList.html(`
                    <div class="col-xs12 text-center py-5 text-on-surface-variant">
                        <mdui-icon name="collections_bookmark" style="font-size: 64px; opacity: 0.3;"></mdui-icon>
                        <p class="mt-3 body-large">暂无书籍</p>
                    </div>
                `);
                return;
            }

            this.$bookList.html(this.books.map((book, index) => this.renderBookCard(book, index)).join(''));
        }

        /**
         * 渲染单个书籍卡片
         */
        renderBookCard(book, index) {
            const coverHtml = book.coverUrl
                ? `<img src="${book.coverUrl}" alt="${book.bookName}">`
                : `<span>${book.bookName.charAt(0)}</span>`;

            const description = book.description || '暂无简介';

            return `
                <div class="col-xs12 col-sm6 col-md4 col-lg3">
                    <mdui-card class="book-card h-full w-100" style="max-width: 12rem;" data-book-id="${book.id}">
                        <div class="book-cover cursor-pointer">${coverHtml}</div>
                        <div class="p-3">
                            <div class="title-medium text-ellipsis mb-1" title="${book.bookName}">${book.bookName}</div>
                            <div class="body-small text-on-surface-variant mb-2">${book.author || '未知作者'}</div>
                            <div class="body-small text-on-surface-variant line-clamp-2" style="min-height: 2.5rem;">${description}</div>
                        </div>
                        <div class="d-flex justify-end gap-1 px-3 pb-3">
                            <mdui-button-icon class="btn-edit" icon="edit" data-book-id="${book.id}" data-index="${index}" title="编辑"></mdui-button-icon>
                            <mdui-button-icon class="btn-download" icon="download" data-book-id="${book.id}" data-index="${index}"  data-url="${book.downloadUrl || ''}" title="下载"></mdui-button-icon>
                            <mdui-button-icon class="btn-delete" icon="delete" data-book-id="${book.id}" data-index="${index}"  title="删除"></mdui-button-icon>
                        </div>
                    </mdui-card>
                </div>
            `;
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

            $.request.post('/admin/api/sync', {}, (res) => {
                if (res.code === 200) {
                    $.toaster.success(res.msg || '同步成功');
                    this.loadBooks();
                } else {
                    $.toaster.error(res.msg || '同步失败');
                }
            });
        }

        /**
         * 编辑书籍
         */
        editBook(book) {

            // 打开对话框
            this.editDialog.open();
            this.editDialog.setValue(book);
            // 延迟初始化自动完成（确保对话框已完全打开）
            setTimeout(() => {
                this.initAutocomplete();
            }, 100);
        }

        /**
         * 初始化自动完成功能
         */
        initAutocomplete() {
            const fields = [
                {
                    inputId: 'editCategory',
                    dropdownId: 'categoryDropdown',
                    listId: 'categoryList',
                    options: this.filterOptions.categories || []
                },
                {
                    inputId: 'editFavorite',
                    dropdownId: 'favoriteDropdown',
                    listId: 'favoriteList',
                    options: this.filterOptions.favorites || []
                },
                {
                    inputId: 'editSeries',
                    dropdownId: 'seriesDropdown',
                    listId: 'seriesList',
                    options: this.filterOptions.groupNames || []
                }
            ];

            fields.forEach(field => {
                const input = document.getElementById(field.inputId);
                const dropdown = document.getElementById(field.dropdownId);
                const list = document.getElementById(field.listId);

                if (!input || !dropdown || !list) return;

                // 更新列表内容
                const updateList = (filterText = '') => {
                    const filtered = field.options.filter(opt =>
                        opt.toLowerCase().includes(filterText.toLowerCase())
                    );

                    list.innerHTML = '';

                    if (filtered.length === 0) {
                        const item = document.createElement('mdui-list-item');
                        item.textContent = '无匹配选项';
                        item.disabled = true;
                        list.appendChild(item);
                        return;
                    }

                    filtered.forEach(opt => {
                        const item = document.createElement('mdui-list-item');
                        item.textContent = opt;
                        item.addEventListener('click', () => {
                            input.value = opt;
                            dropdown.open = false;
                        });
                        list.appendChild(item);
                    });
                };

                // 初始填充
                updateList();

                // 点击输入框显示下拉
                input.addEventListener('click', () => {
                    if (field.options.length > 0) {
                        updateList(input.value || '');
                        dropdown.open = true;
                    }
                });

                // 输入时实时过滤
                input.addEventListener('input', () => {
                    const val = input.value || '';
                    updateList(val);
                    if (field.options.length > 0) {
                        dropdown.open = true;
                    }
                });

                // 点击下拉图标打开/关闭
                const endIcon = input.shadowRoot?.querySelector('[part="end-icon"]');
                if (endIcon) {
                    endIcon.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (field.options.length > 0) {
                            updateList(input.value || '');
                            dropdown.open = !dropdown.open;
                        }
                    });
                }
            });
        }

        /**
         * 下载书籍
         */
        downloadBook(bookId, downloadUrl) {
            if (!downloadUrl) {
                $.toaster.warning('该书籍暂无下载地址');
                return;
            }

            // 创建隐藏的 a 标签进行下载
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.download = '';
            link.target = '_blank';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            $.toaster.success('开始下载...');
        }

        /**
         * 打开阅读器
         */
        openReader(bookId) {
            // 在新窗口打开阅读器
            window.open(`/admin/reader?id=${bookId}`, '_blank');
        }

        /**
         * 删除书籍
         */
        deleteBook(bookId) {
            if (!confirm('确定要删除这本书籍吗？')) {
                return;
            }

            $.request.post('/admin/api/book/delete', {id: bookId}, (res) => {
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
            this.$filterSeries.off();
            this.$filterCategory.off();
            this.$filterFavorite.off();
            this.$btnResetFilters.off();
            this.$btnAdd.off();
            this.$btnSync.off();
            this.$bookList.off();
            this.$activeFilters.off();
        }
    }

    const bookPage = new BookPage();
    bookPage.init();

    window.pageOnUnLoad = function () {
        bookPage.destroy();
    };
};
