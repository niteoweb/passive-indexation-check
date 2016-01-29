var passiveIndexationCheckJS = (function ($) {

    var adminNoticeId = '#passiveIdentificationCheckNotice';

    var _private = {
        /**
         *
         * Send AJAX post request to WordPress AJAX url.
         *
         * @param  {string}   formId      Form id.
         * @param  {object}   extraParams Extra parameters to be added besides form data.
         * @param  {Function} callback    Callback for returning data.
         *
         * @return {void}
         *
         */
        sendRequest: function(formId, extraParams, callback)
        {
            var data = false;

            if (formId) {
                data = $('form#' + formId).serialize();
            }
            if (extraParams) {
                data = data ? data + '&' + $.param(extraParams) : $.param(extraParams);
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function (data, successCode, jqXHR) {
                    data.reqStatus = jqXHR.status;
                    callback(data);
                },
                error: function (jqXHR, textStatus, error) {
                    var data = {
                        reqStatus: jqXHR.status
                    };
                    callback(data);
                }
            });
        },
        /**
         *
         * Handle WP notification message.
         *
         * @param  {object} data Data that was returned by plugin AJAX action.
         *
         * @return {void}
         */
        triggerMessage: function(data) {
            $(adminNoticeId).removeClass('error updated').hide();

            if (_private.isRequestSuccessfull(data)) {
                if (data.success) {
                    $(adminNoticeId).addClass('updated').
                        html(_private.wrapMessage(data.data.msg));                    
                } else {
                    $(adminNoticeId).addClass('error').
                        html(_private.wrapMessage(data.data.msg));
                }
            } else {
                var msg = 'There was a ' + data.reqStatus + ' error while trying to complete your request.';
                $(adminNoticeId).addClass('error').
                    html(_private.wrapMessage(msg));
            }

            $(adminNoticeId).show(300);

        },
        isRequestSuccessfull: function(data) {
            if (data.reqStatus >= 200 && data.reqStatus <= 226) {
                return true;
            }
            return false;
        },
        /**
         *
         * Helper function for wrapping provided string into paragraph.
         * 
         * @param  {string} message String you wish to wrap in paragraph (<p>).
         * 
         * @return {string}         Returns wrapped string.
         * 
         */
        wrapMessage: function(message) {
            return '<p>' + message + '</p>';
        },
        updateEmailsList: function(emails)
        {
            var emailsHtml = '';
            for (var i=0; i<emails.length; i++) {
                var email = emails[i];
                emailsHtml += '<span>' + email + '<span>';
                emailsHtml += '<a onclick="passiveIndexationCheckJS.deleteEmail(\'' + email + '\');">';
                emailsHtml += ' <span class="dashicons dashicons-no-alt" style="color: #d9534f;"></span>';
                emailsHtml += '</a><br>';
            }
            $('#passiveIndexationCheckEmailsList').html(emailsHtml);
        },

        // Request functions

        /**
         *
         * Add email request.
         *
         * Sends form data to backend and adds email to notification list.
         *
         */
        addEmail: function()
        {
            var extraParams = {
                action: 'passive_indexation_check_add_email'
            };

            _private.sendRequest('passiveIndexationCheckForm', extraParams, function (data) {
                if (_private.isRequestSuccessfull(data)) {
                    if (data.success) {
                        _private.updateEmailsList(data.data.notificationEmails);
                        $('input[name=added_notifier]').val('');                        
                    }
                }
                _private.triggerMessage(data);
            });
        },
        /**
         *
         * Update plugin settings request.
         *
         * @param  {string} formId Form id that we will send serialized to the backend.
         *
         * @return {void}
         *
         */
        updateSettings: function(formId)
        {
            var extraParams = {
                action: 'passive_indexation_check_update_settings'
            };

            _private.sendRequest(formId, extraParams, function (data) {
                if (_private.isRequestSuccessfull(data)) {
                    if (data.success) {
                        _private.updateEmailsList(data.data.notificationEmails);
                        $('#passiveIndexationCheckDays').val(data.data.notificationTime);                        
                    }
                }
                _private.triggerMessage(data);
            });
        },

    };

    var _public = {
        /**
         *
         * Send delete email from notifiers to backend.
         *
         * @param  {string} email Email to delete.
         *
         * @return {void}
         * 
         */
        deleteEmail: function(email)
        {
            var extraParams = {
                action: 'passive_indexation_check_delete_email',
                delete_notifier: email
            };

            _private.sendRequest('passiveIndexationCheckForm', extraParams, function (data) {
                if (_private.isRequestSuccessfull(data)) {
                    if (data.success) {
                        _private.updateEmailsList(data.data.notificationEmails);
                    }
                }
                _private.triggerMessage(data);
            });            
        }
    };

    $(document).ready(function() {
        $('#passiveIndexationCheckAddEmail').on('click', function (event) {
            event.preventDefault();
            _private.addEmail();
        });
        $('form#passiveIndexationCheckForm').on('submit', function (event) {
            event.preventDefault();
            _private.updateSettings('passiveIndexationCheckForm');
        });
    });

    return _public;

})(jQuery);