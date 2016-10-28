
//$(document).ready(function () {


$(function () {
    $('.fksdbexport.js-renderer').each(function (e) {
        console.debug(e);
        var data = JSON.parse($(this).attr('data'));
        var f = new Function("var data = arguments[0]; var container = arguments[1];" + $(this).data('js'));
        console.debug(this);

        f.call({}, data, this);
    });
});



//});




