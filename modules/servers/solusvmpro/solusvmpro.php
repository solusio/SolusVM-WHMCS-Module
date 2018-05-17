<?php

/**
 * WHMCS SolusVM Module
 *
 */

require_once __DIR__ . '/lib/Curl.php';
require_once __DIR__ . '/lib/CaseInsensitiveArray.php';
require_once __DIR__ . '/lib/SolusVM.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use SolusVM\SolusVM;

if ( ! defined( "WHMCS" ) ) {
    die( "This file cannot be accessed directly" );
}

if ( file_exists( __DIR__ . "/custom.php" ) ) {
    require_once( __DIR__ . "/custom.php" );
}

SolusVM::loadLang();

function initConfigOption()
{
    if(!isset($_POST['id'])){
        $data = Capsule::table('tblproducts')->where('servertype', 'solusvmpro')->where('id', $_GET['id'])->get();
    }else{
        $data = Capsule::table('tblproducts')->where('servertype', 'solusvmpro')->where('id', $_POST['id'])->get();
    }
    $packageconfigoption = [];
    if(is_array($data) && count($data) > 0) {
        $packageconfigoption[1] = $data[0]->configoption1;
        $packageconfigoption[3] = $data[0]->configoption3;
        $packageconfigoption[5] = $data[0]->configoption5;
    }

    return $packageconfigoption;
}


