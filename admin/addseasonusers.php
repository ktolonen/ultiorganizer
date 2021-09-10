<?php
include_once $include_prefix.'lib/configuration.functions.php';
include_once $include_prefix.'lib/facebook.functions.php';
include_once $include_prefix.'lib/url.functions.php';

$LAYOUT_ID = ADDSEASONUSERS;
$title = _("Event users");
$html = "";
$seasonId = $_GET["season"];

if(!isSeasonAdmin($seasonId)){
   die('Insufficient rights');  
}

if(!empty($_POST['add'])){
  $userid = $_POST['userid'];
  if(empty($userid)){
    $userid = UserIdForMail($_POST['email']);
  }
  
  if(IsRegistered($userid)){
    if($_GET["access"]=="eventadmin"){
      AddSeasonUserRole($userid, "seasonadmin:".$seasonId, $seasonId);
    }elseif($_GET["access"]=="teamadmin"){
      AddSeasonUserRole($userid, "teamadmin:".$_POST["team"], $seasonId);    
    }elseif($_GET["access"]=="gameadmin"){
      $reservations = $_POST["reservations"];
      foreach($reservations as $res){
        $games = ReservationGames($res);
        while ($game = mysqli_fetch_assoc($games)) {
          AddSeasonUserRole($userid, 'gameadmin:'.$game['game_id'],$seasonId);
        }
      }    
    }elseif($_GET["access"]=="accradmin"){
      $teams = $_POST["teams"];
      foreach($teams as $teamId){
        AddSeasonUserRole($userid, 'accradmin:'.$teamId,$seasonId);
      }
    }    
    $html .= "<p>".sprintf(_("User rights added for %s."), utf8entities($userid))."</p>";
  }else{
    $html .= "<p class='warning'>"._("Invalid user")."</p>";
  }
}elseif(!empty($_POST['remove_x'])){
  if($_GET["access"]=="eventadmin"){
    RemoveSeasonUserRole($_POST['delId'], "seasonadmin:".$seasonId, $seasonId);
  }elseif($_GET["access"]=="teamadmin"){
    RemoveSeasonUserRole($_POST['delId'], "teamadmin:".$_POST['teamId'], $seasonId);    
  }elseif($_GET["access"]=="accradmin"){
    RemoveSeasonUserRole($_POST['delId'], "accradmin:".$_POST['teamId'], $seasonId);    
  }
  $_GET["access"]="";
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<h3>"._("Event admins").":</h3>";
$admins = SeasonAdmins($seasonId);
$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=eventadmin' name='eventadmin'>";
$html .= "<table>";
foreach($admins as $user){
  $html .= "<tr>";
  $html .= "<td style='width:75px'>".$user['userid']."</td><td>". utf8entities($user['name'])." (<a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>)</td>";
  $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"document.eventadmin.delId.value='".utf8entities($user['userid'])."';\"/></td>";
  $html .= "</tr>\n";
}
$html .= "</table>";
if(!empty($_GET["access"]) && $_GET["access"]=="eventadmin"){
  $html .= "<table style='white-space: nowrap' cellpadding='2px'>\n";
  $html .= "<tr><td>"._("User Id")."</td><td><input class='input' size='20' name='userid'/></td><td>"._("or")."</td>\n";
  $html .= "<td>"._("E-Mail")."</td><td><input class='input' size='20' name='email'/</td></tr>\n";
  $html .= "</table>";
  $html .= "<p><input class='button' name='add' type='submit' value='"._("Grant rights")."'/></p>";  
}else{
  $html .= "<p><a href='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=eventadmin'>"._("Add more ...")."</a></p>";
}
$html .= "<div><input type='hidden' name='delId'/></div>";
$html .= "</form>";

$html .= "<h3>"._("Team admins").":</h3>";
$admins = SeasonTeamAdmins($seasonId);
$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=teamadmin' name='teamadmin'>";
$html .= "<table style='white-space: nowrap;'>";
foreach($admins as $user){
  $html .= "<tr>";
  $teaminfo = TeamInfo($user['team_id']);
  $html .= "<td style='width:175px'>".utf8entities(U_($teaminfo['seriesname'])).", ".utf8entities(U_($teaminfo['name']))."</td>\n";
  $html .= "<td style='width:75px'>".$user['userid']."</td><td>". utf8entities($user['name'])." (<a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>)</td>";
  $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"document.teamadmin.delId.value='".utf8entities($user['userid'])."';document.teamadmin.teamId.value='".utf8entities($user['team_id'])."';\"/></td>";
  $html .= "</tr>\n";;
}
$html .= "</table>";
if(!empty($_GET["access"]) && $_GET["access"]=="teamadmin"){
  $html .= "<table style='white-space: nowrap' cellpadding='2px'>\n";
  $html .= "<tr><td>";
  $teams = SeasonTeams($seasonId);
  $html .= "<select class='dropdown' name='team'>";
  foreach($teams as $team){
    $html .= "<option class='dropdown' value='".utf8entities($team['team_id'])."'>". utf8entities(U_($team['seriesname'])).", ".utf8entities(U_($team['name'])) ."</option>";
  }
  $html .= "</select>";
  
  $html .= "</td><td>"._("User Id")."</td><td><input class='input' size='20' name='userid'/></td><td>"._("or")."</td>\n";
  $html .= "<td>"._("E-Mail")."</td><td><input class='input' size='20' name='email'/</td></tr>\n";
  $html .= "</table>";
  $html .= "<p><input class='button' name='add' type='submit' value='"._("Grant rights")."'/></p>";  
}else{
  $html .= "<p><a href='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=teamadmin'>"._("Add more ...")."</a></p>";
}
$html .= "<div><input type='hidden' name='delId'/></div>";
$html .= "<div><input type='hidden' name='teamId'/></div>";
$html .= "</form>";


$html .= "<h3>"._("Scorekeepers").":</h3>";
$seasongames = SeasonAllGames($seasonId);
$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=gameadmin' name='gameadmin'>";
$html .= "<table style='white-space: nowrap;'>";
//all event admins have score keeping rights
$admins = SeasonAdmins($seasonId);
foreach($admins as $user){
  $html .= "<tr>";
  $html .= "<td style='width:75px'>".$user['userid']."</td><td>". utf8entities($user['name'])." (<a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>)</td>";
  $html .= "<td>"._("All games")."</td>";
  $html .= "<td>"._("In role of event admin")."</td>";
  $html .= "</tr>\n";;
}

$admins = SeasonGameAdmins($seasonId);

foreach($admins as $user){
  $html .= "<tr>";
  $html .= "<td style='width:75px'>".$user['userid']."</td><td>". utf8entities($user['name'])." (<a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>)</td>";
  if($user['games']==count($seasongames)){
    $html .= "<td>"._("All games")."</td>";
  }else{
    $html .= "<td>"._("Some games")."</td>";
  }
  $html .= "</tr>\n";;
}

$teamresp = 0;
foreach($seasongames as $game){
  if(!empty($game['respteam'])){
    $teamresp++;    
  }
}

if($teamresp){
  $html .= "<tr>";
  $html .= "<td colspan='2'><i>"._("All team admins have scorekeeping rights for teams' games.")."</i></td>";
  $html .= "</tr>\n";;
}

$html .= "</table>";
if(!empty($_GET["access"]) && $_GET["access"]=="gameadmin"){
  $html .= "<table style='white-space: nowrap' cellpadding='2px'>\n";
  $html .= "<tr><td>"._("User Id")."</td><td><input class='input' size='20' name='userid'/></td><td>"._("or")."</td>\n";
  $html .= "<td>"._("E-Mail")."</td><td><input class='input' size='20' name='email'/</td></tr>\n";

  $reservations = SeasonReservations($seasonId);
  $html .= "<tr><td colspan='5'><select multiple='multiple' size='".count($reservations)."' name='reservations[]'>";
  foreach($reservations as $row){
    $html .= "<option value='".utf8entities($row['id'])."'>";
    $html .= utf8entities($row['reservationgroup']) ." ". utf8entities($row['name']) .", "._("Field")." ".utf8entities($row['fieldname'])." (".JustDate($row['starttime']) .")";
    $html .= "</option>";
  }
  $html .= "</select></td></tr>";
  $html .= "</table>";  
  $html .= "<p><input class='button' name='add' type='submit' value='"._("Grant rights")."'/></p>";  
}else{
  $html .= "<p><a href='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=gameadmin'>"._("Add more ...")."</a></p>";
}
$html .= "</form>";

$html .= "<h3>"._("Roster accreditation rights").":</h3>";
$html .= "<form method='post' action='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=accradmin' name='accradmin'>";
$html .= "<table  style='white-space: nowrap;'>";
//all event admins have score keeping rights
$admins = SeasonAdmins($seasonId);
foreach($admins as $user){
  $html .= "<tr>";
  $html .= "<td style='width:175px'>"._("All teams")."</td>";
  $html .= "<td style='width:75px'>".$user['userid']."</td><td>". utf8entities($user['name'])." (<a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>)</td>";
  $html .= "<td>"._("In role of event admin")."</td>";
  $html .= "</tr>\n";;
}

$admins = SeasonAccreditationAdmins($seasonId);
foreach($admins as $user){
  $html .= "<tr>";
  $teaminfo = TeamInfo($user['team_id']);
  $html .= "<td style='width:175px'>".utf8entities(U_($teaminfo['seriesname'])).", ".utf8entities(U_($teaminfo['name']))."</td>\n";
  $html .= "<td style='width:75px'>".$user['userid']."</td><td>". utf8entities($user['name'])." (<a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>)</td>";
  $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"document.accradmin.delId.value='".utf8entities($user['userid'])."';document.accradmin.teamId.value='".utf8entities($user['team_id'])."';\"/></td>";
  $html .= "</tr>\n";;
}
$html .= "</table>";
if(!empty($_GET["access"]) && $_GET["access"]=="accradmin"){
  $html .= "<table style='white-space: nowrap' cellpadding='2px'>\n";
  $html .= "<tr><td>"._("User Id")."</td><td><input class='input' size='20' name='userid'/></td><td>"._("or")."</td>\n";
  $html .= "<td>"._("E-Mail")."</td><td><input class='input' size='20' name='email'/</td></tr>\n";
  
  $teams = SeasonTeams($seasonId);
  $html .= "<tr><td colspan='5'><select multiple='multiple' size='".count($teams)."' name='teams[]'>";
  foreach($teams as $team){
    $html .= "<option value='".utf8entities($team['team_id'])."'>";
    $html .= utf8entities(U_($team['seriesname'])).", ".utf8entities(U_($team['name']));
    $html .= "</option>";
  }
  $html .= "</select></td></tr>";
  $html .= "</table>";
  $html .= "<p><input class='button' name='add' type='submit' value='"._("Grant rights")."'/></p>";  
}else{
  $html .= "<p><a href='?view=admin/addseasonusers&amp;season=".$seasonId."&amp;access=accradmin'>"._("Add more ...")."</a></p>";
}
$html .= "<div><input type='hidden' name='delId'/></div>";
$html .= "<div><input type='hidden' name='teamId'/></div>";
$html .= "</form>";

echo $html;
contentEnd();
pageEnd();
?>