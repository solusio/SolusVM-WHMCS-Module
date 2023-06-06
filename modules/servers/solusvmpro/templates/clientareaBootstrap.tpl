<script type="text/javascript" src="modules/servers/solusvmpro/js/get_user_data.js"></script>
<script type="text/javascript" src="modules/servers/solusvmpro/js/hostname.js"></script>
<script type="text/javascript" src="modules/servers/solusvmpro/js/rootpassword.js"></script>
<script type="text/javascript" src="modules/servers/solusvmpro/js/vncpassword.js"></script>
<script type="text/javascript" src="modules/servers/solusvmpro/js/rescuemode.js"></script>

{literal}
<script>
    $(function () {

        var reload = false;
        var url = window.location.href;
        patPre = '&serveraction=custom&a=';
        patAr = ['shutdown', 'reboot', 'boot'];
        for (var testPat in patAr) {
            pat = patPre + patAr[testPat];
            if (url.indexOf(pat) > 0) {
                alertModuleCustomButtonSuccess = $('#alertModuleCustomButtonSuccess');
                if (alertModuleCustomButtonSuccess) {
                    url = url.replace(pat, '');
                    window.location.href = url;
                    reload = true;
                }
                break;
            }
        }

        if (!reload) {
            var vserverid = {/literal}{$data.vserverid}{literal};
            window.solusvmpro_get_and_fill_client_data(vserverid);
            window.solusvmpro_hostname(vserverid, {
                'solusvmpro_invalidHostname': '{/literal}{$LANG.solusvmpro_invalidHostname}{literal}',
                'solusvmpro_change': '{/literal}{$LANG.solusvmpro_change}{literal}'
            });
            window.solusvmpro_rootpassword(vserverid, {
                'solusvmpro_invalidRootpassword': '{/literal}{$LANG.solusvmpro_invalidRootpassword}{literal}',
                'solusvmpro_change': '{/literal}{$LANG.solusvmpro_change}{literal}',
                'solusvmpro_confirmRootPassword': '{/literal}{$LANG.solusvmpro_confirmRootPassword}{literal}',
                'solusvmpro_confirmErrorPassword': '{/literal}{$LANG.solusvmpro_confirmErrorPassword}{literal}',
                'solusvmpro_confirmPassword': '{/literal}{$LANG.solusvmpro_confirmPassword}{literal}'
            });
            window.solusvmpro_vncpassword(vserverid, {
                'solusvmpro_invalidVNCpassword': '{/literal}{$LANG.solusvmpro_invalidVNCpassword}{literal}',
                'solusvmpro_change': '{/literal}{$LANG.solusvmpro_change}{literal}',
                'solusvmpro_confirmVNCPassword': '{/literal}{$LANG.solusvmpro_confirmVNCPassword}{literal}',
                'solusvmpro_confirmErrorPassword': '{/literal}{$LANG.solusvmpro_confirmErrorPassword}{literal}',
                'solusvmpro_confirmPassword': '{/literal}{$LANG.solusvmpro_confirmPassword}{literal}'
            });
            window.solusvmpro_rescuemode(vserverid, {
                'solusvmpro_refresh': '{/literal}{$LANG.solusvmpro_refresh}{literal}',
                'solusvmpro_processing': '{/literal}{$LANG.solusvmpro_processing}{literal}'
            });

            var cookieNameForAccordionGroup = 'solusvmpro_activeAccordionGroup_Client';
            var last = document.cookie.replace(new RegExp("(?:(?:^|.*;)\\s*" + encodeURIComponent(cookieNameForAccordionGroup).replace(/[\-\.\+\*]/g, "\\$&") + "\\s*\\=\\s*([^;]*).*$)|^.*$"), "$1")
            $("#solusvmpro_accordion .panel-collapse").removeClass('in');
            if (last == "") {
                last = "solusvmpro_collapseSix";
            }
            if (last !== 'none') {
                $("#" + last).addClass("in");
            }

            $("#solusvmpro_accordion").on('shown.bs.collapse', function () {
                var active = $("#solusvmpro_accordion .in").attr('id');
                document.cookie = cookieNameForAccordionGroup + "=" + active;
            });

            $("#solusvmpro_accordion").on('hidden.bs.collapse', function () {
                var active = 'none';
                document.cookie = cookieNameForAccordionGroup + "=" + active;
            });
        }
    });

