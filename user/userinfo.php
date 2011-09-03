<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/reservation.functions.php';

$message = "";
$error = 0;

if (!empty($_GET['user'])) {
  if (($_GET['user']) != $_SESSION['uid'] && !hasEditUsersRight()) {
    die('Insufficient rights to change user info');
  } else {
    $userid = $_GET['user'];
  }
} else {
  $userid = $_SESSION['uid'];
}

if (IsFacebookEnabled() && $_SESSION['uid'] == $userid) {
  global $serverConf;
  $fb_cookie = FBCookie($serverConf['FacebookAppId'], $serverConf['FacebookAppSecret']);

  if ($fb_cookie) {
    if ((!empty($_GET['linkfacebook'])) && ($_GET['linkfacebook'] == "true")) {
      ReMapFBUserId($fb_cookie, $userid);
    }
    if ((!empty($_GET['unlinkfacebook'])) && ($_GET['unlinkfacebook'] == "true")) {
      UnMapFBUserId($fb_cookie, $userid);
    }
  }
  $fb_props = getFacebookUserProperties($userid);
  if (FBLoggedIn($fb_cookie, $fb_props)) {
    if (!empty($_POST['linkfbplayer'])) {
      LinkFBPlayer($userid, $_POST['fbPlayerId'], array("won"));
      $fb_props = getFacebookUserProperties($userid);
    }
    if (!empty($_POST['unlinkfbplayer'])) {
      UnLinkFBPlayer($userid, $_POST['fbPlayerId']);
      $fb_props = getFacebookUserProperties($userid);
    }
  }
}

