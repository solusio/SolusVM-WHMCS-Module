<?php

namespace SolusVM;

use SolusVM\Curl;
use Illuminate\Database\Capsule\Manager as Capsule;


class SolusVM {
    protected $url;
    protected $fwdurl;
    protected $modType;
    protected $username;
    protected $idkey;
    protected $extData;
    protected $masterID;
    protected $params = array();
    protected $configOption;
    protected $configOptionVirtualizationType;
    protected $configOptionDefaultNode;
    protected $configOptionOperatingSystem;
    protected $configOptionUsernamePrefix;
    protected $serviceid;
    protected $pid;
    public $configIni;
    public $result = '';
    public $rawResult = '';
    public $cpHostname;


    public function __construct( $params, $debug = false ) {

        if ( $debug === true ) {
            ini_set( 'display_errors', 1 );
            ini_set( 'display_startup_errors', 1 );
            error_reporting( E_ALL );
        }

        if ( is_array( $params ) ) {
            $this->params = $params;
        }

        $this->modType = strtolower( $this->getParam( "configoption1" ) );
        if ( ! $this->modType ) {
            $this->modType = "admin";
        }

        if ( $this->getParam( "serverid" ) == "0" || $this->getParam( "serverid" ) == "" ) {
            $masterPart     = explode( "-", $this->getParam( "configoption3" ) );
            $this->masterID = $masterPart[0];
        } else {
            $this->masterID = $this->getParam( "serverid" );
        }

        ## Grab the variables we need from the database to connect to the correct master
        $row = Capsule::table( 'tblservers' )->where( 'id', $this->masterID )->first();
        $this->cpHostname = $row->hostname;

        $this->idkey   = decrypt( $row->password );
        $this->extData = $this->serverExtra( array( 'master' => $this->masterID ) );

        if ( $this->getExtData( "connect-via-hostname" ) == "yes" ) {
            $conaddr = $row->hostname;
        } else {
            $conaddr = $row->ipaddress;
        }

        $cport = 0;
        if ( $this->getExtData( "port" ) ) {
            $cport = $this->getExtData( "port" );
        }
        if ( $row->secure ) {
            if ( ! $cport ) {
                $cport = "5656";
            }
            $this->url    = "https://" . $conaddr . ":" . $cport . "/api/" . $this->modType . "/command.php";
            $this->fwdurl = "https://" . $conaddr . ":" . $cport;
        } else {
            if ( ! $cport ) {
                $cport = "5353";
            }
            $this->url    = "http://" . $conaddr . ":" . $cport . "/api/" . $this->modType . "/command.php/";
            $this->fwdurl = "http://" . $conaddr . ":" . $cport;
        }

        if ( ! $this->getExtData( "connect-timeout" ) ) {
            $this->extData["connect-timeout"] = "100";
        }
        if ( ! $this->getExtData( "connect-data-timeout" ) ) {
            $this->extData["connect-data-timeout"] = "100";
        }

        $this->username = $row->username;

        $this->configOption                   = $this->getParam( "configoptions" );
        $this->configOptionVirtualizationType = $this->getParam( "configoption5" );
        $this->configOptionDefaultNode        = $this->getParam( "configoption2" );
        $this->configOptionOperatingSystem    = $this->getParam( "configoption6" );
        $this->configOptionUsernamePrefix     = $this->getParam( "configoption7" );

        $this->serviceid = $this->getParam( "serviceid" ); # Unique ID of the product/service in the WHMCS Database
        $this->pid       = $this->getParam( "pid" ); # Product/Service ID

        //Parse Ini file
        $config_file  = dirname(__DIR__) . '/configure.ini';
        $this->configIni = parse_ini_file( $config_file );

    }

    public function getParam( $name ) {
        if ( isset( $this->params[ $name ] ) ) {
            return $this->params[ $name ];
        }

        return "";
    }

    public function getExtData( $name ) {
        if ( isset( $this->extData[ $name ] ) ) {
            return $this->extData[ $name ];
        }

        return "";
    }

