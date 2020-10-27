<?php
define( "CLIENTAREA", true );

require( "../../../init.php" );
session_start();

require_once __DIR__ . '/lib/Curl.php';
require_once __DIR__ . '/lib/CaseInsensitiveArray.php';
require_once __DIR__ . '/lib/SolusVM.php';

use Composer\Semver\Comparator;
use Illuminate\Database\Capsule\Manager as Capsule;
use SolusVM\SolusVM;

$vserverid = (int) $_GET["vserverid"];

SolusVM::loadLang();

$ca = new WHMCS_ClientArea();
if ( ! $ca->isLoggedIn() ) {
    echo $_LANG['solusvmpro_unauthorized'] . "</body>";
    exit();
}

// Adding this to workaround CORE-15619 WHMCS bug
$whmcsVersion = $CONFIG['Version'];
$uid = $ca->getUserID();
if (Comparator::greaterThanOrEqualTo($whmcsVersion,'8')) {
    $uid = Auth::client()->id;
}

$params = SolusVM::getParamsFromVserviceID( $vserverid, $uid );
if ( ! $params ) {
    $result = array(
        'status'        => 'error',
        'displaystatus' => $_LANG['solusvmpro_vserverNotFound'],
    );
    echo json_encode( $result );
    exit();
}

$solusvm = new SolusVM( $params );

$callArray = array( "vserverid" => $vserverid );

$solusvm->apiCall( 'vserver-infoall', $callArray );
$r = $solusvm->result;

$cparams = $solusvm->clientAreaCalculations( $r );

if($r['rescuemode'] != 0){
    $solusvm->apiCall( 'vserver-rescue', $callArray );
    $cparams['rescueData'] = $solusvm->result;
}

echo json_encode( $cparams );
