<?php

use WHMCS\ClientArea;
use WHMCS\Database\Capsule;

use SolusVM\SolusVM;

define('CLIENTAREA', true);

require("../../../init.php");

$results = localAPI( 'GetConfigurationValue', array('setting' => 'SystemURL') );
$systemurl = $results['value'];

$ca = new ClientArea();

$ca->initPage();
$ca->requireLogin();

require_once __DIR__ . '/lib/Curl.php';
require_once __DIR__ . '/lib/CaseInsensitiveArray.php';
require_once __DIR__ . '/lib/SolusVM.php';


SolusVM::loadLang();
?>

    <head>
        <!-- Bootstrap -->
        <link href="<?php echo $systemurl; ?>assets/css/bootstrap.min.css" rel="stylesheet">
        <link href="<?php echo $systemurl; ?>assets/css/font-awesome.min.css" rel="stylesheet">

        <!-- Styling -->
        <link href="<?php echo $systemurl; ?>templates/six/css/overrides.css" rel="stylesheet">
        <link href="<?php echo $systemurl; ?>templates/six/css/styles.css" rel="stylesheet">

        <link href='//fonts.googleapis.com/css?family=Source+Code+Pro:400,300' rel='stylesheet' type='text/css'>
        <title><?php echo $_LANG['solusvmpro_html5Console']; ?></title>
        <style>

            .terminal {
                padding: 0px;
                border: #454545 solid 0px;
                font-family: 'Source Code Pro', monospace;
                font-size: 12px;
                font-weight: 400;
                line-height: 14px;
                color: #02FC1E !important;
                background-color: #454545 !important;
                width: 800px;
                box-shadow: 0;
            }

            .reverse-video {
                background-color: orange;
            }
        </style>

        <script src="<?php echo $systemurl; ?>assets/js/jquery.min.js"></script>
        <script type="text/javascript" src="term/term.js"></script>
        <link rel="stylesheet" href="//maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">

    </head>
    <body style="margin:10px;padding: 10px; background: none;">

<?php

$ca = new WHMCS_ClientArea();
if (!$ca->isLoggedIn()) {
    if ((!isset($_SESSION['adminid']) || ((int)$_SESSION['adminid'] <= 0))) {
        echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_unauthorized'] . '</div></body>';
        exit();
    }
    $uid = (int)$_GET['uid'];
} else {
    $uid = $ca->getUserID();
}

$servid = (int)$_GET['id'];
if ($servid == "") {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_unauthorized'] . '</div></body>';
    exit();
}

$params = SolusVM::getParamsFromServiceID($servid, $uid);
if ($params === false) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_vserverNotFound'] . '</div></body>';
    exit;
}
$solusvm = new SolusVM($params);

if ($solusvm->getExtData("clientfunctions") == "disable") {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_functionDisabled'] . '</body>';
    exit;
}
if ($solusvm->getExtData("html5serialconsole") == "disable") {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_functionDisabled'] . '</body>';
    exit;
}

################### Code ###################

if (isset($_POST["sessioncancel"])) {
    $callArray = array("access" => "disable", "vserverid" => $params['vserver']);
} elseif (isset($_POST["sessioncreate"])) {
    $stime = $_POST["sessiontime"];
    if (!is_numeric($stime)) {
        exit();
    } else {
        $callArray = array("access" => "enable", "time" => $stime, "vserverid" => $params['vserver']);
    }
} else {
    ## The call string for the connection function
    $callArray = array("vserverid" => $params['vserver']);
}

$solusvm->apiCall('vserver-console', $callArray);
$r = $solusvm->result;

