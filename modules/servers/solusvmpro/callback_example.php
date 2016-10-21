<?php

// it needs to be removed
exit();

/**
 * @var $admin_user
 * The username of a administrator in your whmcs.
 */

$admin_user = "admin";

/**
 * @var $security_hash
 * The hash as shown in SolusVM.
 */

$security_hash = "xxx";

/**
 * @var $connection_ip
 * The ip address of your SolusVM master.
 */

$connection_ip = "xxx";


/**
 * NO NEED TO EDIT BELOW THIS LINE!
 */

require( "../../../init.php" );

use Illuminate\Database\Capsule\Manager as Capsule;

$hash      = isset( $_POST["hash"] ) ? $_POST["hash"] : "";
$action    = isset( $_POST["action"] ) ? $_POST["action"] : "";
$vserverid = isset( $_POST["vserverid"] ) ? (int) $_POST["vserverid"] : "";
$extra     = isset( $_POST["data"] ) ? $_POST["data"] : "";

$extra_var = array();
if ( $extra ) {
    $extra_var = unserialize( base64_decode( $extra ) );
}

$remote_addr = isset( $_SERVER["REMOTE_ADDR"] ) ? $_SERVER["REMOTE_ADDR"] : "";


if ( $remote_addr != $connection_ip ) {
    echo "invalid ip";
    exit();
}
if ( $hash != $security_hash ) {
    echo "invalid hash";
    exit();
}

if ( ! ctype_digit( $vserverid ) ) {
    echo "invalid vserverid";
    exit();
}

$product = Capsule::table( 'tblcustomfields' )
                  ->join( 'tblcustomfieldsvalues', 'tblcustomfieldsvalues.fieldid', '=', 'tblcustomfields.id' )
                  ->select( 'tblcustomfields.id AS field_id', 'tblcustomfields.type AS field_type,', 'tblcustomfields.fieldname AS field_name', 'tblcustomfieldsvalues.fieldid AS value_id', 'tblcustomfieldsvalues.value AS value_value', 'tblcustomfieldsvalues.relid AS value_productid' )
                  ->where( 'tblcustomfields.type', 'product' )
                  ->where( 'tblcustomfields.fieldname', 'vserverid' )
                  ->where( 'tblcustomfieldsvalues.value', $vserverid )
                  ->first();

if ( ! $product ) {
    echo "vserverid not found";
    exit();
}

$hosting_id = $product->value_productid;
switch ( $action ) {

    case "suspend":

        /**
         * Product suspend
         */

        $values["messagename"] = "Service Suspension Notification";
        $values["id"]          = $hosting_id;

        Capsule::table( 'tblhosting' )
               ->where( 'id', $hosting_id )
               ->update(
                   [
                       'domainstatus'  => 'Suspended',
                       'suspendreason' => $extra_var["suspendreason"],
                   ]
               );
        $output = localAPI( 'sendemail', $values, $admin_user );

        if ( $output["result"] == "success" ) {
            echo "<success>1</success>";
        } else {
            echo "Error: " . $output["message"];
        }

        break;

    case "unsuspend":

        /**
         * Product unsuspend
         */

        Capsule::table( 'tblhosting' )
               ->where( 'id', $hosting_id )
               ->update(
                   [
                       'domainstatus'  => 'Active',
                       'suspendreason' => '',
                   ]
               );

        echo "<success>1</success>";

        break;

    case "terminate":

        /**
         * Product terminate
         */

        Capsule::table( 'tblhosting' )
               ->where( 'id', $hosting_id )
               ->update(
                   [
                       'domainstatus' => 'Terminated',
                   ]
               );

        echo "<success>1</success>";

        break;

    case "changehostname":

        /**
         * Product change hostname
         */

        Capsule::table( 'tblhosting' )
               ->where( 'id', $hosting_id )
               ->update(
                   [
                       'domain' => $extra_var["newhostname"],
                   ]
               );

        echo "<success>1</success>";

        break;

    case "changeip":

        /**
         * Product IP Change
         */

        $ip_list = array();

        if ( isset( $extra_var["ipv4"] ) && $extra_var["ipv4"] ) {
            $ipv4_list = explode( ",", $extra_var["ipv4"] );
            foreach ( $ipv4_list as $ipv4 ) {
                $ip_list[] = $ipv4;
            }
        }

        if ( isset( $extra_var["ipv6"] ) && $extra_var["ipv6"] ) {
            $ipv6_list = explode( ",", $extra_var["ipv6"] );
            foreach ( $ipv6_list as $ipv6 ) {
                $ip_list[] = $ipv6;
            }
        }

        if ( $ip_list ) {
            $ips = implode( "\n", $ip_list );
        } else {
            $ips = "";
        }

        Capsule::table( 'tblhosting' )
               ->where( 'id', $hosting_id )
               ->update(
                   [
                       'assignedips' => $ips,
                   ]
               );

        echo "<success>1</success>";

        break;

    default:

        /**
         * If no product function is defined
         */

        echo "<success>1</success>";
}

