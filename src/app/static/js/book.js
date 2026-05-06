/**
 * 书籍列表页面交互逻辑
 * @file book.js
 */

window.pageLoadFiles = [
    'Toaster',
    'CardView',
    'Form',
    "FileUploader",
    "DialogForm",
    'Layer',
    '/js/components/Book.js'
];

window.pageOnLoad = function (loading) {

    /**
     * 书籍页面管理类
     */
    class BookPage {
        constructor() {
            this.cardView = null;
            this.filterOptions = {};
            this.editDialog = document.querySelector("#bookEditDialog");
            this.batchDialog = document.querySelector("#batchEditDialog");
            this.$dragOverlay = $("#dragOverlay");
        }

        init() {
            this.loadFilters();
        }

        /**
         * 初始化 CardView
         */
        initCardView() {
            const that = this;
            const supportedReaderExt = ['.epub', '.mobi', '.azw', '.azw3', '.pdf'];
            const openReader = (row) => {
                const filename = row.filename || '';
                const ext = '.' + (filename.split('.').pop() || '').toLowerCase();
                if (!filename) {
                    $.toaster.warning('文件名缺失，无法打开阅读器');
                    return;
                }
                if (!supportedReaderExt.includes(ext)) {
                    $.toaster.warning('该文件格式暂不支持阅读器打开');
                    return;
                }
                const title = row.bookName || filename;
                const readerUrl = `/admin/reader?file=${encodeURIComponent(filename)}&title=${encodeURIComponent(title)}`;
                window.open(readerUrl, '_blank', 'noopener');
            };
            const renderBookBase = (row) => {
                const cover = row.coverUrl
                    ? '/proxy/'+encodeURIComponent(row.coverUrl)
                    : `/webdav/${encodeURIComponent(row.filename || "")}`;
                const title = row.bookName || "未命名书籍";
                const author = row.author || "未知作者";

                return `
                    <book-card
                        cover="${$.escapeHtml(cover)}"
                        title="${$.escapeHtml(title)}"
                        author="${$.escapeHtml(author)}"
                    ></book-card>
                `;
            };
            this.cardView = new CardView("#bookTable");
            this.cardView.load({
                uri: "/admin/api/book/list",
                cardWidth: "180px",
                template: `
                    <div class="book-card-shell d-flex flex-col h-full p-2">
                        {{bookCard}}
                        <div class="book-actions d-flex items-center gap-1">{{actions}}</div>
                    </div>
                `,
                columns: [
                    {
                        field: "bookCard",
                        formatter: (value, row) => {
                            return renderBookBase(row);
                        }
                    },
                    {
                        field: "actions",
                        formatter: (value, row, index) => {
                            return `
                                <mdui-button-icon class="btn-edit" icon="edit" data-id="${row.id}" data-index="${index}"></mdui-button-icon>
                                <mdui-button-icon class="btn-delete" icon="delete" data-id="${row.id}" data-index="${index}"></mdui-button-icon>
                            `;
                        }
                    }
                ],
                events: {
                    onCardClick: (row) => {
                        openReader(row);
                    }
                },
                empty_msg: "暂无书籍数据",
                page: true,
                pageSizes: [24, 48, 96],
                selectable: true
            });

            // 绑定操作按钮事件
            $('#bookTable').on('click', '.btn-edit', function (e) {
                e.stopPropagation();
                const index = $(this).data('index');
                const book = that.cardView.getRow(index);
                that.editDialog.open();
                that.editDialog.setValue(book);
            }).on('click', '.btn-delete', function (e) {
                e.stopPropagation();
                const bookId = $(this).data('id');
                $.layer.confirm({
                    msg: '确定要删除这本书籍吗？',
                    yes: () => {
                        $.request.postForm('/admin/api/book/delete', {id: bookId}, (res) => {
                            if (res.code === 200) {
                                $.toaster.success(res.msg || '删除成功');
                                that.cardView.reload($.form.val("#searchForm"), true);
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
            
            const triggerSearch = () => {
                that.cardView.reload($.form.val("#searchForm"), true);
            };

            const debouncedSearch = this.debounce(triggerSearch, 300);
            const $searchForm = $('#searchForm');

            // 回车提交保留兼容，同时阻止默认跳转
            $searchForm.on('submit', (e) => {
                e.preventDefault();
                triggerSearch();
            });

            // 输入和筛选变更时自动触发搜索（防抖）
            $searchForm.on('input change', 'mdui-text-field, mdui-select, input, select', () => {
                debouncedSearch();
            });

            // 表单重置后同步刷新结果
            $searchForm.on('reset', () => {
                setTimeout(triggerSearch, 0);
            });

            // 添加书籍（支持多文件上传）
            $('#btnAdd').on('click', () => {
                const input = document.createElement('input');
                input.type = 'file';
                input.multiple = true;
                input.accept = '.epub,.mobi,.azw,.azw3,.pdf,.txt';
                
                input.onchange = (e) => {
                    const files = e.target.files;
                    if (files && files.length > 0) {
                        that.uploadBatch(Array.from(files));
                    }
                };
                
                input.click();
            });


            function sync(){
                $.request.postForm('/admin/api/sync', {}, (res) => {
                    if (res.code === 200) {
                        $.toaster.success(res.msg);
                        that.cardView.reload($.form.val("#searchForm"), true);
                    } else if(res.code === 201){
                        $.toaster.success(res.msg);
                        setTimeout(sync,2000);
                    } else {
                        $.toaster.error(res.msg);
                    }
                });
            }

            // 同步 WebDAV
            $('#btnSync').on('click', () => {
                sync();
            });

            // 删除重复书籍
            $('#btnRemoveDuplicates').on('click', () => {
                $.layer.confirm({
                    msg: '确定要删除重复书籍吗？<br><small class="text-on-surface-variant">将保留相同书名+作者中最后导入的版本</small>',
                    yes: () => {
                        $("body").showLoading("正在查找并删除重复书籍...");
                        $.request.postForm('/admin/api/book/removeDuplicates', {}, (res) => {
                            $("body").closeLoading();
                            if (res.code === 200) {
                                $.toaster.success(res.msg);
                                that.cardView.reload($.form.val("#searchForm"), true);
                            } else {
                                $.toaster.error(res.msg);
                            }
                        });
                    },
                    no: () => {},
                    title: '删除重复书籍'
                });
            });

            // 批量编辑按钮
            $('#btnBatchEdit').on('click', () => {
                const selected = that.cardView.getSelectedRows();
                if (!selected || selected.length === 0) {
                    $.toaster.warning('请先选择要操作的书籍');
                    return;
                }
                that.batchDialog.open();
            });

            // 批量删除按钮
            $('#btnBatchDelete').on('click', () => {
                const selected = that.cardView.getSelectedRows();
                if (!selected || selected.length === 0) {
                    $.toaster.warning('请先选择要删除的书籍');
                    return;
                }
                
                $.layer.confirm({
                    msg: `确定要删除选中的 ${selected.length} 本书籍吗？<br><small class="text-on-surface-variant">此操作不可恢复</small>`,
                    yes: () => that.batchDelete(selected),
                    no: () => {},
                    title: '批量删除'
                });
            });

            // 批量刮削封面
            $('#btnBatchScrape').on('click', () => {
                const selected = that.cardView.getSelectedRows();
                if (!selected || selected.length === 0) {
                    $.toaster.warning('请先选择要刮削的书籍');
                    return;
                }
                that.batchScrape(selected);
            });

            // 拖拽上传
            this.initDragUpload();

            // 编辑书籍提交
            this.editDialog.submit('/admin/api/book/update', () => {
                that.cardView.reload($.form.val("#searchForm"), true);
            });

            // 批量编辑提交 - 传入 null 作为 URL，直接处理数据
            this.batchDialog.submit(null, (formData) => {
                const selected = that.cardView.getSelectedRows();
                if (!selected || selected.length === 0) {
                    $.toaster.warn('没有选中的书籍');
                    return;
                }

                // 获取批量设置的值（只更新用户填写的字段）
                const batchData = {};
                if (formData.author) batchData.author = formData.author;
                if (formData.category) batchData.category = formData.category;
                if (formData.favorite) batchData.favorite = formData.favorite;
                if (formData.series) batchData.series = formData.series;

                if (Object.keys(batchData).length === 0) {
                    $.toaster.warn('请至少填写一个要批量设置的字段');
                    return;
                }

                // 关闭对话框，开始批量更新
                that.batchDialog.close();
                $("body").showLoading(`正在批量更新 ${selected.length} 本书籍...`);
                
                let successCount = 0;
                let failCount = 0;
                const total = selected.length;

                const updateNext = (index) => {
                    if (index >= total) {
                        $("body").closeLoading();
                        if (failCount === 0) {
                            $.toaster.success(`批量更新完成：${successCount} 本成功`);
                        } else {
                            $.toaster.warn(`批量更新完成：${successCount} 本成功，${failCount} 本失败`);
                        }
                        that.cardView.reload($.form.val("#searchForm"), true);
                        return;
                    }

                    const book = selected[index];
                    const updateData = { ...book, ...batchData };

                    $.request.postForm('/admin/api/book/update', updateData, (res) => {
                        if (res.code === 200) {
                            successCount++;
                        } else {
                            failCount++;
                        }
                        updateNext(index + 1);
                    }, () => {
                        failCount++;
                        updateNext(index + 1);
                    });
                };

                updateNext(0);
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
                            <image-loader src="/proxy/${cover}" 
                                 style="width: 60px; height: 80px; border-radius: 4px; flex-shrink: 0;" 
                                ></image-loader>
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
                    // 筛选选项加载完成后初始化卡片视图和事件
                    this.initCardView();
                    this.bindEvents();
                    // 初始化自动完成（只执行一次）
                    this.initAutocomplete();
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

                // 批量上传
                this.uploadBatch(validFiles);
            });
        }

        /**
         * 批量删除书籍
         */
        batchDelete(books) {
            const that = this;
            const total = books.length;
            let successCount = 0;
            let failCount = 0;

            $("body").showLoading(`正在删除 ${total} 本书籍...`);

            const deleteNext = (index) => {
                if (index >= total) {
                    $("body").closeLoading();
                    $.toaster[failCount === 0 ? 'success' : 'warn'](`删除完成：${successCount} 成功，${failCount} 失败`);
                    that.cardView.reload($.form.val("#searchForm"), true);
                    return;
                }

                $.request.postForm('/admin/api/book/delete', { id: books[index].id }, (res) => {
                    res.code === 200 ? successCount++ : failCount++;
                    deleteNext(index + 1);
                }, () => {
                    failCount++;
                    deleteNext(index + 1);
                });
            };

            deleteNext(0);
        }

        /**
         * 批量刮削封面
         */
        batchScrape(books) {
            const that = this;
            const total = books.length;
            let successCount = 0;
            let failCount = 0;

            $("body").showLoading(`正在刮削封面 (0/${total})...`);

            const scrapeNext = (index) => {
                if (index >= total) {
                    $("body").closeLoading();
                    $.toaster[failCount === 0 ? 'success' : 'warn'](`刮削完成：${successCount} 成功，${failCount} 失败`);
                    that.cardView.reload($.form.val("#searchForm"), true);
                    return;
                }

                $("body").showLoading(`正在刮削封面 (${index + 1}/${total})...`);

                $.request.postForm('/admin/api/book/scrapeCover', { id: books[index].id }, (res) => {
                    res.code === 200 ? successCount++ : failCount++;
                    scrapeNext(index + 1);
                }, () => {
                    failCount++;
                    scrapeNext(index + 1);
                });
            };

            scrapeNext(0);
        }

        /**
         * 批量上传文件
         */
        uploadBatch(files) {
            const that = this;
            const total = files.length;
            let count = 0;
            
            files.forEach(file => {
                this.uploadFile(file, () => {
                    if (++count === total) {
                        $.toaster.success(`${total} 个文件上传完成`);
                        that.cardView.reload($.form.val("#searchForm"), true);
                    }
                });
            });
        }

        /**
         * 上传单个文件
         */
        uploadFile(file, onDone) {
            const config = {
                uploadEndpoint: '/admin/api/upload',
                uploadData: {},
                chunked: true,
                chunkSize: 1024 * 1024 * 2,
                maxDirectSize: 10 * 1024 * 1024,
                onSuccess: (res) => {
                    $.request.postForm("/admin/api/publish", {
                        name: res.data,
                        series: $("mdui-select[name=series]").val()
                    }, () => onDone && onDone());
                },
                onError: (msg) => {
                    $.toaster.error(`${file.name}: ${msg || '上传失败'}`);
                    onDone && onDone();
                }
            };

            const shouldUseChunked = config.chunked || file.size > config.maxDirectSize;
            shouldUseChunked ? $.file._uploadWithChunks(file, config) : $.file._uploadDirect(file, config);
        }

        /**
         * 初始化自动完成功能（只执行一次）
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
                
                // 防止重复初始化
                if (input.dataset.autocompleteInit === 'true') return;
                input.dataset.autocompleteInit = 'true';

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

                // 输入时实时过滤（但不自动打开下拉框）
                input.addEventListener('input', () => {
                    const val = input.value || '';
                    updateList(val);
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

        debounce(fn, delay = 300) {
            let timer = null;
            return (...args) => {
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        /**
         * 销毁
         */
        destroy() {
            if (this.cardView) this.cardView.destroy();
            $('#searchForm, #btnAdd, #btnSync, #btnRemoveDuplicates, #btnBatchDelete').off();
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
