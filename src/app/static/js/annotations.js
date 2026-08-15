/**
 * 高亮与笔记：按书列表 / 词云。
 */
window.pageLoadFiles = ['Toaster', 'Request'];

window.pageOnLoad = function () {
    var books = [];
    var annotations = [];
    var currentView = 'list';
    var selectedWord = '';

    function annotationText(item) {
        return ((item.text || '') + ' ' + (item.note || '')).trim();
    }

    function typeLabel(type) {
        return type === 'note' ? '笔记' : (type === 'bookmark' ? '书签' : '高亮');
    }

    function colorValue(color) {
        var colors = {
            yellow: '#d6a900', red: '#c43c39', blue: '#3678c8', green: '#39875b',
            cyan: '#168b9b', gray: '#777', orange: '#c46a19', purple: '#8056a8'
        };
        return colors[color] || 'rgb(var(--mdui-color-primary))';
    }

    function renderItem(item) {
        var main = item.text || (item.type === 'bookmark' ? '书签' : '无高亮文本');
        var location = [item.chapter, item.pageno ? ('第 ' + item.pageno + ' 页') : ''].filter(Boolean).join(' · ');
        return '<div class="annotation-item d-flex gap-3">'
            + '<div class="annotation-mark" style="background:' + colorValue(item.color) + '"></div>'
            + '<div class="min-w-0 flex-1">'
            + '<div class="d-flex items-center justify-between gap-2 mb-1">'
            + '<span class="label-small">' + $.escapeHtml(typeLabel(item.type)) + '</span>'
            + '<span class="annotation-meta">' + $.escapeHtml(item.datetime_updated || item.datetime || '') + '</span></div>'
            + '<div class="body-medium" style="white-space:pre-wrap">' + $.escapeHtml(main) + '</div>'
            + (item.note ? '<div class="annotation-note body-medium">' + $.escapeHtml(item.note) + '</div>' : '')
            + (location ? '<div class="annotation-meta mt-2">' + $.escapeHtml(location) + '</div>' : '')
            + '</div></div>';
    }

    function renderList() {
        var query = ($('#annotation-search')[0].value || '').trim().toLowerCase();
        var visible = books.filter(function (book) {
            if (!query) return true;
            var content = [book.title, book.authors, book.filename].concat(
                annotations.filter(function (a) { return a.filename === book.filename; }).map(annotationText)
            ).join(' ').toLowerCase();
            return content.includes(query);
        });

        if (!visible.length) {
            $('#annotation-list').html('<mdui-card class="p-3 rounded-lg"><div class="body-small text-on-surface-variant">没有匹配的高亮或笔记</div></mdui-card>');
            return;
        }

        $('#annotation-list').html(visible.map(function (book) {
            var rows = annotations.filter(function (a) { return a.filename === book.filename; });
            return '<mdui-card class="annotation-book rounded-lg w-100 mb-3" data-file="' + $.escapeHtml(book.filename) + '">'
                + '<div class="annotation-book-head d-flex items-center gap-3 p-3">'
                + '<image-loader class="annotation-cover" src="' + $.escapeHtml(book.coverUrl || '') + '"></image-loader>'
                + '<div class="flex-1 min-w-0"><div class="title-medium text-ellipsis">' + $.escapeHtml(book.title || book.filename) + '</div>'
                + '<div class="body-small text-on-surface-variant text-ellipsis">' + $.escapeHtml(book.authors || '未知作者') + '</div>'
                + '<div class="annotation-meta mt-1">' + book.highlights + ' 条高亮 · ' + book.notes + ' 条笔记'
                + (book.bookmarks ? (' · ' + book.bookmarks + ' 个书签') : '') + '</div></div>'
                + '<mdui-icon class="annotation-expand" name="expand_more"></mdui-icon></div>'
                + '<div class="annotation-items d-none">' + rows.map(renderItem).join('') + '</div></mdui-card>';
        }).join(''));
    }

    function wordsFrom(text) {
        var words = [];
        if (window.Intl && Intl.Segmenter) {
            var segments = new Intl.Segmenter('zh-CN', { granularity: 'word' }).segment(text);
            Array.from(segments).forEach(function (part) {
                if (part.isWordLike) words.push(part.segment.toLowerCase());
            });
        } else {
            words = text.toLowerCase().match(/[\u3400-\u9fff]{2,}|[a-z0-9]{2,}/g) || [];
        }
        var stop = new Set(['这个', '那个', '一个', '一种', '就是', '不是', '没有', '可以', '因为', '所以', '但是', '如果', '我们', '你们', '他们', '自己', '什么', '怎么', '已经', '还是', '以及', '而且', '然后', '这里', '那里']);
        return words.filter(function (word) {
            return word.length > 1 && !stop.has(word) && !/^\d+$/.test(word);
        });
    }

    function renderCloud() {
        var query = ($('#annotation-search')[0].value || '').trim().toLowerCase();
        var source = annotations.filter(function (item) {
            return !query || annotationText(item).toLowerCase().includes(query);
        });
        var count = {};
        wordsFrom(source.map(annotationText).join(' ')).forEach(function (word) {
            count[word] = (count[word] || 0) + 1;
        });
        var entries = Object.keys(count).map(function (word) {
            return [word, count[word]];
        }).sort(function (a, b) {
            return b[1] - a[1] || a[0].localeCompare(b[0]);
        }).slice(0, 80);

        if (!entries.length) {
            $('#annotation-cloud').html('<div class="body-small text-on-surface-variant">没有足够文本生成词云</div>');
            renderCloudResults('');
            return;
        }
        var max = entries[0][1];
        var min = entries[entries.length - 1][1];
        $('#annotation-cloud').html(entries.map(function (entry) {
            var ratio = max === min ? .5 : (entry[1] - min) / (max - min);
            var size = Math.round(14 + Math.sqrt(ratio) * 28);
            return '<button type="button" data-word="' + $.escapeHtml(entry[0]) + '"'
                + ' style="font-size:' + size + 'px;opacity:' + (.62 + ratio * .38) + '"'
                + ' title="' + entry[1] + ' 次">' + $.escapeHtml(entry[0]) + '</button>';
        }).join(''));
        renderCloudResults(selectedWord);
    }

    function renderCloudResults(word) {
        selectedWord = word;
        if (!word) {
            $('#annotation-cloud-title').addClass('d-none');
            $('#annotation-cloud-results').empty();
            return;
        }
        var hits = annotations.filter(function (item) {
            return annotationText(item).toLowerCase().includes(word.toLowerCase());
        });
        $('#annotation-cloud-title').removeClass('d-none').text('“' + word + '” · ' + hits.length + ' 条');
        $('#annotation-cloud-results').html(hits.map(function (item) {
            var book = books.find(function (b) { return b.filename === item.filename; }) || {};
            return '<div class="annotation-cloud-hit"><div class="label-small text-on-surface-variant mb-1">'
                + $.escapeHtml(book.title || item.filename) + ' · ' + $.escapeHtml(item.chapter || '') + '</div>'
                + '<div class="body-medium">' + $.escapeHtml(annotationText(item)) + '</div></div>';
        }).join(''));
    }

    function render() {
        if (currentView === 'list') renderList();
        else renderCloud();
    }

    $.request.get('/index/stats/annotations', {}, function (res) {
        if (!res || res.code !== 200) {
            $.toaster.error((res && res.msg) || '加载失败');
            return;
        }
        books = (res.data && res.data.books) || [];
        annotations = (res.data && res.data.annotations) || [];
        $('#annotation-summary').text(books.length + ' 本书 · ' + annotations.length + ' 条高亮、笔记与书签');
        render();
    }, function () {
        $.toaster.error('加载高亮与笔记失败');
    });

    $('.annotation-view-switch').on('click', 'button', function () {
        currentView = $(this).data('view');
        $('.annotation-view-switch button').removeClass('active');
        $(this).addClass('active');
        if (currentView === 'list') {
            $('#annotation-list').removeClass('d-none');
            $('#annotation-cloud-view').addClass('d-none');
        } else {
            $('#annotation-list').addClass('d-none');
            $('#annotation-cloud-view').removeClass('d-none');
        }
        render();
    });
    $('#annotation-search').on('input', render);
    $('#annotation-list').on('click', '.annotation-book-head', function () {
        var card = $(this).parent();
        var items = card.find('.annotation-items');
        var hidden = items.hasClass('d-none');
        if (hidden) items.removeClass('d-none');
        else items.addClass('d-none');
        card.find('.annotation-expand').attr('name', hidden ? 'expand_less' : 'expand_more');
    });
    $('#annotation-cloud').on('click', 'button[data-word]', function () {
        renderCloudResults($(this).data('word'));
    });
};
