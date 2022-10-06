$(function () {
    window.solusvmpro_rescuemode = function (vserverid, lang, token, area) {
        token = typeof token !== 'undefined' ? token : "";
        if (typeof vserverid === 'undefined') {
            return false;
        }

        $('.changerescuemode').on('click', function () {
            var button = $(this);
            var rescueAction = 'rescueenable';
            var rescueValue = $('#rescueImage').val();

            if(typeof rescueValue === "undefined"){
                rescueValue = true;
                rescueAction = 'rescuedisable';
            }

            var msgSuccess = $('#rescuemodeMsgSuccess');
            var msgError = $('#rescuemodedMsgError');
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

            button.html('<span class="glyphicon glyphicon-refresh spinning"></span> ' + lang['solusvmpro_processing']);
            button.prop('disabled', true);
            var ajaxData = {
                modop: 'custom',
                a: 'ChangeRescueMode',
                rescueAction: rescueAction,
                rescueValue: rescueValue,
                ajax: 1,
                ac: 'Custom_ChangeRescueMode'
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

                button.html(lang['solusvmpro_refresh']);
                if(area == 'admin'){
                    button.attr('onclick', 'loadcontrol();');
                    $('#solusvmpro_collapseSeven .btn').removeClass('changerescuemode');
                }else{
                    button.attr('onclick', 'location.reload();');
                    $('#solusvmpro_collapseSeven .btn').removeClass('changerescuemode');
                }
                button.prop('disabled', false);

            }).fail(function (jqXHR, textStatus) {
                //console.log(jqXHR);
                //console.log(textStatus);
            });
        });

        return true;
    }
});

