/**
 * 高亮与笔记：CardView 一本书一行，点击书头展开/折叠该书注解。
 */
window.pageLoadFiles = ['Toaster', 'CardView'];

window.pageOnLoad = function () {
    var COLORS = {
        yellow: '#d6a900', red: '#c43c39', blue: '#3678c8', green: '#39875b',
        cyan: '#168b9b', gray: '#777', orange: '#c46a19', purple: '#8056a8'
    };
    var TYPES = { highlight: '高亮', note: '笔记', bookmark: '书签' };
    var params = { search: '', type: '' };
    var searchTimer = null;

    function renderItem(item) {
        var text = item.text || (item.type === 'bookmark' ? '书签' : '无高亮文本');
        var location = [item.chapter, item.pageno ? ('第 ' + item.pageno + ' 页') : ''].filter(Boolean).join(' · ');
        return '<div class="annotation-item d-flex gap-3">'
            + '<div class="annotation-mark" style="background:' + (COLORS[item.color] || 'rgb(var(--mdui-color-primary))') + '"></div>'
            + '<div class="min-w-0 flex-1">'
            + '<div class="d-flex items-center justify-between gap-2 mb-1">'
            + '<span class="label-small">' + TYPES[item.type] + '</span>'
            + '<span class="annotation-meta">' + $.escapeHtml(item.datetime_updated || item.datetime) + '</span></div>'
            + '<div class="body-medium" style="white-space:pre-wrap">' + $.escapeHtml(text) + '</div>'
            + (item.note ? '<div class="annotation-note body-medium">' + $.escapeHtml(item.note) + '</div>' : '')
            + (location ? '<div class="annotation-meta mt-2">' + $.escapeHtml(location) + '</div>' : '')
            + '</div></div>';
    }

    function renderBook(book) {
        var expanded = params.search !== '';
        return '<mdui-card class="annotation-book rounded-lg w-100">'
            + '<div class="annotation-book-head d-flex items-center gap-3 p-3">'
            + '<image-loader class="annotation-cover" src="' + $.escapeHtml(book.coverUrl) + '"></image-loader>'
            + '<div class="flex-1 min-w-0"><div class="title-medium text-ellipsis">' + $.escapeHtml(book.title) + '</div>'
            + '<div class="body-small text-on-surface-variant text-ellipsis">' + $.escapeHtml(book.authors || '未知作者') + '</div>'
            + '<div class="annotation-meta mt-1">' + book.highlights + ' 条高亮 · ' + book.notes + ' 条笔记'
            + (book.bookmarks ? (' · ' + book.bookmarks + ' 个书签') : '') + '</div></div>'
            + '<mdui-icon class="annotation-expand" name="' + (expanded ? 'expand_less' : 'expand_more') + '"></mdui-icon></div>'
            + '<div class="annotation-items' + (expanded ? '' : ' d-none') + '">' + book.annotations.map(renderItem).join('') + '</div>'
            + '</mdui-card>';
    }

    var cardView = new CardView('#annotationTable');
    cardView.load({
        uri: '/index/stats/annotationPage',
        params: params,
        cardWidth: '100%',
        template: '{{book}}',
        columns: [{ field: 'book', formatter: function (value, row) { return renderBook(row); } }],
        empty_msg: '没有匹配的高亮或笔记',
        page: true,
        pageSizes: [10, 20, 50],
        selectable: false
    });

    $('#annotationTable').on('click', '.annotation-book-head', function () {
        var card = $(this).parent();
        var items = card.find('.annotation-items');
        var hidden = items.hasClass('d-none');
        if (hidden) items.removeClass('d-none');
        else items.addClass('d-none');
        card.find('.annotation-expand').attr('name', hidden ? 'expand_less' : 'expand_more');
    });

    $('#annotation-search').on('input', function () {
        clearTimeout(searchTimer);
        var value = this.value.trim();
        searchTimer = setTimeout(function () {
            params.search = value;
            cardView.reload(params, true);
        }, 300);
    });

    $('.annotation-type-switch').on('click', 'button', function () {
        $('.annotation-type-switch button').removeClass('active');
        $(this).addClass('active');
        params.type = $(this).attr('data-type');
        cardView.reload(params, true);
    });

    window.pageOnUnLoad = function () {
        clearTimeout(searchTimer);
        cardView.destroy();
        $('#annotationTable, #annotation-search, .annotation-type-switch').off();
    };
};
