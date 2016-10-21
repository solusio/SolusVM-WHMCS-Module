$(function () {
    window.solusvmpro_hostname = function (vserverid, lang, token) {
        token = typeof token !== 'undefined' ? token : "";
        if (typeof vserverid === 'undefined') {
            return false;
        }

        $('#changehostname').on('click', function () {
            var button = $(this);
            var newhostname = $('#newhostname').val();
            newhostname = newhostname.trim();

            var msgSuccess = $('#hostnameMsgSuccess');
            var msgError = $('#hostnameMsgError');
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
            if (newhostname === '') {
                showSuccessOrErrorMsg(false, lang['solusvmpro_invalidHostname']);
                return false;
            }

            button.html('<span class="glyphicon glyphicon-refresh spinning"></span> ' + lang['solusvmpro_change']);
            button.prop('disabled', true);
            var ajaxData = {
                vserverid: vserverid,
                modop: 'custom',
                a: 'ChangeHostname',
                newhostname: newhostname,
                ajax: 1,
                ac: 'Custom_ChangeHostname'
            };
            $.ajax({
                /*type: "POST",*/
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

