/**
 * 书籍列表页面交互逻辑
 * @file book.js
 */

window.pageLoadFiles = [
    'Toaster',
    'DataTable',
    "FileUploader",
    "DialogForm",
    'Layer'
];

window.pageOnLoad = function (loading) {

    /**
     * 书籍页面管理类
     */
    class BookPage {
        constructor() {
            // 搜索和筛选参数
            this.searchParams = {
                filterType: '',
                filterValue: ''
            };

            // 筛选选项缓存
            this.filterOptions = {
                groupNames: [],
                categories: [],
                favorites: []
            };

            // DataTable 实例
            this.dataTable = null;
        }

        /**
         * 初始化
         */
        init() {
            this.bindElements();
            this.loadFilters();
        }

        /**
         * 绑定DOM元素
         */
        bindElements() {
            this.$filterSeries = $('#filterSeries');
            this.$filterCategory = $('#filterCategory');
            this.$filterFavorite = $('#filterFavorite');
            this.$activeFilters = $('#activeFilters');
            this.$btnResetFilters = $('#btnResetFilters');
            this.$btnAdd = $('#btnAdd');
            this.$btnSync = $('#btnSync');
            this.editDialog = document.getElementById('bookEditDialog');
            this.$dragOverlay = $('#dragOverlay');
        }

        /**
         * 初始化 DataTable
         */
        initDataTable() {
            this.dataTable = new DataTable("#bookTable");
            this.dataTable.load({
                uri: "/admin/api/book/list",
                columns: [
                    {
                        field: "coverUrl",
                        name: "封面",
                        align: "center",
                        width: 80,
                        formatter: (value, row, index) => {
                            if (value) {
                                return `<img src="/proxy/${encodeURIComponent(value)}" alt="${row.bookName}" class="book-cover-thumb">`;
                            }
                            return `<div class="book-cover-placeholder">${row.bookName.charAt(0)}</div>`;
                        }
                    },
                    {
                        field: "bookName",
                        name: "书名",
                        align: "left",
                        width: 180,
                        formatter: (value, row, index) => {
                            return `<span class="book-title">${value}</span>`;
                        }
                    },
                    {
                        field: "author",
                        name: "作者",
                        align: "left",
                        width: 150,
                        formatter: (value, row, index) => {
                            return value || '未知作者';
                        }
                    },
                    {
                        field: "description",
                        name: "简介",
                        align: "left",
                        width: "auto",
                        formatter: (value, row, index) => {
                            if (!value) return '-';
                            return `<span class="book-description" title="${value}">${value}</span>`;
                        }
                    },
                    {
                        field: "rate",
                        name: "评分",
                        align: "center",
                        width: 120,
                        formatter: (value, row, index) => {
                            const rating = parseInt(value) || 0;
                            return rating > 0 ? '⭐'.repeat(rating) : '-';
                        }
                    },
                    {
                        field: "series",
                        name: "系列",
                        align: "left",
                        width: 150,
                        formatter: (value, row, index) => {
                            return value || '-';
                        }
                    },
                    {
                        field: "category",
                        name: "分类",
                        align: "center",
                        width: 120,
                        formatter: (value, row, index) => {
                            return value || '-';
                        }
                    },
                    {
                        field: "favorite",
                        name: "收藏",
                        align: "center",
                        fixed: "right",
                        width: 120,
                        formatter: (value, row, index) => {
                            return value || '-';
                        }
                    },

                    {
                        field: "_op",
                        name: "操作",
                        align: "center",
                        fixed: "right",
                        width: 120,
                        formatter: (value, row, index) => {
                            return `
                                <mdui-button-icon class="btn-edit" icon="edit" data-id="${row.id}" data-index="${index}" title="编辑"></mdui-button-icon>
                                <mdui-button-icon class="btn-delete" icon="delete" data-id="${row.id}" data-index="${index}"  title="删除"></mdui-button-icon>
                            `;
                        }
                    }
                ],
                mobile: true,
                lineHeight: "auto",
                height: "auto",
                events: {
                    onRowClick: (row, rowIndex) => {
                        // 可选：点击行编辑
                    },
                    onCellClick: (row, rowIndex, colIndex, colName) => {
                        // 单元格点击事件
                    },
                    onPaged: (page, pageSize) => {
                        // 分页切换事件
                    }
                },
                empty_msg: "暂无书籍数据",
                page: true,
                selectable: false
            });

            let that = this;
            // 绑定表格内的操作按钮事件
            $('#bookTable').on('click', '.btn-edit', function (e) {

                const index = $(this).data('index');

                let book = that.dataTable.getRow(index);
                that.editDialog.open();
                that.editDialog.setValue(book);
                // 延迟初始化自动完成（确保对话框已完全打开）
                setTimeout(() => {
                    that.initAutocomplete();
                }, 100);
            }).on('click', '.btn-delete', function (e) {
                const bookId = $(this).data('id');
                $.layer.confirm({
                    msg: '确定要删除这本书籍吗？',
                    yes: () => {
                        $.request.postForm('/admin/api/book/delete', {id: bookId}, (res) => {
                            if (res.code === 200) {
                                $.toaster.success(res.msg || '删除成功');
                                that.reloadTable();
                            } else {
                                $.toaster.error(res.msg || '删除失败');
                            }
                        });
                    },
                    no: () => {},
                    title: '删除'
                });
            });
        }

        /**
         * 绑定事件
         */
        bindEvents() {
            // 系列筛选
            this.$filterSeries.on('change', (e) => {
                const val = $(e.target).val();
                if (val) {
                    this.searchParams.filterType = 'groupName';
                    this.searchParams.filterValue = val;
                    this.$filterCategory.val('');
                    this.$filterFavorite.val('');
                } else {
                    this.searchParams.filterType = '';
                    this.searchParams.filterValue = '';
                }
                this.updateActiveFilters();
                this.reloadTable();
            });

            // 分类筛选
            this.$filterCategory.on('change', (e) => {
                const val = $(e.target).val();
                if (val) {
                    this.searchParams.filterType = 'category';
                    this.searchParams.filterValue = val;
                    this.$filterSeries.val('');
                    this.$filterFavorite.val('');
                } else {
                    this.searchParams.filterType = '';
                    this.searchParams.filterValue = '';
                }
                this.updateActiveFilters();
                this.reloadTable();
            });

            // 收藏夹筛选
            this.$filterFavorite.on('change', (e) => {
                const val = $(e.target).val();
                if (val) {
                    this.searchParams.filterType = 'favorite';
                    this.searchParams.filterValue = val;
                    this.$filterSeries.val('');
                    this.$filterCategory.val('');
                } else {
                    this.searchParams.filterType = '';
                    this.searchParams.filterValue = '';
                }
                this.updateActiveFilters();
                this.reloadTable();
            });

            // 重置所有筛选
            this.$btnResetFilters.on('click', () => {
                this.searchParams.filterType = '';
                this.searchParams.filterValue = '';
                this.$filterSeries.val('');
                this.$filterCategory.val('');
                this.$filterFavorite.val('');
                this.updateActiveFilters();
                this.reloadTable();
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
                            this.reloadTable();
                        });
                    },
                    onError: (msg) => {
                        $.toaster.error(msg || '上传失败');
                    }
                });
            });

            // 同步 WebDAV
            this.$btnSync.on('click', () => {
                this.syncWebDAV();
            });

            // 拖拽上传
            this.initDragUpload();

            // 设置提交回调
            this.editDialog.submit('/admin/api/book/update', (formData, response) => {
                this.reloadTable();
            });

            // 豆瓣搜索
            $(this.editDialog).on("click", "#douban", () => {
                let bookName = $("#bookName").val().trim();
                
                if (!bookName) {
                    $.toaster.error('请先输入书名');
                    return;
                }
                
                $(this.editDialog).showLoading();
                $.request.postForm("/admin/api/douban", {
                    q: bookName
                }, (data) => {
                    $(this.editDialog).closeLoading();
                    
                    if (data.code !== 200) {
                        $.toaster.error(data.msg);
                    } else {
                        this.showBookSelector(data.data);
                    }
                });
            });
        }
        
        /**
         * 重新加载表格
         */
        reloadTable() {
            if (this.dataTable) {
                this.dataTable.reload(this.searchParams, true);
            }
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
            
            // 字段映射：表单name -> 豆瓣数据字段
            $.form.val('#bookEditForm', {
                bookName: book.title,
                author: book.author,
                description: book.full_intro || book.intro,
                publisher: book.publisher,
                publishYear: book.year,
                isbn: book.isbn,
                pages: book.pages,
                price: book.price,
                coverUrl: book.cover_url,
                rate: Math.floor(( book.rating || 0) / 2)
            });
            
            $.toaster.success('已填充书籍信息');
        }


        /**
         * 加载筛选选项
         */
        loadFilters() {
            $.request.get('/admin/api/book/filters', {}, (res) => {
                if (res.code === 200) {
                    this.filterOptions = res.data;
                    this.populateFilterDropdowns();
                    // 筛选选项加载完成后初始化表格和事件
                    this.initDataTable();
                    this.bindEvents();
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

            // 当前激活的筛选
            if (this.searchParams.filterType && this.searchParams.filterValue) {
                let label = '';
                switch (this.searchParams.filterType) {
                    case 'groupName':
                        label = `系列: ${this.searchParams.filterValue}`;
                        break;
                    case 'category':
                        label = `分类: ${this.searchParams.filterValue}`;
                        break;
                    case 'favorite':
                        label = `收藏: ${this.searchParams.filterValue}`;
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
            if (type === 'filter') {
                this.searchParams.filterType = '';
                this.searchParams.filterValue = '';
                this.$filterSeries.val('');
                this.$filterCategory.val('');
                this.$filterFavorite.val('');
            }
            this.updateActiveFilters();
            this.reloadTable();
        }

        /**
         * 同步 WebDAV
         */
        syncWebDAV() {
            $.toaster.info('正在同步 WebDAV...');

            $.request.post('/admin/api/sync', {}, (res) => {
                if (res.code === 200) {
                    $.toaster.success(res.msg || '同步成功');
                    this.reloadTable();
                } else {
                    $.toaster.error(res.msg || '同步失败');
                }
            });
        }

        /**
         * 初始化拖拽上传
         */
        initDragUpload() {
            let dragCounter = 0;
            const allowedExtensions = ['.epub', '.mobi', '.azw', '.azw3', '.pdf', '.txt'];

            // 阻止默认拖拽行为 - dragover
            $(document).on('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
            });

            // 拖拽进入
            $(document).on('dragenter', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dragCounter++;
                if (dragCounter === 1) {
                    this.$dragOverlay.addClass('active');
                }
            });

            // 拖拽离开
            $(document).on('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                dragCounter--;
                if (dragCounter === 0) {
                    this.$dragOverlay.removeClass('active');
                }
            });

            // 放下文件
            $(document).on('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                dragCounter = 0;
                this.$dragOverlay.removeClass('active');

                // 获取原生事件对象
                const evt = e.originalEvent || e;
                const files = evt.dataTransfer?.files;
                
                if (!files || files.length === 0) return;

                // 过滤允许的文件类型
                const validFiles = Array.from(files).filter(file => {
                    const ext = '.' + file.name.split('.').pop().toLowerCase();
                    return allowedExtensions.includes(ext);
                });

                if (validFiles.length === 0) {
                    $.toaster.error('不支持的文件格式，仅支持: ' + allowedExtensions.join(', '));
                    return;
                }

                // 上传所有有效文件
                validFiles.forEach(file => this.uploadFile(file));
            });
        }

        /**
         * 上传单个文件
         * @param {File} file - 文件对象
         */
        uploadFile(file) {
            $.file._uploadDirect(file, {
                uploadEndpoint: '/admin/api/upload',
                uploadData: {},
                onSuccess: (res) => {
                    $.toaster.success('上传成功');
                    $.request.postForm("/admin/api/publish", {name: res.data}, (response) => {
                        $.toaster.success(response.msg);
                        this.reloadTable();
                    });
                },
                onError: (msg) => {
                    $.toaster.error(msg || '上传失败');
                }
            });
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
         * 销毁
         */
        destroy() {
            // 销毁 DataTable
            if (this.dataTable) {
                this.dataTable.destroy();
            }
            
            // 解绑事件
            this.$filterSeries.off();
            this.$filterCategory.off();
            this.$filterFavorite.off();
            this.$btnResetFilters.off();
            this.$btnAdd.off();
            this.$btnSync.off();
            this.$activeFilters.off();
            $(this.editDialog).off();
            
            // 清理拖拽事件
            $(document).off('dragover drop dragenter dragleave');
        }
    }

    const bookPage = new BookPage();
    bookPage.init();

    window.pageOnUnLoad = function () {
        bookPage.destroy();
    };
};