if ($userid != "anonymous") {
  //process itself if submit was pressed
  if(!empty($_POST['save'])){
    $newUsername=$_POST['UserName'];
    $newName=$_POST['Name'];
    $newLocale=$_POST['userlocale'];

    $message = "";
    if(empty($newUsername)|| strlen($newUsername) < 3 || strlen($newUsername) > 20){
      $message .= "<p class='warning'>"._("Username is too short (min. 3 letters).")."</p>";
      $error = 1;
    }
    
    if (IsRegistered($newUsername) && $userid != $newUsername){
      $message .=  "<p class='warning'>"._("The username is already in use.")."</p>";
      $error = 1;
    }
    
    if(empty($newName)){
      $message .= "<p class='warning'>"._("Name can not be empty.")."</p>";
      $error = 1;
    }

    $uidcheck = mysql_real_escape_string($newUsername);

    if($uidcheck != $newUsername){
      $message .= "<p class='warning'>"._("Illegal characters in the username.")."</p>";
      $error = 1;
    }

    global $locales;
    if (!isset($locales[$newLocale])) {
      $message .= "<p class='warning'>"._("Unsupported language:")." ".$newLocale."</p>";
      $error = 1;
    }
    
    if(!$error){
      $message .= "<p>"._("Changes were saved")."</p><hr/>";
    }else{
      $message .= "<p class='warning'><b>"._("Chages were NOT saved")."</b></p><hr/>";
    }
    

    if(!$error) {
      $success = false;
      $oldLocale = getUserLocale($userid);
      if ($oldLocale != $newLocale) {
        SetUserLocale($userid, $newLocale);
        if ($_SESSION['uid'] == $userid) {
          $_SESSION['userproperties']['locale'] = array($newLocale => 0);
          setSessionLocale();
          loadDBTranslations($newLocale);
        }
      }
      $userinfo = UserInfo($userid);
      $success = UserUpdateInfo($userinfo['id'],$userid, $newUsername, $newName);
      if ($success) {
        if ($newUsername != $_SESSION['uid'] && $userid != $newUsername) {
          header('location:?view=user/userinfo&user='.urlencode($newUsername));
          exit;
        } else {
          $userid = $newUsername;
        }
      }
    }
  }
  
  if(!empty($_POST['changepsw'])) {
    $newPassword1=$_POST['Password1'];
    $newPassword2=$_POST['Password2'];
    if(empty($newPassword1)){
      $message .= "<p class='warning'>"._("Password cannot be empty.")."</p>";
      $error = 1;
    }
    	
    if(!empty($newPassword1) && (strlen($newPassword1) <5 || strlen($newPassword1) > 20)){
      $message .= "<p class='warning'>"._("Password is too short (min. 5 letters).")."</p>";
      $error = 1;
    }
    
    if(!empty($newPassword1) && ($newPassword1 != $newPassword2)){
      $message .= "<p class='warning'>"._("Passwords do not match.")."</p>";
      $error = 1;
    }
    if(!$error){
      $message .= "<p>"._("Changes were saved")."</p><hr/>";
    }else{
      $message .= "<p class='warning'><b>"._("Chages were NOT saved")."</b></p><hr/>";
    }
    
    if(!$error){
      UserChangePassword($userid,$newPassword1);
    }
    
  }

  if (!empty($_POST['addeditseasons']) && !empty($_POST['addeditseasonslist'])) {
    foreach($_POST['addeditseasonslist'] as $seasonid) {
      AddEditSeason($userid, $seasonid);
    }
  }
  if (!empty($_POST['remeditseasons']) && !empty($_POST['remeditseasonslist'])) {
    foreach($_POST['remeditseasonslist'] as $propid) {
      RemoveEditSeason($userid, $propid);
    }
  }
  if (!empty($_POST['rempoolselector_x'])) {
    RemovePoolSelector($userid, $_POST['deleteSelectorId']);
  }
  if (!empty($_POST['remuserrole_x'])) {
    RemoveUserRole($userid, $_POST['deleteRoleId']);
  }
  if (!empty($_POST['remextraemail_x'])) {
    RemoveExtraEmail($userid, $_POST['deleteExtraEmail']);
  }

  if (!empty($_POST['toprimaryemail'])) {
    ToPrimaryEmail($userid, $_POST['toPrimaryEmailVal']);
  }

  if (!empty($_POST['selectpoolselector'])) {
    if ($_POST['selectortype'] == 'currentseason') {
      $selector = 'currentseason';
      AddPoolSelector($userid, $selector);
    } elseif ($_POST['selectortype'] == 'team') {
      foreach ($_POST['teams'] as $teamid) {
        AddPoolSelector($userid, 'team:'.$teamid);
      }
    } elseif ($_POST['selectortype'] == 'season') {
      foreach ($_POST['searchseasons'] as $seasonid) {
        AddPoolSelector($userid, 'season:'.$seasonid);
      }
    } elseif ($_POST['selectortype'] == 'series') {
      foreach ($_POST['series'] as $seriesid) {
        AddPoolSelector($userid, 'series:'.$seriesid);
      }
    } elseif ($_POST['selectortype'] == 'pool') {
      foreach ($_POST['pools'] as $poolid) {
        AddPoolSelector($userid, 'pool:'.$poolid);
      }
    }
  }

  if (!empty($_POST['selectuserrole'])) {
    if ($_POST['userrole'] == 'superadmin') {
      $selector = 'superadmin';
      AddUserRole($userid, $selector);
    } elseif ($_POST['userrole'] == 'translationadmin') {
      $selector = 'translationadmin';
      AddUserRole($userid, $selector);
    } elseif ($_POST['userrole'] == 'useradmin') {
      $selector = 'useradmin';
      AddUserRole($userid, $selector);
    } elseif ($_POST['userrole'] == 'teamadmin') {
      foreach ($_POST['teams'] as $teamid) {
        AddUserRole($userid, 'teamadmin:'.$teamid);
      }
    } elseif ($_POST['userrole'] == 'accradmin') {
      foreach ($_POST['teams'] as $teamid) {
        AddUserRole($userid, 'accradmin:'.$teamid);
      }
    } elseif ($_POST['userrole'] == 'seasonadmin') {
      foreach ($_POST['searchseasons'] as $seasonid) {
        AddUserRole($userid, 'seasonadmin:'.$seasonid);
      }
    } elseif ($_POST['userrole'] == 'seriesadmin') {
      foreach ($_POST['series'] as $seriesid) {
        AddUserRole($userid, 'seriesadmin:'.$seriesid);
      }
    } elseif ($_POST['userrole'] == 'resadmin') {
      foreach ($_POST['reservations'] as $reservationId) {
        AddUserRole($userid, 'resadmin:'.$reservationId);
      }
    } elseif ($_POST['userrole'] == 'resgameadmin') {
      foreach ($_POST['reservations'] as $reservationId) {
        AddUserRole($userid, 'resgameadmin:'.$reservationId);
      }
    } elseif ($_POST['userrole'] == 'gameadmin') {
      foreach ($_POST['games'] as $gameId) {
        AddUserRole($userid, 'gameadmin:'.$gameId);
      }
    } elseif ($_POST['userrole'] == 'playeradmin') {
      foreach ($_POST['players'] as $playerId) {
        AddUserRole($userid, 'playeradmin:'.$playerId);
      }
    }
  }

}