    public function apiCall( $faction, $postVars = array() ) {
        $this->result = '';

        if ( $faction == "fwdurl" ) {
            $result = $this->fwdurl;
            $this->debugLog( 'solusvmpro', $faction, '', $result, '', array() );

            return $result;
        }

        $error      = '';
        $postfields = array();

        foreach ( $postVars as $z => $x ) {
            $postfields[ $z ] = $x;
        }

        $postfields["id"]     = $this->username;
        $postfields["key"]    = $this->idkey;
        $postfields['action'] = $faction;

        $curl = new Curl();
        $curl->setTimeout( $this->getExtData( "connect-timeout" ) );
        $curl->setConnectTimeout( $this->getExtData( "connect-data-timeout" ) );
        $curl->setHeader( 'Expect', '' );
        if ( $this->getExtData( "htpasswd-username" ) && $this->getExtData( "htpasswd-password" ) ) {
            $curl->setBasicAuthentication( $this->getExtData( 'htpasswd-username' ), $this->getExtData( 'htpasswd-password' ) );
        }

        if ( $this->getExtData( "ssl_verifypeer" ) == "no" ) {
            $curl->setOpt(CURLOPT_SSL_VERIFYPEER, false);
        }

        if ( $this->getExtData( "ssl_verifyhost" ) == "no" ) {
            $curl->setOpt(CURLOPT_SSL_VERIFYHOST, false);
        }

        $curl->post( $this->url, $postfields );
        $result = $curl->rawResponse;

        ## This will give you the debug output if debug=on is defined in the servers hash field
        if ( $this->getExtData( "debug" ) == "on" ) {
            if ( $_SESSION['adminid'] ) {
                print_r( $postfields );
                print_r( array( "master" => $this->masterID, "usertype" => $this->modType ) );
                echo "<strong>httpStatusCode:</strong> " . $curl->httpStatusCode . "</br><br>";
                echo "<strong>effectiveUrl:</strong> " . $curl->effectiveUrl . "</br><br>";
            }
        }
        $curl->close();

        $this->debugLog( 'solusvmpro', $faction, $postfields, $result, $error, array( $postfields["id"], $postfields["key"] ) );

        $this->rawResult = $result;
        $this->result    = $this->sortReturn( $result );

        return $result;

    }

    public function sortReturn( $data ) {
        preg_match_all( '/<(.*?)>([^<]+)<\/\\1>/i', $data, $matches );
        $result = array();
        foreach ( $matches[1] as $k => $v ) {
            $result[ $v ] = $matches[2][ $k ];
        }
        $this->debugLog( 'solusvmpro', 'process', $data, $result, $result, '' );

        return $result;
    }

    public function serverExtra( $mServer = array() ) {
        if ( ! isset( $mServer["master"] ) ) {
            return array();
        }
        $results = array();

        $masterPart = explode( "-", $mServer["master"] );

        $row = Capsule::table( 'tblservers' )->where( 'id', $masterPart[0] )->first();

        ## check we have some vars
        if ( $row ) {
            $data    = htmlspecialchars_decode( $row->accesshash );
            $results = $this->sortReturn( $data );
        }

        if ( ! is_array( $results ) ) {
            return array();
        }

        return $results;
    }

    public function passwordGen( $length = 10, $chars = 'abcdefghijklmnopqrstuvwxyzABCEFGHIJKLMNOPQRSTUVWXYZ1234567890' ) {
        $chars_length = ( strlen( $chars ) - 1 );
        $string       = $chars{rand( 0, $chars_length )};
        for ( $i = 1; $i < $length; $i = strlen( $string ) ) {
            $r = $chars{rand( 0, $chars_length )};
            if ( $r != $string{$i - 1} ) {
                $string .= $r;
            }
        }

        return $string;
    }

    public function getUsername() {
        $clientData = $this->getParam( "clientsdetails" );

        if ( $this->getExtData( "username-prefix" ) ) {
            $clientUsername = $this->getExtData( "username-prefix" ) . $clientData["id"];
        } else {
            if ( $this->configOptionUsernamePrefix ) {
                $clientUsername = $this->configOptionUsernamePrefix . $clientData["id"];
            } else {
                $clientUsername = "vmuser" . $clientData["id"];
            }
        }

        return $clientUsername;
    }

    public function getBuildNode() {

        $buildNode = '';
        ## Lets check if we are using an overide flag for the node type and set the node we need
        if ( $this->configOptionVirtualizationType == "OpenVZ" ) {
            if ( $this->getExtData( "default-openvz-node" ) ) {
                $buildNode = $this->getExtData( "default-openvz-node" );
            } else {
                $buildNode = $this->configOptionDefaultNode;
            }
        } else if ( $this->configOptionVirtualizationType == "Xen-PV" ) {
            if ( $this->getExtData( "default-xen-node" ) ) {
                $buildNode = $this->getExtData( "default-xen-node" );
            } else {
                $buildNode = $this->configOptionDefaultNode;
            }
        } else if ( $this->configOptionVirtualizationType == "KVM" ) {
            if ( $this->getExtData( "default-kvm-node" ) ) {
                $buildNode = $this->getExtData( "default-kvm-node" );
            } else {
                $buildNode = $this->configOptionDefaultNode;
            }
        } else if ( $this->configOptionVirtualizationType == "Xen-HVM" ) {
            if ( $this->getExtData( "default-hvm-node" ) ) {
                $buildNode = $this->getExtData( "default-hvm-node" );
            } else {
                $buildNode = $this->configOptionDefaultNode;
            }
        }

        return $buildNode;
    }

