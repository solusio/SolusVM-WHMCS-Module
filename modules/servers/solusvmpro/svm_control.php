<?php

define("CLIENTAREA", true);

require("../../../init.php");
session_start();

require_once ROOTDIR . '/modules/servers/solusvmpro/lib/Curl.php';
require_once ROOTDIR . '/modules/servers/solusvmpro/lib/CaseInsensitiveArray.php';
require_once ROOTDIR . '/modules/servers/solusvmpro/lib/SolusVM.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use SolusVM\SolusVM;

/** @var array $_LANG */
SolusVM::loadLang();

function setFromGetOrSendError($param)
{
    global $_LANG;
    $res = 0;
    if (isset($_GET[$param])) {
        $res = (int)$_GET[$param];
    }
    if ($res === 0) {
        die($_LANG['solusvmpro_unauthorized'] . "</body>");
    }

    return $res;
}

$adminid = 0;
if (isset($_SESSION['adminid'])) {
    $adminid = (int)$_SESSION['adminid'];
}
if ($adminid === 0) {
    echo $_LANG['solusvmpro_unauthorized'] . "</body>";
    exit();
}

$vserverid = setFromGetOrSendError('id');
$uid = setFromGetOrSendError('userid');
$serverid = setFromGetOrSendError('serverid');
$serviceid = setFromGetOrSendError('serviceid');

$params = SolusVM::getParamsFromServiceID($serviceid, $uid);
if ($params === false) {
    echo $_LANG['solusvmpro_vserverNotFound'] . '</body>';
    exit;
}
if (($params['vserver'] != $vserverid) || ($params['serverid'] != $serverid)) {
    echo $_LANG['solusvmpro_wrongData'] . '</body>';
    exit;
}

$solusvm = new SolusVM($params);

$callArray = ["vserverid" => $vserverid];

$solusvm->apiCall('vserver-infoall', $callArray);
$r = $solusvm->result;

