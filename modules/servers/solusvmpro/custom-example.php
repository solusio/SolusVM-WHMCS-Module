<?php
##############################################################################
###      Custom function file for SolusVM WHMCS module version 3           ###
##############################################################################
### Reaname this file to custom.php and uncomment the functions you require
### Report any bugs or feature requests for this module to the following link:
### http://developer.soluslabs.com/projects/billing-whmcs
### FULL Descriptions of these functions are located here:
### http://docs.solusvm.com/v2/Default.htm#Modules/Billing/WHMCS/Custom-Configuration.htm
##############################################################################

# function solusvmpro_hostname($params)
# {
#   ###########################################################################
#   ### This function allows you to randomly create a hostname when a virtual
#   ### server is ordered, if no hostname is specified.
#   ###########################################################################
#   ################################## CODE ###################################
#   $serviceid = $params['serviceid'];
#   $clientsdetails = $params['clientsdetails'];
#   if(!empty($params['domain'])) {
#     $currentHost = $params['domain'] {
#       strlen($params['domain']) - 1}
#     ;
#     if(!strcmp($currentHost, ".")) {
#       $newHost = substr($params['domain'], 0, -1);
#       mysql_real_escape_string($newHost);
#       mysql_query("UPDATE tblhosting SET `domain` = '$newHost' WHERE `id` = '$serviceid'");
#     } else {
#       $newHost = $params['domain'];
#     }
#   } else {
#     $newHost = "vps" . $serviceid . $clientsdetails['id'] . ".EXAMPLEDOMAIN.COM";
#   }
#   return $newHost;
# }

# function solusvmpro_username($params)
# {
#   ###########################################################################
#   ### This function allows you to create a random username for the solusvm
#   ### login. This is handy if you want seperate accounts for all virtual
#   ### servers.
#   ###########################################################################
#   ################################## CODE ###################################
#   $uniqueCod = md5(uniqid(mt_rand(), true));
#   $uniqueCod = substr($uniqueCod, 1, 5);
#   $uname = "USER" . $serviceid . $uniqueCod;
#   return $uname;
# }

# function solusvmpro_AdminLink($params)
# {
#   ###########################################################################
#   ### This function allows you to create a direct login link to your admincp
#   ### from the server list in whmcs
#   ###########################################################################
#   ################################## CODE ###################################
#   $code = '<form action="https://' . $params['serverip'] . ':5656/admincp/login.php" method="post" target="_blank">
#             <input type="hidden" name="username" value="ADMINUSERNAME" />
#             <input type="hidden" name="password" value="ADMINPASSOWRD" />
#             <input type="submit" name="Submit" value="Login" />
#             </form>
#			';
#   return $code;
# }

# function solusvmpro_create_one($params)
# {
# }

# function solusvmpro_create_two($params)
# {
# }

# function solusvmpro_create_three($params)
# {
# }

# function solusvmpro_create_four($params)
# {
# }

# function solusvmpro_create_five($params)
# {
# }

# function solusvmpro_terminate_pre($params)
# {
# }

# function solusvmpro_terminate_post_success($params)
# {
# }

# function solusvmpro_terminate_post_error($params)
# {
# }

# function solusvmpro_suspend_pre($params)
# {
# }

# function solusvmpro_suspend_post_success($params)
# {
# }

# function solusvmpro_suspend_post_error($params)
# {
# }

# function solusvmpro_unsuspend_pre($params)
# {
# }

# function solusvmpro_unsuspend_post_success($params)
# {
# }

# function solusvmpro_unsuspend_post_error($params)
# {
# }

# function solusvmpro_changepackage_pre($params)
# {
# }

# function solusvmpro_changepackage_post_success($params)
# {
# }

# function solusvmpro_changepackage_post_error($params)
# {
# }

