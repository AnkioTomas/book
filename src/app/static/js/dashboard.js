/**
 * 仪表盘页面交互逻辑
 * @file dashboard.js
 */

window.pageLoadFiles = [
    '/js/components/Book.js'
];

window.pageOnLoad = () => {

    $('.js-resume-reading').on('click',function () {
        let target = $(this)
        const file = target.data('file') || '';
        if (!file) {
            return;
        }
        const title = target.data('title') || file;
        const readerUrl = `/admin/reader?file=${encodeURIComponent(file)}&title=${encodeURIComponent(title)}`;
        window.open(readerUrl, '_blank', 'noopener');
    });
};




