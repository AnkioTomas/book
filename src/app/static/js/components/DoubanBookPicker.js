/**
 * 豆瓣搜索结果选择对话框
 * @file DoubanBookPicker.js
 */
class DoubanBookPicker extends HTMLElement {
    connectedCallback() {
        this._books = [];

        this.innerHTML = `
            <style>
                #doubanPickerBody{max-height:60vh;overflow-y:auto}
                .picker-item{cursor:pointer;--mdui-comp-list-item-one-line-height:auto;padding:12px 16px}
                .picker-row{display:flex;gap:12px;width:100%}
                .picker-cover{width:60px;height:80px;border-radius:4px;flex-shrink:0}
                .picker-meta{flex:1;min-width:0}
                .picker-title-text{font-size:1rem;font-weight:600;margin-bottom:4px}
                .picker-sub{font-size:.875rem;color:rgba(var(--mdui-color-on-surface-variant));margin-bottom:4px}
                .picker-rating{font-size:.875rem;margin-bottom:4px}
                .picker-intro{font-size:.8125rem;line-height:1.4;color:rgba(var(--mdui-color-on-surface-variant))}
            </style>
            <mdui-dialog id="doubanPickerDialog" close-on-overlay-click>
                <mdui-top-app-bar slot="header">
                    <mdui-top-app-bar-title id="doubanPickerTitle">选择书籍</mdui-top-app-bar-title>
                    <mdui-button-icon id="doubanPickerClose" icon="close" slot="end-icon"></mdui-button-icon>
                </mdui-top-app-bar>
                <div id="doubanPickerBody"><mdui-list id="doubanPickerList"></mdui-list></div>
            </mdui-dialog>
        `;

        var picker = this;

        $('#doubanPickerClose').on('click', function () {
            $('#doubanPickerDialog')[0].open = false;
        });

        $('#doubanPickerList').on('click', 'mdui-list-item[data-index]', function () {
            var book = picker._books[parseInt($(this).attr('data-index'), 10)];
            if (!book) return;
            picker.dispatchEvent(new CustomEvent('select', { detail: book, bubbles: true }));
            $('#doubanPickerDialog')[0].open = false;
        });
    }

    open(books) {
        if (!books || !books.length) {
            $.toaster.warning('未找到匹配的书籍');
            return;
        }
        this._books = books;
        $('#doubanPickerTitle').text('选择书籍 (共 ' + books.length + ' 条结果)');
        this.renderList();
        $('#doubanPickerDialog')[0].open = true;
    }

    renderList() {
        var html = '';
        for (var i = 0; i < this._books.length; i++) {
            html += this.renderItem(this._books[i], i);
        }
        var $list = $('#doubanPickerList');
        $list.html(html);
        $.emitter.emit('imageLoader', $list[0]);
    }

    renderItem(book, index) {
        var cover = encodeURIComponent(book.cover_url || '');
        var title = $.escapeHtml(book.title || '未知书名');
        var meta = $.escapeHtml(
            (book.author || '未知作者')
            + (book.publisher ? ' / ' + book.publisher : '')
            + (book.year ? ' / ' + book.year : '')
        );
        var rating = book.rating
            ? '<div class="picker-rating">⭐ ' + $.escapeHtml(String(book.rating)) + '</div>'
            : '';
        var intro = book.intro
            ? '<div class="picker-intro">' + $.escapeHtml(book.intro.substring(0, 100)) + '...</div>'
            : '';

        return ''
            + '<mdui-list-item data-index="' + index + '" class="picker-item">'
            + '<div class="picker-row">'
            + '<image-loader class="picker-cover" src="/proxy/' + cover + '" no-refer></image-loader>'
            + '<div class="picker-meta">'
            + '<div class="picker-title-text">' + title + '</div>'
            + '<div class="picker-sub">' + meta + '</div>'
            + rating + intro
            + '</div></div></mdui-list-item>'
            + '<mdui-divider></mdui-divider>';
    }

    destroy() {
        this._books = [];
    }

    static toFormData(book) {
        return {
            bookName: book.title,
            author: book.author,
            description: book.full_intro || book.intro,
            publisher: book.publisher,
            publishYear: book.year,
            isbn: book.isbn,
            pages: book.pages,
            price: book.price,
            coverUrl: book.cover_url,
            rate: Math.floor((book.rating || 0) / 2)
        };
    }
}

customElements.define('douban-book-picker', DoubanBookPicker);
