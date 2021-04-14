document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.fksdbexport.js-renderer').forEach((el) => {
        const data = JSON.parse(el.getAttribute('data'));
        const f = new Function('let data = arguments[0]; let container = arguments[1];' + el.getAttribute('data-js'));
        f(data, el);
    });
});
