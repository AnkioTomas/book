/**
 * 书籍列表页面交互逻辑
 * @file book.js
 */

window.pageLoadFiles = [
    'Toaster',
    'DataTable',
    'Form',
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
            this.dataTable = null;
            this.filterOptions = {};
            this.editDialog = document.querySelector("#bookEditDialog")
        }

        init() {
            this.loadFilters();
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
                            return `<img src="/webdav/${encodeURI(row.filename)}" alt="${row.bookName}" class="book-cover-thumb">`;
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
                                that.dataTable.reload({}, false);
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
            const that = this;
            
            // 搜索表单提交
            $.form.submit("#searchForm", {
                callback: function (data) {
                    that.dataTable.reload(data, true);
                }
            });

            // 添加书籍
            $('#btnAdd').on('click', () => {
                $.file.upload({
                    accept: '.epub,.mobi,.azw,.azw3,.pdf,.txt',
                    uploadEndpoint: '/admin/api/upload',
                    onSuccess: (res) => {
                        $.request.postForm("/admin/api/publish", {name: res.data}, (res) => {
                            $.toaster.success(res.msg);
                            that.dataTable.reload({}, false);
                        });
                    },
                    onError: (msg) => $.toaster.error(msg || '上传失败')
                });
            });

            // 同步 WebDAV
            $('#btnSync').on('click', () => {
                $("body").showLoading("正在同步 WebDAV...")
                $.request.postForm('/admin/api/sync', {}, (res) => {
                    $("body").closeLoading();
                    if (res.code === 200) {
                        $.toaster.success(res.msg);
                        that.dataTable.reload({}, false);
                    } else {
                        $.toaster.error(res.msg);
                    }
                });
            });

            // 拖拽上传
            this.initDragUpload();

            // 编辑书籍提交
            this.editDialog.submit('/admin/api/book/update', () => {
                this.dataTable.reload({}, false);
            });

            // 豆瓣搜索
            $(this.editDialog).on("click", "#douban", () => {
                const bookName = $("#bookName").val().trim();
                if (!bookName) return $.toaster.error('请先输入书名');
                
                $(that.editDialog).showLoading();
                $.request.postForm("/admin/api/douban", {q: bookName}, (data) => {
                    $(that.editDialog).closeLoading();
                    data.code === 200 ? that.showBookSelector(data.data) : $.toaster.error(data.msg);
                });
            });
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
            const fillSelect = (name, items) => {
                const $select = $(`select[name="${name}"], mdui-select[name="${name}"]`);
                if (items && items.length > 0) {
                    items.forEach(item => {
                        $select.append(`<mdui-menu-item value="${item}">${item}</mdui-menu-item>`);
                    });
                }
            };

            fillSelect('series', this.filterOptions.groupNames);
            fillSelect('category', this.filterOptions.categories);
            fillSelect('favorite', this.filterOptions.favorites);
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
         */
        uploadFile(file) {
            $.file._uploadDirect(file, {
                uploadEndpoint: '/admin/api/upload',
                uploadData: {},
                onSuccess: (res) => {
                    $.request.postForm("/admin/api/publish", {name: res.data}, (response) => {
                        $.toaster.success(response.msg);
                        this.dataTable.reload({}, false);
                    });
                },
                onError: (msg) => $.toaster.error(msg || '上传失败')
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
            if (this.dataTable) this.dataTable.destroy();
            $('#searchForm, #btnAdd, #btnSync').off();
            $(this.editDialog).off();
            $(document).off('dragover drop dragenter dragleave');
        }
    }

    const bookPage = new BookPage();
    bookPage.init();

    window.pageOnUnLoad = function () {
        bookPage.destroy();
    };
};
