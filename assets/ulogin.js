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
                    if (nw.length > 0) nw.remove();
                    var networks = jQuery('.ulogin_accounts').find('.ulogin_provider:visible');
                    if (networks.length == 0) {
                        jQuery('.ulogin_accounts').parent().find('.delete_str').hide();
                    }
                    uloginMessage(data.title, data.msg, 'success');
                    break;
            }
        }
    });
}


function adduLoginNetworkBlock(networks, title, msg) {
    var uAccounts = jQuery('.ulogin_accounts');

    uAccounts.each(function(){
        for (var network in networks) {
            var nwdata = networks[network],
                uNetwork = jQuery(this).find('[data-ulogin-network='+network+']');

            if (uNetwork.length == 0) {
                var onclick = '';
                if (jQuery(this).hasClass('can_delete')) {
                    onclick = ' onclick="uloginDeleteAccount(\'' + network + '\')"';
                }

                var name = '';
                if (nwdata['first_name'] || nwdata['last_name']) {
                    if (nwdata['first_name']) name = nwdata['first_name'];
                    if (nwdata['last_name']) {
                        if (name) name += ' ';
                        name += nwdata['last_name'];
                    }
                } else if (nwdata['name']) {
                    name = nwdata['name'];
                }
                if (nwdata['email']) {
                    if (name) name += ' ('+nwdata['email']+')';
                    else name = nwdata['email'];
                }
                jQuery(this).append(
                    '<div data-ulogin-network="' + network + '" class="ulogin_provider"' + onclick + '><i class="big_provider '+network+'_big"></i><span>'+name+'</span></div>'
                );
                jQuery(this).parent().find('.delete_str').show();
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