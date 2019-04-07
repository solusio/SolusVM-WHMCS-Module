$(function () {
    window.solusvmpro_get_and_fill_client_data = function (vserverid) {
        if (typeof vserverid === 'undefined') {
            return false;
        }
        $.ajax({
            method: "GET",
            url: "modules/servers/solusvmpro/get_client_data.php",
            data: {vserverid: vserverid},
            cache: false,
            dataType: 'json'/*,
             timeout: 2000*/
        }).done(function (data) {
            $('#displayState').html(data.displaystatus);

            if (data.displaybandwidthbar) {
                $('#displaybandwidthbarInfoSpan1').html(data.bandwidthused);
                $('#displaybandwidthbarInfoSpan2').html(data.bandwidthtotal);
                $('#displaybandwidthbarInfoSpan3').html(data.bandwidthfree);
                var bandwidthProgressbar = $('#bandwidthProgressbar');
                bandwidthProgressbar.attr('aria-valuenow', data.bandwidthpercent).css('width', data.bandwidthpercent + '%').css('background-color', data.bandwidthcolor);
                bandwidthProgressbar.html(data.bandwidthpercent + '%');
                $('#displaybandwidthbar').show();
            }

            if (data.displaymemorybar) {
                $('#displaymemorybarInfoSpan1').html(data.memoryused);
                $('#displaymemorybarInfoSpan2').html(data.memorytotal);
                $('#displaymemorybarInfoSpan3').html(data.memoryfree);
                var memoryProgressbar = $('#memoryProgressbar');
                memoryProgressbar.attr('aria-valuenow', data.memorypercent).css('width', data.memorypercent + '%').css('background-color', data.memorycolor);
                memoryProgressbar.html(data.memorypercent + '%');
                $('#displaymemorybar').show();
            }

            if (data.displayhddbar) {
                $('#displayhddbarInfoSpan1').html(data.hddused);
                $('#displayhddbarInfoSpan2').html(data.hddtotal);
                $('#displayhddbarInfoSpan3').html(data.hddfree);
                var hddProgressbar = $('#hddProgressbar');
                hddProgressbar.attr('aria-valuenow', data.hddpercent).css('width', data.hddpercent + '%').css('background-color', data.hddcolor);
                hddProgressbar.html(data.hddpercent + '%');
                $('#displayhddbar').show();
            }

            if (data.controlpanellink) {
                $("#controlpanellink").attr("onclick", "window.open('" + data.controlpanellink + "','_blank')");
            }

            var optionsIds = ["displayreboot", "displayshutdown", "displayboot", "displayconsole", "displayhtml5console", "displayvnc", "displayrootpassword", "displayhostname", "displayvncpassword", "displayrescuemode", "displaypanelbutton", "displayclientkeyauth", "displaytunenable", "displaytundisable"];

            var showOptions = false;
            optionsIds.forEach(function (v) {
                if (data.hasOwnProperty(v)) {
                    if (data[v] == 1) {
                        $('#' + v).show();
                        showOptions = true;
                    }
                }
            });
            if (showOptions) {
                $('#showOptions').show();
            }

            var itemsDataIds = ["ipcsv"];
            itemsDataIds.forEach(function (v) {
                if (data.hasOwnProperty(v)) {
                    $('#' + v).html(data[v]);
                }
            });

            var itemsShowIds = ["displaygraphs", "displayips", "clientkeyautherror", "displaypanelbutton"];
            itemsShowIds.forEach(function (v) {
                if (data.hasOwnProperty(v)) {
                    if (data[v] == 1) {
                        $('#' + v).show();
                    }
                }
            });

            if (data.displaytrafficgraph == 1) {
                $('#trafficgraph').show();
                $('#trafficgraphurlImg').attr('src', data.trafficgraphurl);
            }
            if (data.displayloadgraph == 1) {
                $('#loadgraph').show();
                $('#loadgraphurlImg').attr('src', data.loadgraphurl);
            }
            if (data.displaymemorygraph == 1) {
                $('#memorygraph').show();
                $('#memorygraphurlImg').attr('src', data.memorygraphurl);
            }
            if (data.displayhddgraph == 1) {
                $('#hddgraph').show();
                $('#hddgraphurlImg').attr('src', data.hddgraphurl);
            }

            //rescueMode
            if (data.rescuemode == 0) {
                $('#rescueEnabled').show();
                $('#rescueDisabled').remove();
            }else if(data.rescueData){
                $('#rescueDisabled').show();
                $('#rescueEnabled').remove();

                $('#rescueip').html(data.rescueData.ip);
                $('#rescueport').html(data.rescueData.port);
                $('#rescueuser').html(data.rescueData.user);
                $('#rescuepassword').html(data.rescueData.password);
            }


        }).fail(function (jqXHR, textStatus) {
            $('#displayState').hide();
            $('#displayStateUnavailable').show();
        });

        return true;
    }
});