    public function getBuildOperatingSystem() {

        $configType = '';

        ## Check to see if an OS selecter is being used via config options
        if ( $this->configOptionVirtualizationType == "OpenVZ" ) {
            if ( $this->getExtData( "openvz-os-selector-name" ) ) {
                $configType = $this->getExtData( "openvz-os-selector-name" );
            } else {
                $configType = "Operating System";
            }
        } else if ( $this->configOptionVirtualizationType == "Xen-PV" ) {
            if ( $this->getExtData( "xenpv-os-selector-name" ) ) {
                $configType = $this->getExtData( "xenpv-os-selector-name" );
            } else {
                $configType = "Operating System";
            }
        } else if ( $this->configOptionVirtualizationType == "Xen-HVM" ) {
            if ( $this->getExtData( "xenhvm-os-selector-name" ) ) {
                $configType = $this->getExtData( "xenhvm-os-selector-name" );
            } else {
                $configType = "Operating System";
            }
        } else if ( $this->configOptionVirtualizationType == "KVM" ) {
            if ( $this->getExtData( "kvm-os-selector-name" ) ) {
                $configType = $this->getExtData( "kvm-os-selector-name" );
            } else {
                $configType = "Operating System";
            }
        }

        if ( ! isset( $this->configOption[ $configType ] ) ) {
            $buildOperatingSystem = $this->configOptionOperatingSystem;
        } else {
            $ossplit              = explode( "|", $this->configOption[ $configType ] );
            $buildOperatingSystem = $ossplit[0];
        }

        return $buildOperatingSystem;
    }

    public function getCPGroup() {

        ## Control Panel Chooser
        if ( $this->getExtData( "control-panel-selector-name" ) ) {
            $configTypeCP = $this->getExtData( "control-panel-selector-name" );
        } else {
            $configTypeCP = "Control Panel";
        }

        if ( isset( $this->configOption[ $configTypeCP ] ) ) {
            $cpsplit = explode( "|", $this->configOption[ $configTypeCP ] );
            $cpGroup = $cpsplit[0];
        } else {
            $cpGroup = "";
        }

        return $cpGroup;
    }

    public function getBuildGroup() {

        ################################################
        ## Group selector

        $configTypeG = '';

        if ( $this->configOptionVirtualizationType == "OpenVZ" ) {
            if ( $this->getExtData( "openvz-group-selector-name" ) ) {
                $configTypeG = $this->getExtData( "openvz-group-selector-name" );
            } else {
                $configTypeG = "Location";
            }
        } else if ( $this->configOptionVirtualizationType == "Xen-PV" ) {
            if ( $this->getExtData( "xenpv-group-selector-name" ) ) {
                $configTypeG = $this->getExtData( "xenpv-group-selector-name" );
            } else {
                $configTypeG = "Location";
            }
        } else if ( $this->configOptionVirtualizationType == "Xen-HVM" ) {
            if ( $this->getExtData( "xenhvm-group-selector-name" ) ) {
                $configTypeG = $this->getExtData( "xenhvm-group-selector-name" );
            } else {
                $configTypeG = "Location";
            }
        } else if ( $this->configOptionVirtualizationType == "KVM" ) {
            if ( $this->getExtData( "kvm-group-selector-name" ) ) {
                $configTypeG = $this->getExtData( "kvm-group-selector-name" );
            } else {
                $configTypeG = "Location";
            }
        }

        if ( ! isset( $this->configOption[ $configTypeG ] ) ) {
            $splitGroup            = explode( "|", $this->getParam( "configoption9" ) );
            $configOptionNodeGroup = $splitGroup[0];

            $buildGroup = $configOptionNodeGroup;

        } else {
            $groupsplit = explode( "|", $this->configOption[ $configTypeG ] );
            $buildGroup = $groupsplit[0];

        }

        return $buildGroup;

    }

    public function getCmem() {
        if ( $this->getExtData( "custom-config-memory" ) ) {
            $custom_config_memory = $this->getExtData( "custom-config-memory" );
        } else {
            $custom_config_memory = "Memory";
        }

        if ( ! isset( $this->configOption[ $custom_config_memory ] ) ) {
            $cmem = "";
        } else {
            $memsplit = explode( "|", $this->configOption[ $custom_config_memory ] );
            $cmem     = $memsplit[0];
        }

        return $cmem;
    }

    public function getCdisk() {

        if ( $this->getExtData( "custom-config-diskspace" ) ) {
            $custom_config_diskspace = $this->getExtData( "custom-config-diskspace" );
        } else {
            $custom_config_diskspace = "Disk Space";
        }

        if ( ! isset( $this->configOption[ $custom_config_diskspace ] ) ) {
            $cdisk = "";
        } else {
            $disksplit = explode( "|", $this->configOption[ $custom_config_diskspace ] );
            $cdisk     = $disksplit[0];
        }

        return $cdisk;
    }

