var $ = jQuery;
$(document).ready(function () {
    console.debug($('div[data]'));
    $('div[data]').each(function () {
        var data = JSON.parse($(this).attr('data'));
        var f = new Function("console.log(arguments);var data = arguments[0]; var container = arguments[1];" + $(this).data('js'));
        console.debug(this);

        f.call({},data, this);
    });
});