</script>
{/literal}

<style>
    .margin-top-20 {
        margin-top: 20px;
    }

    .margin-5-button button {
        margin-top: 5px;
        margin-bottom: 5px;
    }
</style>

<div class="row">
    <div class="col-md-3">
        {$LANG.solusvmpro_status}
    </div>
    <div class="col-md-9" id="displayState">
        {$LANG.solusvmpro_loading}
    </div>
    <div class="col-md-3">
        {$LANG.solusvmpro_rebuild}
    </div>
    <div class="col-md-9">
        <form action="#" method="post">
            <select name="vserver_templates">
                {foreach $data.templates as $item}
                    <option value="{$item}">{$item}</option>
                {/foreach}
                <input type="submit" name="change_os" class="btn btn-primary margin-left-5" value="{$LANG.solusvmpro_submit_rebuild}">
            </select>
        </form>
    </div>
    <div class="col-md-9" id="displayStateUnavailable" style="display: none">
        <strong>{$LANG.solusvmpro_unavailable}</strong>
    </div>

    <div id="displaybandwidthbar" style="display: none">
        <div class="col-md-3 margin-top-20">
            {$LANG.solusvmpro_bandwidth}
        </div>
        <div class="col-md-9 margin-top-20" id="displaybandwidthbarInfo">
            <span id="displaybandwidthbarInfoSpan1"></span> {$LANG.solusvmpro_of} <span
                    id="displaybandwidthbarInfoSpan2"></span> {$LANG.solusvmpro_used} / <span
                    id="displaybandwidthbarInfoSpan3"></span> {$LANG.solusvmpro_free}
        </div>
        <div class="col-md-12">
            <div class="progress">
                <div class="progress-bar" id="bandwidthProgressbar" role="progressbar"
                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                     style="width: 0; min-width: 2em;">
                    0%
                </div>
            </div>
        </div>
    </div>

    <div id="displaymemorybar" style="display: none">
        <div class="col-md-3">
            {$LANG.solusvmpro_memory}
        </div>
        <div class="col-md-9" id="displaymemorybarInfo">
            <span id="displaymemorybarInfoSpan1"></span> {$LANG.solusvmpro_of} <span
                    id="displaymemorybarInfoSpan2"></span> {$LANG.solusvmpro_used} / <span
                    id="displaymemorybarInfoSpan3"></span> {$LANG.solusvmpro_free}
        </div>
        <div class="col-md-12">
            <div class="progress">
                <div class="progress-bar" id="memoryProgressbar" role="progressbar"
                     aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"
                     style="width: 0; min-width: 2em;">
                    0%
                </div>
            </div>
        </div>
    </div>

    <div id="displayhddbar" style="display: none">
        <div class="col-md-3">
            {$LANG.solusvmpro_disk}
        </div>
        <div class="col-md-9" id="displayhddbarInfo">
            <span id="displayhddbarInfoSpan1"></span> {$LANG.solusvmpro_of} <span
                    id="displayhddbarInfoSpan2"></span> {$LANG.solusvmpro_used} / <span
                    id="displayhddbarInfoSpan3"></span> {$LANG.solusvmpro_free}
        </div>
        <div class="col-md-12">
            <div class="progress">
                <div class="progress-bar" id="hddProgressbar" role="progressbar" aria-valuenow="0"
                     aria-valuemin="0" aria-valuemax="100"
                     style="width: 0; min-width: 2em;">
                    0%
                </div>
            </div>
        </div>
    </div>

    <div id="showOptions" style="display: none">
        <div class="row">
            <div class="col-md-12">
                {$LANG.solusvmpro_options}:
            </div>
            <div class="col-md-12 margin-5-button">
                <span id="displayreboot" style="display: none">
                    <a data-toggle="modal" class="btn btn-default" href="#" data-target="#confirm-reboot"
                       role="button">{$LANG.solusvmpro_reboot}</a>
                </span>
                <span id="displayshutdown" style="display: none">
                    <a data-toggle="modal" class="btn btn-default" href="#" data-target="#confirm-shutdown"
                       role="button">{$LANG.solusvmpro_shutdown}</a>
                </span>
                <span id="displayboot" style="display: none">
                    <button class="btn btn-default"
                            onclick="window.location='clientarea.php?action=productdetails&id={$serviceid}&serveraction=custom&a=boot'">
                        {$LANG.solusvmpro_boot}
                    </button>
                </span>
                <span id="displayconsole" style="display: none">
                    <button class="btn btn-default"
                            onClick="window.open('modules/servers/solusvmpro/console.php?id={$serviceid}','_blank','width=670,height=400,status=no,location=no,toolbar=no,menubar=no')">
                        {$LANG.solusvmpro_serialConsole}
                    </button>
                </span>
                <span id="displayhtml5console" style="display: none">
                    <button class="btn btn-default"
                            onClick="window.open('modules/servers/solusvmpro/html5console.php?id={$serviceid}','_blank','width=870,height=600,status=no,resizable=yes,copyhistory=no,location=no,toolbar=no,menubar=no,scrollbars=1')">
                        {$LANG.solusvmpro_html5Console}
                    </button>
                </span>
                <span id="displayvnc" style="display: none">
                    <button class="btn btn-default"
                            onClick="window.open('modules/servers/solusvmpro/vnc.php?id={$serviceid}','_blank','width=400,height=200,status=no,location=no,toolbar=no,menubar=no')">
                        {$LANG.solusvmpro_vnc}
                    </button>
                </span>
                <span id="displaypanelbutton" style="display: none">
                    <button class="btn btn-default" id="controlpanellink">
                        {$LANG.solusvmpro_controlPanel}
                    </button>
                </span>
                <span id="displayclientkeyauth" style="display: none">
                    <form action="" name="solusvm" method="post">
                        <input type="submit" class="btn btn-success" name="logintosolusvm"
                               value="{$LANG.solusvmpro_manage}">
                    </form>
                </span><br/>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="panel-group" id="solusvmpro_accordion" role="tablist" aria-multiselectable="false">
                    <div class="panel panel-default" id="displayrootpassword" style="display: none">
                        <div class="panel-heading" role="tab" id="headingOne">
                            <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse"
                                   data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseOne"
                                   aria-expanded="false" aria-controls="solusvmpro_collapseOne">
                                    {$LANG.solusvmpro_rootPassword}
                                </a>
                            </h4>
                        </div>
                        <div id="solusvmpro_collapseOne" class="panel-collapse collapse" role="tabpanel"
                             aria-labelledby="headingOne">
                            <div class="panel-body">

                                <div class="row">
                                    <div id="rootpasswordMsgSuccess" class="alert alert-success" role="alert"
                                         style="display: none"></div>
                                    <div id="rootpasswordMsgError" class="alert alert-danger" role="alert"
                                         style="display: none"></div>
                                </div>
                                <div class="row margin-10">
                                    <div class="col-xs-2"></div>
                                    <div class="col-xs-8">
                                        <div class="form-group">
                                            <label for="newrootpassword">{$LANG.solusvmpro_newPassword}</label>
                                            <input type="password" class="form-control" name="newrootpassword"
                                                   id="newrootpassword"
                                                   placeholder="{$LANG.solusvmpro_enterRootPassword}" value="">
                                        </div>
                                    </div>
                                    <div class="col-xs-2"></div>
                                </div>
                                <div class="row margin-10">
                                    <div class="col-xs-2"></div>
                                    <div class="col-xs-8">
                                        <div class="form-group">
                                            <label for="confirmnewrootpassword">{$LANG.solusvmpro_confirmPassword}</label>
                                            <input type="password" class="form-control" name="confirmnewrootpassword"
                                                   id="confirmnewrootpassword"
                                                   placeholder="{$LANG.solusvmpro_confirmRootPassword}" value="">
                                        </div>
                                        <button type="button" id="changerootpassword"
                                                class="btn btn-action">{$LANG.solusvmpro_change}</button>
                                    </div>
                                    <div class="col-xs-2"></div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default" id="displayhostname" style="display: none">
                        <div class="panel-heading" role="tab" id="headingThree">
                            <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse"
                                   data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseThree"
                                   aria-expanded="false" aria-controls="solusvmpro_collapseThree">
                                    {$LANG.solusvmpro_hostname}
                                </a>
                            </h4>
                        </div>
                        <div id="solusvmpro_collapseThree" class="panel-collapse collapse" role="tabpanel"
                             aria-labelledby="headingThree">
                            <div class="panel-body">

                                <div class="row">
                                    <div id="hostnameMsgSuccess" class="alert alert-success" role="alert" style="display: none"></div>
                                    <div id="hostnameMsgError" class="alert alert-danger" role="alert" style="display: none"></div>
                                </div>
                                <div class="row margin-10">
                                    <div class="col-xs-2"></div>
                                    <div class="col-xs-8">
                                        <div class="form-group">
                                            <label for="newhostname">{$LANG.solusvmpro_newHostname}</label>
                                            <input type="text" class="form-control" name="newhostname" id="newhostname"
                                                   placeholder="{$LANG.solusvmpro_enterHostname}" value="">
                                        </div>
                                        <button type="button" id="changehostname"
                                                class="btn btn-action">{$LANG.solusvmpro_change}</button>
                                    </div>
                                    <div class="col-xs-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel panel-default" id="displayvncpassword" style="display: none">
                        <div class="panel-heading" role="tab" id="headingFive">
                            <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse"
                                   data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseFive"
                                   aria-expanded="false" aria-controls="solusvmpro_collapseFive">
                                    {$LANG.solusvmpro_vncPassword}
                                </a>
                            </h4>
                        </div>
                        <div id="solusvmpro_collapseFive" class="panel-collapse collapse" role="tabpanel"
                             aria-labelledby="headingFive">
                            <div class="panel-body">

                                <div class="row">
                                    <div id="vncpasswordMsgSuccess" class="alert alert-success" role="alert" style="display: none"></div>
                                    <div id="vncpasswordMsgError" class="alert alert-danger" role="alert" style="display: none"></div>
                                </div>
                                <div class="row margin-10">
                                    <div class="col-xs-2"></div>
                                    <div class="col-xs-8">
                                        <div class="form-group">
                                            <label for="newvncpassword">{$LANG.solusvmpro_newPassword}</label>
                                            <input type="password" class="form-control" name="newvncpassword"
                                                   id="newvncpassword" placeholder="{$LANG.solusvmpro_enterVNCPassword}" value="">
                                        </div>
                                    </div>
                                    <div class="col-xs-2"></div>
                                </div>
                                <div class="row margin-10">
                                    <div class="col-xs-2"></div>
                                    <div class="col-xs-8">
                                        <div class="form-group">
                                            <label for="confirmnewvncpassword">{$LANG.solusvmpro_confirmPassword}</label>
                                            <input type="password" class="form-control" name="confirmnewvncpassword"
                                                   id="confirmnewvncpassword" placeholder="{$LANG.solusvmpro_confirmVNCPassword}" value="">
                                        </div>
                                        <button type="button" id="changevncpassword" class="btn btn-action">{$LANG.solusvmpro_change}</button>
                                    </div>
                                    <div class="col-xs-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-default" id="displayrescuemode" style="display: none">
                        <div class="panel-heading" role="tab" id="headingSeven">
                            <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseSeven" aria-expanded="false" aria-controls="solusvmpro_collapseSeven">
                                    {$LANG.solusvmpro_rescueMode}
                                </a>
                            </h4>
                        </div>
                        <div id="solusvmpro_collapseSeven" class="panel-collapse collapse" role="tabpanel"
                             aria-labelledby="headingFive">
                            <div class="panel-body">
                                <div class="row">
                                    <div id="rescuemodeMsgSuccess" class="alert alert-success" role="alert" style="display: none"></div>
                                    <div id="rescuemodeMsgError" class="alert alert-danger" role="alert" style="display: none"></div>
                                </div>
                                <div class="row margin-10">
                                    <div class="col-xs-2"></div>
                                        <div class="col-xs-8" style="display: none;" id="rescueEnabled">
                                            <div class="form-group">
                                                <label for="rescueImage">{$LANG.solusvmpro_rescueImage}</label>
                                                <select name="rescueImage" class="form-control" id="rescueImage">
                                                    <option value="1">4.x Kernel 64bit</option>
                                                    <option value="2">3.x Kernel 64bit</option>
                                                    <option value="3">3.x Kernel 32bit</option>
                                                </select>
                                            </div>
                                            <button type="button" class="btn btn-action changerescuemode">
                                                {$LANG.solusvmpro_enable}
                                            </button>
                                        </div>
                                        <div class="col-xs-8" style="display: none;" id="rescueDisabled">
                                            <div class="form-group">
                                                <ul class="list-group">
                                                    <li class="list-group-item">{$LANG.solusvmpro_ipAddress}: <span id="rescueip"></span></li>
                                                    <li class="list-group-item">{$LANG.solusvmpro_port}: <span id="rescueport"></span></li>
                                                    <li class="list-group-item">{$LANG.solusvmpro_user}: <span id="rescueuser"></span></li>
                                                    <li class="list-group-item">{$LANG.solusvmpro_rootPassword}: <span id="rescuepassword"></span></li>
                                                </ul>
                                            </div>
                                            <button type="button" class="btn btn-action changerescuemode">
                                                {$LANG.solusvmpro_disable}
                                            </button>
                                        </div>
                                    <div class="col-xs-6"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel panel-default" id="displaygraphs" style="display: none">
                        <div class="panel-heading" role="tab" id="headingSix">
                            <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse"
                                   data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseSix"
                                   aria-expanded="false" aria-controls="solusvmpro_collapseSix">
                                    {$LANG.solusvmpro_graphs}
                                </a>
                            </h4>
                        </div>
                        <div id="solusvmpro_collapseSix" class="panel-collapse collapse" role="tabpanel"
                             aria-labelledby="headingSix">
                            <div class="panel-body">

                                <div class="col-md-12 margin-top-20" id="trafficgraph" style="display: none">
                                    <img id="trafficgraphurlImg" alt="Traffic Graph Unavailable">
                                </div>

                                <div class="col-md-12 margin-top-20" id="loadgraph" style="display: none">
                                    <img id="loadgraphurlImg" alt="Load Graph Unavailable">
                                </div>

                                <div class="col-md-12 margin-top-20" id="memorygraph" style="display: none">
                                    <img id="memorygraphurlImg" alt="Memory Graph Unavailable">
                                </div>

                                <div class="col-md-12 margin-top-20" id="hddgraph" style="display: none">
                                    <img id="hddgraphurlImg" alt="Hdd Graph Unavailable">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="clientkeyautherror" style="display: none">
        <div class="col-md-12 bg-danger">
            {$LANG.solusvmpro_accessUnavailable}
        </div>
    </div>


    <div id="displayips" style="display: none">
        <div class="col-md-3 margin-top-20">
            {$LANG.solusvmpro_ipAddress}
        </div>
        <div class="col-md-9 margin-top-20" id="ipcsv">
        </div>
    </div>

    <div class="modal fade" id="confirm-reboot" tabindex="-1" role="dialog" aria-labelledby="rebootModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="myModalLabel">{$LANG.solusvmpro_reboot_confirm_label}</h4>
                </div>
                <div class="modal-body">
                    <p>{$LANG.solusvmpro_reboot_confirm}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default"
                            data-dismiss="modal">{$LANG.solusvmpro_cancel}</button>
                    <input type="button" class="btn btn-warning" value="{$LANG.solusvmpro_reboot}"
                           onclick="window.location='clientarea.php?action=productdetails&id={$serviceid}&serveraction=custom&a=reboot'">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirm-shutdown" tabindex="-1" role="dialog" aria-labelledby="shutdownModalLabel"
         aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="myModalLabel">{$LANG.solusvmpro_shutdown_confirm_label}</h4>
                </div>
                <div class="modal-body">
                    <p>{$LANG.solusvmpro_shutdown_confirm}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default"
                            data-dismiss="modal">{$LANG.solusvmpro_cancel}</button>
                    <input type="button" class="btn btn-warning" value="{$LANG.solusvmpro_shutdown}"
                           onclick="window.location='clientarea.php?action=productdetails&id={$serviceid}&serveraction=custom&a=shutdown'">
                </div>
            </div>
        </div>
    </div>
</div>
