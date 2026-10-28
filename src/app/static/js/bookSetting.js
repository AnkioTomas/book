window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function () {
    $.form.manage('/index/calibre/bookConfig', '#bookForm');
};
