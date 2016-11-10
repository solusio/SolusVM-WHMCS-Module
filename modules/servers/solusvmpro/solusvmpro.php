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
            solusvmpro_create_one( $params );
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
    try {
        if ( function_exists( 'solusvmpro_suspend_pre' ) ) {
            solusvmpro_suspend_pre( $params );
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
    try {
        if ( function_exists( 'solusvmpro_unsuspend_pre' ) ) {
            solusvmpro_unsuspend_pre( $params );
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
    try {
        if ( function_exists( 'solusvmpro_terminate_pre' ) ) {
            solusvmpro_terminate_pre( $params );
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
    try {
        if ( function_exists( 'solusvmpro_changepackage_pre' ) ) {
            solusvmpro_changepackage_pre( $params );
        }

        $solusvm     = new SolusVM( $params );
        $customField = $solusvm->getParam( "customfields" );

        ## The call string for the connection fuction
        $callArray = array(
            "plan"      => $params["configoption4"],
            "type"      => $solusvm->getVT(),
            "vserverid" => $customField["vserverid"]
        );

        $solusvm->apiCall( 'vserver-change', $callArray );

        if ( $solusvm->result["status"] == "success" ) {
            $result = "success";
        } else {
            $result = (string) $solusvm->result["statusmsg"];
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
    if ( isset( $_GET['modop'] ) && ( $_GET['modop'] == 'custom' ) ) {
        if ( isset( $_GET['a'] ) ) {
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

