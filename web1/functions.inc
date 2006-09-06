<?php
#
#  functions.inc
#
#  2006.05.25/Sean Boran: Production
#  2006.01.24/Thomas Dagonnier: First prototype
#
#  Copyright (C) 2006 Swisscom
#  Licensed under GPL, see LICENSE file or http://www.gnu.org/licenses/gpl.html
####################################


function db_connect()
{
  global $connect, $dbhost, $dbuser, $dbpass, $dbname;

  $connect=mysql_connect($dbhost, $dbuser, $dbpass)
     or die("Could not connect to mysql: " . mysql_error());
  mysql_select_db($dbname, $connect) or die("Could not select database")
     or die("Could not select DB: " . mysql_error());;
}


function vmps_header()
{
  echo '<p ALIGN=CENTER><a href="./index.html">VMPS Menu</a></p>';
}

function vmps_footer()
{
  echo '<p ALIGN=CENTER><a href="./index.html">VMPS Menu</a></p>';
}



function debug1($msg) {
  global $debug_flag1, $debug_to_syslog;
  $msg=rtrim($msg);
  if (($debug_flag1==TRUE) && (strlen($msg)>0) ) {
    if ($debug_to_syslog===TRUE) {
      syslog(LOG_INFO, "Debug1: $msg");
    } else {
      echo "Debug1: $msg<br>";
    }
  }
}
function debug2($msg) {
  global $debug_flag2, $debug_to_syslog;
  $msg=rtrim($msg);
  if (($debug_flag2==TRUE) && (strlen($msg)>0) ) {
    if ($debug_to_syslog===TRUE) {
      syslog(LOG_INFO, "Debug2: $msg");
    } else {
      echo "Debug2: $msg<br>";
    }
  }
}

function get_nmap_id($mac) {
  db_connect();
        $sel = "SELECT id FROM nac_hostscanned WHERE mac='".$mac."';";
        $res = mysql_query($sel);
        $num = mysql_num_rows($res);

        if ($num > 0) {
                $mac = mysql_fetch_array($res);
                return($mac[0]);
        } else {
                return(FALSE);
        };
};

function get_nmap_os($nmap_ip) {
  db_connect();
        $sel = "SELECT os FROM nac_hostscanned WHERE id='$nmap_ip';";
        $res = mysql_query($sel);
        $num = mysql_num_rows($res);

        if ($num > 0) {
                $os = mysql_fetch_array($res);
                return($os[0]);
        } else {
                return(FALSE);
        };
};

function get_nmap_mac($id) {

};


// not used
function validate_input($string) {
  rtrim($string,' ');
  if (stristr($string, ' ') OR stristr($string,';')) {
    return(-1);
  } else {
    $input = mysql_real_escape_string($string);
    return($input);
  };
};

function validate_webinput($string)
{
  rtrim($string,' ');
  if (get_magic_quotes_gpc()) {
    $value = stripslashes($string);
  }

  // Remove dodgy characters by escaping them
  if (!is_numeric($string)) {
    $string = mysql_real_escape_string($string);
    // Quote as well?
    #$string = "'" . mysql_real_escape_string($string) . "'";
  }
  return $string;
}

function display_os_select($selected) {
  global $connect;
  db_connect();

  $query = "SELECT * FROM sys_os ORDER BY value ASC;";
  $res = mysql_query($query, $connect);

  $html .= '<option value="">(any)</option>';
  while ($os = mysql_fetch_array($res)) {
    if ($os['value'] != '') {
      $html .= "<option ";
	  if (($selected == $os['id']) && $selected != '') { $html .= "selected "; };
      $html .= "value=\"". $os['id']. '">';
      $html .= $os['value']. '</option>';
    };
  };
  return($html);
};

function display_description_select($selected) {
  global $connect;
  db_connect();

  $query = "SELECT * FROM users ORDER BY Surname ASC;";
  $res = mysql_query($query, $connect);

  $html .= '<option value="">(any)</option>';
  while ($user = mysql_fetch_array($res)) {
    if ($user['Surname'] != '') {
      $html .= "<option ";
	  if (($selected == $user['AssocNtAccount']) && $selected != '') { $html .= "selected "; };
	  $html .= "value=\"". $user['AssocNtAccount']. '">';
      $html .= $user['Surname']. ' '. $user['GivenName'].', ';
      $html .= $user['Department']. '</option>';
    };

  };
  return($html);
};


function get_user_email($username) {
  db_connect();
  $query = "SELECT rfc822mailbox FROM users WHERE AssocNtAccount = '$username'; ";
  $res = mysql_query($query);
  $user = mysql_fetch_array($res);

  return($user[0]);
};

function get_user_name($username) {
  db_connect();
  $query = "SELECT * FROM users WHERE AssocNtAccount = '$username'; ";
  $res = mysql_query($query);
  $user = mysql_fetch_array($res);
  $name = $user['Surname'].' '.$user['GivenName'].', '.$user['Department'];

  return($name);
};

