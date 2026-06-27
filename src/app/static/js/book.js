/**
 * 书籍列表页面交互逻辑
 * @file book.js
 */

window.pageLoadFiles = [
    'Toaster',
    'CardView',
    'Form',
    'SearchInput',
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
    var onContextAction = null;
    var aiSource = null;


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

    // AI 分类：提交后台任务，AI 自动判断分类和标签并写库
    function aiClassifyRun(books) {
        if (!books || !books.length) return;
        var ids = books.map(function (b) { return b.id; });

        $.request.get('/index/book/aiClassify', { ids: JSON.stringify(ids) }, function (res) {
            if (res.code === 200) {
                $.toaster.success(res.msg || '已提交后台 AI 分类任务');
            } else {
                $.toaster.error(res.msg || 'AI 分类提交失败');
            }
        }, function () {
            $.toaster.error('AI 分类提交失败');
        });
    }

    function reload() {
        cardView.reload($.url.getAllParams(), true);
    }

    function requireSelected(warnMsg) {
        var selected = cardView.getSelectedRows();
        if (!selected || !selected.length) {
            $.toaster.warn(warnMsg);
            return null;
        }
        return selected;
    }

    function confirm(msg, title, onYes) {
        $.layer.confirm({ msg: msg, title: title, yes: onYes, no: function () {} });
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

    function bookIds(books) {
        return JSON.stringify(books.map(function (b) { return b.id; }));
    }

    // 批量请求：一次提交 ids，带 loading，完成后弹提示并刷新
    function batchRequest(url, data, loadingText) {
        $("body").showLoading(loadingText);
        $.request.postForm(url, data, function (res) {
            $("body").closeLoading();
            if (res.code === 200) {
                $.toaster.success(res.msg);
                cardView.reload();
            } else {
                $.toaster.error(res.msg || '操作失败');
            }
        }, function () {
            $("body").closeLoading();
            $.toaster.error('请求失败');
        });
    }

    function batchDelete(books) {
        batchRequest('/index/book/delete', { ids: bookIds(books) }, '正在删除 ' + books.length + ' 本书籍...');
    }

    // 刮削是慢 I/O，走后台任务，进度在任务面板查看
    function batchScrape(books) {
        $.request.postForm('/index/book/scrapeCover', { ids: bookIds(books) }, function (res) {
            res.code === 200 ? $.toaster.success(res.msg) : $.toaster.error(res.msg || '刮削提交失败');
        });
    }

    function batchReadState(books, read) {
        batchRequest('/index/book/batchRead', { ids: bookIds(books), read: read ? '1' : '0' }, read ? '正在标记已读…' : '正在标记未读…');
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
                $.file._uploadWithChunks(file, {
                    uploadEndpoint: '/index/upload/upload',
                    uploadData: {},
                    chunkSize: 1024 * 1024 * 2,
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
                });
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
                if (!filename) return $.toaster.warn('文件名缺失，无法下载');
                window.open('/index/book/file?filename=' + encodeURIComponent(filename), '_blank', 'noopener');
            },
            edit: function (book) {
                editDialog.open();
                editDialog.setValue(book);
                var fav = document.getElementById('editFavorite');
                if (fav) fav.setValue(book.favorite || '', book.favorite || '');
            },
            delete: function (book) {
                confirm('确定要删除这本书籍吗？', '删除', function () { batchDelete([book]); });
            },
            scrape: function (book) { batchScrape([book]); },
            toggleRead: function (book) { batchReadState([book], !book.hasReadTag); },
            aiIdentify: function (book) { aiIdentifyRun([book]); },
            aiClassify: function (book) { aiClassifyRun([book]); }
        };

        onContextAction = function (e) {
            var detail = (e.originalEvent || e).detail;
            var fn = actions[detail.action];
            if (fn) fn(detail.book);
        };
        $bookContextMenu.on('action', onContextAction);
    }

    function syncWebdav() {
        $.request.postForm('/index/book/sync', {}, function (res) {
            if (res.code === 200) {
                $.toaster.success(res.msg);
            } else if (res.code === 201) {
                $.toaster.success(res.msg);
            } else {
                $.toaster.error(res.msg);
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
                if (e.target.files && e.target.files.length) uploadBatch(DragUpload.filterFiles(e.target.files, BOOK_EXTENSIONS));
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

        $('#btnBatchAiClassify').on('click', function () {
            var selected = requireSelected('请先选择要分类的书籍');
            if (!selected) return;
            confirm('确定让 AI 自动判断选中的 ' + selected.length + ' 本书籍的分类和标签吗？此操作会直接保存。', 'AI 分类', function () {
                aiClassifyRun(selected);
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

        editDialog.submit('/index/book/update', function () { cardView.reload(false); });

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
            batchData.ids = bookIds(selected);
            batchRequest('/index/book/batchUpdate', batchData, '正在批量更新 ' + selected.length + ' 本书籍...');
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

            $(editDialog).showLoading('AI 准备中…');

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
                            aiSource.close();
                            $(editDialog).closeLoading();
                            $.toaster.error(data.text || 'AI 填充失败');
                        } else {
                            $(editDialog).updateLoading(data.text || 'AI 工作中…');
                        }
                    },
                    result: function (data) {
                        if (data && typeof data === 'object') {
                            $.form.val('#bookEditForm', data);
                            $.toaster.success('AI 已填充，请核对后保存');
                        }
                    },
                    done: function () {
                        $(editDialog).closeLoading();
                    }
                },
                onError: function () {
                    if (aiSource) {
                        $(editDialog).closeLoading();
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
    });

    $.emitter.on('pjax:prevented', reload);

    window.pageOnUnLoad = function () {
        if (aiSource) { aiSource.close(); aiSource = null; }
        if (onContextAction) $bookContextMenu.off('action', onContextAction);
        if (cardView) cardView.destroy();
        if ($bookContextMenu.length) $bookContextMenu[0].destroy();
        if ($dragUpload.length) $dragUpload[0].destroy();
        if ($doubanPicker.length) $doubanPicker[0].destroy();
        $('#searchForm, [id^="btn"]').off();
        $(editDialog).off();
    };
};
