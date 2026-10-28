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
    '/js/components/MetaFillPicker.js',
    '/js/components/ZlibPicker.js',
    'ContextMenu',
    'DragUpload'
];

window.pageOnLoad = function () {

    var BOOK_EXTENSIONS = ['.epub', '.mobi', '.azw', '.azw3', '.pdf', '.txt'];

    var cardView = null;
    var filterOptions = {};
    var editDialog = $('#bookEditDialog')[0];
    var batchDialog = $('#batchEditDialog')[0];
    var setCategoryDialog = $('#setCategoryDialog')[0];
    var setCategoryTargets = [];
    // 不是真实分类名的特殊 favorite 筛选值
    var SPECIAL_FAVORITES = ['', 'empty', 'incomplete', 'unorganized'];
    var $metaFill = $('#metaFillPicker');
    var $zlibPicker = $('#zlibPicker');
    var $dragUpload = $('drag-upload');
    var bookCtxCleanup = null;
    var editExtrasReady = false;

    function coverPreviewSrc(url) {
        url = String(url || '').trim();
        if (!url) return '';
        if (url.indexOf('/proxy/') === 0 || url.indexOf('/webdav/') === 0 || url.indexOf('data:') === 0) {
            return url;
        }
        return '/proxy/' + encodeURIComponent(url);
    }

    function readEditTags() {
        return String($('#editCategory').val() || '')
            .split(/\n+/)
            .map(function (t) { return t.trim(); })
            .filter(Boolean);
    }

    function writeEditTags(tags) {
        var seen = {};
        var clean = [];
        (tags || []).forEach(function (t) {
            t = String(t || '').trim();
            if (!t || seen[t]) return;
            seen[t] = true;
            clean.push(t);
        });
        $('#editCategory').val(clean.join('\n'));
        renderEditTagChips(clean);
    }

    function renderEditTagChips(tags) {
        var $box = $('#editTagChips');
        $box.empty();
        (tags || []).forEach(function (tag) {
            var $chip = $('<mdui-chip variant="input" deletable></mdui-chip>');
            $chip.attr('data-tag', tag);
            $chip.text(tag);
            $chip.on('delete', function () { removeEditTag(tag); });
            $box.append($chip);
        });
    }

    function addEditTag(raw) {
        var tag = String(raw || '').trim();
        if (!tag) return;
        var tags = readEditTags();
        if (tags.indexOf(tag) >= 0) return;
        tags.push(tag);
        writeEditTags(tags);
    }

    function removeEditTag(tag) {
        writeEditTags(readEditTags().filter(function (t) { return t !== tag; }));
    }

    function renderEditRateStars(rate) {
        rate = Math.max(0, Math.min(5, parseInt(rate, 10) || 0));
        var $box = $('#editRateStars');
        $box.find('mdui-button-icon[data-rate]').remove();
        for (var i = 1; i <= 5; i++) {
            var filled = i <= rate;
            var $btn = $('<mdui-button-icon></mdui-button-icon>');
            $btn.attr({
                'data-rate': String(i),
                icon: filled ? 'star' : 'star_border',
                title: i + ' 星'
            });
            $box.append($btn);
        }
    }

    function setEditRate(rate) {
        rate = Math.max(0, Math.min(5, parseInt(rate, 10) || 0));
        $('#editRate').val(String(rate));
        renderEditRateStars(rate);
    }

    function syncCoverPreview() {
        var url = $('#editCoverUrl').val();
        var src = coverPreviewSrc(url);
        var $box = $('#editCoverBox');
        var $img = $('#editCoverPreview');
        if (!src) {
            $box.removeClass('has-cover');
            $img.removeAttr('src');
            return;
        }
        $img.attr('src', src);
        $box.addClass('has-cover');
        $img.off('error.editCover').on('error.editCover', function () {
            $box.removeClass('has-cover');
        });
    }

    function syncEditFormExtras() {
        renderEditTagChips(readEditTags());
        setEditRate($('#editRate').val());
        syncCoverPreview();
    }

    function initEditFormExtras() {
        if (editExtrasReady) return;
        editExtrasReady = true;

        $('#editCoverUrl').on('input change', syncCoverPreview);

        $('#editRateStars').on('click', 'mdui-button-icon[data-rate]', function () {
            var next = parseInt($(this).attr('data-rate'), 10) || 0;
            var cur = parseInt($('#editRate').val(), 10) || 0;
            setEditRate(cur === next ? 0 : next);
        });

        function commitTagInput() {
            var $input = $('#editTagInput');
            addEditTag($input.val());
            $input.val('');
        }

        $('#editTagAddBtn').on('click', commitTagInput);
        $('#editTagInput').on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                commitTagInput();
            }
        });

        syncEditFormExtras();
    }

    // AI 识别：提交后台任务，AI 自动检索并直接写库，进度在任务面板查看
    // onlyEmpty=true 时仅补空字段（缺失详情）
    function aiIdentifyRun(books, onlyEmpty) {
        if (!books || !books.length) return;
        var ids = books.map(function (b) { return b.id; });
        var params = { ids: JSON.stringify(ids) };
        if (onlyEmpty) params.onlyEmpty = '1';

        $.request.get('/index/book/aiIdentify', params, function (res) {
            if (res.code === 200) {
                $.toaster.success(res.msg || '已提交后台 AI 识别任务');
            } else {
                $.toaster.error(res.msg || 'AI 识别提交失败');
            }
        }, function () {
            $.toaster.error('AI 识别提交失败');
        });
    }

    function aiFillIncompleteAll() {
        $.request.get('/index/book/aiFillIncomplete', {}, function (res) {
            if (res.code === 200) {
                $.toaster.success(res.msg || '已提交');
            } else {
                $.toaster.error(res.msg || '提交失败');
            }
        }, function () {
            $.toaster.error('提交失败');
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

    // 设置分类：单本 / 批量共用对话框，复用 batchUpdate，仅改 favorite
    function openSetCategory(books) {
        if (!books || !books.length) return;
        setCategoryTargets = books;
        var input = document.getElementById('setCategoryFavorite');
        var current = books.length === 1 ? (books[0].favorite || '') : '';
        if (input) input.setValue(current, current);
        $('#setCategoryTip').text(books.length === 1
            ? '为《' + (books[0].bookName || books[0].filename || '') + '》设置分类'
            : '为选中的 ' + books.length + ' 本书籍设置同一分类');
        setCategoryDialog.open();
    }

    function applyCategory(name) {
        var books = setCategoryTargets;
        if (!books || !books.length) return;
        setCategoryDialog.close();
        batchRequest('/index/book/batchUpdate', { ids: bookIds(books), favorite: name },
            name ? '正在设置分类…' : '正在清空分类…');
    }

    // 隐藏 / 取消隐藏当前分类（仅在进入某个具体分类时显示按钮）
    function initCategoryHiddenToggle() {
        var current = $.url.getParam('favorite') || '';
        var $btn = $('#btnToggleCategoryHidden');
        if (SPECIAL_FAVORITES.indexOf(current) !== -1) {
            $btn.hide();
            return;
        }
        var hidden = (filterOptions.hiddenFavorites || []).indexOf(current) !== -1;
        $btn.attr('icon', hidden ? 'visibility' : 'visibility_off')
            .text(hidden ? '取消隐藏此分类' : '隐藏此分类')
            .show();
        $btn.off('click').on('click', function () {
            var toHide = !hidden;
            var doIt = function () {
                $.request.postForm('/index/book/categoryHidden', { name: current, hidden: toHide ? '1' : '0' }, function (res) {
                    if (res.code !== 200) return $.toaster.error(res.msg || '操作失败');
                    $.toaster.success(res.msg);
                    var list = filterOptions.hiddenFavorites || [];
                    if (toHide) {
                        list.push(current);
                    } else {
                        list = list.filter(function (n) { return n !== current; });
                    }
                    filterOptions.hiddenFavorites = list;
                    initCategoryHiddenToggle();
                }, function () {
                    $.toaster.error('请求失败');
                });
            };
            if (toHide) {
                confirm('隐藏后，分类「' + $.escapeHtml(current) + '」下的书不会出现在「全部书籍」等列表中，进入该分类仍可查看，可随时取消隐藏。', '隐藏分类', doIt);
            } else {
                doIt();
            }
        });
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

    function showBookMenu(book, x, y) {
        $.menu({
            x: x,
            y: y,
            actions: [
                {
                    text: '下载',
                    icon: 'download',
                    action: function () {
                        var filename = String(book.filename || '').trim();
                        if (!filename) return $.toaster.warn('文件名缺失，无法下载');
                        window.open('/index/book/file?filename=' + encodeURIComponent(filename), '_blank', 'noopener');
                    }
                },
                {
                    text: '编辑',
                    icon: 'edit',
                    action: function () {
                        editDialog.open();
                        editDialog.setValue(book);
                        var fav = document.getElementById('editFavorite');
                        if (fav) fav.setValue(book.favorite || '', book.favorite || '');
                        syncEditFormExtras();
                    }
                },
                {
                    text: '设置分类',
                    icon: 'topic',
                    action: function () {
                        // 若当前有多选且包含该书，则对所有选中项设置
                        var selected = cardView.getSelectedRows() || [];
                        var inSel = selected.some(function (b) { return b.id === book.id; });
                        openSetCategory(inSel && selected.length > 1 ? selected : [book]);
                    }
                },
                { text: 'AI 识别', icon: 'auto_awesome', action: function () { aiIdentifyRun([book]); } },
                { text: 'AI 分类', icon: 'label', action: function () { aiClassifyRun([book]); } },
                {
                    text: '删除',
                    icon: 'delete',
                    danger: true,
                    action: function () {
                        confirm('确定要删除这本书籍吗？', '删除', function () { batchDelete([book]); });
                    }
                },
                { type: 'divider' },
                { text: '刮削封面', icon: 'image', action: function () { batchScrape([book]); } },
                {
                    text: book.hasReadTag ? '标记未读' : '标记已读',
                    icon: book.hasReadTag ? 'radio_button_unchecked' : 'task_alt',
                    action: function () { batchReadState([book], !book.hasReadTag); }
                }
            ]
        });
    }

    function initBookContextMenu() {
        var $cards = $(cardView.cardsContainer);
        var $root = $(cardView.cardsContainer.closest('#container') || cardView.cardsContainer);
        var pressTimer = null;

        function bookFromItem($item) {
            var index = $item.attr('data-index');
            if (index === undefined || index === '') return null;
            return cardView.getRow(Number(index));
        }

        $cards.on('contextmenu.bookCtx', '.card-view-item', function (e) {
            var book = bookFromItem($(this));
            if (!book) return;
            e.preventDefault();
            e.stopPropagation();
            var oe = e.originalEvent || e;
            showBookMenu(book, oe.clientX, oe.clientY);
        });

        $cards.on('touchstart.bookCtx', '.card-view-item', function () {
            var $item = $(this);
            pressTimer = setTimeout(function () {
                var book = bookFromItem($item);
                if (!book) return;
                var el = $item.find('book-card')[0] || $item[0];
                var r = el.getBoundingClientRect();
                showBookMenu(book, r.left + r.width / 2, r.top + r.height / 2);
            }, 500);
        });

        $root.on('touchend.bookCtx touchmove.bookCtx', function () {
            clearTimeout(pressTimer);
        });

        bookCtxCleanup = function () {
            clearTimeout(pressTimer);
            $cards.off('.bookCtx');
            $root.off('.bookCtx');
            // 只关菜单，不 destroy 全局单例：pjax 回来时 ContextMenu.js 不会重跑
            if (window.contextMenuInstance) window.contextMenuInstance.hide();
        };
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

        $('#btnZlib').on('click', function () {
            if (!$zlibPicker.length || typeof $zlibPicker[0].open !== 'function') {
                return $.toaster.error('Z站组件未加载');
            }
            var preset = String($('#searchForm [name=search]').val() || '').trim();
            $zlibPicker[0].open(preset);
        });
        $zlibPicker.off('ingested').on('ingested', function () {
            if (cardView) cardView.reload(true);
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
                aiIdentifyRun(selected, false);
            });
        });

        $('#btnBatchAiFillMissing').on('click', function () {
            var selected = cardView.getSelectedRows();
            if (selected && selected.length) {
                confirm(
                    '将对选中的 ' + selected.length + ' 本仅补全空字段（作者、简介、分类、标签），已有内容不覆盖。',
                    'AI 填充缺失',
                    function () { aiIdentifyRun(selected, true); }
                );
                return;
            }
            confirm(
                '未选中书籍时，将对书库中全部「缺失详情」的书执行 AI 填充（仅补空字段）。可在任务面板查看进度。',
                'AI 填充缺失',
                function () { aiFillIncompleteAll(); }
            );
        });

        $('#btnBatchAiClassify').on('click', function () {
            var selected = requireSelected('请先选择要分类的书籍');
            if (!selected) return;
            confirm('确定让 AI 自动判断选中的 ' + selected.length + ' 本书籍的分类和标签吗？此操作会直接保存。', 'AI 分类', function () {
                aiClassifyRun(selected);
            });
        });

        $('#btnBatchSetCategory').on('click', function () {
            var selected = requireSelected('请先选择要设置分类的书籍');
            if (selected) openSetCategory(selected);
        });

        setCategoryDialog.submit(null, function () {
            var input = document.getElementById('setCategoryFavorite');
            var name = String((input && (input.text || input.value)) || '').trim();
            if (!name) return $.toaster.warn('请输入或选择分类；如需移除请点「清空分类」');
            applyCategory(name);
        });

        $(setCategoryDialog).on('click', '#btnClearCategory', function () {
            applyCategory('');
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

        $('#btnOrganize').on('click', function () {
            var selected = requireSelected('请先选择要整理的书籍');
            if (!selected) return;
            $("body").showLoading('正在预览整理方案…');
            $.request.postForm('/index/book/organizePreview', { ids: bookIds(selected) }, function (res) {
                $("body").closeLoading();
                if (res.code !== 200) {
                    return $.toaster.error(res.msg || '预览失败');
                }
                var data = res.data || {};
                var items = data.items || [];
                var changed = data.changed || 0;
                if (!changed) {
                    return $.toaster.info('选中书籍已在目标路径，无需整理');
                }
                var lines = items.filter(function (it) { return it.changed; }).slice(0, 30).map(function (it) {
                    return '<div style="margin:4px 0;font-size:12px;word-break:break-all;">'
                        + '<div style="opacity:.7;">' + $('<div>').text(it.from).html() + '</div>'
                        + '<div>→ ' + $('<div>').text(it.to).html() + '</div></div>';
                }).join('');
                if (changed > 30) {
                    lines += '<div style="margin-top:8px;opacity:.7;">…还有 ' + (changed - 30) + ' 本未列出</div>';
                }
                confirm(
                    '将按分类分子目录并重命名 <b>' + changed + '</b> 本（共选 ' + items.length + ' 本）。'
                    + '<br><small class="text-on-surface-variant">会改动 WebDAV 上的文件路径，请确认静读天下能识别子目录后再执行。</small>'
                    + '<div style="max-height:240px;overflow:auto;margin-top:10px;text-align:left;">' + lines + '</div>',
                    '整理源文件',
                    function () {
                        $.request.postForm('/index/book/organize', { ids: bookIds(selected) }, function (r) {
                            if (r.code === 200) {
                                $.toaster.success(r.msg || '已提交整理任务');
                            } else {
                                $.toaster.error(r.msg || '提交失败');
                            }
                        }, function () {
                            $.toaster.error('提交失败');
                        });
                    }
                );
            }, function () {
                $("body").closeLoading();
                $.toaster.error('预览请求失败');
            });
        });

        $dragUpload.on('files', function (e) {
            uploadBatch((e.originalEvent || e).detail);
        });

        $metaFill.on('apply', function (e) {
            var data = (e.originalEvent || e).detail || {};
            $.form.val('#bookEditForm', data);
            syncEditFormExtras();
        });

        editDialog.submit('/index/book/update', function () { cardView.reload(false); });

        initEditFormExtras();

        $(editDialog).on('click', '#metaFillBtn', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openMetaFill();
        });

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

        function openMetaFill() {
            var bookName = $('#bookName').val().trim();
            if (!bookName) return $.toaster.error('请先输入书名');
            if (!$metaFill.length || typeof $metaFill[0].open !== 'function') return;
            var cur = $.form.val('#bookEditForm') || {};
            $metaFill[0].open(bookName, cur);
        }
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
            pageSizes: [24, 48, 96, 192, 384, 768],
            selectable: true
        });

        initBookContextMenu();
        bindEvents();
        initCategoryHiddenToggle();
    });

    $.emitter.on('pjax:prevented', reload);

    window.pageOnUnLoad = function () {
        if (bookCtxCleanup) bookCtxCleanup();
        if (cardView) cardView.destroy();
        if ($dragUpload.length) $dragUpload[0].destroy();
        if ($metaFill.length && $metaFill[0].destroy) $metaFill[0].destroy();
        if ($zlibPicker.length && $zlibPicker[0].destroy) $zlibPicker[0].destroy();
        $('#searchForm, [id^="btn"]').off();
        $(editDialog).off();
        $(setCategoryDialog).off();
        $metaFill.off();
        $zlibPicker.off();
    };
};