function user_tooltip($username) {
  db_connect();
  $query = "SELECT * FROM users WHERE AssocNtAccount = '$username'; ";
  $res = mysql_query($query);
  $user = mysql_fetch_array($res);

  //$text = $user['GivenName'].' '.$user['Surname'].', '.$user['Department']." (";
  $text .= $user['HouseIdentifier'].' '.$user['PhysicalDeliveryOfficeName']." / ";

  // Shorter tel number for INO
  if (stristr($user['TelephoneNumber'],'31-342')) {
    $user['TelephoneNumber'] = str_replace(' ','',substr($user['TelephoneNumber'],strlen($user['TelephoneNumber'])-7,7));
  };

  $text .= $user['TelephoneNumber']. " / ";
  $text .= $user['Mobile'];
  //."\n";

  return($text);
};

function get_vlan_color($vlan) {
  if (($vlan == 0) || ($vlan == 900)) { return('#CCCCCC'); };
  if ($vlan < 9)                      { return('#FFCC66'); };
  if ($vlan < 500)                    { return('#FFFFCC'); };

  return('#CCFFFF');
};

function format_mac($macdouble) {
  $numbers = explode(':',$macdouble);

  $dot=1;
  foreach($numbers as $value) {
    $mac .= $value;
    if ($dot == 1) {
      $dot = 0;

    } else {
      $dot = 1;
      $mac .= '.';
    }
  };
  $mac = rtrim($mac,'.');
  return($mac);
};

function get_vlan_descr($vlan) {
  if ($vlan) {
    db_connect();
    $query = "SELECT vlan_description FROM vlan WHERE id = $vlan";
    //echo $query;
    $descr = mysql_fetch_array(mysql_query($query));
    return($descr[0]);

  } else {
    return('N/A');
  };
};

function get_location($switch_ip,$port) {
  db_connect();
        $query = "SELECT * FROM patchcable WHERE nach_switch = '$switch_ip' AND nach_port = '$port';";
        $res = mysql_query($query);
        if (mysql_num_rows($res) > 0) {
                $port = mysql_fetch_array($res);
                return($port['von_office']);
        } else {
                return(FALSE);
        };
};

function get_switch_name($switch_ip) {
  db_connect();
        $query = "SELECT name FROM switch WHERE ip = '$switch_ip';";
        $res = mysql_query($query);
        if (mysql_num_rows($res) > 0) {
                $port = mysql_fetch_array($res);
                return($port[0]);
        } else {
                return(FALSE);
        };
};

function display_host_table($where) {
		$query = "SELECT * FROM systems WHERE $where ;";
//		echo $query.'<br>';
		$mysql_res = mysql_query($query);
		if (mysql_num_rows($mysql_res) > 0) {
			$html .= "<table cellspacing=0 cellpadding=5 border=1>\n";
			$html .= "<tr><th>OS<td>Nmap<td>ePO<th>Hostname<th>Owner<th>Inventar<th>MAC<th>Last IP<th>VLAN<th>Standard<br>location<th colspan=2>LastSeen\n";
			while ($row = mysql_fetch_array($mysql_res,MYSQL_ASSOC)) {
				$html .= '<tr bgcolor="'.get_vlan_color($row['vlan']).'">';
				$html .= '<td align=center><img src="os/'.$row['os'].'.gif" border=0>';
// extra details
				$mac = $row['mac'];
				$html .= '<td align=center>';
				if (get_nmap_id($mac)) { $html .= 'X'; };

				$html .= '<td align=center>';
				if (mysql_num_rows(mysql_query("SELECT * FROM EpoComputerProperties WHERE NetAddress = '$mac'")) > 0) { $html .= 'X'; };
				
// name => print details link
				$html .= '<td><b><a href="display.php?single_host='.$row['mac'].'">';
				$html .= $row['name'].'</a></b>';
// owner => email link
				$html .= '<td>';
				  if ($row['description']) {
					$html .= '<a href="mailto:'.get_user_email($row['description']).'" ';
					$html .= 'title="'.user_tooltip($row['description']).'" ';
					//$html .= '>'.$row['description']."</a>\n";
					$html .= '>'.get_user_name($row['description'])."</a>\n";
				  } else {
					$html .= '<i>Unknown</i>';
				  };
				$html .= '<td>'.$row['inventar'];
				$html .= '<td>'.$row['mac'];
				$html .= '<td>'.$row['r_ip'];
				$html .= '<td>'.get_vlan_descr($row['vlan']); // get_vlan_short(get_vlan_descr
					if (! $row['building']) { $row['building'] = 'Ber-Omu93' ; };
				$html .= '<td>'.$row['building'].' '.$row['office'];
				$html .= '<td>'.get_location($row['switch'],$row['port']).'<td>'.$row['LastSeen'].'</font>';
				$html .= "\n";
			};
			$html .= "</table>\n";
		} else {
			$html .= '<i>No record found</i>';
		};
	return($html);
};

?>