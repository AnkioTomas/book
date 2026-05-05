/**
 * 仪表盘页面交互逻辑
 * @file dashboard.js
 */

window.pageLoadFiles = [
    '/js/components/Book.js'
];

window.pageOnLoad = () => {
    const onResumeReadingClick = (event) => {
        const target = event.target.closest('.js-resume-reading');
        if (!target) {
            return;
        }
        const file = target.getAttribute('data-file') || '';
        if (!file) {
            return;
        }
        const title = target.getAttribute('data-title') || file;
        const readerUrl = `/admin/reader?file=${encodeURIComponent(file)}&title=${encodeURIComponent(title)}`;
        window.open(readerUrl, '_blank', 'noopener');
    };
    document.addEventListener('click', onResumeReadingClick);
    window.pageOnUnLoad = () => {
        document.removeEventListener('click', onResumeReadingClick);
    };
};




