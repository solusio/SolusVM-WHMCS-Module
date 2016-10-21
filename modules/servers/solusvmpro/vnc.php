<?php

define( "CLIENTAREA", true );
require( "../../../init.php" );

require_once __DIR__ . '/lib/Curl.php';
require_once __DIR__ . '/lib/CaseInsensitiveArray.php';
require_once __DIR__ . '/lib/SolusVM.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use SolusVM\SolusVM;

SolusVM::loadLang();

echo '
    <head>
        <!-- Bootstrap -->
        <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
        <link href="/assets/css/font-awesome.min.css" rel="stylesheet">

        <!-- Styling -->
        <link href="/templates/six/css/overrides.css" rel="stylesheet">
        <link href="/templates/six/css/styles.css" rel="stylesheet">
    </head>
    <body>';

$ca = new WHMCS_ClientArea();
if ( ! $ca->isLoggedIn() ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_unauthorized'] . '</div></body>';
    exit();
}

//$pagetitle = "Serial Console";
//$ca->setPageTitle( $pagetitle );

$servid = isset( $_GET['id'] ) ? (int) $_GET['id'] : "";

if ( $servid == "" ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_unauthorized'] . '</div></body>';
    exit();
}

$uid = $ca->getUserID();

$params = SolusVM::getParamsFromServiceID( $servid, $uid );
if ( $params === false ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_vserverNotFound'] . '</div></body>';
    exit;
}
$solusvm = new SolusVM( $params );

################### Code ###################
if ( $solusvm->getExtData["clientfunctions"] == "disable" ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_functionDisabled'] . '</body>';
}

if ( $solusvm->getExtData["vnc"] == "disable" ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_functionDisabled'] . '</body>';
}

$callArray = array( "vserverid" => $params["vserver"] );
$solusvm->apiCall( 'vserver-vnc', $callArray );
$r = $solusvm->result;


if ( $r["status"] != "success" ) {
    $pagedata = '<div class="alert alert-danger">' . $_LANG['solusvmpro_unknownError'] . '</div>';
    echo $pagedata;
    echo '</body>';
    exit();
}
if ( isset( $r["vncip"] ) ) {
    $r["vncip"] = trim( $r["vncip"] );
}
if ( isset( $r["sockethost"] ) ) {
    $r["sockethost"] = trim( $r["sockethost"] );
}

