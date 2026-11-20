window.pageLoadFiles = [
    'Form',
];

window.pageOnLoad = function () {
    $.form.manage('/index/booksetting/bookConfig', '#bookForm');
};
