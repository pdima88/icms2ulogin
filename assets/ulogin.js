if ( (typeof jQuery === 'undefined') && !window.jQuery ) {
    document.write(unescape("%3Cscript type='text/javascript' src='//ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js'%3E%3C/script%3E%3Cscript type='text/javascript'%3EjQuery.noConflict();%3C/script%3E"));
} else {
    if((typeof jQuery === 'undefined') && window.jQuery) {
        jQuery = window.jQuery;
    } else if((typeof jQuery !== 'undefined') && !window.jQuery) {
        window.jQuery = jQuery;
    }
}


function uloginCallback(token){
    jQuery.ajax({
        url: '/ulogin/login',
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {token: token},
        success: function (data) {
            switch (data.answerType) {
                case 'error':
                    uloginMessage(data.title, data.msg, data.answerType);
                    break;
                case 'success':
                    if (jQuery('.ulogin_accounts').length > 0){
                        adduLoginNetworkBlock(data.networks, data.title, data.msg);
                    } else {
                        location.reload();
                    }
                    break;
                case 'verify':
                    // Верификация аккаунта
                    uLogin.mergeAccounts(token);
                    uloginMessage(data.title, data.msg, data.answerType);
                    break;
                case 'merge':
                    // Синхронизация аккаунтов
                    uLogin.mergeAccounts(token, data.existIdentity);
                    uloginMessage(data.title, data.msg, data.answerType);
                    break;
            }
        }
    });
}

function uloginMessage(title, msg, answerType) {
    var mess = (title != '') ? title + '<br>' : '';
    mess += (msg != '') ? msg : '';

    var class_msg = 'message_';
    if (jQuery.inArray(answerType, ['error','success']) >= 0) {
        class_msg += answerType;
    } else {
        class_msg += 'info';
    }

    mess = '<div class="' + class_msg + '">' + mess + '</div>';

    var sess_messages = jQuery('.sess_messages');
    if (sess_messages.length > 0){
        sess_messages.append(mess);
    } else {
        var body =  jQuery('#body');
        if (body.length > 0) {
            body.prepend('<div class="sess_messages">' + mess + '</div>');
        }
    }
}

function uloginDeleteAccount(network){
    console.log(network);
    jQuery.ajax({
        url: '/ulogin/delete_account',
        type: 'POST',
        dataType: 'json',
        cache: false,
        data: {network: network},
        error: function (data, textStatus, errorThrown) {
            alert('Не удалось выполнить запрос');
        },
        success: function (data) {
            switch (data.answerType) {
                case 'error':
                    uloginMessage(data.title, data.msg, 'error');
                    break;
                case 'success':
                    var nw = jQuery('.ulogin_accounts').find('[data-ulogin-network='+network+']');
                    if (nw.length > 0) nw.hide();
                    uloginMessage(data.title, data.msg, 'success');
                    break;
            }
        }
    });
}


function adduLoginNetworkBlock(networks, title, msg) {
    var uAccounts = jQuery('.ulogin_accounts');

    uAccounts.each(function(){
        for (var uid in networks) {
            var network = networks[uid],
                uNetwork = jQuery(this).find('[data-ulogin-network='+network+']');

            if (uNetwork.length == 0) {
                var onclick = '';
                if (jQuery(this).hasClass('can_delete')) {
                    onclick = ' onclick="uloginDeleteAccount(\'' + network + '\')"';
                }
                jQuery(this).append(
                    '<div data-ulogin-network="' + network + '" class="ulogin_provider big_provider ' + network + '_big"' + onclick + '></div>'
                );
                uloginMessage(title, msg, 'success');
            } else {
                if (uNetwork.is(':hidden')) {
                    uloginMessage(title, msg, 'success');
                }
                uNetwork.show();
            }
        }
    });
}