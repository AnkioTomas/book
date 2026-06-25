/**
 * 统计面板交互：仅处理“从未翻开”清单的打开。
 * @file stats.js
 */

window.pageLoadFiles = [
    'Toaster',
];

window.pageOnLoad = () => {
    const supportedReaderExt = ['.epub', '.mobi', '.azw', '.azw3', '.pdf'];

    const openReader = (filename, title) => {
        if (!filename) {
            return;
        }
        const ext = '.' + (filename.split('.').pop() || '').toLowerCase();
        if (!supportedReaderExt.includes(ext)) {
            $.toaster.warning('该文件格式暂不支持阅读器打开');
            return;
        }
        const readerUrl = `/index/book/reader?file=${encodeURIComponent(filename)}&title=${encodeURIComponent(title || filename)}`;
        window.open(readerUrl, '_blank', 'noopener');
    };

    $('#container').on('click', '.js-open-reader', function () {
        const target = $(this);
        openReader(target.data('file') || '', target.data('title') || '');
    });
};