if ( $r["type"] != "xenhvm" && $r["type"] != "kvm" ) {
    $pagedata = '<div class="alert alert-danger">' . $_LANG['solusvmpro_unknownError'] . '</div>';
    echo $pagedata;
    echo '</body>';
    exit();
}
if ( $r["sockethost"] ) {
    $htmlvnc = '&nbsp;&nbsp;<input type="button" class="button" name="" id="" value="' . $_LANG['solusvmpro_HTMLVNC'] . '" onClick="window.location=\'vnc.php?id=' . $servid . '&_vnc=html\'"/>';
}
$javavnc = '&nbsp;&nbsp;<input type="button" class="button" name="" id="" value="' . $_LANG['solusvmpro_JavaVNC'] . '" onClick="window.location=\'vnc.php?id=' . $servid . '&_vnc=java\'"/>';
$buttons = '<div align="center">' . $javavnc . $htmlvnc . '<br><br><input type="button" class="button" name="" id="" value="' . $_LANG['solusvmpro_refresh'] . '" onClick="window.location=\'vnc.php?id=' . $servid . '&_vnc=java\'"/></div><br>';
if ( $_GET['_vnc'] == "java" ) {
    $pagedata = $buttons . '<div align="center"><APPLET CODE="VncViewer.class" ARCHIVE="java/vnc/VncViewer.jar">
            <PARAM NAME="Open new window" VALUE=Yes>
            <PARAM NAME="Scaling factor" VALUE=auto>
<PARAM NAME="PORT" VALUE="' . $r['vncport'] . '">
<PARAM NAME="HOST" VALUE="' . $r['vncip'] . '">
<PARAM NAME="PASSWORD" VALUE="' . $r['vncpassword'] . '">' . $_LANG['solusvmpro_needsJavaPlugins'] . '<applet></applet>
</APPLET></div>';
    echo $pagedata;
} elseif ( $_GET['_vnc'] == "html" ) { ?>


    <!DOCTYPE html>
    <html>
    <head>

        <!--
        noVNC example: simple example using default UI
        Copyright (C) 2012 Joel Martin
        Copyright (C) 2013 Samuel Mannehed for Cendio AB
        noVNC is licensed under the MPL 2.0 (see LICENSE.txt)
        This file is licensed under the 2-Clause BSD license (see LICENSE.txt).

        Connect parameters are provided in query string:
            http://example.com/?host=HOST&port=PORT&encrypt=1&true_color=1
        -->
        <title><?php echo $_LANG['solusvmpro_noVNC']; ?></title>

        <meta charset="utf-8">

        <!-- Always force latest IE rendering engine (even in intranet) & Chrome Frame
                    Remove this if you use the .htaccess -->
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

        <!-- Apple iOS Safari settings -->
        <meta name="viewport"
              content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <meta name="apple-mobile-web-app-capable" content="yes"/>
        <meta name="apple-mobile-web-app-status-bar-style"
              content="black-translucent"/>
        <!-- App Start Icon  -->
        <link rel="apple-touch-startup-image"
              href="novnc/images/screen_320x460.png"/>
        <!-- For iOS devices set the icon to use if user bookmarks app on their homescreen -->
        <link rel="apple-touch-icon"
              href="novnc/images/screen_57x57.png">
        <!--
        <link rel="apple-touch-icon-precomposed" href="images/screen_57x57.png" />
        -->


        <!-- Stylesheets -->
        <link rel="stylesheet" href="novnc/include/base.css" title="plain">

        <!--
       <script type='text/javascript'
           src='http://getfirebug.com/releases/lite/1.2/firebug-lite-compressed.js'></script>
       -->
        <script src="novnc/include/util.js"></script>
    </head>

    <body style="margin: 0px;">
    <div id="noVNC_screen">
        <div id="noVNC_status_bar" class="noVNC_status_bar"
             style="margin-top: 0px;">
            <table border=0 width="100%">
                <tr>
                    <td>
                        <div id="noVNC_status"
                             style="position: relative; height: auto;">
                            <?php echo $_LANG['solusvmpro_loading']; ?>
                        </div>
                    </td>
                    <td width="1%">
                        <div id="noVNC_buttons">
                            <input type=button value="Send CtrlAltDel"
                                   id="sendCtrlAltDelButton">
                        <span id="noVNC_xvp_buttons">
                        <input type=button value="<?php echo $_LANG['solusvmpro_shutdown']; ?>"
                               id="xvpShutdownButton">
                        <input type=button value="<?php echo $_LANG['solusvmpro_reboot']; ?>"
                               id="xvpRebootButton">
                        <input type=button value="<?php echo $_LANG['solusvmpro_reset']; ?>"
                               id="xvpResetButton">
                        </span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <canvas id="noVNC_canvas" width="640px" height="20px">
            <?php echo $_LANG['solusvmpro_canvasNotSupported']; ?>
        </canvas>
    </div>

    <script>
        /*jslint white: false */
        /*global window, $, Util, RFB, */
        "use strict";

        // Load supporting scripts
        Util.load_scripts(["novnc/include/webutil.js", "novnc/include/base64.js", "novnc/include/websock.js", "novnc/include/des.js",
            "novnc/include/keysymdef.js", "novnc/include/keyboard.js", "novnc/include/input.js", "novnc/include/display.js",
            "novnc/include/jsunzip.js", "novnc/include/rfb.js"]);

        var rfb;

        function passwordRequired(rfb) {
            var msg;
            msg = '<form onsubmit="return setPassword();"';
            msg += '  style="margin-bottom: 0px">';
            msg += 'Password Required: ';
            msg += '<input type=password size=10 id="password_input" class="noVNC_status">';
            msg += '<\/form>';
            $D('noVNC_status_bar').setAttribute("class", "noVNC_status_warn");
            $D('noVNC_status').innerHTML = msg;
        }
        function setPassword() {
            rfb.sendPassword($D('password_input').value);
            return false;
        }
        function sendCtrlAltDel() {
            rfb.sendCtrlAltDel();
            return false;
        }
        function xvpShutdown() {
            rfb.xvpShutdown();
            return false;
        }
        function xvpReboot() {
            rfb.xvpReboot();
            return false;
        }
        function xvpReset() {
            rfb.xvpReset();
            return false;
        }
        function updateState(rfb, state, oldstate, msg) {
            var s, sb, cad, level;
            s = $D('noVNC_status');
            sb = $D('noVNC_status_bar');
            cad = $D('sendCtrlAltDelButton');
            switch (state) {
                case 'failed':
                    level = "error";
                    break;
                case 'fatal':
                    level = "error";
                    break;
                case 'normal':
                    level = "normal";
                    break;
                case 'disconnected':
                    level = "normal";
                    break;
                case 'loaded':
                    level = "normal";
                    break;
                default:
                    level = "warn";
                    break;
            }

            if (state === "normal") {
                cad.disabled = false;
            } else {
                cad.disabled = true;
                xvpInit(0);
            }

            if (typeof(msg) !== 'undefined') {
                sb.setAttribute("class", "noVNC_status_" + level);
                s.innerHTML = msg;
            }
        }

        function xvpInit(ver) {
            var xvpbuttons;
            xvpbuttons = $D('noVNC_xvp_buttons');
            if (ver >= 1) {
                xvpbuttons.style.display = 'inline';
            } else {
                xvpbuttons.style.display = 'none';
            }
        }

        window.onscriptsload = function () {
            var host, port, password, path, token;

            $D('sendCtrlAltDelButton').style.display = "inline";
            $D('sendCtrlAltDelButton').onclick = sendCtrlAltDel;
            $D('xvpShutdownButton').onclick = xvpShutdown;
            $D('xvpRebootButton').onclick = xvpReboot;
            $D('xvpResetButton').onclick = xvpReset;

            WebUtil.init_logging(WebUtil.getQueryVar('logging', 'warn'));
            document.title = unescape(WebUtil.getQueryVar('title', 'noVNC'));
            // By default, use the host and port of server that served this file
            host = WebUtil.getQueryVar('host', window.location.hostname);
            port = WebUtil.getQueryVar('port', window.location.port);

            // if port == 80 (or 443) then it won't be present and should be
            // set manually
            if (!port) {
                if (window.location.protocol.substring(0, 5) == 'https') {
                    port = 443;
                }
                else if (window.location.protocol.substring(0, 4) == 'http') {
                    port = 80;
                }
            }

            // If a token variable is passed in, set the parameter in a cookie.
            // This is used by nova-novncproxy.
            token = WebUtil.getQueryVar('token', null);
            if (token) {
                WebUtil.createCookie('token', token, 1)
            }

            password = WebUtil.getQueryVar('password', '');
            path = WebUtil.getQueryVar('path', 'websockify');

            if ((!host) || (!port)) {
                updateState('failed',
                    "Must specify host and port in URL");
                return;
            }

            rfb = new RFB({
                'target': $D('noVNC_canvas'),
                'encrypt': WebUtil.getQueryVar('encrypt',
                    (window.location.protocol === "https:")),
                'repeaterID': WebUtil.getQueryVar('repeaterID', ''),
                'true_color': WebUtil.getQueryVar('true_color', true),
                'local_cursor': WebUtil.getQueryVar('cursor', true),
                'shared': WebUtil.getQueryVar('shared', true),
                'view_only': WebUtil.getQueryVar('view_only', false),
                'updateState': updateState,
                'onXvpInit': xvpInit,
                'onPasswordRequired': passwordRequired
            });
            rfb.connect('<?=$r["sockethost"];?>', '<?=$r["socketport"];?>', '<?=$r["socketpassword"];?>', '?token=<?=$r["sockethash"];?>');
        };
    </script>
    </body>
    </html>
    <?php
} else {
    $pagedata = $buttons;
    echo $pagedata;
}

################## Code End ################

echo '</body>';
