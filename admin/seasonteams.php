<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

$LAYOUT_ID = SEASONTEAMS;
$html = "";
$season = $_GET["season"];
$series_id = CurrentSeries($season);

$title = utf8entities(SeasonName($season)) . ": " . _("Teams");

if ($series_id <= 0) {
  showPage($title, "<p>" . _("No divisions defined. Define at least one division first.") . "</p>");
  die;
}

$series = SeasonSeries($season);

//team parameters
$tp = array(
  "team_id" => "",
  "name" => "",
  "club" => "",
  "country" => "",
  "abbreviation" => "",
  "series" => $series_id,
  "pool" => "0",
  "rank" => "",
  "valid" => "1",
  "bye" => ""
);

$seasonInfo = SeasonInfo($season);

//remove
if (!empty($_POST['remove_x'])) {
  $id = $_POST['hiddenDeleteId'];
  if (CanDeleteTeam($id)) {
    DeleteTeam($id);
  }
}

//add
if (!empty($_POST['add'])) {
  $tp['name'] = !empty($_POST['name0']) ? $_POST['name0'] : "no name";
  $tp['club'] = !empty($_POST['club0']) ? $_POST['club0'] : "";
  $tp['rank'] = !empty($_POST["seed0"]) ? $_POST["seed0"] : "0";
  if (!empty($tp['club'])) {
    $clubId = ClubId($tp['club']);
    if ($clubId == -1) {
      $clubId = AddClub($series_id, $tp['club']);
    }
    $tp['club'] = $clubId;
  }
  $tp['country'] = !empty($_POST['country0']) ? $_POST['country0'] : "";
  $tp['abbreviation'] = !empty($_POST['abbrev0']) ? $_POST['abbrev0'] : "";
  AddTeam($tp);
}

//set
if (!empty($_POST['save'])) {
  $teams = SeriesTeams($series_id, true);
  foreach ($teams as $team) {
    $team_id = $team['team_id'];
    $tp['team_id'] = $team_id;
    $tp['name'] = !empty($_POST["name$team_id"]) ? $_POST["name$team_id"] : "no name";
    $tp['club'] = !empty($_POST["club$team_id"]) ? $_POST["club$team_id"] : "";
    $tp['rank'] = !empty($_POST["seed$team_id"]) ? $_POST["seed$team_id"] : "0";
    if (!empty($tp['club'])) {
      $clubId = ClubId($tp['club']);
      if ($clubId == -1) {
        $clubId = AddClub($series_id, $tp['club']);
      }
      $tp['club'] = $clubId;
    }
    $tp['country'] = !empty($_POST["country$team_id"]) ? $_POST["country$team_id"] : "";
    $tp['abbreviation'] = !empty($_POST["abbrev$team_id"]) ? $_POST["abbrev$team_id"] : "";
    SetTeam($tp);
  }
}

if (!empty($_POST['copy'])) {
  SeriesCopyTeams($series_id, $_POST["copyteams"]);
}

$series_info = SeriesInfo($series_id);
$teams = SeriesTeams($series_id, true);

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
  function setId(id) {
    var input = document.getElementById("hiddenDeleteId");
    input.value = id;
  }
</script>
<?php
$setFocus = "onload=\"document.getElementById('name0').focus();\"";
pageTopHeadClose($title, false, $setFocus);
leftMenu($LAYOUT_ID);
contentStart();

foreach ($series as $row) {
  $menutabs[U_($row['name'])] = "?view=admin/seasonteams&season=" . $season . "&series=" . $row['series_id'];
}
$menutabs[_("...")] = "?view=admin/seasonseries&season=" . $season;
pageMenu($menutabs, "?view=admin/seasonteams&season=" . $season . "&series=" . $series_id);

$html .= "<form method='post' action='?view=admin/seasonteams&amp;season=$season&amp;series=" . $series_id . "'>";

$row = SeriesInfo($series_id);

$html .= "<table class='admintable'>\n";

$html .= "<tr><th class='center' title='" . _("Seed") . "'>#</th>";
$html .= "<th>" . _("Name") . "</th>";
$html .= "<th>" . _("Abbrev") . "</th>";

if (!intval($seasonInfo['isnationalteams'])) {
  $html .= "<th>" . _("Club") . "</th>";
}
if (intval($seasonInfo['isinternational'])) {
  $html .= "<th>" . _("Country") . "</th>";
}
$html .= "<th>" . _("Contact person") . "</th>";
$html .= "<th>" . _("Roster") . "</th>";
$html .= "<th></th></tr>\n";

$total = 0;