if ($r["status"] == "success") {
    if ($r["sessionactive"] == "1") {
        if ($r["type"] != "openvz" && $r["type"] != "xen") {
            exit();
        }

        /*if(trim($r['consoledomain']) !=='' ){
            $host_connect = $r['consoledomain'];
        } else {
            $host_connect = $r['consoleip'];
        }*/
        $host_connect = $solusvm->cpHostname;

        ?>

        <div align="center">
            <p style="padding-bottom: 5px"><?php echo $_LANG['solusvmpro_password'] . ': ' . $r['consolepassword']; ?></p>
            <br>
        </div>
    <br>
        <form action="" method="post" name="cancelsession">
            <div style="padding: 10px 0 10px 0; text-align:center">
                <input name="sessioncancel" type="submit" value="<?php echo $_LANG['solusvmpro_cancelSession']; ?>">
            </div>
        </form>

        <div
            style="margin: auto;background-color: #454545;width: 800px;padding:10px 10px 0 10px;font-family: 'Source Code Pro', monospace;color:#fff;">
            <div
                style="display:inline-block;width:100%;border-bottom: 1px solid #ffffff;font-size: 14px;padding-bottom:10px">
                <div style="float: left">Secure Shell Terminal: vt220</div>
                <div style="float: right;margin-left: 10px; cursor: pointer">
                    <i title="<?php echo $_LANG['solusvmpro_quit']; ?>" onclick="window.close();"
                       class="fa fa-times-circle"></i>
                </div>
                <div style="float: right;margin-left: 10px ; cursor: pointer">
                    <i title="<?php echo $_LANG['solusvmpro_reconnect']; ?>"
                       onclick=" open_terminal({connectionkey: '<?= $r['key']; ?>'});"
                       class="fa fa-refresh"></i>
                </div>
                <div style="float: right;margin-left: 10px; cursor: pointer">
            <span style="color: #02FC1E"><i title="<?php echo $_LANG['solusvmpro_greenText']; ?>"
                                            onclick="$('.terminal').css('cssText', 'color: #02FC1E !important');"
                                            class="fa fa-font"></i></span>
                </div>
                <div style="float: right;margin-left: 10px; cursor: pointer">
                        <span style="color: #ffffff"><i data-toggle="tooltip" data-placement="top"
                                                        title="<?php echo $_LANG['solusvmpro_whiteText']; ?>"
                                                        onclick="$('.terminal').css('cssText', 'color: #ffffff !important');"
                                                        class="fa fa-font"></i></span>
                </div>
                <div style="float: right;margin-left: 10px; cursor: pointer">
                        <span style="color: #00C4FC"><i data-toggle="tooltip" data-placement="top"
                                                        title="<?php echo $_LANG['solusvmpro_blueText']; ?>"
                                                        onclick="$('.terminal').css('cssText', 'color: #00C4FC !important');"
                                                        class="fa fa-font"></i></span>
                </div>
                <div style="float: right;margin-left: 10px; cursor: pointer">
                        <span style="color: #FCF901"><i data-toggle="tooltip" data-placement="top"
                                                        title="<?php echo $_LANG['solusvmpro_yellowText']; ?>"
                                                        onclick="$('.terminal').css('cssText', 'color: #FCF901 !important');"
                                                        class="fa fa-font"></i></span>
                </div>
            </div>
        </div>
        <div id="term-container"
             style="margin: auto;background-color: #454545;border: 10px solid #454545;width: 800px;height: 500px;font-family: 'Source Code Pro', monospace;overflow: hidden;">
            <div id="term">
            </div>
        </div>

        <script type="application/javascript">
            window.charsize = {width: 109, height: 35};
            window.connections = [];
            function open_terminal(n) {
                var e = null;
                var e = new SSH_client, o = new Terminal({
                    cols: window.charsize.width,
                    rows: window.charsize.height,
                    useStyle: true,
                    screenKeys: true,
                    cursorBlink: true,
                    convertEol: true
                });
                o.on("data", function (n) {
                    e.send(n)
                }), o.open(), $(".terminal").detach().appendTo("#term"), o.write("Connecting..."), e.connect($.extend(n, {
                    onError: function (n) {
                        o.write("Error: " + n + "\r\n")
                    }, onConnect: function () {
                        e.resize(window.charsize), o.write("\r");
                        window.noop_timer = setInterval(function () {
                            e.resize(window.charsize)
                        }, 8000)
                    }, onClose: function () {
                        clearInterval(window.noop_timer);
                        o.write("Connection closed\r\n")
                    }, onData: function (n) {
                        o.write(n)
                    }
                }))
            }
            function SSH_client() {
            }
            SSH_client.prototype.connect = function (options) {
                var endpoint = 'wss://<?=$host_connect;?>:5021/ssh/' + encodeURIComponent(options.connectionkey);
                window.connections.forEach(function (con) {
                    con.close()
                });
                if (window.WebSocket) {
                    this._connection = new WebSocket(endpoint)
                } else if (window.MozWebSocket) {
                    this._connection = MozWebSocket(endpoint)
                } else {
                    options.onError('WebSocket Not Supported');
                    return
                }
                window.connections.push(this._connection);
                this._connection.onopen = function () {
                    options.onConnect()
                };
                this._connection.onmessage = function (evt) {
                    var data = JSON.parse(evt.data.toString());
                    if (data.error !== undefined) {
                        options.onError(data.error)
                    } else {
                        options.onData(data.data)
                    }
                };
                this._connection.onclose = function (evt) {
                    options.onClose()
                }
            };
            SSH_client.prototype.send = function (data) {
                this._connection.send(JSON.stringify({'data': data}))
            };
            SSH_client.prototype.resize = function (data) {
                this._connection.send(JSON.stringify({'resize': data}))
            };
            SSH_client.prototype.close = function () {
                this._connection.close()
            };
        </script>


        <script type="text/javascript">
            open_terminal({connectionkey: '<?=$r['key'];?>'});
        </script>

    <?php

    } else {
    ## If not active

    ?>
        <div class="row margin-10">
            <div class="col-xs-2"></div>
            <div class="col-xs-8">
                <form class="form-inline" method="post" name="createsession">
                    <div class="form-group">
                        <label for="sessiontime"><?php echo $_LANG['solusvmpro_time']; ?></label>
                        <select class="form-control" name="sessiontime" id="sessiontime">
                            <option value="1">1 <?php echo $_LANG['solusvmpro_hour']; ?></option>
                            <option value="2">2 <?php echo $_LANG['solusvmpro_hours']; ?></option>
                            <option value="3">3 <?php echo $_LANG['solusvmpro_hours']; ?></option>
                            <option value="4">4 <?php echo $_LANG['solusvmpro_hours']; ?></option>
                            <option value="5">5 <?php echo $_LANG['solusvmpro_hours']; ?></option>
                            <option value="6">6 <?php echo $_LANG['solusvmpro_hours']; ?></option>
                            <option value="7">7 <?php echo $_LANG['solusvmpro_hours']; ?></option>
                            <option value="8">8 <?php echo $_LANG['solusvmpro_hours']; ?></option>
                        </select>
                    </div>
                    <button name="sessioncreate" type="submit"
                            class="btn btn-success"><?php echo $_LANG['solusvmpro_createSession']; ?></button>
                    <button type="button" class="btn btn-danger"
                            onclick="window.close()"><?php echo $_LANG['solusvmpro_closeWindow']; ?></button>
                </form>
            </div>
            <div class="col-xs-2"></div>
        </div>

        <?php
    }


} else {
    if (isset($r["statusmsg"])) {
        $pagedata = (string)$r["statusmsg"];
    } else {
        $pagedata = $_LANG['solusvmpro_couldntConnectMaster'];
    }
    $pagedata .= '<br><br>';

    echo $pagedata;
}

################## Code End ################
echo '</body>';
