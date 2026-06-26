/**
 * 全页拖拽上传覆盖层
 * @file DragUpload.js
 */
class DragUpload extends HTMLElement {
    connectedCallback() {
        this._counter = 0;
        this._root = this.closest('#container') || this.parentElement;

        var hint = this.getAttribute('hint') || '';

        this.innerHTML = `
            <style>
                :host{display:contents}
                #bookDragOverlay{position:fixed;inset:0;background:rgba(var(--mdui-color-primary),.1);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:1000;pointer-events:none}
                #bookDragOverlay.active{display:flex}
                #bookDragOverlayContent{background:rgba(var(--mdui-color-surface-container-highest));border:3px dashed rgba(var(--mdui-color-primary));border-radius:16px;padding:3rem;text-align:center;box-shadow:var(--mdui-elevation-level3)}
                #bookDragHint{margin-top:.5rem;font-size:.875rem;color:rgba(var(--mdui-color-on-surface-variant))}
            </style>
            <div id="bookDragOverlay">
                <div id="bookDragOverlayContent">
                    <mdui-icon name="upload" style="font-size:64px;color:rgba(var(--mdui-color-primary))"></mdui-icon>
                    <div class="headline-medium mt-3">拖放文件到此处上传</div>
                    ${hint ? `<div id="bookDragHint">支持格式: ${$.escapeHtml(hint)}</div>` : ''}
                </div>
            </div>
        `;

        var self = this;
        var $root = $(this._root);
        var $overlay = $('#bookDragOverlay');

        function prevent(e) { e.preventDefault(); e.stopPropagation(); }

        $root.on('dragover', prevent);
        $root.on('dragenter', function (e) {
            prevent(e);
            if (++self._counter === 1) $overlay.addClass('active');
        });
        $root.on('dragleave', function (e) {
            prevent(e);
            if (--self._counter === 0) $overlay.removeClass('active');
        });
        $root.on('drop', function (e) {
            prevent(e);
            self._counter = 0;
            $overlay.removeClass('active');

            var dt = (e.originalEvent || e).dataTransfer;
            if (!dt || !dt.files || !dt.files.length) return;

            var exts = self.getAcceptList();
            var valid = DragUpload.filterFiles(dt.files, exts);
            if (!valid.length) {
                $.toaster.error('不支持的文件格式，仅支持: ' + exts.join(', '));
                return;
            }
            self.dispatchEvent(new CustomEvent('files', { detail: valid, bubbles: true }));
        });
    }

    getAcceptList() {
        var raw = this.getAttribute('accept') || '';
        if (!raw) return [];
        return raw.split(',').map(function (s) { return s.trim().toLowerCase(); }).filter(Boolean);
    }

    destroy() {
        this._counter = 0;
        $('#bookDragOverlay').removeClass('active');
    }

    static filterFiles(files, extensions) {
        var valid = [];
        for (var i = 0; i < files.length; i++) {
            var ext = '.' + files[i].name.split('.').pop().toLowerCase();
            if (extensions.includes(ext)) valid.push(files[i]);
        }
        return valid;
    }
}

customElements.define('drag-upload', DragUpload);