if ($r["status"] == "success") {

    $cparams = $solusvm->clientAreaCalculations($r);

    $graphs = '';
    if ($r["state"] == "online" || $r["state"] == "offline") {
        if ($cparams["displaytrafficgraph"] == 1) {
            $graphs .= '
                <div class="col-md-12 margin-top-20">
                    <img src="' . $cparams["trafficgraphurl"] . '" alt="Traffic Graph Unavailable">
                </div>
            ';
            if ($r["type"] == "kvm" || $r["type"] == "xen") {

                $cparams['hddgraphurl'] = str_replace("bandwidth", "io", $cparams['trafficgraphurl']);
                $graphs .= '
                <div class="col-md-12 margin-top-20">
                    <img src="' . $cparams['hddgraphurl'] . '" alt="HDD Graph Unavailable">
                </div>
            ';
            }
        }
        if ($cparams["displayloadgraph"] == 1) {
            $graphs .= '
                <div class="col-md-12 margin-top-20">
                    <img src="' . $cparams["loadgraphurl"] . '" alt="Load Graph Unavailable">
                </div>
            ';
        }
        if ($cparams["displaymemorygraph"] == 1) {
            $graphs .= '
                <div class="col-md-12 margin-top-20">
                    <img src="' . $cparams["memorygraphurl"] . '" alt="Memory Graph Unavailable">
                </div>
            ';
        }
        $bwshow = '
            <div class="col-md-12 margin-top-20">
                ' . $cparams["bandwidthused"] . ' ' . $_LANG["solusvmpro_of"] . '
                ' . $cparams["bandwidthtotal"] . ' ' . $_LANG["solusvmpro_used"] . ' /
                ' . $cparams["bandwidthfree"] . ' ' . $_LANG["solusvmpro_free"] . '
            </div>
            <div class="col-md-12">
                <div class="progress">
                    <div class="progress-bar" id="bandwidthProgressbar" role="progressbar"
                         aria-valuenow="' . $cparams["bandwidthpercent"] . '" aria-valuemin="0" aria-valuemax="100"
                         style="width: ' . $cparams["bandwidthpercent"] . '% ;min-width: 2em; background-color: ' . $cparams["bandwidthcolor"] . '">
                        ' . $cparams["bandwidthpercent"] . '%
                    </div>
                </div>
            </div>
        ';

        $vstatus = $cparams["displaystatus"];

        $vmstatus = '
            <div class="col-md-3 margin-top-20">
                ' . $_LANG['solusvmpro_status'] . '
            </div>
            <div class="col-md-9 margin-top-20">
                ' . $vstatus . '
            </div>
        ';

        $node = '
            <div class="col-md-3">
                ' . $_LANG['solusvmpro_node'] . '
            </div>
            <div class="col-md-9">
                ' . $cparams["node"] . '
            </div>
        ';

        if ($r["type"] == "openvz") {
            $mem = '
                <div class="col-md-12 margin-top-20">
                    ' . $cparams["memoryused"] . ' ' . $_LANG["solusvmpro_of"] . '
                    ' . $cparams["memorytotal"] . ' ' . $_LANG["solusvmpro_used"] . ' /
                    ' . $cparams["memoryfree"] . ' ' . $_LANG["solusvmpro_free"] . '
                </div>
                <div class="col-md-12">
                    <div class="progress">
                        <div class="progress-bar" id="memoryProgressbar" role="progressbar"
                             aria-valuenow="' . $cparams["memorypercent"] . '" aria-valuemin="0" aria-valuemax="100"
                             style="width: ' . $cparams["memorypercent"] . '% ;min-width: 2em; background-color: ' . $cparams["memorycolor"] . '">
                            ' . $cparams["memorypercent"] . '%
                        </div>
                    </div>
                </div>
            ';
        }
        if ($r["type"] == "openvz" || $r["type"] == "xen") {
            $hdd = '
                <div class="col-md-12 margin-top-20">
                    ' . $cparams["hddused"] . ' ' . $_LANG["solusvmpro_of"] . '
                    ' . $cparams["hddtotal"] . ' ' . $_LANG["solusvmpro_used"] . ' /
                    ' . $cparams["hddfree"] . ' ' . $_LANG["solusvmpro_free"] . '
                </div>
                <div class="col-md-12">
                    <div class="progress">
                        <div class="progress-bar" id="hddProgressbar" role="progressbar"
                             aria-valuenow="' . $cparams["hddpercent"] . '" aria-valuemin="0" aria-valuemax="100"
                             style="width: ' . $cparams["hddpercent"] . '% ;min-width: 2em; background-color: ' . $cparams["hddcolor"] . '">
                            ' . $cparams["hddpercent"] . '%
                        </div>
                    </div>
                </div>
            ';
        }

        if ($r["type"] == "openvz" || $r["type"] == "xen") {
            $html5Console = '<button type="button" style="width: 165px" class="btn btn-default" onclick="window.open(\'../modules/servers/solusvmpro/html5console.php?id=' . $serviceid . '&uid=' . $uid . '\', \'_blank\',\'width=880,height=600,status=no,resizable=yes,copyhistory=no,location=no,toolbar=no,menubar=no,scrollbars=1\')">
            ' . $_LANG['solusvmpro_html5Console'] . '</button>';
        }

        if ($r["type"] == "openvz" || $r["type"] == "xen") {
            $console = '<button type="button" class="btn btn-default" onclick="window.open(\'../modules/servers/solusvmpro/console.php?id=' . $serviceid . '&uid=' . $uid . '\', \'_blank\',\'width=830,height=750,status=no,location=no,toolbar=no,scrollbars=1,menubar=no\')">' . $_LANG["solusvmpro_serialConsole"] . '</button>';
            $cpass = '';
        } else {
            $console = '<button type="button" class="btn btn-default" onclick="window.open(\'../modules/servers/solusvmpro/vnc.php?id=' . $serviceid . '&uid=' . $uid . '\', \'_blank\',\'width=800,height=600,status=no,location=no,toolbar=no,menubar=no,,scrollbars=1,resizable=yes\')">' . $_LANG['solusvmpro_vnc'] . '</button>';
            $cpass = '
                <script type="text/javascript" src="/modules/servers/solusvmpro/js/vncpassword.js"></script>
                <script type="text/javascript">
                    window.solusvmpro_vncpassword(vserverid, {"solusvmpro_invalidVNCpassword": "' . $_LANG['solusvmpro_invalidVNCpassword'] . '","solusvmpro_change":"' . $_LANG['solusvmpro_change'] . '","solusvmpro_confirmVNCPassword":"' . $_LANG['solusvmpro_confirmVNCPassword'] . '","solusvmpro_confirmErrorPassword":"' . $_LANG['solusvmpro_confirmErrorPassword'] . '","solusvmpro_confirmPassword":"' . $_LANG['solusvmpro_confirmPassword'] . '"}, token);
                </script>

                <div class="panel panel-default" id="displayvncpassword">
                    <div class="panel-heading" role="tab" id="headingFive">
                        <h4 class="panel-title">
                            <a class="collapsed" role="button" data-toggle="collapse" data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseFive" aria-expanded="false" aria-controls="solusvmpro_collapseFive">
                                ' . $_LANG['solusvmpro_vncPassword'] . '
                            </a>
                        </h4>
                    </div>
                    <div id="solusvmpro_collapseFive" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingFive">
                        <div class="panel-body">

                            <div class="row">
                                <div id="vncpasswordMsgSuccess" class="alert alert-success" role="alert" style="display: none"></div>
                                <div id="vncpasswordMsgError" class="alert alert-danger" role="alert" style="display: none"></div>
                            </div>
                            <div class="row margin-10">
                                <div class="col-xs-2"></div>
                                <div class="col-xs-4">
                                    <div class="form-group">
                                        <label for="newvncpassword">' . $_LANG['solusvmpro_newPassword'] . '</label>
                                        <input type="password" class="form-control" name="newvncpassword" id="newvncpassword"
                                               placeholder="' . $_LANG['solusvmpro_enterVNCPassword'] . '" value="">
                                    </div>
                                </div>
                                <div class="col-xs-6"></div>
                            </div>
                            <div class="row margin-10">
                                <div class="col-xs-2"></div>
                                <div class="col-xs-4">
                                    <div class="form-group">
                                        <label for="confirmnewvncpassword">' . $_LANG['solusvmpro_confirmPassword'] . '</label>
                                        <input type="password" class="form-control" name="confirmnewvncpassword" id="confirmnewvncpassword"
                                               placeholder="' . $_LANG['solusvmpro_confirmVNCPassword'] . '" value="">
                                    </div>
                                    <button type="button" id="changevncpassword" class="btn btn-action">' . $_LANG['solusvmpro_change'] . '</button>
                                </div>
                                <div class="col-xs-6"></div>
                            </div>

                        </div>
                    </div>
                </div>
            ';

            if($r['rescuemode']==0){
                $rescueForm = '
                        <div class="col-xs-4"><div class="form-group">
                                <label for="rescueImage ">' . $_LANG['solusvmpro_rescueImage'] . '</label>
                                <select name="rescueImage" class="form-control" id="rescueImage">
                                    <option value="1">4.x Kernel 64bit</option>
                                    <option value="2">3.x Kernel 64bit</option>
                                    <option value="3">3.x Kernel 32bit</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-action changerescuemode">' . $_LANG['solusvmpro_enable'] . '</button>
                        </div>';
            }else{
                $callArray = array('vserverid' => $vserverid);
                $solusvm->apiCall('vserver-rescue', $callArray);
                $rescue = $solusvm->result;

                if ($rescue['status'] === 'success') {
                    $rescueForm = '
                   <div class="col-xs-4"><div class="form-group"><ul class="list-group">
                      <li class="list-group-item">'.$_LANG['solusvmpro_ipAddress'].': '.$rescue['ip'].'</li>
                      <li class="list-group-item">'.$_LANG['solusvmpro_port'].': '.$rescue['port'].'</li>
                      <li class="list-group-item">'.$_LANG['solusvmpro_user'].': '.$rescue['user'].'</li>
                      <li class="list-group-item">'.$_LANG['solusvmpro_rootPassword'].': '.$rescue['password'].'</li>
                   </ul></div>
                      <button type="button" class="btn btn-action changerescuemode">' . $_LANG['solusvmpro_disable'] . '</button>
                   </div>';
                }
            }

            $rescuemode = '
                <script type="text/javascript" src="/modules/servers/solusvmpro/js/rescuemode.js"></script>
                <script type="text/javascript">
                    window.solusvmpro_rescuemode(vserverid, {"solusvmpro_refresh": "'. $_LANG['solusvmpro_refresh'] .'", "solusvmpro_processing": "' . $_LANG['solusvmpro_processing'] . '"}, token, "adminArea");
                </script>
                <div class="panel panel-default" id="displayrescuemode">
                        <div class="panel-heading" role="tab" id="headingSeven">
                            <h4 class="panel-title">
                                <a class="collapsed" role="button" data-toggle="collapse" data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseSeven" aria-expanded="false" aria-controls="solusvmpro_collapseSeven">
                                    ' . $_LANG['solusvmpro_rescueMode'] . '
                                </a>
                            </h4>
                        </div>
                        <div id="solusvmpro_collapseSeven" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingFive">
                            <div class="panel-body">
                                <div class="row">
                                    <div id="rescuemodeMsgSuccess" class="alert alert-success" role="alert" style="display: none"></div>
                                    <div id="rescuemodeMsgError" class="alert alert-danger" role="alert" style="display: none"></div>
                                </div>
                                <div class="row margin-10">
                                    <div class="col-xs-2"></div>
                                    '. $rescueForm . '
                <div class="col-xs-6"></div></div></div></div></div>';
        }

        if ($r["type"] == "openvz" || $r["type"] == "xen") {
            $rpass = '
                <script type="text/javascript" src="/modules/servers/solusvmpro/js/rootpassword.js"></script>
                <script type="text/javascript">
                    window.solusvmpro_rootpassword(vserverid, {"solusvmpro_invalidRootpassword": "' . $_LANG['solusvmpro_invalidRootpassword'] . '","solusvmpro_change":"' . $_LANG['solusvmpro_change'] . '","solusvmpro_confirmRootPassword":"' . $_LANG['solusvmpro_confirmRootPassword'] . '","solusvmpro_confirmErrorPassword":"' . $_LANG['solusvmpro_confirmErrorPassword'] . '","solusvmpro_confirmPassword":"' . $_LANG['solusvmpro_confirmPassword'] . '"}, token);
                </script>
                <div class="panel panel-default" id="displayrootpassword">
                    <div class="panel-heading" role="tab" id="headingOne">
                        <h4 class="panel-title">
                            <a role="button" data-toggle="collapse" data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseOne" aria-expanded="false" aria-controls="solusvmpro_collapseOne">
                                ' . $_LANG['solusvmpro_rootPassword'] . '
                            </a>
                        </h4>
                    </div>
                    <div id="solusvmpro_collapseOne" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingOne">
                        <div class="panel-body">

                            <div class="row">
                                <div id="rootpasswordMsgSuccess" class="alert alert-success" role="alert" style="display: none"></div>
                                <div id="rootpasswordMsgError" class="alert alert-danger" role="alert" style="display: none"></div>
                            </div>
                            <div class="row margin-10">
                                <div class="col-xs-2"></div>
                                <div class="col-xs-8">
                                    <div class="form-group">
                                        <label for="newrootpassword">' . $_LANG['solusvmpro_newPassword'] . '</label>
                                        <input type="password" class="form-control" name="newrootpassword" id="newrootpassword"
                                               placeholder="' . $_LANG['solusvmpro_enterRootPassword'] . '" value="">
                                    </div>
                                </div>
                                <div class="col-xs-2"></div>
                            </div>
                            <div class="row margin-10">
                                <div class="col-xs-2"></div>
                                <div class="col-xs-8">
                                    <div class="form-group">
                                        <label for="confirmnewrootpassword">' . $_LANG['solusvmpro_confirmPassword'] . '</label>
                                        <input type="password" class="form-control" name="confirmnewrootpassword" id="confirmnewrootpassword"
                                               placeholder="' . $_LANG['solusvmpro_confirmRootPassword'] . '" value="">
                                    </div>
                                    <button type="button" id="changerootpassword" class="btn btn-action">' . $_LANG['solusvmpro_change'] . '</button>
                                </div>
                                <div class="col-xs-2"></div>
                            </div>

                        </div>
                    </div>
                </div>
            ';
        }

        if ($r["type"] == "openvz" || $r["type"] == "xen") {
            $chostname = '
                <script type="text/javascript" src="/modules/servers/solusvmpro/js/hostname.js"></script>
                <script type="text/javascript">
                    window.solusvmpro_hostname(vserverid, {"solusvmpro_invalidHostname": "' . $_LANG['solusvmpro_invalidHostname'] . '","solusvmpro_change":"' . $_LANG['solusvmpro_change'] . '"}, token);
                </script>
                <div class="panel panel-default" id="displayhostname">
                    <div class="panel-heading" role="tab" id="headingThree">
                        <h4 class="panel-title">
                            <a class="collapsed" role="button" data-toggle="collapse" data-parent="#solusvmpro_accordion" href="#solusvmpro_collapseThree" aria-expanded="false" aria-controls="solusvmpro_collapseThree">
                                ' . $_LANG['solusvmpro_hostname'] . '
                            </a>
                        </h4>
                    </div>
                    <div id="solusvmpro_collapseThree" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingThree">
                        <div class="panel-body">

                            <div class="row">
                                <div id="hostnameMsgSuccess" class="alert alert-success" role="alert" style="display: none"></div>
                                <div id="hostnameMsgError" class="alert alert-danger" role="alert" style="display: none"></div>
                            </div>
                            <div class="row margin-10">
                                <div class="col-xs-2"></div>
                                <div class="col-xs-8">
                                    <div class="form-group">
                                        <label for="newhostname">' . $_LANG['solusvmpro_newHostname'] . '</label>
                                        <input type="text" class="form-control" name="newhostname" id="newhostname"
                                               placeholder="' . $_LANG['solusvmpro_enterHostname'] . '" value="">
                                    </div>
                                    <button type="button" id="changehostname" class="btn btn-action">' . $_LANG['solusvmpro_change'] . '</button>
                                </div>
                                <div class="col-xs-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            ';
        }

        if ($solusvm->getExtData("controlpanelbutton-admin") != "") {
            $cpbutton = '<button type="button" class="btn btn-default" onclick="window.open(\'' . $solusvm->getExtData("controlpanelbutton-admin") . '\', \'_blank\')">' . $_LANG['solusvmpro_controlPanel'] . '</button>';
        }

        if ($solusvm->getExtData("controlpanelbutton-manage") != "") {
            $cpmanagebutton = '<button type="button" class="btn btn-default" onclick="window.open(\'' . $solusvm->getExtData("controlpanelbutton-manage") . '/manage.php?id=' . $vserverid . '\', \'_blank\')">' . $_LANG['solusvmpro_manage'] . '</button>';
        }

        echo '
            <style>
                .margin-top-20 {
                    margin-top: 20px;
                }
                .margin-button button {
                    margin-top: 5px;
                    margin-bottom: 5px;
                }
            </style>
            <div class="col-md-12 margin-5-button">
                ' . $console . $html5Console . $cpbutton . $cpmanagebutton . '
            </div>
            <br><br>

            <script type="text/javascript">
                var vserverid = ' . $vserverid . ';
                token = "' . generate_token("link") . '";
            </script>
            
            <div class="panel-group" id="solusvmpro_accordion" role="tablist" aria-multiselectable="true">
                ' . $rpass . $chostname . $cpass . $rescuemode.'
            </div>

            ' . $vmstatus . $mem . $hdd . $bwshow . $node . $graphs;

    } else {

        if ($r["state"] == "disabled") {
            echo '<span style="color: #000"><strong>' . $_LANG['solusvmpro_suspended'] . '</strong></span>';
        } else {
            echo $vstatus;
        }
    }
} else {
    echo $_LANG['solusvmpro_connectionError'];
    exit();
}
