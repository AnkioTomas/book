/**
 * 仪表盘页面交互逻辑
 * @file dashboard.js
 */

window.pageLoadFiles = [
    'Toaster',
    '/components/dataTable/CardView.css',
    '/js/components/Book.js'
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
        const readerUrl = `/admin/reader?file=${encodeURIComponent(filename)}&title=${encodeURIComponent(finalTitle)}`;
        window.open(readerUrl, '_blank', 'noopener');
    };

    $('.js-resume-reading').on('click', function () {
        const target = $(this);
        openReader(target.data('file') || '', target.data('title') || '');
    });

    // 最近添加：后端渲染，仅绑定整卡点击
    $('#recentAdded').on('click', '.js-open-reader', function () {
        const target = $(this);
        openReader(target.data('file') || '', target.data('title') || '');
    });
};
