/**
 * 仪表盘页面交互逻辑
 * @file dashboard.js
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
        const finalTitle = title || filename;
        const readerUrl = `/index/book/reader?file=${encodeURIComponent(filename)}&title=${encodeURIComponent(finalTitle)}`;
        window.open(readerUrl, '_blank', 'noopener');
    };

    $('.js-resume-reading').on('click', function () {
        const target = $(this);
        openReader(target.data('file') || '', target.data('title') || '');
    });
};
