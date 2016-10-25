<?php

define( "CLIENTAREA", true );
require( "../../../init.php" );

require_once __DIR__ . '/lib/Curl.php';
require_once __DIR__ . '/lib/CaseInsensitiveArray.php';
require_once __DIR__ . '/lib/SolusVM.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use SolusVM\SolusVM;

/** @var array $_LANG */
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
    if((!isset($_SESSION['adminid']) || ((int)$_SESSION['adminid'] <= 0))){
        echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_unauthorized'] . '</div></body>';
        exit();
    }
    $uid = (int)$_GET['uid'];
}else{
    $uid = $ca->getUserID();
}
$servid = (int) $_GET['id'];
if ( $servid == "" ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_unauthorized'] . '</div></body>';
    exit();
}

$params = SolusVM::getParamsFromServiceID( $servid, $uid );
if ( $params === false ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_vserverNotFound'] . '</div></body>';
    exit;
}
$solusvm = new SolusVM( $params );

if ( $solusvm->getExtData( "clientfunctions" ) == "disable" ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_functionDisabled'] . '</body>';
    exit;
}
if ( $solusvm->getExtData( "serialconsole" ) == "disable" ) {
    echo '<div class="alert alert-danger">' . $_LANG['solusvmpro_functionDisabled'] . '</body>';
    exit;
}

################### Code ###################

if ( isset( $_POST["sessioncancel"] ) ) {
    $callArray = array( "access" => "disable", "vserverid" => $params['vserver'] );
} elseif ( isset( $_POST["sessioncreate"] ) ) {
    $stime = $_POST["sessiontime"];
    if ( ! is_numeric( $stime ) ) {
        exit();
    } else {
        $callArray = array( "access" => "enable", "time" => $stime, "vserverid" => $params['vserver'] );
    }
} else {
    ## The call string for the connection function
    $callArray = array( "vserverid" => $params['vserver'] );
}

$solusvm->apiCall( 'vserver-console', $callArray );
$r = $solusvm->result;

if ( $r["status"] == "success" ) {
    if ( $r["sessionactive"] == "1" ) {
        if ( $r["type"] != "openvz" && $r["type"] != "xen" ) {
            exit();
        }

        ?>

        <div align="center"><p style="padding-bottom: 5px"><?php echo $_LANG['solusvmpro_password'] . ': ' . $r['consolepassword']; ?></p><br>
            <applet code="com.jcraft.jcterm.JCTermApplet.class"
                    archive="jcterm-0.0.10.jar?1,jsch-0.1.46.jar?1,jzlib-1.1.1.jar?1" width="800" height="600"
                    codebase="java/jcterm/">
                <param name="jcterm.font_size" value="12">
                <param name="jcterm.fg_bg" value="#000000:#ffffff,#ffffff:#000000,#00ff00:#000000">
                <param name="jcterm.destinations"
                       value="<?php echo $r['consoleusername']; ?>@<?php echo $r['consoleip']; ?>:<?php echo $r['consoleport']; ?>">
            </applet>
        </div>
        <br>
        <form action="" method="post" name="cancelsession">
            <div style="padding: 10px 0 10px 0; text-align:center">
                <input name="sessioncancel" type="submit" value="<?php echo $_LANG['solusvmpro_cancelSession']; ?>">
            </div>
        </form>

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
                    <button name="sessioncreate" type="submit" class="btn btn-success"><?php echo $_LANG['solusvmpro_createSession']; ?></button>
                    <button type="button" class="btn btn-danger" onclick="window.close()"><?php echo $_LANG['solusvmpro_closeWindow']; ?></button>
                </form>
            </div>
            <div class="col-xs-2"></div>
        </div>

        <?php
    }


} else {
    if ( isset( $r["statusmsg"] ) ) {
        $pagedata = (string) $r["statusmsg"];
    } else {
        $pagedata = $_LANG['solusvmpro_couldntConnectMaster'];
    }
    $pagedata .= '<br><br>';

    echo $pagedata;
}

################## Code End ################

echo '</body>';