    public function getCbandwidth() {
        if ( $this->getExtData( "custom-config-bandwidth" ) ) {
            $custom_config_bandwidth = $this->getExtData( "custom-config-bandwidth" );
        } else {
            $custom_config_bandwidth = "Bandwidth";
        }

        if ( ! isset( $this->configOption[ $custom_config_bandwidth ] ) ) {
            $cbandwidth = "";
        } else {
            $bandwidthsplit = explode( "|", $this->configOption[ $custom_config_bandwidth ] );
            $cbandwidth     = $bandwidthsplit[0];
        }

        return $cbandwidth;
    }

    public function getCcpu() {
        if ( $this->getExtData( "custom-config-cpu" ) ) {
            $custom_config_cpu = $this->getExtData( "custom-config-cpu" );
        } else {
            $custom_config_cpu = "CPU";
        }

        if ( ! isset( $this->configOption[ $custom_config_cpu ] ) ) {
            $ccpu = "";
        } else {
            $cpusplit = explode( "|", $this->configOption[ $custom_config_cpu ] );
            $ccpu     = $cpusplit[0];
        }

        return $ccpu;
    }

    public function getCextraip() {
        if ( $this->getExtData( "custom-config-extraip" ) ) {
            $custom_config_extraip = $this->getExtData( "custom-config-extraip" );
        } else {
            $custom_config_extraip = "Extra IP Address";
        }

        if ( ! isset( $this->configOption[ $custom_config_extraip ] ) ) {
            $cextraip = "";
        } else {
            $extraipsplit = explode( "|", $this->configOption[ $custom_config_extraip ] );
            $cextraip     = $extraipsplit[0];
        }

        return $cextraip;
    }

    public function getCnspeed() {
        if ( $this->getExtData( "custom-config-nspeed" ) ) {
            $custom_config_nspeed = $this->getExtData( "custom-config-nspeed" );
        } else {
            $custom_config_nspeed = "Network Speed";
        }

        if ( ! isset( $this->configOption[ $custom_config_nspeed ] ) ) {
            $cextraspeed = "";
        } else {
            $extraspeedsplit = explode( "|", $this->configOption[ $custom_config_nspeed ] );
            $cextraspeed     = $extraspeedsplit[0];
        }

        return $cextraspeed;
    }

    public function getHostname() {
        ## Sort the hostname
        $domain = $this->getParam( 'domain' );
        if ( ! empty( $domain ) ) {
            $currentHostname         = $this->getParam( 'domain' );
            $currentHostnameLastChar = substr( $currentHostname, - 1 );
            if ( ! strcmp( $currentHostnameLastChar, "." ) ) {
                $newHostname = substr( $currentHostname, 0, - 1 );
                $this->setHostname( $newHostname );
            } else {
                $newHostname = $this->getParam( 'domain' );
            }
        } else {
            if ( $this->getExtData( "default-hostname" ) ) {
                $newHostname = $this->getExtData( "default-hostname" );
            } else {
                $newHostname = "vps.server.com";
            }
        }

        if ( $newHostname == "" ) {
            $newHostname = "vps.server.com";
        }

        return $newHostname;

    }

    public function getNewDataPassword() {
        if ( $this->getParam( 'password' ) == 'auto-generated' || $this->getParam( 'password' ) == "" ) {
            $this->params['password'] = $this->passwordGen();
            $passwordenc              = encrypt( $this->params['password'] );
            Capsule::table( 'tblhosting' )
                   ->where( 'id', $this->serviceid )
                   ->update(
                       [
                           'password' => $passwordenc,
                       ]
                   );
        }
        $newData         = Capsule::table( 'tblhosting' )->where( 'id', $this->serviceid )->first();
        $newDataPassword = decrypt( $newData->password );

        return $newDataPassword;
    }


    public function removeipTerminatedProduct() {
        if ( $this->getExtData( "removeip-terminated-product" ) != "no" ) {
            ## remove the ip's
            Capsule::table( 'tblhosting' )
                   ->where( 'id', $this->serviceid )
                   ->update(
                       [
                           'dedicatedip' => '',
                           'assignedips' => '',

                       ]
                   );
        }

    }

    public function removevserveridTerminatedProduct() {
        if ( $this->getExtData( "removevserverid-terminated-product" ) != "no" ) {
            $this->setCustomfieldsValue( 'vserverid', "" );
        }
    }

    public function setCustomfieldsValue( $field, $value ) {
        $value = (string) $value;

        $res = Capsule::table( 'tblcustomfields' )->where( 'relid', $this->pid )->where( 'fieldname', $field )->first();
        if ( $res ) {
            $fieldValue = Capsule::table( 'tblcustomfieldsvalues' )->where( 'relid', $this->serviceid )->where( 'fieldid', $res->id )->first();
            if ( $fieldValue ) {
                if ( $fieldValue->value !== $value ) {
                    Capsule::table( 'tblcustomfieldsvalues' )
                           ->where( 'relid', $this->serviceid )
                           ->where( 'fieldid', $res->id )
                           ->update(
                               [
                                   'value' => $value,
                               ]
                           );
                }
            } else {
                Capsule::table( 'tblcustomfieldsvalues' )
                       ->insert(
                           [
                               'relid'   => $this->serviceid,
                               'fieldid' => $res->id,
                               'value'   => $value,
                           ]
                       );
            }
        }
    }

