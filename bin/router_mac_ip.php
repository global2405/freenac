#!/usr/bin/php -f
<?php
/**
 * /opt/nac/bin/router_mac
 *
 * Long description for file:
 * Get MAC / IP table of active hosts from core routers
 * - update the IP for known MACs, witha time stamp
 * - lookup the name for MACs called "unknown"
 * - insert all new unknown MACs, with IP, DNS name, and make as status "unmanaged"
 *
 * On IOS do "show ip arp"
 *        or "sh ip arp vrf insec"
 * Further reading: 
 *    http://www.cisco.com/public/sw-center/netmgmt/cmtk/mibs.shtml
 *    The "getif" tool for exploring MIBs.
 *
 * PHP version 5
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published
 * by the Free Software Foundation.
 *
 * @package			FreeNAC
 * @author			Sean Boran (FreeNAC Core Team)
 * @copyright		2006 FreeNAC
 * @license			http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 2
 * @version			SVN: $Id$
 * @link				http://www.freenac.net
 *
 */


# Debugging
$debug_to_syslog=true;
$mysql_write1=true;                    # Just test or actually write DB changes??
$mysql_write2=true;                    # Just test or actually write DB changes??

require_once "funcs.inc.php";               # Load settings & common functions

$logger->setDebugLevel(0);
#$logger->setLogToStdErr();

// allow performance measurements
   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $starttime = $mtime;

db_connect();
global $connect;


if ( !$conf->core_routers ) {   // no results, error?
   logit("no routers specified in core_routers variable in config.inc");
   log2db('info',"no routers specified in core_routers variable in config.inc");
   exit -1;
}


// Get the mac addresses of all unknown devices
// for use the autoupdating of DNS names, see below
if ( $conf->router_mac_ip_update_from_dns ) {   // feature enabled?

  #$sql="SELECT mac FROM systems WHERE name='unknown'";
  $sql="SELECT mac FROM systems WHERE name LIKE '%unknown%'";
  $result=mysql_query($sql,$connect);
  if (!$result) { die('Invalid query: '.mysql_error()); }
  $i=0;
  $uk_mac=array();
  while($row=mysql_fetch_row($result)){
        $uk_mac[$i]=$row[0];
        $i++;
  }
  debug1("router_mac_ip_update_from_dns: $i unknowns noted\n");
}


foreach (split(' ', $conf->core_routers) as $router) {
 $count_updates=0;
 // query interface list and split into an array
 debug2($router_snmpwalk." $router ipNetToMediaPhysAddress\n");
 $results=explode("\n", syscall($router_snmpwalk." $router ipNetToMediaPhysAddress"));


 if (( count($results) === 0 ) || (!strpos($results[0],'ipNetToMediaPhysAddress'))){   // no results, error?
   logit("No results retrieved from router $router: SNMP errors?");
   debug2($results[$i]);
   #log2db('info',"No results retrieved from router $router: SNMP errors?");
 }

 // go though each pair and update the SYSTEMS table
 for ($i = 0; $i < count($results); $i++){
  debug2("Pre-match results: " .$results[$i]);

  if ( preg_match("/ipNetToMediaPhysAddress\.(\d+)\.(.*) = STRING: (.*)/", $results[$i], $matches) ) {
     $ip=$matches[2];
     #debug2("$ip - $matches[3] ");
     $mac=normalise_mac($matches[3]);
     debug2("$ip - $mac ");

     if ( preg_match($conf->router_mac_ip_ignore_ip, $ip) ) {
       debug2("Ignore Non relevant Networks: $ip - $mac ");
       continue;
     }
     if ( preg_match($conf->router_mac_ip_ignore_mac, $mac) ) {
       debug2("Ignore Non relevant macs: $mac ");
       continue;
     }

     $query1="UPDATE systems SET r_timestamp=NOW(), r_ip='$ip' ";
     $query2='';
     $where= " WHERE mac='$mac'";

          // if this mac has no associated name i.e. 'unknown', try to get the fqdn for it
          if ( $conf->router_mac_ip_update_from_dns ) {   // feature enabled?
            if (in_array($mac,$uk_mac)) {
              $fqdn=gethostbyaddr($ip);
              debug1("got $fqdn $ip $mac");
              if($fqdn!=$ip) { // We got the host name, now update it
                // strip domain name
                list($hostname_only) = split('[.]', $fqdn);
                $hostname_only = strtolower($hostname_only);
                $query2=", name='$hostname_only' ";
                logit("Change name of $mac to its DNS name $hostname_only");
                #if (!mysql_query($sql,$connect)) { die('Invalid query: '.mysql_error()); }
              }
            }
          }

        $query=$query1 . $query2 . $where;
        $rowcount=0;
        if ($mysql_write1) {
          $res = mysql_query($query, $connect);
          if (!$res) { die('Invalid query:' . mysql_error()); }
          $rowcount=mysql_affected_rows($connect);
          debug2($query ."==> rows:" .$rowcount);
        } else {
          echo("QUERY DRYRUN: $query\n");
        }

        // Analyse results by checking rowcount
        if ($rowcount==1) {          # it worked
          debug1("$ip - $mac : updated in systems table");
          $count_updates++;

        } else if (($rowcount==0) && ($conf->router_mac_ip_discoverall)) {   
          // New unmanaged systems have been discovered, lets insert/document them
          // TBD: make sure that all IPs come from our networks? So far, only local
          //      IPs were visible
          debug2("$ip - $mac: new, so insert into systems");
          # TBD: What vlan should we use? In theor it makes no difference, since these device should onyl be unmanaged,
          # but if thy connect to a VMPS port saome day??
          # We could use $conf->set_vlan_for_unknowns, or set to '1' which is the default. For now use the latter.
          $query1="INSERT INTO systems SET  mac='$mac', vlan='1', status=3, r_timestamp=NOW(), r_ip='$ip', comment='Auto discovered by router_mac_ip'";
          $query2='';
          if ( $conf->router_mac_ip_update_from_dns ) {   // flag to disable name lookup
              $fqdn=gethostbyaddr($ip);
              debug2("got $fqdn $ip $mac");
              if($fqdn!=$ip) { // We got the host name, now update it
                // strip domain name
                list($hostname_only) = split('[.]', $fqdn);
                $hostname_only = strtolower($hostname_only);
                $query2=", name='$hostname_only' ";
              }
          }
          logit("New unmanaged end-device: mac=$mac ip=$ip dns=$hostname_only");
          $query=$query1 . $query2;
          if ($mysql_write2) {
            if (!mysql_query($query,$connect)) { die('Invalid query: '.mysql_error()); }
          } else {
            echo("QUERY DRYRUN: $query\n");
          }
        } else if ($rowcount == -1) {   # problem
          logit("Error query failed: $query");

        } else if ($rowcount > 1) {   # problem: duplicates
          #logit("$query");
          logit("$ip - $mac : duplicates in systems table - ERROR");
        }   

  }
 }

 # Don't write to the GUI logging table any more, its too noisy/frequent
 #log2db('info',"Update $count_updates mac/ip tables from router $router");
 logit("Update $count_updates mac/ip tables from $router");
}

  // measure performance
   $mtime = microtime();
   $mtime = explode(" ",$mtime);
   $mtime = $mtime[1] + $mtime[0];
   $endtime = $mtime;
   $totaltime = ($endtime - $starttime);
   debug1("Time taken= ".$totaltime." seconds\n");

###
?>