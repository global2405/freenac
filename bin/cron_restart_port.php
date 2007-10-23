#!/usr/bin/php -f
<?php
/**
 * /opt/nac/bin/cron_restart_port
 *
 * Long description for file:
 * Go though the port table and check for the restart flag, and 
 * restart the ports via SNMP (if this option is enabled in config.inc)
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

require_once 'funcs.inc.php';

$logger->setDebugLevel(0);
#$logger->setLogToStdErr();

#####-- functions

function restart_port($switch, $port) {
  global $conf;
  if ($conf->check_port_restart) {
     $answer=syscall("php restart_port.php $port $switch");
     #logit($answer);
     debug1($answer);
  }
}


#####-- main() -------

## Connect to DB
  db_connect();

debug1("cron_restart_port");

#$query="SELECT switch,name,comment FROM port WHERE restart_now='1'";
$query="SELECT switch.name as switchname, switch.ip as switchip,port.name,port.comment FROM port INNER JOIN switch on port.switch=switch.id WHERE restart_now='1'";
  $res = mysql_query($query);
  if (!$res) { die('Invalid query: ' . mysql_error()); }

  while ($line = mysql_fetch_array($res, MYSQL_NUM)) {
    logit(            "restart_port switch $line[0] $line[1], $line[2], $line[3], $line[4]");
    log2db('info', "restart_port switch $line[0] $line[1], $line[2], $line[3], $line[4]");
    #$switch=v_sql_1_select("select ip from switch where id='$line[0]'");
    $switch=$line[1];
    restart_port($switch, $line[2]);
  }

# Now reset the flag, since its done
$query="UPDATE port SET restart_now='0' ";
  debug1($query);
  $res2 = mysql_query($query, $connect);
  if (!$res2) { die('Invalid query: ' . mysql_error()); }

mysql_close($connect);
?>