function solusvmpro_ConfigOptions() {
    try {

        $packageconfigoption = initConfigOption();

        $master_array = array();
        /** @var stdClass $row */
        foreach ( Capsule::table( 'tblservers' )->where( 'type', 'solusvmpro' )->get() as $row ) {
            $master_array[] = $row->id . " - " . $row->name;
        }

        $master_list = implode( ",", $master_array );

        $vt = '';
        if ( $packageconfigoption[5] == "OpenVZ" ) {
            $vt = "openvz";
        } elseif ( $packageconfigoption[5] == "Xen-PV" ) {
            $vt = "xen";
        } elseif ( $packageconfigoption[5] == "Xen-HVM" ) {
            $vt = "xen hvm";
        } elseif ( $packageconfigoption[5] == "KVM" ) {
            $vt = "kvm";
        }

        $solusvm = new SolusVM( array( 'configoption1' => $packageconfigoption[1], 'configoption3' => $packageconfigoption[3] ) );

        $callArray = array( "type" => $vt );

        ## List plans
        $solusvm->apiCall( 'listplans', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $default_plan = $solusvm->result["plans"];
        } else {
            $default_plan = $solusvm->rawResult;
        }

        ## List nodes
        $solusvm->apiCall( 'listnodes', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $default_node = $solusvm->result["nodes"];
        } else {
            $default_node = $solusvm->rawResult;
        }

        ## List node groups
        $solusvm->apiCall( 'listnodegroups', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $default_nodegroup = $solusvm->result["nodegroups"];
        } else {
            $default_nodegroup = $solusvm->rawResult;
        }

        ## List templates
        $solusvm->apiCall( 'listtemplates', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $default_template = $solusvm->result["templates"];
        } else {
            $default_template = $solusvm->rawResult;
        }

        $iprange     = implode( ",", range( 1, 500 ) );
        $configarray = array(
            "Module User Type"         => array( "Type" => "dropdown", "Options" => "Admin,Reseller" ),
            "Default Node"             => array( "Type" => "dropdown", "Options" => "$default_node" ),
            "Master Server"            => array( "Type" => "dropdown", "Options" => "$master_list" ),
            "Default Plan"             => array( "Type" => "dropdown", "Options" => "$default_plan" ),
            "Virtualization Type"      => array( "Type" => "dropdown", "Options" => "OpenVZ,Xen-PV,Xen-HVM,KVM" ),
            "Default Operating System" => array( "Type" => "dropdown", "Options" => "$default_template" ),
            "Username Prefix"          => array( "Type" => "text", "Size" => "25" ),
            "IP Addresses"             => array( "Type" => "dropdown", "Options" => "$iprange" ),
            "Node Group"               => array( "Type" => "dropdown", "Options" => "$default_nodegroup" ),
            "Internal IP"              => array( "Type" => "dropdown", "Options" => "No,Yes" ),
        );

        return $configarray;
    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Config Options',
            __FUNCTION__,
            '',
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}


function solusvmpro_CreateAccount( $params ) {
    try {
        global $_LANG;

        $solusvm = new SolusVM( $params );

        $serviceid      = $solusvm->getParam( "serviceid" ); # Unique ID of the product/service in the WHMCS Database
        $clientsdetails = $solusvm->getParam( "clientsdetails" );

        # Product module option settings from ConfigOptions array above
        $configOptionPlan       = $solusvm->getParam( "configoption4" );
        $configOptionIpCount    = $solusvm->getParam( "configoption8" );
        $configOptionInternalIp = $solusvm->getParam( "configoption10" );
        # Array of clients details - firstname, lastname, email, country, etc...
        $customField = $solusvm->getParam( "customfields" ); # Array of custom field values for the product

        if ( function_exists( 'solusvmpro_create_one' ) ) {
            $res = solusvmpro_create_one( $params );
            if( $res['cancel_process'] === true ){
                return $_LANG['solusvmpro_cancel_custom_create_process'];
            }
        }

        $newDataPassword = $solusvm->getNewDataPassword();
        if ( function_exists( 'solusvmpro_username' ) ) {
            $clientUsername = solusvmpro_username( $params );
        } else {
            $clientUsername = $solusvm->getUsername();
        }
        $buildNode            = $solusvm->getBuildNode();
        $buildOperatingSystem = $solusvm->getBuildOperatingSystem();
        $cpGroup              = $solusvm->getCPGroup();
        $buildGroup           = $solusvm->getBuildGroup();

        #########################################
        ## Custom settings from config options ##
        #########################################

        $cmem       = $solusvm->getCmem();
        $cdisk      = $solusvm->getCdisk();
        $cbandwidth = $solusvm->getCbandwidth();
        $ccpu       = $solusvm->getCcpu();
        $cextraip   = $solusvm->getCextraip();
        $cnspeed    = $solusvm->getCnspeed();

        #########################################

        if ( function_exists( 'solusvmpro_hostname' ) ) {
            $newHost = solusvmpro_hostname( $params );
        } else {
            $newHost = $solusvm->getHostname();
        }

        if ( function_exists( 'solusvmpro_create_two' ) ) {
            solusvmpro_create_two( $params );
        }

        ## The call string for the connection function
        $callArray = array(
            "username"  => $clientUsername,
            "password"  => $newDataPassword,
            "email"     => $clientsdetails['email'],
            "firstname" => $clientsdetails['firstname'],
            "lastname"  => $clientsdetails['lastname']
        );

        $solusvm->apiCall( 'client-create', $callArray );
        $r = $solusvm->result;

        if ( $r["status"] != "success" && $r["statusmsg"] != "Client already exists" ) {
            return "Cannot create client";
        }

        ## Update the username field
        Capsule::table( 'tblhosting' )
               ->where( 'id', $serviceid )
               ->update(
                   [
                       'username' => $clientUsername,
                   ]
               );

        $returnData["password"] = $r["password"];

        ## Check for a vserverid and if it exists check to see if theres already a vps created
        $noVps = '';
        $r     = array();
        if ( empty( $customField['vserverid'] ) ) {
            $noVps = 'success';
        } else {
            ## The call string for the connection fuction
            $callArray = array( "vserverid" => $customField['vserverid'] );
            $solusvm->apiCall( 'vserver-checkexists', $callArray );
            $r = $solusvm->result;
        }

        if ( $r["statusmsg"] != "Virtual server not found" && $noVps != 'success' ) {
            return "Virtual server already exists";
        }

        ## Rename the types so the API can read them
        $vt = $solusvm->getVT();

        if ( function_exists( 'solusvmpro_create_three' ) ) {
            solusvmpro_create_three( $params );
        }

        if ( $configOptionInternalIp == "Yes" ) {
            $isinternalip = "1";
        } else {
            $isinternalip = "0";
        }
        ## The call string for the connection function
        $callArray = array(
            "customextraip"   => $cextraip,
            "customcpu"       => $ccpu,
            "custombandwidth" => $cbandwidth,
            "customdiskspace" => $cdisk,
            "custommemory"    => $cmem,
            "customnspeed"    => $cnspeed,
            "hvmt"            => "1",
            "type"            => $vt,
            "nodegroup"       => $buildGroup,
            "node"            => $buildNode,
            "hostname"        => $newHost,
            "username"        => $clientUsername,
            "password"        => $newDataPassword,
            "plan"            => $configOptionPlan,
            "template"        => $buildOperatingSystem,
            "issuelicense"    => $cpGroup,
            "internalip"      => $isinternalip,
            "ips"             => $configOptionIpCount
        );

        logModuleCall(
            'create account',
            '',
            $callArray,
            $callArray,
            ''
        );


        $solusvm->apiCall( 'vserver-create', $callArray );
        $r = $solusvm->result;

        if ( $r["status"] == "success" ) {

            $fields = [
                "vserverid",
                "nodeid",
                "consoleuser",
                "rootpassword",
                "instructions",
                "vncip",
                "vncport",
                "vncpassword",
                "internalip"
            ];

            foreach ( $fields as $field ) {
                if ( ! isset( $r[ $field ] ) ) {
                    $r[ $field ] = '';
                }
            }

            foreach ( $fields as $field ) {
                $solusvm->setCustomfieldsValue( $field, $r[ $field ] );
            }

            ## Insert the dedicated ip
            $mainip = $r["mainipaddress"];
            Capsule::table( 'tblhosting' )
                   ->where( 'id', $serviceid )
                   ->update(
                       [
                           'dedicatedip' => $mainip,
                       ]
                   );

            ## Update the hostname just in case solus changed it
            $solusvm->setHostname( $r["hostname"] );

            ## Sort out the extra ip's if there is any
            $extraip = $r["extraipaddress"];
            if ( ! empty( $extraip ) ) {
                ## Remove the comma and replace with a line break
                $iplist = str_replace( ",", "\n", $extraip );
                Capsule::table( 'tblhosting' )
                       ->where( 'id', $serviceid )
                       ->update(
                           [
                               'assignedips' => $iplist,
                           ]
                       );
            }
            $result = "success";

        } else { // else creation failed
            $result = $r["statusmsg"];
        }

        if ( $result == "success" ) {
            if ( function_exists( 'solusvmpro_create_four' ) ) {
                solusvmpro_create_four( $params );
            }
        } else {
            if ( function_exists( 'solusvmpro_create_five' ) ) {
                solusvmpro_create_five( $params );
            }
        }

        return $result;

    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Create Account',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

function solusvmpro_SuspendAccount( $params ) {
    global $_LANG;

    try {
        if ( function_exists( 'solusvmpro_suspend_pre' ) ) {
            $res = solusvmpro_suspend_pre( $params );
            if( $res['cancel_process'] === true ){
                return $_LANG['solusvmpro_cancel_custom_suspend_process'];
            }
        }

        $solusvm = new SolusVM( $params );

        $customField = $solusvm->getParam( "customfields" );

        ## The call string for the connection fuction
        $callArray = array( "vserverid" => $customField["vserverid"] );

        $solusvm->apiCall( 'vserver-suspend', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $result = "success";
        } else {
            $result = (string) $solusvm->result["statusmsg"];
        }

        if ( $result == "success" ) {
            if ( function_exists( 'solusvmpro_suspend_post_success' ) ) {
                solusvmpro_suspend_post_success( $params );
            }
        } else {
            if ( function_exists( 'solusvmpro_suspend_post_error' ) ) {
                solusvmpro_suspend_post_error( $params );
            }
        }

        return $result;
    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Suspend Account',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

function solusvmpro_UnsuspendAccount( $params ) {
    global $_LANG;
    try {
        if ( function_exists( 'solusvmpro_unsuspend_pre' ) ) {
            $res = solusvmpro_unsuspend_pre( $params );
            if( $res['cancel_process'] === true ){
                return $_LANG['solusvmpro_cancel_custom_unsuspend_process'];
            }
        }

        $solusvm = new SolusVM( $params );

        $customField = $solusvm->getParam( "customfields" );

        ## The call string for the connection fuction
        $callArray = array( "vserverid" => $customField["vserverid"] );

        $solusvm->apiCall( 'vserver-unsuspend', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $result = "success";
        } else {
            $result = (string) $solusvm->result["statusmsg"];
        }

        if ( $result == "success" ) {
            if ( function_exists( 'solusvmpro_unsuspend_post_success' ) ) {
                solusvmpro_unsuspend_post_success( $params );
            }
        } else {
            if ( function_exists( 'solusvmpro_unsuspend_post_error' ) ) {
                solusvmpro_unsuspend_post_error( $params );
            }
        }

        return $result;

    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Unsuspend Account',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

function solusvmpro_TerminateAccount( $params ) {
    global $_LANG;

    try {

        if ( function_exists( 'solusvmpro_terminate_pre' ) ) {
            $res = solusvmpro_terminate_pre( $params );
            if( $res['cancel_process'] === true ){
                return $_LANG['solusvmpro_cancel_custom_terminate_process'];
            }
        }

        $solusvm = new SolusVM( $params );

        $customField = $solusvm->getParam( "customfields" );
        $callArray   = array(
            "vserverid"    => $customField["vserverid"],
            "deleteclient" => "true",
        );

        $solusvm->apiCall( 'vserver-terminate', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $solusvm->removeipTerminatedProduct();

            $solusvm->removevserveridTerminatedProduct();

            $solusvm->setCustomfieldsValue( 'nodeid', "" );
            $solusvm->setCustomfieldsValue( 'rootpassword', "" );
            $solusvm->setCustomfieldsValue( 'instructions', "" );
            $solusvm->setCustomfieldsValue( 'vncip', "" );
            $solusvm->setCustomfieldsValue( 'vncport', "" );
            $solusvm->setCustomfieldsValue( 'vncpassword', "" );
            $solusvm->setCustomfieldsValue( 'internalip', "" );

            $result = "success";
        } else {
            $result = (string) $solusvm->result["statusmsg"];
        }

        if ( $result == "success" ) {
            if ( function_exists( 'solusvmpro_terminate_post_success' ) ) {
                solusvmpro_terminate_post_success( $params );
            }
        } else {
            if ( function_exists( 'solusvmpro_terminate_post_error' ) ) {
                solusvmpro_terminate_post_error( $params );
            }
        }

        return $result;
    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Terminate Account',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}


function solusvmpro_AdminCustomButtonArray() {
    global $_LANG;

    return array(
        $_LANG["solusvmpro_reboot"]   => "reboot",
        $_LANG["solusvmpro_shutdown"] => "shutdown",
        $_LANG["solusvmpro_boot"]     => "boot",
    );
}

function solusvmpro_ClientAreaCustomButtonArray() {
    global $_LANG;

    return array(
        $_LANG["solusvmpro_reboot"]   => "reboot",
        $_LANG["solusvmpro_shutdown"] => "shutdown",
        $_LANG["solusvmpro_boot"]     => "boot",
    );
}

################################################################################
### Reboot function                                                          ###
################################################################################

function solusvmpro_reboot( $params ) {
    try {
        $solusvm     = new SolusVM( $params );
        $customField = $solusvm->getParam( "customfields" );

        ## The call string for the connection fuction
        $callArray = array( "vserverid" => $customField["vserverid"] );

        $solusvm->apiCall( 'vserver-reboot', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $result = "success";
        } else {
            $result = (string) $solusvm->result["statusmsg"];
        }

        return $result;
    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'reboot',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

################################################################################
### Boot function                                                            ###
################################################################################

function solusvmpro_boot( $params ) {
    try {
        $solusvm     = new SolusVM( $params );
        $customField = $solusvm->getParam( "customfields" );

        ## The call string for the connection fuction
        $callArray = array( "vserverid" => $customField["vserverid"] );

        $solusvm->apiCall( 'vserver-boot', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $result = "success";
        } else {
            $result = (string) $solusvm->result["statusmsg"];
        }

        return $result;
    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'boot',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

################################################################################
### Shutdown function                                                        ###
################################################################################

function solusvmpro_shutdown( $params ) {
    try {
        $solusvm     = new SolusVM( $params );
        $customField = $solusvm->getParam( "customfields" );

        ## The call string for the connection fuction
        $callArray = array( "vserverid" => $customField["vserverid"] );

        $solusvm->apiCall( 'vserver-shutdown', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $result = "success";
        } else {
            $result = (string) $solusvm->result["statusmsg"];
        }

        return $result;
    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'shutdown',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}


################################################################################
### Upgrade / Downgrade account function                                     ###
################################################################################

function solusvmpro_ChangePackage( $params ) {
    global $_LANG;
    try {
        if ( function_exists( 'solusvmpro_changepackage_pre' ) ) {
            $res = solusvmpro_changepackage_pre( $params );
            if( $res['cancel_process'] === true ){
                return $_LANG['solusvmpro_cancel_custom_package_change_process'];
            }
        }
        $solusvm     = new SolusVM( $params );
        $customField = $solusvm->getParam( "customfields" );

        #########################################
        ## Custom settings from config options ##
        #########################################
        $cmem       = $solusvm->getCmem();
        $cdisk      = $solusvm->getCdisk();
        $ccpu       = $solusvm->getCcpu();
        $cextraip   = $solusvm->getCextraip();
        $cnspeed    = $solusvm->getCnspeed();
        #########################################

        //Apply custom resources
        if ( !empty($cmem) || !empty($cdisk) || !empty($ccpu) || !empty($cextraip) ){

            $resource_errors = "";
            $error_divider = " ";

            if ( strpos($cmem, ':') !== false ){
                $cmem = str_replace(":", "|", $cmem);

                $solusvm->apiCall( 'vserver-change-memory', array( "memory" => $cmem, "vserverid" => $customField["vserverid"] ) );
                if ( $solusvm->result["status"] != "success" ) {
                    $resource_errors = (string) $solusvm->result["statusmsg"] . $error_divider;
                }

            }

            if ( $cdisk > 0 ){
                $solusvm->apiCall( 'vserver-change-hdd', array( "hdd" => $cdisk, "vserverid" => $customField["vserverid"] ) );
                if ( $solusvm->result["status"] != "success" ) {
                    $resource_errors .= (string) $solusvm->result["statusmsg"] . $error_divider;
                }

            }

            if ( $ccpu > 0 ){
                $solusvm->apiCall( 'vserver-change-cpu', array( "cpu" => $ccpu, "vserverid" => $customField["vserverid"] ) );
                if ( $solusvm->result["status"] != "success" ) {
                    $resource_errors .= (string) $solusvm->result["statusmsg"];
                }

            }

            if ( $cnspeed >= 0 ){
                $solusvm->apiCall( 'vserver-change-nspeed', array( "customnspeed" => $cnspeed, "vserverid" => $customField["vserverid"] ) );
                if ( $solusvm->result["status"] != "success" ) {
                    $resource_errors .= (string) $solusvm->result["statusmsg"];
                }

            }

            if ( $cextraip > 0 ){
                //first() function doesn't work
                $ipaddresses = Capsule::table('tblhosting')->select('assignedips')->where( 'id', $params['serviceid'] )->get();
                $ips = $ipaddresses[0]->assignedips;

                $lines_arr = explode(PHP_EOL, $ips);
                $num_current_ips = count($lines_arr);
                if( empty($lines_arr[0]) ){
                    $num_current_ips -= 1;
                }

                $additional_ips_needed = $cextraip - $num_current_ips;

                if ( $additional_ips_needed > 0 ){

                    for($i=1; $i<=$additional_ips_needed;$i++){

                        $solusvm->apiCall( 'vserver-addip', array( "vserverid" => $customField["vserverid"] ) );

                        if ( $solusvm->result["status"] != "success" ) {
                            $resource_errors .= (string) $solusvm->result["statusmsg"] . $error_divider;
                            break;
                        } else {
                            $lines_arr[] = $solusvm->result['ipaddress'];
                        }
                    }

                } else {

                    for($i=0; $i>$additional_ips_needed;$i--){

                        $solusvm->apiCall( 'vserver-delip', array( "vserverid" => $customField["vserverid"], "ipaddr" => $lines_arr[0]) );

                        if ( $solusvm->result["status"] != "success" ) {
                            $resource_errors .= (string) $solusvm->result["statusmsg"] . $error_divider;
                            break;
                        } else {
                            array_splice($lines_arr,0, 1);
                        }
                    }
                }
            }

            $ipArr = implode(PHP_EOL, $lines_arr);
            if(!empty($ipArr)){
                Capsule::table('tblhosting')->where( 'id', $params['serviceid'] )->update(['assignedips' => $ipArr]);
            }

            $result = empty( $resource_errors )? "success" : $resource_errors;

        } else { // full plan change

            ## The call string for the connection function
            $callArray = array(
                "plan"            => $params["configoption4"],
                "type"            => $solusvm->getVT(),
                "vserverid"       => $customField["vserverid"]
            );
            $solusvm->apiCall( 'vserver-change', $callArray );
            if ( $solusvm->result["status"] == "success" ) {
                $result = "success";
            } else {
                $result = (string) $solusvm->result["statusmsg"];
            }

        }
        if ( $result == "success" ) {
            if ( function_exists( 'solusvmpro_changepackage_post_success' ) ) {
                solusvmpro_changepackage_post_success( $params );
            }
        } else {
            if ( function_exists( 'solusvmpro_changepackage_post_error' ) ) {
                solusvmpro_changepackage_post_error( $params );
            }
        }

        return $result;

    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Change Package',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );
        return $e->getMessage();
    }
}

function solusvmpro_AdminServicesTabFields( $params ) {
    global $_LANG;
    try {
        $solusvm     = new SolusVM( $params );
        $serviceid   = $solusvm->getParam( 'serviceid' );
        $serverid    = $solusvm->getParam( "serverid" );
        $customField = $solusvm->getParam( "customfields" );

        $vserverid = '';
        if ( isset( $customField["vserverid"] ) ) {
            $vserverid = $customField["vserverid"];
        }

        if ( $solusvm->getExtData( "admin-control" ) == "disable" ) {
            return array();
        } else {

            $fieldTitle = $_LANG['solusvmpro_control'] . ' <button type="button" name="live" id="live" class="btn btn-sm" onclick="loadcontrol();" value="' . $_LANG['solusvmpro_refresh'] . '">' . $_LANG['solusvmpro_refresh'] . '</button>';

            $userid = 0;
            if(isset($_GET['userid'])){
                $userid = (int)$_GET['userid'];
            }

            $fieldBodyOnLoad   = '<script type="text/javascript">function loadcontrol(){var control = $(\'#control\'); control.html(\'<p>loading...</p>\'); control.load("../modules/servers/solusvmpro/svm_control.php?userid='.$userid.'&id=' . $vserverid . '&serverid=' . $serverid . '&serviceid=' . $serviceid . '");}$(document).ready(function(){loadcontrol()});</script><div id="control"></div>';
            $fieldBodyOnDemand = '<script type="text/javascript">function loadcontrol(){var control = $(\'#control\'); control.html(\'<p>loading...</p>\'); control.load("../modules/servers/solusvmpro/svm_control.php?userid='.$userid.'&id=' . $vserverid . '&serverid=' . $serverid . '&serviceid=' . $serviceid . '");}</script><div id="control"></div>';

            if ( $solusvm->getExtData( "admin-control-type" ) == "onload" ) {
                $fieldsarray = array( $fieldTitle => $fieldBodyOnLoad );
            } elseif ( $solusvm->getExtData( "admin-control-type" ) == "ondemand" ) {
                $fieldsarray = array( $fieldTitle => $fieldBodyOnDemand );
            } else {
                $fieldsarray = array( $fieldTitle => $fieldBodyOnLoad );
            }

            return $fieldsarray;
        }
    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Admin Services Tab Fields',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

if ( ! function_exists( 'solusvmpro_AdminLink' ) ) {
    function solusvmpro_AdminLink( $params ) {
        try {
            $solusvm = new SolusVM( $params );

            $fwdurl = $solusvm->apiCall( 'fwdurl' );

            $code = '<form action="' . ( $fwdurl ) . '/admincp/login.php" method="post" target="_blank">
                <input type="hidden" name="username" value="ADMINUSERNAME" />
                <input type="hidden" name="password" value="ADMINPASSOWRD" />
                <input type="submit" name="Submit" value="Login" />
                </form>';

            return $code;

        } catch ( Exception $e ) {
            // Record the error in WHMCS's module log.
            logModuleCall(
                'Admin Link',
                __FUNCTION__,
                $params,
                $e->getMessage(),
                $e->getTraceAsString()
            );

            return $e->getMessage();
        }
    }
}

function solusvmpro_Custom_ChangeHostname( $params = '' ) {
    global $_LANG;

    $newhostname   = $_GET['newhostname'];
    $check_section = SolusVM::dns_verify_rdns_section( $newhostname );
    if ( $check_section ) {
        ## The call string for the connection function

        $callArray = array( "vserverid" => $_GET['vserverid'], "hostname" => $newhostname );

        $solusvm = new SolusVM( $params );

        if ( $solusvm->getExtData( "clientfunctions" ) == "disable" ) {
            $result = (object) array(
                'success' => false,
                'msg'     => $_LANG['solusvmpro_functionDisabled'],
            );
            exit( json_encode( $result ) );
        }
        if ( $solusvm->getExtData( "hostname" ) == "disable" ) {
            $result = (object) array(
                'success' => false,
                'msg'     => $_LANG['solusvmpro_functionDisabled'],
            );
            exit( json_encode( $result ) );
        }

        $solusvm->apiCall( 'vserver-hostname', $callArray );
        $r = $solusvm->result;

        $message = '';
        if ( $r["status"] == "success" ) {
            $solusvm->setHostname( $newhostname );
            $message = $_LANG['solusvmpro_hostnameUpdated'];
        } elseif ( $r["status"] == "error" && $r["statusmsg"] == "Hostname not specified" ) {
            $message = $_LANG['solusvmpro_enterHostname'];
        } elseif ( $r["status"] == "error" && $r["statusmsg"] == "Not supported for this virtualization type" ) {
            $message = $_LANG['solusvmpro_virtualizationTypeError'];
        } else {
            $message = $_LANG['solusvmpro_unknownError'];
        }
        $result = (object) array(
            'success' => true,
            'msg'     => $message,
        );
        exit( json_encode( $result ) );

    } else {
        $result = (object) array(
            'success' => false,
            'msg'     => $_LANG['solusvmpro_invalidHostname'],
        );
        exit( json_encode( $result ) );

    }

}

function solusvmpro_Custom_ChangeRootPassword( $params = '' ) {
    global $_LANG;

    $newrootpassword      = $_GET['newrootpassword'];
    $checkNewRootPassword = SolusVM::validateRootPassword( $newrootpassword );
    if ( $checkNewRootPassword ) {
        ## The call string for the connection function
        $callArray = array( "vserverid" => $_GET['vserverid'], "rootpassword" => $newrootpassword );

        $solusvm = new SolusVM( $params );

        if ( $solusvm->getExtData( "clientfunctions" ) == "disable" ) {
            $result = (object) array(
                'success' => false,
                'msg'     => $_LANG['solusvmpro_functionDisabled'],
            );
            exit( json_encode( $result ) );
        }
        if ( $solusvm->getExtData( "rootpassword" ) == "disable" ) {
            $result = (object) array(
                'success' => false,
                'msg'     => $_LANG['solusvmpro_functionDisabled'],
            );
            exit( json_encode( $result ) );
        }

        $solusvm->apiCall( 'vserver-rootpassword', $callArray );
        $r = $solusvm->result;

        $message = '';
        if ( $r["status"] == "success" ) {
            $solusvm->setCustomfieldsValue( 'rootpassword', $newrootpassword );
            $message = $_LANG['solusvmpro_passwordUpdated'];
        } elseif ( $r["status"] == "error" && $r["statusmsg"] == "Root password not specified" ) {
            $message = $_LANG['solusvmpro_enterPassword'];
        } elseif ( $r["status"] == "error" && $r["statusmsg"] == "Not supported for this virtualization type" ) {
            $message = $_LANG['solusvmpro_virtualizationTypeError'];
        } else {
            $message = $_LANG['solusvmpro_unknownError'];
        }
        $result = (object) array(
            'success' => true,
            'msg'     => $message,
        );
        exit( json_encode( $result ) );

    } else {
        $result = (object) array(
            'success' => false,
            'msg'     => $_LANG['solusvmpro_invalidRootpassword'],
        );
        exit( json_encode( $result ) );

    }

}

function solusvmpro_Custom_ChangeVNCPassword( $params = '' ) {
    global $_LANG;

    $newvncpassword      = $_GET['newvncpassword'];
    $checkNewVNCPassword = SolusVM::validateVNCPassword( $newvncpassword );
    if ( $checkNewVNCPassword ) {
        ## The call string for the connection function
        $callArray = array( "vserverid" => $_GET['vserverid'], "vncpassword" => $newvncpassword );

        $solusvm = new SolusVM( $params );

        if ( $solusvm->getExtData( "clientfunctions" ) == "disable" ) {
            $result = (object) array(
                'success' => false,
                'msg'     => $_LANG['solusvmpro_functionDisabled'],
            );
            exit( json_encode( $result ) );
        }
        if ( $solusvm->getExtData( "vncpassword" ) == "disable" ) {
            $result = (object) array(
                'success' => false,
                'msg'     => $_LANG['solusvmpro_functionDisabled'],
            );
            exit( json_encode( $result ) );
        }

        $solusvm->apiCall( 'vserver-vncpassword', $callArray );
        $r = $solusvm->result;

        $message = '';
        if ( $r["status"] == "success" ) {
            $solusvm->setCustomfieldsValue( 'vncpassword', $newvncpassword );
            $message = $_LANG['solusvmpro_passwordUpdated'];
        } elseif ( $r["status"] == "error" && $r["statusmsg"] == "VNC password not specified" ) {
            $message = $_LANG['solusvmpro_enterPassword'];
        } elseif ( $r["status"] == "error" && $r["statusmsg"] == "Not supported for this virtualization type" ) {
            $message = $_LANG['solusvmpro_virtualizationTypeError'];
        } else {
            $message = $_LANG['solusvmpro_unknownError'];
        }
        //$message = "<PRE>" . print_r($r, true) . $solusvm->debugTxt;
        $result = (object) array(
            'success' => true,
            'msg'     => $message,
        );
        exit( json_encode( $result ) );

    } else {
        $result = (object) array(
            'success' => false,
            'msg'     => $_LANG['solusvmpro_invalidVNCpassword'],
        );
        exit( json_encode( $result ) );

    }

}

function solusvmpro_ClientArea( $params ) {
    $notCustomFuntions = [ 'reboot', 'shutdown', 'boot' ];
    if ( isset( $_GET['modop'] ) && ( $_GET['modop'] == 'custom' ) ) {
        if ( isset( $_GET['a'] ) && !in_array( $_GET['a'], $notCustomFuntions ) ) {
            $functionName = 'solusvmpro_' . 'Custom_' . $_GET['a'];
            if ( function_exists( $functionName ) ) {
                $functionName( $params );
            } else {
                $result = (object) array(
                    'success' => false,
                    'msg'     => $functionName . ' not found',
                );
                exit( json_encode( $result ) );

            }
        }
    }
    try {
        $solusvm = new SolusVM( $params );

        $customField = $solusvm->getParam( "customfields" );

        if ( $solusvm->getExtData( "clientfunctions" ) != "disable" ) {

            $solusvm->clientAreaCommands();

            if ( function_exists( 'solusvmpro_customclientarea' ) ) {
                $callArray = array( "vserverid" => $customField["vserverid"], "nographs" => false );
                $solusvm->apiCall( 'vserver-infoall', $callArray );

                if ( $solusvm->result["status"] == "success" ) {
                    $data = $solusvm->clientAreaCalculations( $solusvm->result );

                    return solusvmpro_customclientarea( $params, $data );
                } else {
                    if ( function_exists( 'solusvmpro_customclientareaunavailable' ) ) {
                        $data                  = array();
                        $data["displaystatus"] = "Unavailable";

                        return solusvmpro_customclientareaunavailable( $params, $data );
                    }
                }
            } else {
                $data = array(
                    'vserverid' => $customField["vserverid"],
                );

                return array(
                    'templatefile' => 'templates/clientareaBootstrap.tpl',
                    'vars'         => array(
                        'data' => $data,
                    ),
                );
            }
        }

        return false;

    } catch ( Exception $e ) {
        // Record the error in WHMCS's module log.
        logModuleCall(
            'Client Area',
            __FUNCTION__,
            $params,
            $e->getMessage(),
            $e->getTraceAsString()
        );

        return $e->getMessage();
    }
}

/*
 * Usage Update
 * Info: https://developers.whmcs.com/provisioning-modules/usage-update/
 * Run Manually: /admin/reports.php?report=disk_usage_summary&action=updatestats
 */

function solusvmpro_UsageUpdate($params)
{
    $solusvm = new SolusVM($params);

    if (!isset($solusvm->configIni['enableUsageUpdate']) || !$solusvm->configIni['enableUsageUpdate']) {
        return false;
    }
    $ownerRowsHosting = Capsule::table('tblhosting')->where('domainstatus', 'Active')->where('server', $params['serverid']);

    if (isset($solusvm->configIni['updateIntervalDay'])) {
        $ownerRowsHosting->whereRaw('lastupdate < DATE_ADD(CURDATE(),INTERVAL -' . $solusvm->configIni['updateIntervalDay'] . ' DAY)');
    }
    $ownerRows = $ownerRowsHosting->get();

    if ($ownerRows) {
        foreach ($ownerRows as $ownerRow) {
            if (!$ownerRow->id) {
                continue;
            }

            $vserverFieldRow = Capsule::table('tblcustomfields')
                ->where('relid', $ownerRow->packageid)->where('fieldname', 'vserverid')->first();
            $vserverValueRow = Capsule::table('tblcustomfieldsvalues')
                ->where('fieldid', $vserverFieldRow->id)->where('relid', $ownerRow->id)->first();

            $callArray = ['vserverid' => $vserverValueRow->value, 'nographs' => true, 'rdtype' => 'json'];
            $res = $solusvm->apiCall('vserver-infoall', $callArray);
            $r = json_decode($res);

            $bandwidthData = explode(',', $r->bandwidth);
            $bwusage = round($bandwidthData[1] / 1024 ** 2, 0, PHP_ROUND_HALF_UP);
            $bwlimit = round($bandwidthData[0] / 1024 ** 2, 0, PHP_ROUND_HALF_UP);

            $hddData = explode(',', $r->hdd);
            $diskusage = round($hddData[1] / 1024 ** 2, 0, PHP_ROUND_HALF_UP);
            $disklimit = round($hddData[0] / 1024 ** 2, 0, PHP_ROUND_HALF_UP);

            Capsule::table('tblhosting')
                ->where('id', $ownerRow->id)
                ->update(
                    [
                        'bwusage' => $bwusage,
                        'bwlimit' => $bwlimit,
                        'diskusage' => $diskusage,
                        'disklimit' => $disklimit,
                        'lastupdate' => date('Y-m-d H:i:s')
                    ]
                );
        }
    }
}

/*
 * Rescue Mode
 * Info: https://docs.solusvm.com/display/DOCS/Rescue+Mode
 */

function solusvmpro_Custom_ChangeRescueMode( $params = '' ) {
    global $_LANG;

    $rescueAction      = $_GET['rescueAction'];
    $rescueValue       = $_GET['rescueValue'];

    if ( $rescueValue && $rescueAction) {
        // The call string for the connection function
        $callArray = array( 'vserverid' => $_GET['vserverid'], $rescueAction => $rescueValue );
        $solusvm = new SolusVM( $params );

        $solusvm->apiCall( 'vserver-rescue', $callArray );
        $r = $solusvm->result;

        if ( $r['status'] == 'success' ) {
            $message = $_LANG['solusvmpro_rescueenabled'];
            if($rescueAction == 'rescuedisable') {
                $message = $_LANG['solusvmpro_rescuedisabled'];
            }
        } elseif ( $r['status'] == 'error') {
            $message = $r['statusmsg'];
        } else {
            $message = $_LANG['solusvmpro_unknownError'];
        }
        $result = (object) array(
            'success' => true,
            'msg'     => $message,
        );
        exit(json_encode($result));
    }

    $result = (object)[
        'success' => false,
        'msg' => $_LANG['solusvmpro_unknownError'],
    ];
    exit(json_encode($result));
}

if ( ! function_exists( 'solusvmpro_customclientareaunavailable' ) ) {
    function solusvmpro_customclientareaunavailable( $params, $cparams ) {
        global $_LANG;
        $output = '
            <div class="row">
                <div class="col-md-3">
                    {$LANG.status}
                </div>
                <div class="col-md-9">
                    <span style="color: #000"><strong>\' . $cparams["displaystatus"] . \'</strong></span>
                </div>
            </div>
        ';

        return $output;
    }
}

