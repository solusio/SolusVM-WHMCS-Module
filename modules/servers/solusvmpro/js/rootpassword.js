$(function () {
    window.solusvmpro_rootpassword = function (vserverid, lang, token) {
        token = typeof token !== 'undefined' ? token : "";
        if (typeof vserverid === 'undefined') {
            return false;
        }

        $('#changerootpassword').on('click', function () {
            var button = $(this);
            var newrootpassword = $('#newrootpassword').val();
            var confirmnewrootpassword = $('#confirmnewrootpassword').val();

            var msgSuccess = $('#rootpasswordMsgSuccess');
            var msgError = $('#rootpasswordMsgError');
            msgSuccess.hide();
            msgError.hide();
            var showSuccessOrErrorMsg = function (success, msg) {
                msgSuccess.hide();
                msgError.hide();
                if (success) {
                    msgSuccess.html(msg);
                    msgSuccess.show();
                } else {
                    msgError.html(msg);
                    msgError.show();
                }
            };
            if (newrootpassword === '') {
                showSuccessOrErrorMsg(false, lang['solusvmpro_invalidRootpassword']);
                return false;
            }
            if (newrootpassword !== confirmnewrootpassword) {
                showSuccessOrErrorMsg(false, lang['solusvmpro_confirmErrorPassword']);
                return false;
            }


            button.html('<span class="glyphicon glyphicon-refresh spinning"></span> ' + lang['solusvmpro_change']);
            button.prop('disabled', true);
            var ajaxData = {
                vserverid: vserverid,
                modop: 'custom',
                a: 'ChangeRootPassword',
                newrootpassword: newrootpassword,
                ajax: 1,
                ac: 'Custom_ChangeRootPassword'
            };
            $.ajax({
                url: document.location.href + token,
                data: ajaxData,
                cache: false,
                dataType: 'json'/*,
                 timeout: 2000*/
            }).done(function (data) {

                var dataMsg = '';
                if (data.hasOwnProperty("msg")) {
                    dataMsg = data.msg;
                }

                var dataSuccess = false;
                if (data.hasOwnProperty("success")) {
                    dataSuccess = data.success;
                }

                showSuccessOrErrorMsg(dataSuccess, dataMsg);

                button.html(lang['solusvmpro_change']);
                button.prop('disabled', false);

            }).fail(function (jqXHR, textStatus) {
                //console.log(jqXHR);
                //console.log(textStatus);
            });
        });

        return true;
    }
});

