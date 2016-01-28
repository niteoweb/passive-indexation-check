var passiveIndexationCheckJS = (function ($) {
 
    function sendRequest(params, callback)
    {
        var nonce = jQuery('input[name=passive_indexation_check_nonce]').val();
        params['nonce'] = nonce;

        if (nonce) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: params,
                success: function (data) {
                    callback(data);
                },
                error: function () {
                    callback(false);
                }
            });
        } else {
            callback(false);
        }
    }

    function triggerRequestError()
    {
        swal(
            {
                title: 'Error',
                text: 'An unknown error occured while requesting the server. Please try again.',
                timer: 4000,
                type: 'error'
            }
        );
    }

    function triggerError(errorMsg)
    {
        swal(
            {
                title: 'Error',
                text: errorMsg,
                timer: 4000,
                type: 'error'
            }
        );
    }

    function addEmail()
    {
        var email = jQuery('input[name=passive_indexation_check_emails]').val();
        var params = {
            action: 'passive_indexation_check_add_email',
            addedNotifier: email
        };

        sendRequest(params, function (data) {
            if (data) {
                if (data.success) {
                    updateEmailsList(data.data.notificationEmails);
                    jQuery('input[name=passive_indexation_check_emails]').val('');
                    swal(
                        {
                            title: 'Email added',
                            text: data.data.msg,
                            timer: 4000,
                            type: 'success'
                        }
                    );
                } else {
                    triggerError(data.data.msg);
                }
            } else {
                triggerRequestError();
            }
        });
    }

    function deleteEmail(email)
    {
        var params = {
            action: 'passive_indexation_check_delete_email',
            deleteNotifier: email
        };

        sendRequest(params, function (data) {
            if (data) {
                if (data.success) {
                    updateEmailsList(data.data.notificationEmails);
                    swal(
                        {
                            title: 'Email deleted',
                            text: data.data.msg,
                            timer: 4000,
                            type: 'success'
                        }
                    );
                } else {
                    triggerError(data.data.msg);
                }
            } else {
                triggerRequestError();
            }
        });
    }

    function updateEmailsList(emails)
    {
        var emailsHtml = '';
        for (var i=0; i<emails.length; i++) {
            var email = emails[i];
            emailsHtml += '<span>' + email + '<span>';
            emailsHtml += '<a onclick="passiveIndexationCheckJS.deleteEmail(\'' + email + '\');">';
            emailsHtml += ' <span class="dashicons dashicons-no-alt" style="color: #d9534f;"></span>';
            emailsHtml += '</a><br>';
        }
        jQuery('#passiveIndexationCheckEmailsList').html(emailsHtml);
    }

    function updateSettings()
    {
        var params = {
            action: 'passive_indexation_check_update_settings',
            notification_time: jQuery('#passiveIndexationCheckDays option:selected').val()
        };

        sendRequest(params, function (data) {
            if (data) {
                if (data.success) {
                    updateEmailsList(data.data.notificationEmails);
                    jQuery('#passiveIndexationCheckDays').val(data.data.notificationTime);
                    swal(
                        {
                            title: 'Settings updated',
                            text: 'Settings were successfully updated.',
                            timer: 4000,
                            type: 'success'
                        }
                    );
                } else {
                    triggerError(data.data.msg);
                }
            } else {
                triggerRequestError();
            }
        });
    }

    return {
        addEmail: addEmail,
        deleteEmail: deleteEmail,
        updateSettings: updateSettings
    };

})(document, jQuery);