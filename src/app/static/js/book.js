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
    '/js/components/Book.js',
    '/js/components/DoubanBookPicker.js',
    '/js/components/BookContextMenu.js',
    '/js/components/DragUpload.js'
];

window.pageOnLoad = function () {

    var BOOK_EXTENSIONS = ['.epub', '.mobi', '.azw', '.azw3', '.pdf', '.txt'];

    var cardView = null;
    var filterOptions = {};
    var editDialog = $('#bookEditDialog')[0];
    var batchDialog = $('#batchEditDialog')[0];
    var $bookContextMenu = $('#bookContextMenu');
    var $doubanPicker = $('#doubanBookPicker');
    var $dragUpload = $('drag-upload');
    var syncTimer = null;
    var isDestroyed = false;
    var onContextAction = null;
    var aiSource = null;
    var aiLoadingEl = null;

    // 用 loading.js 在指定容器上显示/更新/关闭遮罩
    function aiLoadingShow(el, text) {
        aiLoadingEl = el;
        $(el).showLoading(text || 'AI 工作中…');
    }

    function aiLoadingUpdate(text) {
        if (aiLoadingEl) $(aiLoadingEl).updateLoading(text || 'AI 工作中…');
    }

    function aiStop() {
        if (aiSource) { aiSource.close(); aiSource = null; }
        if (aiLoadingEl) { $(aiLoadingEl).closeLoading(); aiLoadingEl = null; }
    }

    // AI 识别：提交后台任务，AI 自动检索并直接写库，进度在任务面板查看
    function aiIdentifyRun(books) {
        if (!books || !books.length) return;
        var ids = books.map(function (b) { return b.id; });

        $.request.get('/index/book/aiIdentify', { ids: JSON.stringify(ids) }, function (res) {
            if (res.code === 200) {
                $.toaster.success(res.msg || '已提交后台 AI 识别任务');
            } else {
                $.toaster.error(res.msg || 'AI 识别提交失败');
            }
        }, function () {
            $.toaster.error('AI 识别提交失败');
        });
    }

    function reload() {
        if (isDestroyed) return;
        cardView.reload($.url.getAllParams(), true);
    }

    function requireSelected(warnMsg) {
        var selected = cardView.getSelectedRows();
        if (!selected || !selected.length) {
            $.toaster.warning(warnMsg);
            return null;
        }
        return selected;
    }

    function confirm(msg, title, onYes) {
        $.layer.confirm({ msg: msg, title: title, yes: onYes, no: function () {} });
    }

    function filterBookFiles(files) {
        return DragUpload.filterFiles(files, BOOK_EXTENSIONS);
    }

    function openReader(row) {
        var filename = row.filename || '';
        var ext = '.' + (filename.split('.').pop() || '').toLowerCase();
        if (!filename) return $.toaster.warning('文件名缺失，无法打开阅读器');
        if (ext === '.txt' || !BOOK_EXTENSIONS.includes(ext)) {
            return $.toaster.warning('该文件格式暂不支持阅读器打开');
        }
        window.open(
            '/index/book/reader?file=' + encodeURIComponent(filename) + '&title=' + encodeURIComponent(row.bookName || filename),
            '_blank',
            'noopener'
        );
    }

    function runSequential(books, options) {
        var index = 0, ok = 0, fail = 0, total = books.length;

        function next() {
            if (index >= total) {
                $("body").closeLoading();
                options.onDone(ok, fail);
                cardView.reload(true);
                return;
            }
            if (options.onProgress) options.onProgress(index + 1, total);
            options.request(books[index], function (success) {
                success ? ok++ : fail++;
                index++;
                next();
            });
        }

        $("body").showLoading(options.loadingText);
        next();
    }

    function sequentialPost(books, opts) {
        runSequential(books, {
            loadingText: opts.loadingText,
            onProgress: opts.onProgress,
            request: function (book, done) {
                $.request.postForm(opts.url, opts.data(book), function (res) {
                    done(res.code === 200);
                }, function () { done(false); });
            },
            onDone: function (ok, fail) {
                $.toaster[fail ? 'warn' : 'success'](opts.doneText(ok, fail));
            }
        });
    }

    function batchDelete(books) {
        sequentialPost(books, {
            url: '/index/book/delete',
            data: function (book) { return { id: book.id }; },
            loadingText: '正在删除 ' + books.length + ' 本书籍...',
            doneText: function (ok, fail) { return '删除完成：' + ok + ' 成功，' + fail + ' 失败'; }
        });
    }

    function batchScrape(books) {
        sequentialPost(books, {
            url: '/index/book/scrapeCover',
            data: function (book) { return { id: book.id }; },
            loadingText: '正在刮削封面 (0/' + books.length + ')...',
            onProgress: function (current, total) {
                $("body").showLoading('正在刮削封面 (' + current + '/' + total + ')...');
            },
            doneText: function (ok, fail) { return '刮削完成：' + ok + ' 成功，' + fail + ' 失败'; }
        });
    }

    function batchReadState(books, read) {
        $("body").showLoading(read ? '正在标记已读…' : '正在标记未读…');
        $.request.postForm(
            '/index/book/batchRead',
            { ids: JSON.stringify(books.map(function (b) { return b.id; })), read: read ? '1' : '0' },
            function (res) {
                $("body").closeLoading();
                if (res.code === 200) {
                    $.toaster.success(res.msg);
                    cardView.reload(true);
                } else {
                    $.toaster.error(res.msg || '操作失败');
                }
            },
            function () {
                $("body").closeLoading();
                $.toaster.error('请求失败');
            }
        );
    }

    function uploadBatch(files) {
        var total = files.length, count = 0;

        function onOneDone() {
            if (++count === total) {
                $.toaster.success(total + ' 个文件上传完成');
                cardView.reload(true);
            }
        }

        for (var i = 0; i < files.length; i++) {
            (function (file) {
                var config = {
                    uploadEndpoint: '/index/upload/upload',
                    uploadData: {},
                    chunked: true,
                    chunkSize: 1024 * 1024 * 2,
                    maxDirectSize: 10 * 1024 * 1024,
                    onSuccess: function (res) {
                        $.request.postForm("/index/upload/publish", {
                            name: res.data,
                            series: $("mdui-select[name=series]").val()
                        }, onOneDone);
                    },
                    onError: function (msg) {
                        $.toaster.error(file.name + ': ' + (msg || '上传失败'));
                        onOneDone();
                    }
                };
                if (config.chunked || file.size > config.maxDirectSize) {
                    $.file._uploadWithChunks(file, config);
                } else {
                    $.file._uploadDirect(file, config);
                }
            })(files[i]);
        }
    }

    function initBookContextMenu() {
        $bookContextMenu[0].bind(cardView.cardsContainer, function (index) {
            return cardView.getRow(index);
        });

        var actions = {
            download: function (book) {
                var filename = String(book.filename || '').trim();
                if (!filename) return $.toaster.warning('文件名缺失，无法下载');
                window.open('/index/book/file?filename=' + encodeURIComponent(filename), '_blank', 'noopener');
            },
            edit: function (book) {
                editDialog.open();
                editDialog.setValue(book);
            },
            delete: function (book) {
                confirm('确定要删除这本书籍吗？', '删除', function () { batchDelete([book]); });
            },
            scrape: function (book) { batchScrape([book]); },
            toggleRead: function (book) { batchReadState([book], !book.hasReadTag); },
            aiIdentify: function (book) { aiIdentifyRun([book]); }
        };

        onContextAction = function (e) {
            var detail = (e.originalEvent || e).detail;
            var fn = actions[detail.action];
            if (fn) fn(detail.book);
        };
        $bookContextMenu.on('action', onContextAction);
    }

    function syncWebdav() {
        if (isDestroyed) return;
        $.request.postForm('/index/book/sync', {}, function (res) {
            if (isDestroyed) return;
            if (res.code === 200) {
                $.toaster.success(res.msg);
                cardView.reload();
            } else if (res.code === 201) {
                $.toaster.success(res.msg);
                if (syncTimer) clearTimeout(syncTimer);
                syncTimer = setTimeout(syncWebdav, 2000);
            } else {
                $.toaster.error(res.msg);
            }
        });
    }

    function initAutocomplete() {
        [
            { inputId: 'editCategory', dropdownId: 'categoryDropdown', listId: 'categoryList', options: filterOptions.categories || [] },
            { inputId: 'editFavorite', dropdownId: 'favoriteDropdown', listId: 'favoriteList', options: filterOptions.favorites || [] },
            { inputId: 'editSeries', dropdownId: 'seriesDropdown', listId: 'seriesList', options: filterOptions.groupNames || [] }
        ].forEach(function (field) {
            var $input = $('#' + field.inputId);
            var $dropdown = $('#' + field.dropdownId);
            var $list = $('#' + field.listId);
            if (!$input.length || !$dropdown.length || !$list.length || $input.data('autocompleteInit')) return;
            $input.data('autocompleteInit', true);

            function renderList(filterText) {
                var filtered = field.options.filter(function (opt) {
                    return opt.toLowerCase().includes((filterText || '').toLowerCase());
                });
                if (!filtered.length) {
                    $list.html('<mdui-list-item disabled>无匹配选项</mdui-list-item>');
                    return;
                }
                $list.html(filtered.map(function (opt) {
                    return '<mdui-list-item data-value="' + $.escapeHtml(opt) + '">' + $.escapeHtml(opt) + '</mdui-list-item>';
                }).join(''));
            }

            $list.on('click', '[data-value]', function () {
                $input.val($(this).attr('data-value'));
                $dropdown[0].open = false;
            });

            renderList();
            $input.on('input', function () { renderList($input.val()); });
            var endIcon = $input[0].shadowRoot && $input[0].shadowRoot.querySelector('[part="end-icon"]');
            if (endIcon) {
                $(endIcon).on('click', function (e) {
                    e.stopPropagation();
                    if (field.options.length) {
                        renderList($input.val());
                        $dropdown[0].open = !$dropdown[0].open;
                    }
                });
            }
        });
    }

    function bindEvents() {
        var throttledSearch = $.throttle(reload, 1000);

        $('#searchForm').on('input change', 'mdui-text-field', function () {
            $.url.setParam($(this).attr('name'), $(this).val());
            throttledSearch();
        }).on('change', 'mdui-select', function () {
            var val = $(this).val();
            $.url.setParam($(this).attr('name'), val == null ? '' : val);
            reload();
        });

        $('#btnAdd').on('click', function () {
            var input = document.createElement('input');
            input.type = 'file';
            input.multiple = true;
            input.accept = BOOK_EXTENSIONS.join(',');
            input.onchange = function (e) {
                if (e.target.files && e.target.files.length) uploadBatch(filterBookFiles(e.target.files));
            };
            input.click();
        });

        $('#btnSync').on('click', syncWebdav);

        $('#btnRemoveDuplicates').on('click', function () {
            confirm(
                '确定要删除重复书籍吗？<br><small class="text-on-surface-variant">将保留相同书名+作者中最后导入的版本</small>',
                '删除重复书籍',
                function () {
                    $("body").showLoading("正在查找并删除重复书籍...");
                    $.request.postForm('/index/book/removeDuplicates', {}, function (res) {
                        $("body").closeLoading();
                        if (res.code === 200) {
                            $.toaster.success(res.msg);
                            cardView.reload(true);
                        } else {
                            $.toaster.error(res.msg);
                        }
                    });
                }
            );
        });

        $('#btnBatchEdit').on('click', function () {
            if (requireSelected('请先选择要操作的书籍')) batchDialog.open();
        });

        $('#btnBatchAiIdentify').on('click', function () {
            var selected = requireSelected('请先选择要识别的书籍');
            if (!selected) return;
            confirm('确定让 AI 自动识别并直接修改选中的 ' + selected.length + ' 本书籍信息吗？此操作无需人工核对、会直接保存。', 'AI 识别', function () {
                aiIdentifyRun(selected);
            });
        });

        $('#btnBatchDelete').on('click', function () {
            var selected = requireSelected('请先选择要删除的书籍');
            if (!selected) return;
            confirm(
                '确定要删除选中的 ' + selected.length + ' 本书籍吗？<br><small class="text-on-surface-variant">此操作不可恢复</small>',
                '批量删除',
                function () { batchDelete(selected); }
            );
        });

        $('#btnBatchScrape').on('click', function () {
            var selected = requireSelected('请先选择要刮削的书籍');
            if (selected) batchScrape(selected);
        });

        $('#btnBatchMarkRead, #btnBatchMarkUnread').on('click', function () {
            var selected = requireSelected('请先选择要标记的书籍');
            if (selected) batchReadState(selected, this.id === 'btnBatchMarkRead');
        });

        $dragUpload.on('files', function (e) {
            uploadBatch((e.originalEvent || e).detail);
        });

        $doubanPicker.on('select', function (e) {
            $.form.val('#bookEditForm', DoubanBookPicker.toFormData((e.originalEvent || e).detail));
            $.toaster.success('已填充书籍信息');
        });

        editDialog.submit('/index/book/update', function () { cardView.reload(); });

        batchDialog.submit(null, function (formData) {
            var selected = requireSelected('没有选中的书籍');
            if (!selected) return;

            var batchData = {};
            ['author', 'category', 'favorite', 'series'].forEach(function (key) {
                if (formData[key]) batchData[key] = formData[key];
            });
            if (!Object.keys(batchData).length) {
                return $.toaster.warn('请至少填写一个要批量设置的字段');
            }

            batchDialog.close();
            sequentialPost(selected, {
                url: '/index/book/update',
                data: function (book) { return Object.assign({}, book, batchData); },
                loadingText: '正在批量更新 ' + selected.length + ' 本书籍...',
                doneText: function (ok, fail) {
                    return fail
                        ? '批量更新完成：' + ok + ' 本成功，' + fail + ' 失败'
                        : '批量更新完成：' + ok + ' 本成功';
                }
            });
        });

        $(editDialog).on("click", "#douban", function () {
            var bookName = $("#bookName").val().trim();
            if (!bookName) return $.toaster.error('请先输入书名');
            $(editDialog).showLoading();
            $.request.postForm("/index/douban/search", { q: bookName }, function (data) {
                $(editDialog).closeLoading();
                if (data.code === 200) {
                    $doubanPicker[0].open(data.data);
                } else {
                    $.toaster.error(data.msg);
                }
            });
        });

        $(editDialog).on("click", "#aiFill", function () {
            var bookName = $("#bookName").val().trim();
            if (!bookName) return $.toaster.error('请先输入书名');

            aiStop();
            aiLoadingShow(editDialog, 'AI 准备中…');

            aiSource = $.request.sse('/index/book/aiFill', {
                params: {
                    bookName: bookName,
                    author: $('#bookEditForm [name=author]').val().trim()
                },
                autoReconnect: false,
                eventHandlers: {
                    chunk: function (data) {
                        if (!data || typeof data !== 'object') return;
                        if (data.type === 'error') {
                            aiStop();
                            $.toaster.error(data.text || 'AI 填充失败');
                        } else {
                            aiLoadingUpdate(data.text || 'AI 工作中…');
                        }
                    },
                    result: function (data) {
                        if (data && typeof data === 'object') {
                            $.form.val('#bookEditForm', data);
                            $.toaster.success('AI 已填充，请核对后保存');
                        }
                    },
                    done: aiStop
                },
                onError: function () {
                    if (aiSource) {
                        aiStop();
                        $.toaster.error('AI 连接中断');
                    }
                }
            });
        });
    }

    $.request.get('/index/book/filters', {}, function (res) {
        if (res.code !== 200) return;

        filterOptions = res.data;

        var filterMap = { series: 'groupNames', category: 'categories', favorite: 'favorites' };
        Object.keys(filterMap).forEach(function (name) {
            var items = filterOptions[filterMap[name]] || [];
            var $select = $('mdui-select[name="' + name + '"]');
            items.forEach(function (item) {
                var safe = $.escapeHtml(item);
                $select.append('<mdui-menu-item value="' + safe + '">' + safe + '</mdui-menu-item>');
            });
        });

        var $finished = $('mdui-select[name="finished"]');
        var finishedVal = $.url.getParam('finished');
        if ($finished.length && finishedVal !== null && finishedVal !== '') {
            $finished[0].value = finishedVal;
        }

        cardView = new CardView("#bookTable");
        cardView.load({
            params: $.url.getAllParams(),
            uri: "/index/book/list",
            cardWidth: "180px",
            template: '<div class="book-card-content p-2">{{bookCard}}</div>',
            columns: [{
                field: "bookCard",
                formatter: function (value, row) { return BookCard.formatRow(row); }
            }],
            events: { onCardClick: openReader },
            empty_msg: "暂无书籍数据",
            page: true,
            pageSizes: [24, 48, 96],
            selectable: true
        });

        initBookContextMenu();
        bindEvents();
        initAutocomplete();
    });

    $.emitter.on('pjax:prevented', reload);

    window.pageOnUnLoad = function () {
        isDestroyed = true;
        aiStop();
        if (syncTimer) clearTimeout(syncTimer);
        if (onContextAction) $bookContextMenu.off('action', onContextAction);
        if (cardView) cardView.destroy();
        if ($bookContextMenu.length) $bookContextMenu[0].destroy();
        if ($dragUpload.length) $dragUpload[0].destroy();
        if ($doubanPicker.length) $doubanPicker[0].destroy();
        $('#searchForm, [id^="btn"]').off();
        $(editDialog).off();
    };
};