    public function clientAreaCalculations( $result ) {
        global $_LANG;

            $cparams = array(
                "displaymemorybar"     => 0,
                "displayhddbar"        => 0,
                "displaybandwidthbar"  => 0,
                "displaygraphs"        => 0,
                "displayips"           => 0,
                "displayhtml5console"  => 0,
                "displayconsole"       => 0,
                "displayvnc"           => 0,
                "displayvncpassword"   => 0,
                "displaypanelbutton"   => 0,
                "displayrootpassword"  => 0,
                "displayhostname"      => 0,
                "displayreboot"        => 0,
                "displayshutdown"      => 0,
                "displayboot"          => 0,
                "displayclientkeyauth" => 0,
                "clientkeyautherror"   => 0,
                "displaytrafficgraph"  => 0,
                "displayloadgraph"     => 0,
                "displaymemorygraph"   => 0,
                "displayrescuemode"    => 0,
            );

            $vstatusAr = array(
                'online'  => array(
                    'msg'   => $_LANG['solusvmpro_online'],
                    'color' => ( isset($this->configIni['statusOnlineColor'] ) ? $this->configIni['statusOnlineColor'] : '0C0')
                ),
                'offline' => array(
                    'msg'   => $_LANG['solusvmpro_offline'],
                    'color' => ( isset($this->configIni['statusOfflineColor'] ) ? $this->configIni['statusOfflineColor'] : 'F00')
                ),
                'disabled' => array(
                    'msg'   => $_LANG['solusvmpro_suspended'],
                    'color' => ( isset($this->configIni['statusDisabledColor'] ) ? $this->configIni['statusDisabledColor'] : '000')
                ),
                'unavailable' => array(
                    'msg'   => $_LANG['solusvmpro_unavailable'],
                    'color' => ( isset($this->configIni['statusUnavailableColor'] ) ? $this->configIni['statusUnavailableColor'] : '000')
                )
            );

            if ( $result["status"] == "success" ) {
                $vstatus = '<span style="color: #'.$vstatusAr[$result["state"]]['color'].'"><strong>' . $vstatusAr[$result["state"]]['msg'] . '</strong></span>';
            } else {
                $vstatus = '<span style="color: #'.$vstatusAr['unavailable']['color'].'"><strong>' . $vstatusAr['unavailable']['msg'] . '</strong></span>';
            }

            $cparams["displaystatus"] = $vstatus;
            $cparams["node"] = $result["node"];

            if ($result["state"] == "online" || $result["state"] == "offline") {
                //Bandwidth graph
                $bandwidthData    = explode(",", $result["bandwidth"]);
                $usedBwPercentage = $bandwidthData[3];
                $bandwidthData[1] = $bandwidthData[1] / 1024;
                $bandwidthData[2] = $bandwidthData[2] / 1024;
                $bandwidthData[0] = $bandwidthData[0] / 1024;
                $bwUsed           = $this->bwFormat($bandwidthData[1]);
                $bwFree           = $this->bwFormat($bandwidthData[2]);
                $bwTotal          = $this->bwFormat($bandwidthData[0]);

                $bwGraphColor = "#";
                if ($usedBwPercentage < 75) {
                    $bwGraphColor .= (isset($this->configIni['pbSuccessColor']) ? $this->configIni['pbSuccessColor'] : '36e22d');
                } else if ($usedBwPercentage > 90) {
                    $bwGraphColor .= (isset($this->configIni['pbDangerColor']) ? $this->configIni['pbDangerColor'] : 'f82812');
                } else {
                    $bwGraphColor .= (isset($this->configIni['pbWarningColor']) ? $this->configIni['pbWarningColor'] : 'f8aa12');
                }

                if ($this->getExtData("bwusage") != "disable") {

                    $cparams["displaybandwidthbar"] = 1;
                    $cparams["bandwidthpercent"]    = $usedBwPercentage;
                    $cparams["bandwidthcolor"]      = $bwGraphColor;
                    $cparams["bandwidthused"]       = $bwUsed;
                    $cparams["bandwidthtotal"]      = $bwTotal;
                    $cparams["bandwidthfree"]       = $bwFree;
                }

                //Memory graph
                if ($result["type"] == "openvz") {
                    if ($this->getExtData("memusage") != "disable") {
                        $memData           = explode(",", $result["memory"]);
                        $usedMemPercentage = $memData[3];
                        $memData[1]        = $memData[1] / 1024;
                        $memData[2]        = $memData[2] / 1024;
                        $memData[0]        = $memData[0] / 1024;
                        $memUsed           = $this->bwFormat($memData[1]);
                        $memFree           = $this->bwFormat($memData[2]);
                        $memTotal          = $this->bwFormat($memData[0]);

                        $memGraphColor = "#";
                        if ($usedMemPercentage < 75) {
                            $memGraphColor .= (isset($this->configIni['pbSuccessColor']) ? $this->configIni['pbSuccessColor'] : '36e22d');
                        } else if ($usedMemPercentage > 90) {
                            $memGraphColor .= (isset($this->configIni['pbDangerColor']) ? $this->configIni['pbDangerColor'] : 'f82812');
                        } else {
                            $memGraphColor .= (isset($this->configIni['pbWarningColor']) ? $this->configIni['pbWarningColor'] : 'f8aa12');
                        }

                        $cparams["displaymemorybar"] = 1;
                        $cparams["memorypercent"]    = $usedMemPercentage;
                        $cparams["memorycolor"]      = $memGraphColor;
                        $cparams["memoryused"]       = $memUsed;
                        $cparams["memorytotal"]      = $memTotal;
                        $cparams["memoryfree"]       = $memFree;
                    }
                }

                //HDD graph
                if ($result["type"] == "openvz" || $result["type"] == "xen") {
                    if ($this->getExtData("diskusage") != "disable") {
                        $hddData           = explode(",", $result["hdd"]);
                        $usedHddPercentage = $hddData[3];
                        $hddData[1]        = $hddData[1] / 1024;
                        $hddData[2]        = $hddData[2] / 1024;
                        $hddData[0]        = $hddData[0] / 1024;
                        $hddUsed           = $this->bwFormat($hddData[1]);
                        $hddFree           = $this->bwFormat($hddData[2]);
                        $hddTotal          = $this->bwFormat($hddData[0]);

                        $hddGraphColor = "#";
                        if ($usedHddPercentage < 75) {
                            $hddGraphColor .= (isset($this->configIni['pbSuccessColor']) ? $this->configIni['pbSuccessColor'] : '36e22d');
                        } else if ($usedHddPercentage > 90) {
                            $hddGraphColor .= (isset($this->configIni['pbDangerColor']) ? $this->configIni['pbDangerColor'] : 'f82812');
                        } else {
                            $hddGraphColor .= (isset($this->configIni['pbWarningColor']) ? $this->configIni['pbWarningColor'] : 'f8aa12');
                        }

                        $cparams["displayhddbar"] = 1;
                        $cparams["hddpercent"]    = $usedHddPercentage;
                        $cparams["hddcolor"]      = $hddGraphColor;
                        $cparams["hddused"]       = $hddUsed;
                        $cparams["hddtotal"]      = $hddTotal;
                        $cparams["hddfree"]       = $hddFree;
                    }
                }
            }

            if ( $this->getExtData( "graphs" ) != "disable" ) {
                $cparams["displaygraphs"] = 1;
            }
            if ( $this->getExtData( "iplist" ) != "disable" ) {
                $cparams["displayips"] = 1;
                $cparams["ipcsv"]      = $result["ipaddresses"];
            }
            $cparams["mainip"] = $result["mainipaddress"];

            if ( $result["type"] == "openvz" || $result["type"] == "xen" ) {
                if ( $this->getExtData( "serialconsole" ) != "disable" ) {
                    $cparams["displayconsole"] = 1;
                }
                if ( $this->getExtData( "rootpassword" ) != "disable" ) {
                    $cparams["displayrootpassword"] = 1;
                }
                if ( $this->getExtData( "hostname" ) != "disable" ) {
                    $cparams["displayhostname"] = 1;
                }
                if ( $this->getExtData( "html5serialconsole" ) != "disable" ) {
                    $cparams["displayhtml5console"] = 1;
                }
            } else {
                if ( $this->getExtData( "vnc" ) != "disable" ) {
                    $cparams["displayvnc"] = 1;
                }
                if ( $this->getExtData( "vncpassword" ) != "disable" ) {
                    $cparams["displayvncpassword"] = 1;
                }
            }

            if ( $result['type'] == 'kvm' ) {
                $cparams['displayrescuemode'] = 1;
                $cparams['rescuemode'] = $result['rescuemode'];
            }

            if ( $this->getExtData( "controlpanelbutton" ) != "" ) {
                $cparams["displaypanelbutton"] = 1;
                $cparams["controlpanellink"]   = $this->getExtData( "controlpanelbutton" );
            }


            if ( $this->getExtData( "reboot" ) != "disable" ) {
                $cparams["displayreboot"] = 1;
            }

            if ( $this->getExtData( "shutdown" ) != "disable" ) {
                $cparams["displayshutdown"] = 1;
            }

            if ( $this->getExtData( "boot" ) != "disable" ) {
                $cparams["displayboot"] = 1;
            }

            if ( $this->getExtData( "clientkeyauth" ) != "" ) {
                $cparams["displayclientkeyauth"] = 1;
            }

            if ( $this->getExtData( "clientkeyauthreturnurl" ) != "" ) {
                $cparams["clientkeyauthreturnurl"] = $this->getExtData( "clientkeyauthreturnurl" );
            }



        if ( $this->getExtData( "graphs" ) != "disable" ) {
            $url = $this->apiCall( "fwdurl" );

            if ( isset( $result["trafficgraph"] ) ) {
                $cparams["displaytrafficgraph"] = 1;
                $cparams["trafficgraphurl"]     = $url . $result['trafficgraph'];

                if ( $result["type"] == "kvm" || $result["type"] == "xen"){
                    $cparams["displayhddgraph"] = 1;
                    $result['hddgraph'] = str_replace("bandwidth", "io", $result['trafficgraph']);
                    $cparams["hddgraphurl"]     = $url . $result['hddgraph'];
                }
            }
            if ( isset( $result["loadgraph"] ) ) {
                $cparams["displayloadgraph"] = 1;
                $cparams["loadgraphurl"]     = $url . $result['loadgraph'];
            }
            if ( isset( $result["memorygraph"] ) ) {
                $cparams["displaymemorygraph"] = 1;
                $cparams["memorygraphurl"]     = $url . $result['memorygraph'];
            }
            $cparams["displaygraphs"] = 1;
        }

        return $cparams;
    }

