/**
 * 书籍卡片右键 / 长按菜单
 * @file BookContextMenu.js
 */
class BookContextMenu extends HTMLElement {
    connectedCallback() {
        this.innerHTML = `
            <style>#bookCtxAnchor{position:fixed;width:0;height:0;pointer-events:none}</style>
            <mdui-dropdown id="bookCtxDropdown" trigger="manual" placement="bottom-start">
                <div id="bookCtxAnchor" slot="trigger" aria-hidden="true"></div>
                <mdui-menu id="bookCtxMenu">
                    <mdui-menu-item id="bookCtxDownload" data-action="download" icon="download">下载</mdui-menu-item>
                    <mdui-menu-item id="bookCtxEdit" data-action="edit" icon="edit">编辑</mdui-menu-item>
                    <mdui-menu-item id="bookCtxAiIdentify" data-action="aiIdentify" icon="auto_awesome">AI 识别</mdui-menu-item>
                    <mdui-menu-item id="bookCtxAiClassify" data-action="aiClassify" icon="label">AI 分类</mdui-menu-item>
                    <mdui-menu-item id="bookCtxDelete" data-action="delete" icon="delete">删除</mdui-menu-item>
                    <mdui-divider></mdui-divider>
                    <mdui-menu-item id="bookCtxScrape" data-action="scrape" icon="image">刮削封面</mdui-menu-item>
                    <mdui-menu-item id="bookCtxToggleRead" data-action="toggleRead" icon="task_alt">标记已读</mdui-menu-item>
                </mdui-menu>
            </mdui-dropdown>
        `;

        var menu = this;

        $('#bookCtxMenu').on('click', '[data-action]', function () {
            if (!menu._book) return;
            menu.dispatchEvent(new CustomEvent('action', {
                detail: { action: $(this).attr('data-action'), book: menu._book },
                bubbles: true
            }));
            $('#bookCtxDropdown')[0].open = false;
        });
    }

    bind(container, getRow) {
        if (this._container) {
            $(this._container).off('contextmenu touchstart');
        }
        if (this._root) {
            $(this._root).off('click touchend touchmove');
        }

        this._container = container;
        this._root = container.closest('#container') || container;
        this._getRow = getRow;

        var menu = this;
        var $root = $(this._root);
        var $cards = $(container);

        $root.on('click', function (e) {
            if (!$(e.target).closest('#bookCtxDropdown').length) {
                $('#bookCtxDropdown')[0].open = false;
            }
        }).on('touchend touchmove', function () {
            clearTimeout(menu._pressTimer);
        });

        $cards.on('contextmenu', '.card-view-item', function (e) {
            var index = $(this).attr('data-index');
            if (index === undefined || index === '') return;
            var book = menu._getRow(Number(index));
            if (!book) return;
            e.preventDefault();
            var oe = e.originalEvent || e;
            menu.show(book, oe.clientX, oe.clientY);
            e.stopPropagation();
        });

        $cards.on('touchstart', '.card-view-item', function () {
            var $item = $(this);
            menu._pressTimer = setTimeout(function () {
                var index = $item.attr('data-index');
                if (index === undefined || index === '') return;
                var book = menu._getRow(Number(index));
                if (!book) return;
                var el = $item.find('book-card')[0] || $item[0];
                var r = el.getBoundingClientRect();
                menu.show(book, r.left + r.width / 2, r.top + r.height / 2);
            }, 500);
        });
    }

    show(book, x, y) {
        this._book = book;
        var $toggleRead = $('#bookCtxToggleRead');
        $toggleRead[0].icon = book.hasReadTag ? 'radio_button_unchecked' : 'task_alt';
        $toggleRead[0].textContent = book.hasReadTag ? '标记未读' : '标记已读';
        $('#bookCtxAnchor').css({ left: x + 'px', top: y + 'px' });
        var dropdown = $('#bookCtxDropdown')[0];
        requestAnimationFrame(function () { dropdown.open = true; });
    }

    destroy() {
        clearTimeout(this._pressTimer);
        this._book = null;
        this._container = null;
        this._root = null;
        this._getRow = null;
        $('#bookCtxDropdown')[0].open = false;
    }
}

customElements.define('book-context-menu', BookContextMenu);
