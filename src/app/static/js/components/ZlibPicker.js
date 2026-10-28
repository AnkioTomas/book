/**
 * Z-Library 搜索下载弹窗：搜 → 选 → 服务端下载入库。
 */
class ZlibPicker extends HTMLElement {
    constructor() {
        super();
        this._page = 1;
        this._query = '';
        this._busy = false;
    }

    connectedCallback() {
        if (this._ready) return;
        this._ready = true;
        this.innerHTML = `
<style>
.zlib-body{display:flex;flex-direction:column;gap:12px;min-height:0;height:min(calc(var(--vh, 100vh) * 0.68),600px);width:100%;box-sizing:border-box}
.zlib-search{flex-shrink:0}
.zlib-search mdui-text-field{width:100%;display:block}
.zlib-search mdui-button-icon[slot="end-icon"]{width:2rem;height:2rem;font-size:1.25rem}
.zlib-search mdui-button-icon[slot="end-icon"]::part(button){width:2rem;height:2rem}
.zlib-results{flex:1;min-height:0;overflow:auto;border:1px solid rgba(var(--mdui-color-outline-variant),.4);border-radius:12px;padding:4px 0}
.zlib-item{display:flex;gap:12px;align-items:center;padding:12px 16px;cursor:default}
.zlib-item:hover{background:rgba(var(--mdui-color-on-surface),.06)}
.zlib-cover{width:48px;height:68px;border-radius:6px;object-fit:cover;flex-shrink:0;background:rgba(var(--mdui-color-surface-container-highest),1)}
.zlib-meta{flex:1;min-width:0;display:flex;flex-direction:column;gap:2px}
.zlib-title{font-weight:600;line-height:1.3}
.zlib-sub{font-size:.8125rem;color:rgb(var(--mdui-color-on-surface-variant))}
.zlib-empty{padding:24px 16px;text-align:center;color:rgba(var(--mdui-color-on-surface-variant),.75);font-size:.875rem}
.zlib-actions{display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;padding-top:4px}
</style>
<mdui-dialog id="zlibDialog" class="zlib-dialog" headline="Z站搜书" close-on-overlay-click close-on-esc>
  <div class="zlib-body">
    <div class="zlib-search">
      <mdui-text-field id="zlibQuery" label="书名 / 作者" clearable>
        <mdui-button-icon slot="end-icon" icon="search" id="zlibSearchBtn" title="搜索"></mdui-button-icon>
      </mdui-text-field>
    </div>
    <div class="zlib-results" id="zlibResults">
      <div class="zlib-empty">输入关键词搜索 Z-Library</div>
    </div>
    <div class="zlib-actions">
      <mdui-button id="zlibCloseBtn" variant="text">关闭</mdui-button>
    </div>
  </div>
</mdui-dialog>`;
        this.bind();
    }

    bind() {
        var self = this;
        $('#zlibSearchBtn', this).on('click', function () { self.runSearch(1); });
        $('#zlibQuery', this).on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                self.runSearch(1);
            }
        });
        $('#zlibCloseBtn', this).on('click', function () { self.close(); });
        $('#zlibResults', this).on('click', '[data-zlib-dl]', function () {
            var idx = parseInt($(this).attr('data-zlib-dl'), 10);
            if (!isNaN(idx)) self.download(idx);
        });
    }

    open(preset) {
        this.connectedCallback();
        this._page = 1;
        this._results = [];
        var q = String(preset || '').trim();
        $('#zlibQuery', this).val(q);
        $('#zlibResults', this).html('<div class="zlib-empty">输入关键词搜索 Z-Library</div>');
        $('#zlibDialog', this)[0].open = true;
        if (q) this.runSearch(1);
    }

    close() {
        var dlg = $('#zlibDialog', this)[0];
        if (dlg) dlg.open = false;
    }

    destroy() {
        this.close();
        $('#zlibSearchBtn,#zlibQuery,#zlibCloseBtn,#zlibResults', this).off();
    }

    runSearch(page) {
        var self = this;
        var q = String($('#zlibQuery', this).val() || '').trim();
        if (!q) return $.toaster.warn('请输入书名或作者');
        this._query = q;
        this._page = page || 1;
        var $box = $('#zlibResults', this);
        $box.html('<div class="zlib-empty">搜索中…</div>');
        $(this).showLoading();
        $.request.postForm('/index/zlib/search', {
            q: q,
            page: this._page,
            limit: 20
        }, function (res) {
            $(self).closeLoading();
            if (res.code !== 200 || !res.data || !res.data.length) {
                self._results = [];
                $box.html('<div class="zlib-empty">' + $.escapeHtml(res.msg || '无结果') + '</div>');
                return;
            }
            self._results = res.data;
            self.render();
        }, function () {
            $(self).closeLoading();
            self._results = [];
            $box.html('<div class="zlib-empty">搜索失败</div>');
        });
    }

    render() {
        var html = '';
        for (var i = 0; i < this._results.length; i++) {
            html += this.renderItem(this._results[i], i);
        }
        var $box = $('#zlibResults', this);
        $box.html(html || '<div class="zlib-empty">无结果</div>');
        $.emitter.emit('imageLoader', $box[0]);
    }

    renderItem(book, index) {
        var cover = book.cover ? ('/proxy/' + encodeURIComponent(book.cover)) : '';
        var title = $.escapeHtml(book.title || '未知书名');
        var author = $.escapeHtml(book.author || '未知作者');
        var ext = $.escapeHtml((book.extension || 'epub').toUpperCase());
        var size = book.filesize ? this.fmtSize(book.filesize) : '';
        var sub = [author, ext, size].filter(Boolean).join(' · ');
        var img = cover
            ? '<image-loader class="zlib-cover" src="' + cover + '"></image-loader>'
            : '<div class="zlib-cover"></div>';
        return '<div class="zlib-item">'
            + img
            + '<div class="zlib-meta">'
            + '<div class="zlib-title">' + title + '</div>'
            + '<div class="zlib-sub">' + sub + '</div>'
            + '</div>'
            + '<mdui-button data-zlib-dl="' + index + '" icon="download" variant="tonal">入库</mdui-button>'
            + '</div>';
    }

    fmtSize(n) {
        n = Number(n) || 0;
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(1) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    download(index) {
        var self = this;
        var book = this._results[index];
        if (!book || this._busy) return;
        this._busy = true;
        var $btn = $('[data-zlib-dl="' + index + '"]', this);
        $btn.attr('loading', '');
        var done = function () {
            self._busy = false;
            $btn.removeAttr('loading');
        };
        $.request.postForm('/index/zlib/download', {
            id: book.id,
            hash: book.hash,
            title: book.title || '',
            author: book.author || '',
            extension: book.extension || 'epub'
        }, function (res) {
            done();
            if (res.code !== 200) {
                return $.toaster.error(res.msg || '提交失败');
            }
            $.toaster.success(res.msg || '已提交后台下载');
        }, function () {
            done();
            $.toaster.error('下载请求失败');
        });
    }
}

customElements.define('zlib-picker', ZlibPicker);