foreach ($teams as $team) {
  $team_id = $team['team_id'];
  $total++;

  $teaminfo = TeamFullInfo($team['team_id']);
  $poolname = U_(PoolName($team['team_id']));
  if (!empty($team['name'])) {
    if (intval($seasonInfo['isnationalteams'])) {
      $teamname = utf8entities(U_($team['name']));
    } else {
      $teamname = utf8entities($team['name']);
    }
  } else {
    $teamname = _("No name");
  }
  $html .= "<tr class='admintablerow'>";
  $html .= "<td><input class='input' size='3' maxlength='4' name='seed$team_id' value='" . utf8entities($team['rank']) . "'/></td>";
  $html .= "<td><input class='input' size='20' maxlength='50' name='name$team_id' value='" . utf8entities($team['name']) . "'/></td>";
  $html .= "<td><input class='input' size='4' maxlength='15' name='abbrev$team_id' value='" . utf8entities($team['abbreviation']) . "'/></td>";

  if (!intval($seasonInfo['isnationalteams'])) {
    $html .= "<td><input class='input' size='25' maxlength='50' name='club$team_id' value='" . utf8entities($team['clubname']) . "'/></td>";
  }

  if (intval($seasonInfo['isinternational'])) {
    if (!intval($seasonInfo['isnationalteams'])) {
      $html .= "<td>" . CountryDropListWithValues("country$team_id", "country$team_id", $teaminfo['country'], "80px") . "</td>";
    } else {
      $html .= "<td>" . CountryDropListWithValues("country$team_id", "country$team_id", $teaminfo['country'], "") . "</td>";
    }
  }
  $html .= "<td>";

  $admins = getTeamAdmins($team['team_id']);

  for ($i = 0; $i < count($admins); $i++) {
    $user = $admins[$i];
    $html .= "<a href='?view=user/userinfo&amp;user=" . $user['userid'] . "'>" . utf8entities($user['name']) . "</a>";
    if ($i + 1 < count($admins))
      $html .= "<br/>";
  }

  $html .= "&nbsp;<a href='?view=admin/addteamadmins&amp;series=" . $row['series_id'] . "'>" . _("...") . "</a>";
  $html .= "</td>";

  $html .= "<td class='center'><a href='?view=user/teamplayers&amp;team=" . $team['team_id'] . "'>" . _("Roster") . "</a></td>";

  $html .= "<td>";
  $html .= "<a href='?view=admin/addseasonteams&amp;team=$team_id'><img class='deletebutton' src='images/settings.png' alt='D' title='" . _("edit details") . "'/></a>";
  if (CanDeleteTeam($team['team_id'])) {
    $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $team['team_id'] . ");\"/>";
  }
  $html .= "</td>";
  $html .= "</tr>\n";
}

$total++;
$html .=  "<tr>";
$html .= "<td style='padding-top:15px'><input class='input' size='2' maxlength='4' name='seed0' value='$total'/></td>";
$html .= "<td style='padding-top:15px'><input class='input' size='20' maxlength='50' name='name0' id='name0' value=''/></td>";
$html .= "<td style='padding-top:15px'><input class='input' size='4' maxlength='15' name='abbrev0' value=''/></td>";
if (!intval($seasonInfo['isnationalteams'])) {
  $html .= "<td style='padding-top:15px'><input class='input' size='25' maxlength='50' name='club0' value=''/></td>";
}
if (intval($seasonInfo['isinternational'])) {
  if (!intval($seasonInfo['isnationalteams'])) {
    $html .= "<td style='padding-top:15px'>" . CountryDropListWithValues("country0", "country0", "", "80px") . "</td>";
  } else {
    $html .= "<td style='padding-top:15px'>" . CountryDropListWithValues("country0", "country0", "", "") . "</td>";
  }
}


$html .=  "<td style='padding-top:15px'><input style='margin-left:15px' id='add' class='button' name='add' type='submit' value='" . _("Add") . "'/></td>";
$html .=  "</tr>\n";

$html .= "</table>\n";
$html .=  "<p>";
$html .=  "<input id='save' class='button' name='save' type='submit' value='" . _("Save") . "'/> ";
$html .=  "<input id='cancel' class='button' name='cancel' type='submit' value='" . _("Cancel") . "'/>";
$html .=  "</p>";

$seasons = SeasonsArray();

if (count($seasons)) {
  $html .= "<p>" . _("Add teams from:") . " ";
  $html .= "<select class='dropdown' name='copyteams'>\n";
  foreach ($seasons as $season) {
    $divisions = SeasonSeries($season['season_id']);
    foreach ($divisions as $division) {
      if ($division['type'] != $series_info['type']) {
        continue;
      }

      $html .= "<option class='dropdown' value='" . utf8entities($division['series_id']) . "'>" . utf8entities($season['name'] . " " . $division['name']) . "</option>";
    }
  }
  $html .= "</select>\n";
  $html .= "<input id='copy' class='button' name='copy' type='submit' value='" . _("Copy") . "'/>";
  $html .= "</p>\n";
}

$html .= "<hr/>\n";
$html .= "<p>";
$html .= "<a href='?view=admin/addteamadmins&amp;series=" . $row['series_id'] . "'>" . _("Add Team Admins") . "</a> | ";
$html .= "<a href='?view=user/pdfscoresheet&amp;series=" . $row['series_id'] . "'>" . _("Print team rosters") . "</a></p>";

//stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";

$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
?>