$LAYOUT_ID = USERINFO;
$userinfo = UserInfo($userid);
$title = _("User information").": ".utf8entities($userinfo['name']);
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
?>
<script type="text/javascript">
<!--
function setId(id, name) 
	{
	var input = document.getElementById(name);
	input.value = id;
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
//content
if ($_SESSION['uid'] != "anonymous") {
  //print_r($_POST);
  echo $message;

  echo "<form method='post' action='?view=user/userinfo";
  if (!empty($_GET['user'])) {
    echo "&amp;user=".urlencode($_GET['user']);
  }
  echo "'>\n";
  echo "<table cellpadding='8'>
		<tr><td class='infocell'>"._("Name").":</td>
			<td><input class='input' maxlength='256' id='Name' name='Name' value='".$userinfo['name']."'/></td></tr>
		<tr><td class='infocell'>"._("Username").":</td>
			<td><input class='input' maxlength='20' id='UserName' name='UserName' value='".$userinfo['userid']."'/></td></tr>
		<tr><td class='infocell'>"._("Primary email").":</td>
			<td><a href='mailto:".$userinfo['email']."'/>".$userinfo['email']."</a>&nbsp;
			<a href='?view=user/addextraemail&amp;user=".utf8entities($userid)."'>"._("Add extra address")."</a></td></tr>\n";
  $extraEmails = UserExtraEmails($userid);
  if ($extraEmails) {
    echo "		<tr><td rowspan='".count(extraEmails)."' class='infocell'>"._("Extra emails").":</td>\n";
    $first = true;
    foreach ($extraEmails as $extraEmail) {
      if ($first) {
        $first = false;
      } else {
        echo "		<tr>\n";
      }
      echo "		  <td><a href='mailto:".utf8entities($extraEmail)."'>".utf8entities($extraEmail)."</a>
						<input class='deletebutton' type='image' src='images/remove.png' name='remextraemail' value='X' alt='X' onclick='setId(\"".$extraEmail."\", \"deleteExtraEmail\");'/>
						<input class='button' type='submit' name='toprimaryemail' value='"._("Set as primary")."' onclick='setId(\"".$extraEmail."\", \"toPrimaryEmailVal\");'/>
						</td></tr>\n";
    }
  }
  if (IsFacebookEnabled() && $_SESSION['uid'] == $userid) {
    global $serverConf;
    $fb_cookie = FBCookie($serverConf['FacebookAppId'], $serverConf['FacebookAppSecret']);
    $fb_props = getFacebookUserProperties($userid);
    if (!$fb_cookie) {
      // Login button
      echo "<tr><td class='infocell'>"._("Login via Facebook").":</td>
			<td><fb:login-button perms='email,publish_stream,offline_access'/></td></tr>\n";	
    } elseif ($fb_cookie && !isset($fb_props['facebookuid'])) {
      if (ExistingFBUserId($fb_cookie['uid'])) {
        // Offer to change facebook linkage
        echo "<tr><td class='infocell'>"._("Login via Facebook").":</td>
				<td><a href='?view=user/userinfo&linkfacebook=true'>"._("Change link from this account to my current Facebook account")."</a></td></tr>\n";	
      } else {
        // Offer to link account
        echo "<tr><td class='infocell'>"._("Login via Facebook").":</td>
				<td><a href='?view=user/userinfo&linkfacebook=true'>"._("Link this account to my Facebook account")."</a></td></tr>\n";
      }
    } elseif ($fb_cookie['uid'] == $fb_props['facebookuid']) {
      // Offer to unlink account
      echo "<tr><td class='infocell'>"._("Login via Facebook").":</td>
			<td><a href='?view=user/userinfo&unlinkfacebook=true'>"._("Remove link from this account to my Facebook account")."</a></td></tr>\n";	
    } else {
      // Offer to change facebook linkage
      echo "<tr><td class='infocell'>"._("Login via Facebook").":</td>
			<td><a href='?view=user/userinfo&linkfacebook=true'>"._("Change link from this account to my current Facebook account")."</a></td></tr>\n";	
    }
  }

  echo "		<tr><td class='infocell'>"._("Language").":</td>
			<td><select class='dropdown' name='userlocale'>";
  global $locales;

  $userlocale = getUserLocale($userinfo['userid']);


  foreach ($locales as $localestr => $localename) {
    echo "<option value='".$localestr."'";
    if ($localestr == $userlocale) {
      echo " selected='selected'";
    }
    echo ">".utf8entities($localename)."</option>\n";
  }

  echo "</select></td></tr>";

  echo "<tr><td colspan = '2' align='right'><br/>
		  <input type='hidden' id='deleteExtraEmail' name='deleteExtraEmail'/>
		  <input type='hidden' id='toPrimaryEmailVal' name='toPrimaryEmailVal'/>
		  <input class='button' type='submit' name='save' value='"._("Save")."' />
	      <input class='button' type='submit' name='cancel' value='"._("Cancel")."' />
	      </td></tr>\n";

  	
  echo "</table>\n";

  echo "</form>";

  echo "<hr />\n";

  echo "<h2>"._("Show administration responsibilities")."</h2>\n";
  echo "<form method='post' action='?view=user/userinfo";
  if (!empty($_GET['user'])) {
    echo "&amp;user=".urlencode($_GET['user']);
  }
  echo "'>\n";
  $editseasons = getEditSeasons($userid);
  echo "<table><tr><td><select multiple='multiple' name='remeditseasonslist[]' id='remeditseasonslist' style='height:200px;width:150px'>\n";
  foreach ($editseasons as $season => $id) {
    echo "<option value='".$id."'>".utf8entities(SeasonName($season))."</option>";
  }
  echo "</select></td><td>\n";
  echo "<input class='button' type='submit' name='remeditseasons' style='width:50px' value='"._("Delete")."' /><br />
	      <input class='button' type='submit' name='addeditseasons' style='width:50px' value='"._("Add")."' /></td><td>\n";

  echo "<select multiple='multiple' name='addeditseasonslist[]' id='addeditseasonslist' style='height:200px;width:150px'>\n";
  $seasons = Seasons();
  while($season = mysql_fetch_assoc($seasons)){
    if (empty($editseasons[$season['season_id']])) {
      echo "<option value='".urlencode($season['season_id'])."'>".utf8entities($season['name'])."</option>";
    }
  }
  echo "</select></td></tr></table></form>\n";

  echo "<hr />\n";

  echo "<h2>"._("Show pools")."</h2>\n";
  $poolselectors = getPoolselectors($userid);
  if (!empty($poolselectors)) {
    echo "<form method='post' action='?view=user/userinfo";
    if (!empty($_GET['user'])) {
      echo "&amp;user=".urlencode($_GET['user']);
    }
    echo "'>\n<table cellpadding='2'>\n";
    foreach ($poolselectors as $selector => $param) {
      echo "<tr><td>";
      if ($selector == 'currentseason') {
        echo _("Current event");
      } elseif ($selector == 'team') {
        echo _("Team pools");
        echo " (".utf8entities(getTeamName(key($param))).")";
        $param = current($param);
      } elseif ($selector == 'season') {
        echo _("Event");
        echo " (".utf8entities(SeasonName(key($param))).")";
        $param = current($param);
      } elseif ($selector == 'series') {
        echo _("Division");
        echo " (".utf8entities(getSeriesName(key($param))).")";
        $param = current($param);
      } elseif ($selector == 'pool') {
        echo _("Division");
        echo " (".utf8entities(U_(PoolSeriesName(key($param))).", ".U_(PoolName(key($param)))).")";
        $param = current($param);
      }
      echo "</td><td><input class='deletebutton' src='images/remove.png' type='image' name='rempoolselector' value='X' alt='X' onclick='setId(".$param.", \"deleteSelectorId\");'/></td></tr>\n";
    }
    echo "<tr><td><input type='hidden' id='deleteSelectorId' name='deleteSelectorId'/></td><td></td></tr>";
    echo "</table></form>";
  }
  echo "<form method='get' action='?view=user/select_poolselector";
  if (!empty($_GET['user'])) {
    echo "&amp;user=".urlencode($_GET['user']);
  }
  echo "'>";
  echo "<p><select class='dropdown' name='selectortype'>\n";
  echo "<option value='currentseason'>"._("Current event")."</option>\n";
  echo "<option value='team'>". _("Team pools")."</option>\n";
  echo "<option value='season'>"._("Event")."</option>\n";
  echo "<option value='series'>"._("Division")."</option>\n";
  echo "<option value='pool'>"._("Division")."</option>\n";
  echo "</select>\n";
  echo "<input type='hidden' name='view' value='user/select_poolselector'/>\n";
  if (!empty($_GET['user'])) {
    echo "<input type='hidden' name='user' value='".urlencode($_GET['user'])."'/>\n";
  }
  echo "<input class='button' type='submit' name='addpoolselector' style='width:50px' value='"._("Add")."...' /></p>\n";
  echo "</form>\n";

}

if (hasEditUsersRight() || $_SESSION['uid'] == $userid) {
  echo "<hr />\n";

  echo "<h2>"._("User roles")."</h2>\n";
  $userroles = getUserroles($userid);
  if (!empty($userroles)) {
    echo "<form method='post' action='?view=user/userinfo";
    if (!empty($_GET['user'])) {
      echo "&amp;user=".urlencode($_GET['user']);
    }
    echo "'>\n<table>\n";
    foreach ($userroles as $role => $param) {
      if ($role == 'superadmin') {
        echo "<tr><td>";
        echo _("Administrator");
        echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$param.", \"deleteRoleId\");'/></td></tr>\n";
      } elseif ($role == 'translationadmin') {
        echo "<tr><td>";
        echo _("Translation administrator");
        echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$param.", \"deleteRoleId\");'/></td></tr>\n";
      } elseif ($role == 'useradmin') {
        echo "<tr><td>";
        echo _("User administrator");
        echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$param.", \"deleteRoleId\");'/></td></tr>\n";
      } elseif ($role == 'teamadmin') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Team contact person");
          echo " (".utf8entities(getTeamName($akey)).")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
        }
      } elseif ($role == 'seasonadmin') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Event responsible");
          echo " (".utf8entities(SeasonName($akey)).")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
        }
      } elseif ($role == 'seriesadmin' || $role == 'series') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Division organizer");
          echo " (".utf8entities(getSeriesName($akey)).")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
        }
      } elseif ($role == 'accradmin') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Accreditation official");
          echo " (".utf8entities(getTeamName($akey)).")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
        }
      } elseif ($role == 'resadmin') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Scheduling right");
          $reservationInfo = ReservationInfo($akey);
          $resName = ReservationName($reservationInfo);
          echo " (".$resName.")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
        }
      } elseif ($role == 'resgameadmin') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Reservation game input responsible");
          $reservationInfo = ReservationInfo($akey);
          $resName = ReservationName($reservationInfo);
          echo " (".$resName.")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
        }
      } elseif ($role == 'gameadmin') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Game input responsibility");
          $gameInfo = GameInfo($akey);
          $gameName = GameName($gameInfo);
          echo " (".utf8entities($gameName).")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
        }
      } elseif ($role == 'playeradmin') {
        foreach ($param as $akey => $prop_id) {
          echo "<tr><td>";
          echo _("Player profile administrator");
          $playerInfo = PlayerProfile($akey);
          echo " (".utf8entities($playerInfo['firstname']." ".$playerInfo['lastname']).")";
          echo "</td><td><input class='deletebutton' type='image' src='images/remove.png' name='remuserrole' value='X' alt='X' onclick='setId(".$prop_id.", \"deleteRoleId\");'/></td></tr>\n";
          if (IsFacebookEnabled() && $_SESSION['uid'] == $userid) {
            if (FBLoggedIn($fb_cookie, $fb_props)) {
              if (isset($fb_props['facebookplayer'][$akey])) {
                echo "<tr><td>&raquo; "._("Do not publish the game events of this player on my Facebook feed");
                echo "</td><td><input class='button' type='submit' name='unlinkfbplayer' value='"._("Unpublish")."' onclick='setId(".$akey.", \"fbPlayerId\");'/><br/>\n";
                echo "<a href='?view=user/facebookpublishing&amp;player=".$akey."'>"._("Options")."...</a></td></tr>\n";
              } else {
                echo "<tr><td>&raquo; "._("Publish the game events of this player on my Facebook feed");
                echo "</td><td><input class='button' type='submit' name='linkfbplayer' value='"._("Publish")."' onclick='setId(".$akey.", \"fbPlayerId\");'/></td></tr>\n";
              }
            }
          }
        }
      }
    }
    echo "</table>\n";
    echo "<div><input type='hidden' id='deleteRoleId' name='deleteRoleId'/>";
    echo "<input type='hidden' id='fbPlayerId' name='fbPlayerId'/></div>";
    echo "</form>\n";
  }
}
if (hasEditUsersRight()) {
  echo "<form method='get' action='?view=admin/select_userrole";
  if (!empty($_GET['user'])) {
    echo "&amp;user=".urlencode($_GET['user']);
  }
  echo "'>";
  echo "<p>\n";
  echo "<select class='dropdown' name='userrole'>\n";
  echo "<option value='superadmin'>"._("Administrator")."</option>\n";
  echo "<option value='translationadmin'>"._("Translation administrator")."</option>\n";
  echo "<option value='useradmin'>"._("User administrator")."</option>\n";
  echo "<option value='teamadmin'>". _("Team contact person")."</option>\n";
  echo "<option value='seasonadmin'>"._("Event responsible")."</option>\n";
  echo "<option value='seriesadmin'>"._("Division organizer")."</option>\n";
  echo "<option value='accradmin'>"._("Accreditation official")."</option>\n";
  echo "<option value='resadmin'>"._("Scheduling right")."</option>\n";
  echo "<option value='resgameadmin'>"._("Reservation game input responsible")."</option>\n";
  echo "<option value='gameadmin'>"._("Game input responsibility")."</option>\n";
  echo "<option value='playeradmin'>"._("Player profile administrator")."</option>\n";
	echo "</select>\n";
	echo "<input type='hidden' name='view' value='admin/select_userrole'/>\n";
	if (!empty($_GET['user'])) {
		echo "<input type='hidden' name='user' value='".urlencode($_GET['user'])."'/>\n";
	}
	echo "<input class='button' type='submit' name='addpoolselector' style='width:50px' value='"._("Add")."...' />\n";
	echo "</p>\n";
	echo "</form>\n";
	
}
  echo "<hr/>\n";
  echo "<form method='post' action='?view=user/userinfo";
  if (!empty($_GET['user'])) {
    echo "&amp;user=".urlencode($_GET['user']);
  }
  echo "'>\n";
  echo "<table cellpadding='8'>";
  echo "<tr><td class='infocell'>"._("New  password").":</td>";
  echo "<td><input class='input' type='password' maxlength='20' id='Password1' name='Password1' /></td></tr>";
  echo "<tr><td class='infocell'>"._("Repeat password").":</td>";
  echo "<td><input class='input' type='password' maxlength='20' id='Password2' name='Password2' /></td></tr>";	
  echo "<tr><td colspan = '2' align='right'>";
  echo "<input class='button' type='submit' name='changepsw' value='"._("Change Password")."' />";
  echo "</td></tr>\n";  
  echo "</table>\n";
  echo "</form>\n";
//common end
contentEnd();
pageEnd();
?>
