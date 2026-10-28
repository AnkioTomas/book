/**
 * 元数据补全：左栏来源、右栏结果，选中后勾选要覆盖的字段。
 * @file MetaFillPicker.js
 */
class MetaFillPicker extends HTMLElement {
    connectedCallback() {
        this._sources = [];
        this._results = [];
        this._current = null;
        this._query = '';
        this._sourceId = '';
        this._aiSource = null;
        this._currentForm = null;

        this.innerHTML = `
<style>
.meta-fill-body{display:flex;flex-direction:column;gap:12px;min-height:0;height:min(calc(var(--vh, 100vh) * 0.68),600px);width:100%;box-sizing:border-box}
.meta-fill-search{flex-shrink:0}
.meta-fill-search mdui-text-field{width:100%;display:block}
.meta-fill-search mdui-button-icon[slot="end-icon"]{width:2rem;height:2rem;font-size:1.25rem}
.meta-fill-search mdui-button-icon[slot="end-icon"]::part(button){width:2rem;height:2rem}
.meta-fill-main{display:flex;gap:0;flex:1;min-height:0;border:1px solid rgba(var(--mdui-color-outline-variant),0.4);border-radius:12px;overflow:hidden}
.meta-fill-sources{width:9.5rem;flex-shrink:0;overflow:auto;background:rgba(var(--mdui-color-surface-container),1);border-right:1px solid rgba(var(--mdui-color-outline-variant),0.35)}
.meta-fill-source{display:block;width:100%;text-align:left;padding:12px 14px;border:0;background:transparent;color:inherit;cursor:pointer;font:inherit}
.meta-fill-source:hover{background:rgba(var(--mdui-color-on-surface),0.06)}
.meta-fill-source.active{background:rgba(var(--mdui-color-secondary-container),1);color:rgb(var(--mdui-color-on-secondary-container));font-weight:600}
.meta-fill-results{flex:1;min-width:0;overflow:auto;padding:4px 0}
.meta-fill-empty{padding:24px 16px;color:rgba(var(--mdui-color-on-surface-variant),0.75);font-size:.875rem}
.meta-fill-item{cursor:pointer;--mdui-comp-list-item-one-line-height:auto;padding:12px 16px}
.meta-fill-row{display:flex;gap:12px;width:100%}
.meta-fill-cover{width:56px;height:78px;border-radius:6px;flex-shrink:0;object-fit:cover;background:rgba(var(--mdui-color-surface-container-highest),1)}
.meta-fill-meta{flex:1;min-width:0}
.meta-fill-title{font-size:1rem;font-weight:600;margin-bottom:4px}
.meta-fill-sub{font-size:.8125rem;color:rgba(var(--mdui-color-on-surface-variant));margin-bottom:4px}
.meta-fill-intro{font-size:.75rem;line-height:1.4;color:rgba(var(--mdui-color-on-surface-variant))}
.meta-fill-actions{display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;padding-top:4px}
.meta-pick-list{display:flex;flex-direction:column;gap:8px;max-height:50vh;overflow:auto;padding:4px 0}
.meta-pick-row{display:flex;gap:10px;align-items:flex-start;padding:8px 4px;border-bottom:1px solid rgba(var(--mdui-color-outline-variant),0.25)}
.meta-pick-row mdui-checkbox{flex-shrink:0;margin-top:2px}
.meta-pick-body{min-width:0;flex:1}
.meta-pick-label{font-size:.8125rem;font-weight:600;margin-bottom:2px}
.meta-pick-diff{font-size:.75rem;color:rgba(var(--mdui-color-on-surface-variant));line-height:1.4;word-break:break-all}
@media (max-width:600px){
.meta-fill-main{flex-direction:column}
.meta-fill-sources{width:auto;display:flex;overflow-x:auto;overflow-y:hidden;border-right:0;border-bottom:1px solid rgba(var(--mdui-color-outline-variant),0.35)}
.meta-fill-source{width:auto;flex-shrink:0;white-space:nowrap;padding:10px 14px;min-height:44px}
}
</style>
<mdui-dialog id="metaFillDialog" class="meta-fill-dialog" headline="元数据补全" close-on-overlay-click close-on-esc>
  <div class="meta-fill-body">
    <div class="meta-fill-search">
      <mdui-text-field id="metaFillQuery" label="搜索书名" clearable>
        <mdui-button-icon slot="end-icon" icon="search" id="metaFillSearchBtn" title="搜索"></mdui-button-icon>
      </mdui-text-field>
    </div>
    <div class="meta-fill-main">
      <div class="meta-fill-sources" id="metaFillSources"></div>
      <div class="meta-fill-results" id="metaFillResults">
        <div class="meta-fill-empty">选择左侧来源并搜索</div>
      </div>
    </div>
    <div class="meta-fill-actions">
      <mdui-button id="metaFillClose" variant="text">关闭</mdui-button>
    </div>
  </div>
</mdui-dialog>

<mdui-dialog id="metaPickDialog" headline="选择要覆盖的字段" close-on-overlay-click close-on-esc>
  <div class="meta-pick-list" id="metaPickList"></div>
  <mdui-button slot="action" id="metaPickCancel" variant="text">取消</mdui-button>
  <mdui-button slot="action" id="metaPickApply" variant="filled">应用所选</mdui-button>
</mdui-dialog>
`;

        var self = this;
        $('#metaFillClose', this).on('click', function () { self.close(); });
        $('#metaPickCancel', this).on('click', function () {
            $('#metaPickDialog', self)[0].open = false;
        });
        $('#metaFillSearchBtn', this).on('click', function () { self.runSearch(); });
        $('#metaFillQuery', this).on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                self.runSearch();
            }
        });
        $('#metaFillSources', this).on('click', '.meta-fill-source', function () {
            self.selectSource($(this).attr('data-id'));
        });
        $('#metaFillResults', this).on('click', '[data-index]', function () {
            var idx = parseInt($(this).attr('data-index'), 10);
            var hit = self._results[idx];
            if (hit) self.openFieldPick(hit);
        });
        $('#metaPickApply', this).on('click', function () { self.applyPicked(); });
    }

    open(query, currentForm) {
        this._query = String(query || '').trim();
        this._currentForm = currentForm || {};
        $('#metaFillQuery', this).val(this._query);
        this.ensureSources(function () {
            $('#metaFillDialog', this)[0].open = true;
            if (this._sourceId) {
                this.runSearch();
            } else if (this._sources.length) {
                this.selectSource(this._sources[0].id);
            }
        }.bind(this));
    }

    close() {
        if (this._aiSource) {
            try { this._aiSource.close(); } catch (e) {}
            this._aiSource = null;
        }
        $('#metaFillDialog', this)[0].open = false;
        $('#metaPickDialog', this)[0].open = false;
    }

    ensureSources(done) {
        var self = this;
        if (this._sources.length) {
            this.renderSources();
            done();
            return;
        }
        $.request.get('/index/meta/sources', {}, function (res) {
            if (res.code === 200 && Array.isArray(res.data)) {
                self._sources = res.data;
            } else {
                self._sources = [
                    { id: 'douban', name: '豆瓣' },
                    { id: 'weread', name: '微信读书' },
                    { id: 'ai', name: 'AI 填充' }
                ];
            }
            self.renderSources();
            done();
        }, function () {
            self._sources = [
                { id: 'douban', name: '豆瓣' },
                { id: 'weread', name: '微信读书' },
                { id: 'ai', name: 'AI 填充' }
            ];
            self.renderSources();
            done();
        });
    }

    renderSources() {
        var html = '';
        for (var i = 0; i < this._sources.length; i++) {
            var s = this._sources[i];
            var active = s.id === this._sourceId ? ' active' : '';
            html += '<button type="button" class="meta-fill-source' + active + '" data-id="'
                + $.escapeHtml(s.id) + '">' + $.escapeHtml(s.name) + '</button>';
        }
        $('#metaFillSources', this).html(html);
    }

    selectSource(id) {
        this._sourceId = id;
        this.renderSources();
        this.runSearch();
    }

    runSearch() {
        var self = this;
        var q = String($('#metaFillQuery', this).val() || '').trim();
        this._query = q;
        if (!this._sourceId) return;
        if (!q) {
            $.toaster.warn('请先输入书名');
            return;
        }

        if (this._sourceId === 'ai') {
            this.runAi(q);
            return;
        }

        var $box = $('#metaFillResults', this);
        $box.html('<div class="meta-fill-empty">搜索中…</div>');
        $(this).showLoading();
        $.request.postForm('/index/meta/search', { source: this._sourceId, q: q }, function (res) {
            $(self).closeLoading();
            if (res.code !== 200 || !res.data || !res.data.length) {
                self._results = [];
                $box.html('<div class="meta-fill-empty">' + $.escapeHtml(res.msg || '无搜索结果') + '</div>');
                return;
            }
            self._results = res.data;
            self.renderResults();
        }, function () {
            $(self).closeLoading();
            self._results = [];
            $box.html('<div class="meta-fill-empty">搜索失败</div>');
        });
    }

    runAi(q) {
        var self = this;
        var $box = $('#metaFillResults', this);
        $box.html('<div class="meta-fill-empty">AI 检索中…</div>');
        if (this._aiSource) {
            try { this._aiSource.close(); } catch (e) {}
        }
        var author = (this._currentForm && this._currentForm.author) || '';
        this._aiSource = $.request.sse('/index/book/aiFill', {
            params: { bookName: q, author: author },
            autoReconnect: false,
            eventHandlers: {
                chunk: function (data) {
                    if (!data || typeof data !== 'object') return;
                    if (data.type === 'error') {
                        self._aiSource.close();
                        $box.html('<div class="meta-fill-empty">' + $.escapeHtml(data.text || 'AI 失败') + '</div>');
                    } else {
                        $box.html('<div class="meta-fill-empty">' + $.escapeHtml(data.text || 'AI 工作中…') + '</div>');
                    }
                },
                result: function (data) {
                    if (!data || typeof data !== 'object') return;
                    var hit = {
                        title: data.bookName || q,
                        author: data.author || '',
                        intro: data.description || '',
                        full_intro: data.description || '',
                        cover_url: data.coverUrl || '',
                        rating: data.rate ? Number(data.rate) * 2 : '',
                        tags: String(data.category || '').split(/\n+/).filter(Boolean),
                        series: data.series || '',
                        source: 'ai'
                    };
                    self._results = [hit];
                    self.renderResults();
                    self.openFieldPick(hit);
                },
                done: function () {
                    if (self._aiSource) self._aiSource.close();
                    self._aiSource = null;
                    if (!self._results.length) {
                        $box.html('<div class="meta-fill-empty">AI 无可用结果</div>');
                    }
                }
            },
            onError: function () {
                $box.html('<div class="meta-fill-empty">AI 连接中断</div>');
            }
        });
    }

    renderResults() {
        var html = '';
        for (var i = 0; i < this._results.length; i++) {
            html += this.renderItem(this._results[i], i);
        }
        var $box = $('#metaFillResults', this);
        $box.html(html || '<div class="meta-fill-empty">无搜索结果</div>');
        $.emitter.emit('imageLoader', $box[0]);
    }

    renderItem(book, index) {
        var cover = encodeURIComponent(book.cover_url || '');
        var title = $.escapeHtml(book.title || '未知书名');
        var meta = $.escapeHtml(
            (book.author || '未知作者')
            + (book.publisher ? ' / ' + book.publisher : '')
            + (book.year ? ' / ' + book.year : '')
            + (book.rating ? ' · ★' + book.rating : '')
        );
        var intro = book.intro
            ? '<div class="meta-fill-intro">' + $.escapeHtml(String(book.intro).substring(0, 120)) + (String(book.intro).length > 120 ? '…' : '') + '</div>'
            : '';
        var coverHtml = book.cover_url
            ? '<image-loader class="meta-fill-cover" src="/proxy/' + cover + '" no-refer></image-loader>'
            : '<div class="meta-fill-cover"></div>';

        return ''
            + '<mdui-list-item data-index="' + index + '" class="meta-fill-item">'
            + '<div class="meta-fill-row">' + coverHtml
            + '<div class="meta-fill-meta"><div class="meta-fill-title">' + title + '</div>'
            + '<div class="meta-fill-sub">' + meta + '</div>' + intro + '</div></div>'
            + '</mdui-list-item><mdui-divider></mdui-divider>';
    }

    fieldDefs(hit) {
        var cur = this._currentForm || {};
        var rate5 = '';
        if (hit.rating !== '' && hit.rating != null && !isNaN(Number(hit.rating))) {
            var r = Number(hit.rating);
            rate5 = String(r > 5 ? Math.floor(r / 2) : Math.round(r));
        }
        var tags = Array.isArray(hit.tags) ? hit.tags.join('\n') : '';
        return [
            { key: 'bookName', label: '书名', old: cur.bookName || '', neu: hit.title || '' },
            { key: 'author', label: '作者', old: cur.author || '', neu: hit.author || '' },
            { key: 'coverUrl', label: '封面', old: cur.coverUrl || '', neu: hit.cover_url || '' },
            { key: 'description', label: '简介', old: cur.description || '', neu: hit.full_intro || hit.intro || '' },
            { key: 'category', label: '标签', old: cur.category || '', neu: tags },
            { key: 'series', label: '系列', old: cur.series || '', neu: hit.series || '' },
            { key: 'rate', label: '评分', old: cur.rate != null ? String(cur.rate) : '', neu: rate5 }
        ];
    }

    openFieldPick(hit) {
        this._current = hit;
        var defs = this.fieldDefs(hit).filter(function (f) {
            return String(f.neu || '').trim() !== '' && String(f.neu) !== String(f.old || '');
        });
        if (!defs.length) {
            $.toaster.info('没有可更新的字段');
            return;
        }
        var html = '';
        for (var i = 0; i < defs.length; i++) {
            var f = defs[i];
            var oldShow = this.clip(f.old, 40) || '（空）';
            var newShow = this.clip(f.neu, 40);
            html += '<label class="meta-pick-row">'
                + '<mdui-checkbox checked data-key="' + $.escapeHtml(f.key) + '"></mdui-checkbox>'
                + '<div class="meta-pick-body"><div class="meta-pick-label">' + $.escapeHtml(f.label) + '</div>'
                + '<div class="meta-pick-diff">' + $.escapeHtml(oldShow) + ' → ' + $.escapeHtml(newShow) + '</div>'
                + '</div></label>';
        }
        $('#metaPickList', this).html(html);
        $('#metaPickDialog', this)[0].open = true;
    }

    clip(s, max) {
        s = String(s || '').replace(/\s+/g, ' ').trim();
        if (s.length <= max) return s;
        return s.substring(0, max) + '…';
    }

    applyPicked() {
        if (!this._current) return;
        var pick = {};
        $('#metaPickList mdui-checkbox', this).each(function () {
            if (this.checked) pick[$(this).attr('data-key')] = true;
        });
        var keys = Object.keys(pick);
        if (!keys.length) {
            $.toaster.warn('请至少选择一个字段');
            return;
        }
        var defs = this.fieldDefs(this._current);
        var data = {};
        for (var i = 0; i < defs.length; i++) {
            var f = defs[i];
            if (pick[f.key]) data[f.key] = f.neu;
        }
        this.dispatchEvent(new CustomEvent('apply', { detail: data, bubbles: true }));
        $('#metaPickDialog', this)[0].open = false;
        this.close();
        $.toaster.success('已填入所选字段，请核对后保存');
    }

    destroy() {
        this.close();
        this._results = [];
        this._sources = [];
    }
}

customElements.define('meta-fill-picker', MetaFillPicker);
