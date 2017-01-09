{if count($data)>0}
{literal}
    <style type="text/css">
        div.bar-container {
            border: 1px solid #ccc;
            width: 150px;
            margin: 2px 5px 2px 0;
            padding: 1px;
            float: left;
            background: white;
            position: relative;
        }

        div.bar-container div {
            background-color: #ff7021;
            height: 12px;
        }

        div.bar-container span {
            width: 140px;
            text-align: center;
            float: left;
            font: normal 9px Arial, sans-serif;
            margin-top: 0px;
        }
        
        td.vmbuttons input{
          margin: 2px;
        }
    
    </style>
    <script>
        jQuery(document).ready(function(){
          
          jQuery('#solusvm_reboot_button').click(function(){
            if (confirm('{/literal}{$LANG.rebootconfirm}{literal}')) {
              window.location='clientarea.php?action=productdetails&id={/literal}{$serviceid}{literal}&serveraction=custom&a=reboot';
            }
          });
          jQuery('#solusvm_shutdown_button').click(function(){
            if (confirm('{/literal}{$LANG.shutdownconfirm}{literal}')) {
              window.location='clientarea.php?action=productdetails&id={/literal}{$serviceid}{literal}&serveraction=custom&a=shutdown';
            }
          });
          
        });
    </script>
{/literal}
    <table width="100%" cellspacing="0" cellpadding="0" class="frame">
        <tr>
            <td>
                <table width="100%" border="0" cellpadding="10" cellspacing="0">
                    <tr>
                        <td colspan="2" class="vmbuttons">
                            {if $data['displayreboot'] }
                                <input type="button" id="solusvm_reboot_button" style="width: 135px" value="{$LANG.reboot}">
                            {/if}
                            {if $data['displayshutdown'] }
                                <input type="button" id="solusvm_shutdown_button" style="width: 135px" value="{$LANG.shutdown}">
                            {/if}
                            {if $data['displayboot'] }
                                <input type="button" style="width: 135px" value="{$LANG.boot}"
                                       onClick="window.location='clientarea.php?action=productdetails&id={$serviceid}&serveraction=custom&a=boot'">
                            {/if}
                            {if $data['displayconsole'] }
                                <input type="button" style="width: 135px" value="{$LANG.serialConsole}"
                                       onClick="window.open('modules/servers/solusvmpro/console.php?id={$serviceid}','_blank','width=670,height=500,status=no,location=no,toolbar=no,menubar=no')">
                            {/if}
                            {if $data['displayhtml5console'] }
                                <input type="button" style="width: 180px" value="{$LANG.html5Console}"
                                       onClick="window.open('modules/servers/solusvmpro/html5console.php?id={$serviceid}','_blank','width=880,height=600,status=no,resizable=yes,copyhistory=no,location=no,toolbar=no,menubar=no,scrollbars=1')">
                            {/if}
                            {if $data['displayvnc'] }
                                <input type="button" style="width: 135px" value="{$LANG.vnc}"
                                       onClick="window.open('modules/servers/solusvmpro/vnc.php?id={$serviceid}','_blank','width=400,height=200,status=no,location=no,toolbar=no,menubar=no')">
                            {/if}
                            {if $data['displayrootpassword'] }
                                <input type="button" style="width: 135px" value="{$LANG.rootPassword}"
                                       onClick="window.open('modules/servers/solusvmpro/rootpassword.php?id={$serviceid}','_blank','width=400,height=200,status=no,location=no,toolbar=no,menubar=no')">
                            {/if}
                            {if $data['displayhostname'] }
                                <input type="button" style="width: 135px" value="{$LANG.hostname}"
                                       onClick="window.open('modules/servers/solusvmpro/changehostname.php?id={$serviceid}','_blank','width=400,height=200,status=no,location=no,toolbar=no,menubar=no')">
                            {/if}
                            {if $data['displayvncpassword'] }
                                <input type="button" style="width: 135px" value="{$LANG.vncPassword}"
                                       onClick="window.open('modules/servers/solusvmpro/vncpassword.php?id={$serviceid}','_blank','width=400,height=200,status=no,location=no,toolbar=no,menubar=no')">
                            {/if}
                            {if $data['displaypanelbutton'] }
                                <input type="button" style="width: 135px" value="{$LANG.controlPanel}"
                                       onClick="window.open('{$data["controlpanellink"]}','_blank')">
                            {/if}
                            {if $data['displayclientkeyauth'] }
                                <form action="" name="solusvm" method="post"><input type="submit" class="btn-success"
                                                                                    style="font-weight: bold;width: 135px"
                                                                                    name="logintosolusvm" value="Manage"/>
                                </form>
                            {/if}
                        </td>
                    </tr>



                    {if $data['displaygraphs'] }
                        <tr>
                            <td width="150" class="fieldarea">{$LANG.graphs}:</td>
                            <td align="left"><input type="button" style="width: 135px" 
                                                    value="{$LANG.viewGraphs}"
                                                    onClick="window.open('modules/servers/solusvmpro/graphs.php?id={$serviceid}','_blank','width=800,height=800,status=no,location=no,toolbar=no,scrollbars=1,menubar=no')">
                            </td
                        </tr>
                    {/if}

                    {if $data['displaybandwidthbar'] }
                        <tr>
                            <td width="150" class="fieldarea">{$LANG.bandwidth}:</td>
                            <td align="left">
                                <div class="bar-container"><span id="bar_text">{$data["bandwidthpercent"]}%</span>
                                    <div id="bar_bar" style="width: {$data["bandwidthpercent"]}%;background-color: {$data["bandwidthcolor"]};"></div>
                                </div>{$data["bandwidthused"]} {$LANG.of} {$data["bandwidthtotal"]} {$LANG.used}
                                / {$data["bandwidthfree"]} {$LANG.free}
                            </td>
                        </tr>
                    {/if}
                    {if $data['displaymemorybar'] }
                        <tr>
                            <td width="150" class="fieldarea">{$LANG.memory}:</td>
                            <td align="left">
                                <div class="bar-container"><span id="bar_text">{$data["memorypercent"]}%</span>
                                    <div id="bar_bar" style="width: {$data["memorypercent"]}%;background-color: {$data["memorycolor"]};"></div>
                                </div>{$data["memoryused"]} {$LANG.of} {$data["memorytotal"]} {$LANG.used} / {$data["memoryfree"]} {$LANG.free}
                            </td>
                        </tr>
                    {/if}
                    {if $data['displayhddbar'] }
                        <tr>
                            <td width="150" class="fieldarea">Disk:</td>
                            <td align="left">
                                <div class="bar-container"><span id="bar_text">{$data["hddpercent"]}%</span>

                                    <div id="bar_bar"
                                         style="width: {$data["hddpercent"]}%; background-color: {$data["hddcolor"]};"></div>
                                </div>{$data["hddused"]} {$LANG.of} {$data["hddtotal"]} {$LANG.used} / {$data["hddfree"]} {$LANG.free}
                            </td>
                        </tr>
                    {/if}
                    {if $data['clientkeyautherror'] }
                        <tr>
                            <td align="left">
                                <div class="alert alert-block alert-error">
                                    <p>{$LANG.accessUnavailable}</p>
                                </div>
                            </td>
                        </tr>
                    {/if}

                    {if $data['displaystatus'] }
                        <tr>
                            <td width="150" class="fieldarea">{$LANG.status}:</td>
                            <td align="left">
                                {$data["displaystatus"]}
                            </td>
                        </tr>
                    {/if}

                    {if $data['displayips'] }
                        <tr>
                            <td width="150" class="fieldarea">{$LANG.ipAddress}:</td>
                            <td align="left">{$data["ipcsv"]}</td>
                        </tr>
                    {/if}
                </table>
            </td>
        </tr>
    </table>
{else}
    <span style="text-align: left">
        <h2>{$LANG.VSC}</h2></span>
    <table width="100%" cellspacing="0" cellpadding="0" class="frame">
        <tr>
            <td>
                <table width="100%" border="0" cellpadding="10" cellspacing="0">
                    <tr>
                        <td width="150" class="fieldarea">{$LANG.status}:</td>
                        <td><span style="color: #000"><strong>{$LANG.unavailable}</strong></span></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
{/if}