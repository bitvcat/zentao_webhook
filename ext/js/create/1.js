$(function () {
    $('#type').change(function () {
        var type = $(this).val();
        $('#sendTypeTR').toggle(type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup' && type != 'mmuser');
        $('#secretTR').toggle(type == 'dinggroup' || type == 'feishugroup');
        $('#urlTR').toggle(type != 'dinguser' && type != 'wechatuser' && type != 'feishuuser');
        $('.dinguserTR').toggle(type == 'dinguser');
        $('.wechatTR').toggle(type == 'wechatuser');
        $('.feishuTR').toggle(type == 'feishuuser');
        $('.mmTR').toggle(type == 'mmuser');
        $('#paramsTR').toggle(type != 'bearychat' && type != 'dinggroup' && type != 'dinguser' && type != 'wechatuser' && type != 'wechatgroup' && type != 'feishuuser' && type != 'feishugroup' && type != 'mmuser');
        $('#urlNote').html(urlNote[type]);
    });

    $('.objectType').click(function () {
        if ($(this).prop('checked')) {
            $(this).parent().parent().next().find('input[type=checkbox]').attr('checked', 'checked');
        }
        else {
            $(this).parent().parent().next().find('input[type=checkbox]').removeAttr('checked');
        }
    });

    $('#allParams, #allActions').click(function () {
        if ($(this).prop('checked')) {
            $(this).parents('tr').find('input[type=checkbox]').attr('checked', 'checked');
        }
        else {
            $(this).parents('tr').find('input[type=checkbox][disabled!=disabled]').removeAttr('checked');
        }
    });

    $('#name').focus();
    $('#type').change();
    $('#paramstext').attr('disabled', 'disabled');
});
