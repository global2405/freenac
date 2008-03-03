<?php
/**
 *
 * WebCommon.php
 *
 * Long description for file:
 * Class of common objects/functions for the WebGUI
 * see also the 'Common' Class from which this extends
 *
 * @package     FreeNAC
 * @author      FreeNAC Core Team
 * @copyright   2008 FreeNAC
 * @license     http://www.gnu.org/copyleft/gpl.html   GNU Public License Version 3
 * @version     SVN: $Id
 * @link        http://freenac.net
 *
 */
class WebCommon extends Common 
{
  protected $calling_script;


  function __construct()
  {
    parent::__construct();
    $this->calling_script=basename($_SERVER['SCRIPT_FILENAME']);   // TBD: clean?

    if (! function_exists('mysqli_connect')) {
      throw new DatabaseErrorException("PHP has not been compiled with mysqli support");
    }

    // Show Webpage start, is the constructor the right place?
    echo $this->print_header();  
  }

  /**
   * Add the class name to debug messages
   */
  public function debug($msg, $level=1) 
  {
    if (isset($_SESSION['uid'])) $uid="uid={$_SESSION['uid']} ";
    $this->logger->debug($uid .get_class($this) ." " .$msg, $level);
  }
  public function logit($msg) 
  {
    if (isset($_SESSION['uid'])) $uid="uid={$_SESSION['uid']} ";
    $this->logger->logit(get_class($this) ." " .$msg);
  }


  public function print_header($print_links=true)
  {
    global $header1, $header2, $head_right1, $head_right2;

    if (defined('HEADER')){   // already displayed?
      $this->debug('print_header: HEADER already true',2);

    } else {
      if ($print_links===false) {
        $lthis->debug('print_header: do not print right links', 3);
        $head_right1='';
        $head_right2='';
      }
      $ret= $header1 . $header2;
      define('HEADER',true); // The header is out
      $this->debug('print_header: done', 3);
      return $ret;
    }
  }


  public function print_headerSmall($print_links=true)
  {
    global $header1, $header2, $head_right1, $head_right2;

    if (defined('HEADER')){   // already displayed?
      $this->debug('print_headerSmall: HEADER already true',2);
    } 
    else {
      if ($print_links===false) {
        $lthis->debug('print_header: do not print right links', 3);
        $head_right1='';
        $head_right2='';
      }
      #$ret= $header1 . $header2;
      $ret= $header1 . $header2_small;
      define('HEADER',true); // The header is out
      $this->debug('print_header: done', 3);
      return $ret;
    }
  }


  public function print_footer()
  {
    global $sql_auth, $drupal_auth;

    if (defined('FOOTER')){   // already displayed?
      $this->debug('print_footer: FOOTER already true',2);
    } 
    else {
      if (!isset ($_SESSION['login_data'])) {
        $userdata=">> Not logged in <<";
        $text=<<<EOF
  <div align='center'>
  <font class=user_footer>$userdata</font></p>
  </div>
  </tr> </table> </body> </html>
EOF;
      }
      else {
        $userdata="<br>Logged in as: " .$_SESSION['login_data']
          ." (" .$_SESSION['nac_rights_text'] .")";

        if (($sql_auth===true) || ($drupal_auth===true)) {
          $logout_button="<li><a href='./logout.php'>Log out</a></li>";
        }
        else {
          $logout_button='';
        }

        $text=<<<EOF
  <div align='center'>
  <font class=user_footer>$userdata</font></p>
  </div>
  <div id="headermenue">
  <ul>
     <li><A HREF='javascript:javascript:history.go(-1)'< Back</A></li>
     <li><a href="./index.php">Main Menu</a></li>
     $logout_button
  </ul> </div>
  </tr> </table> </body> </html>
EOF;
     #<li><a href="./ChooseAccount.php">Change Account</a></li>
      }
      return $text;

    }
  }