    public function clientAreaCommands() {

        if ( $_POST['logintosolusvm'] ) {

            if ( $this->getExtData( "clientkeyauthreturnurl" ) != "" ) {
                $sysurl                            = $this->getExtData( "clientkeyauthreturnurl" );
                $cparams["clientkeyauthreturnurl"] = $this->getExtData( "clientkeyauthreturnurl" );
            } else {
                $query  = Capsule::table( 'tblconfiguration' )->where( 'setting', 'SystemURL' )->first();
                $sysurl = $query->value;
            }

            $vserverID = '';
            if ( isset( $this->getParam( "customfields" )["vserverid"] ) && $this->getParam( "customfields" )["vserverid"] !== '' ) {
                $vserverID = $this->getParam( "customfields" )["vserverid"];
            } else {
                $vserverID = $this->params['vserver'];
            }

            $callArray = array(
                "vserverid" => $vserverID,
                "forward"   => "1",
                "returnurl" => $sysurl . "clientarea.php?action=productdetails&id=" . $this->getParam( 'serviceid' ),
            );

            $this->apiCall( 'client-key-login', $callArray );
            $slogin = $this->result;

            if ( $slogin["status"] == "success" ) {

                $callArray = array( "vserverid" => $this->getParam( "vserverid" ) );
                ## Do the connection
                $master_url = $this->apiCall( 'fwdurl', $callArray );

                header( "Location: " . $master_url . "/auth.php?_a=" . $slogin["hasha"] . "&_b=" . $slogin["hashb"] );
                exit();
            } else {
                $cparams["clientkeyautherror"] = 1;
            }
        }
    }


