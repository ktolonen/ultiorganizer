<?php
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/url.functions.php';

$LAYOUT_ID = ADDSEASONUSERS;
$title = _("Team admins");
$html = "";
$seriesId = intval($_GET["series"]);
$seriesinfo = SeriesInfo($seriesId);
$backurl = isset($_POST['backurl']) ? utf8entities($_POST['backurl']) : utf8entities($_SERVER['HTTP_REFERER']);
$teams = SeriesTeams($seriesId);

if (!isSeasonAdmin($seriesinfo['season'])) {
  die('Insufficient rights');
}

if (!empty($_POST['add'])) {


  foreach ($teams as $team) {
    $tid = $team['team_id'];
    $userid = isset($_POST["userid$tid"]) ? $_POST["userid$tid"] : "";
    $email = isset($_POST["email$tid"]) ? $_POST["email$tid"] : "";

    if (empty($userid) && empty($email)) {
      continue;
    } elseif (empty($userid)) {
      $userid = UserIdForMail($email);
      if ($userid == "-1") {
        $html .= "<p class='warning'>" . _("Invalid user:") . " " . $email . "</p>";
        continue;
      }
    }

    if (IsRegistered($userid)) {
      AddSeasonUserRole($userid, "teamadmin:$tid", $seriesinfo['season']);
      $html .= "<p>" . _("User rights added for:") . " " . $userid . "</p>";
    } else {
      $html .= "<p class='warning'>" . _("Invalid user:") . " " . $userid . "</p>";
    }
  }
} elseif (!empty($_POST['remove_x'])) {
  RemoveSeasonUserRole($_POST['delId'], "teamadmin:" . $_POST['teamId'], $seriesinfo['season']);
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<h3>" . _("Team admins") . ":</h3>";
$html .= "<form method='post' action='?view=admin/addteamadmins&amp;series=" . $seriesId . "' name='teamadmin'>";


$admins = SeasonTeamAdmins($seriesinfo['season']);
$html .= "<table style='white-space: nowrap;'>";
foreach ($admins as $user) {
  $teaminfo = TeamInfo($user['team_id']);
  if ($teaminfo['series'] != $seriesId) {
    continue;
  }
  $html .= "<tr>";
  $html .= "<td style='width:175px'>" . utf8entities(U_($teaminfo['seriesname'])) . ", " . utf8entities(U_($teaminfo['name'])) . "</td>\n";
  $html .= "<td style='width:75px'>" . $user['userid'] . "</td><td>" . utf8entities($user['name']) . " (<a href='mailto:" . utf8entities($user['email']) . "'>" . utf8entities($user['email']) . "</a>)</td>";
  $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"document.teamadmin.delId.value='" . utf8entities($user['userid']) . "';document.teamadmin.teamId.value='" . utf8entities($user['team_id']) . "';\"/></td>";
  $html .= "</tr>\n";
}
$html .= "</table>";

$html .= "<h3>" . _("Add more") . "</h3>";
$html .= "<table style='white-space: nowrap;'>";

foreach ($teams as $team) {
  $teaminfo = TeamInfo($team['team_id']);
  $html .= "<tr>";
  $html .= "<td style='width:175px'>" . utf8entities(U_($teaminfo['name'])) . "</td>\n";
  $html .= "<td>" . _("User Id") . "</td><td><input class='input' size='20' name='userid" . $team['team_id'] . "' id='userid" . $team['team_id'] . "'/></td><td>" . _("or") . "</td>\n";
  $html .= "<td>" . _("E-Mail") . "</td><td><input class='input' size='20' name='email" . $team['team_id'] . "' id='email" . $team['team_id'] . "'/</td></tr>\n";
  $html .= "</tr>\n";;
}
$html .= "</table>";
$html .= "<p><a href='?view=admin/adduser&amp;season=" . $seriesinfo['season'] . "'>" . _("Add new user") . "</a></p>";
$html .= "<p>";
$html .= "<input class='button' name='add' type='submit' value='" . _("Grant rights") . "'/>";
$html .= "<input class='button' type='button' value='" . _("Return") . "' onclick=\"window.location.href='$backurl'\" /></p>";
$html .= "</p>";
$html .= "<div><input type='hidden' name='delId'/></div>";
$html .= "<div><input type='hidden' name='teamId'/></div>";
$html .= "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
$html .= "</form>";

echo $html;
contentEnd();
pageEnd();
