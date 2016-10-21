$(function () {
    window.solusvmpro_vncpassword = function (vserverid, lang, token) {
        token = typeof token !== 'undefined' ? token : "";
        if (typeof vserverid === 'undefined') {
            return false;
        }

        $('#changevncpassword').on('click', function () {
            var button = $(this);
            var newvncpassword = $('#newvncpassword').val();
            var confirmnewvncpassword = $('#confirmnewvncpassword').val();

            var msgSuccess = $('#vncpasswordMsgSuccess');
            var msgError = $('#vncpasswordMsgError');
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
            if (newvncpassword === '') {
                showSuccessOrErrorMsg(false, lang['solusvmpro_invalidVNCpassword']);
                return false;
            }
            if (newvncpassword !== confirmnewvncpassword) {
                showSuccessOrErrorMsg(false, lang['solusvmpro_confirmErrorPassword']);
                return false;
            }


            button.html('<span class="glyphicon glyphicon-refresh spinning"></span> ' + lang['solusvmpro_change']);
            button.prop('disabled', true);
            var ajaxData = {
                vserverid: vserverid,
                modop: 'custom',
                a: 'ChangeVNCPassword',
                newvncpassword: newvncpassword,
                ajax: 1,
                ac: 'Custom_ChangeVNCPassword'
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