    public static function dns_verify_rdns_section( $host = "" ) {
        $is_valid = false;
        if ( strlen( $host ) <= 256 && strlen( $host ) >= 4 ) {
            if ( $host[0] != "-" ) {
                if ( substr( $host, - 1 ) != "-" ) {
                    $split_host = explode( ".", $host );
                    $secs       = count( $split_host );
                    if ( $secs >= 1 ) {
                        $q = 0;
                        foreach ( $split_host as $section ) {
                            $rmvchar = array( '-', ' ' );
                            if ( preg_match( '/^[a-zA-Z0-9\-]{0,30}$/i', $section ) ) {
                                if ( ctype_alnum( str_replace( $rmvchar, '', $section ) ) ) {
                                    $q ++;
                                }
                            }
                        }
                        if ( $q == $secs ) {
                            $is_valid = true;
                        }
                    }
                }
            }
        }

        return $is_valid;
    }

    public static function validateRootPassword( $newRootPassword ) {
        $is_valid = false;
        if ( strlen( $newRootPassword ) > 5 ) {
            if ( preg_match( '/^[a-zA-Z0-9\-_]{0,50}$/i', $newRootPassword ) ) {
                $is_valid = true;
            }
        }

        return $is_valid;
    }

    public static function validateVNCPassword( $newVNCPassword ) {
        $is_valid = false;
        if ( strlen( $newVNCPassword ) > 5 ) {
            if ( preg_match( '/^[a-zA-Z0-9\-_]{0,50}$/i', $newVNCPassword ) ) {
                $is_valid = true;
            }
        }

        return $is_valid;
    }