  /* no menu on the botton */
  public function print_footer_empty()
  {
    if (defined('FOOTER')){   // already displayed?
      $this->debug('print_footer: FOOTER already true',2);
    } 
    else {
      $ret="</table></body></html>";
      define('FOOTER',true);
      return $ret;
    }
  }


// ------------ funnions common to the FreeNAC DB scheam, to be reused where possible in the
// ------------ Web GUI

/**
 * Get a Vlan name, given its index number
 */
public function get_vlan($s)
{
   $conn=$this->getConnection();     //  make sure we have a DB connection
   $ret='';
   $q="select default_name from vlan where id='$s'";
     $res = $conn->query($q);
     if ($res === FALSE)
       throw new DatabaseErrorException($q .'; ' .$conn->error);

     while (($row = $res->fetch_assoc()) !== NULL) {
         $ret=$row['default_name'];
     }
   return($ret);
}


/**
 * Look up what systems were seen on a port in the last $conf->web_lastdays days
 */
public function get_hosts($port) 
{
  $conn=$this->getConnection();     //  make sure we have a DB connection
  $ret='';
$q=<<<TXT
SELECT s.name, 
  s.LastSeen, CONCAT(users.Surname, ' ',users.GivenName, ', ',users.Department) as owner 
  FROM systems as s 
  LEFT JOIN users ON users.id = s.uid 
  WHERE LastPort='$port'  AND (TO_DAYS(LastSeen)>=TO_DAYS(CURDATE())-{$this->conf->web_lastdays}) 
TXT;
     $this->debug($q ,3);
     $res = $conn->query($q);
     if ($res === FALSE)
       throw new DatabaseErrorException($q .'; ' .$conn->error);

     while (($row = $res->fetch_assoc()) !== NULL) {
         $ret.=$row['name'] .' (' .$row['owner'] .'), ' .$row['LastSeen'] .'<br>';
     }
   $this->debug($ret ,3);
   return($ret);
}


/**
 * Look up the first switch or patch location ID for a port
 */
public function get_locationid($port) 
{
  $conn=$this->getConnection();     //  make sure we have a DB connection
  $query = "SELECT patchloc.id as patchlocid, switchloc.id as switchlocid FROM port
                  LEFT JOIN patchcable ON patchcable.port = port.id
                   LEFT JOIN location patchloc ON patchloc.id = patchcable.office
                  LEFT JOIN switch ON switch.id = port.switch
                   LEFT JOIN location switchloc ON switchloc.id = switch.location WHERE port.id = '$port';";
  $res = $conn->query($q);
  if ($res === FALSE)
       throw new DatabaseErrorException($q .'; ' .$conn->error);

     while (($row = $res->fetch_assoc()) !== NULL) {

       if (($row['patchlocid'] = '') || (!$row['patchlocid'])) {
         return($row['switchlocid']);
       } 
       else {
         return($row['patchlocid']);
       }
     }
}

/**
 * Look up the first switch or patch location for a port
 */
public function get_location($port) 
{
  $conn=$this->getConnection();     //  make sure we have a DB connection
$q=<<<TXT
SELECT patchloc.id as patchlocid, CONCAT(patchbd.name,' ',patchloc.name) as patchloc,
   switchloc.id as switchlocid, CONCAT(switchbd.name,' ',switchloc.name) as switchloc
   FROM port
   LEFT JOIN patchcable ON patchcable.port = port.id
   LEFT JOIN location patchloc ON patchloc.id = patchcable.office
   LEFT JOIN building as patchbd ON patchbd.id = patchloc.building_id
   LEFT JOIN switch ON switch.id = port.switch
   LEFT JOIN location switchloc ON switchloc.id = switch.location
   LEFT JOIN building as switchbd ON switchbd.id = switchloc.building_id
   WHERE port.id = '$port'
TXT;

  $res = $conn->query($q);
  if ($res === FALSE)
       throw new DatabaseErrorException($q .'; ' .$conn->error);

     while (($row = $res->fetch_assoc()) !== NULL) {

       if (($row['patchloc'] = '') || (!$row['patchloc'])) {
         return($row['switchloc']);
       } 
       else {
         return($row['patchloc']);
       }
     }
}


// ---------------------------------------------
}  // class

?>