    public static function getParamsFromServiceID( $servid, $uid = null ) {

        $ownerRow = Capsule::table( 'tblhosting' )->where( 'id', $servid )->where( 'userid', $uid )->first();
        if ( ! $ownerRow ) {
            return false;
        }

        if ( ! is_null( $uid ) && $uid != $ownerRow->userid ) {
            return false;
        }

        $vserverFieldRow = Capsule::table( 'tblcustomfields' )->where( 'relid', $ownerRow->packageid )->where( 'fieldname', 'vserverid' )->first();
        if ( ! $vserverFieldRow ) {
            return false;
        }

        $vserverValueRow = Capsule::table( 'tblcustomfieldsvalues' )->where( 'fieldid', $vserverFieldRow->id )->where( 'relid', $ownerRow->id )->first();

        if ( ! $vserverValueRow ) {
            return false;
        }

        if ( ! is_numeric( $vserverValueRow->value ) ) {
            return false;
        }

        $serverid = $ownerRow->server;
        if ( $serverid == "0" || $serverid == "" ) {
            $productRow = Capsule::table( 'tblproducts' )->where( 'id', $ownerRow->packageid )->first();
            $masterPart = explode( "-", $productRow->configoption3 );
            $serverid   = $masterPart[0];
        }

        return array(
            'serverid'  => $serverid,
            'vserver'   => $vserverValueRow->value,
            'serviceid' => $vserverValueRow->relid,
            'pid'       => $vserverFieldRow->relid,
        );

    }

    public static function getParamsFromVserviceID( $vserverid, $uid ) {
        /** @var stdClass $hosting */
        foreach ( Capsule::table( 'tblhosting' )->where( 'userid', $uid )->get() as $hosting ) {

            $vserverFieldRow = Capsule::table( 'tblcustomfields' )->where( 'relid', $hosting->packageid )->where( 'fieldname', 'vserverid' )->first();
            if ( ! $vserverFieldRow ) {
                continue;
            }

            $vserverValueRow = Capsule::table( 'tblcustomfieldsvalues' )->where( 'fieldid', $vserverFieldRow->id )->where( 'relid', $hosting->id )->first();

            if ( ! $vserverValueRow ) {
                continue;
            }

            if ( $vserverid == $vserverValueRow->value ) {
                $serverid = $hosting->server;

                return array(
                    'serverid'  => $serverid,
                    'vserver'   => $vserverid,
                    'serviceid' => $vserverValueRow->relid,
                    'pid'       => $vserverFieldRow->relid,
                );
            }

        }

        return false;
    }

    public function bwFormat( $size ) {
        $bytes  = array( ' KB', ' MB', ' GB', ' TB' );
        $resVal = '';
        foreach ( $bytes as $val ) {
            $resVal = $val;
            if ( $size >= 1024 ) {
                $size = $size / 1024;
            } else {
                break;
            }
        }

        return round( $size, 1 ) . $resVal;
    }

    public function setHostname( $newhostname ) {
        if ( ! empty( $this->serviceid ) ) {
            Capsule::table( 'tblhosting' )
                   ->where( 'id', $this->serviceid )
                   ->update(
                       [
                           'domain' => $newhostname,
                       ]
                   );
        }
    }

    public function getVT() {
        $vt = '';
        if ( $this->configOptionVirtualizationType == "OpenVZ" ) {
            $vt = "openvz";
        } elseif ( $this->configOptionVirtualizationType == "Xen-PV" ) {
            $vt = "xen";
        } elseif ( $this->configOptionVirtualizationType == "Xen-HVM" ) {
            $vt = "xen hvm";
        } elseif ( $this->configOptionVirtualizationType == "KVM" ) {
            $vt = "kvm";
        }

        return $vt;
    }

    public static function loadLang( $lang = null ) {
        global $_LANG, $CONFIG;

        $langDir                = __DIR__ . '/../lang/';
        $availableLangsFullPath = glob( $langDir . '*.php' );
        $availableLangs         = array();
        foreach ( $availableLangsFullPath as $availableLang ) {
            $availableLangs[] = strtolower( basename( $availableLang ) );
        }

        if ( empty( $lang ) ) {
            if ( isset( $_SESSION['Language'] ) ) {
                $language = $_SESSION['Language'];
            } else if ( isset( $_SESSION['adminlang'] ) ) {
                $language = $_SESSION['adminlang'];
            } else {
                $language = $CONFIG['Language'];
            }
        } else {
            $language = $lang;
        }

        $language = strtolower( $language ) . '.php';

        if ( ! in_array( $language, $availableLangs ) ) {
            $language = 'english.php';
        }
        require_once( $langDir . $language );
    }

    public function debugLog( $module, $action, $requestString, $responseData, $processedData, $replaceVars ) {
        if ( !$this->configIni[ 'debug' ] ){
            return;
        }
        logModuleCall( $module, $action, $requestString, $responseData, $processedData, $replaceVars );
    }
}

