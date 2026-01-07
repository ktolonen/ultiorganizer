<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

$LAYOUT_ID = SEASONTEAMS;
$html = "";
$addError = "";
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
  try {
    AddTeam($tp);
  } catch (mysqli_sql_exception $e) {
    $addError = "<p class='warning'>" . _("Unable to add team. Please check the team details and pool setup.") . "</p>";
  }
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
$teamarray = "";
$teamnames = array();
$teamlist = TeamNameListBySeriesType($series_info['type']);
if ($teamlist) {
  while ($row = mysqli_fetch_assoc($teamlist)) {
    $name = $row['name'];
    if (!isset($teamnames[$name])) {
      $teamnames[$name] = true;
      $teamarray .= "\"" . addslashes($name) . "\",";
    }
  }
}
$teamarray = trim($teamarray, ',');

$clubarray = "";
if (!intval($seasonInfo['isnationalteams'])) {
  $clublist = ClubList(true);
  foreach ($clublist as $row) {
    $clubarray .= "\"" . addslashes($row['name']) . "\",";
  }
  $clubarray = trim($clubarray, ',');
}
$colSeed = "20px";
$colName = "150px";
$colNameList = ((int)$colName * 1.5) . "px";
$colAbbrev = "40px";
$colClub = "160px";
$colClubList = ((int)$colClub * 1.5) . "px";
$colCountry = "90px";
$colContact = "100px";
$colRoster = "50px";
$colActions = "50px";

//common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));
?>
<script type="text/javascript">
  var teamNames = new Array(
    <?php
    echo $teamarray;
    ?>
  );
  <?php if (!intval($seasonInfo['isnationalteams'])) { ?>
  var clubNames = new Array(
    <?php
    echo $clubarray;
    ?>
  );
  <?php } ?>
</script>
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

if (!empty($addError)) {
  $html .= $addError;
}

$html .= "<form method='post' action='?view=admin/seasonteams&amp;season=$season&amp;series=" . $series_id . "'>";

$row = SeriesInfo($series_id);

$html .= "<table class='admintable'>\n";

$html .= "<tr><th class='center' style='width:$colSeed' title='" . _("Seed") . "'>#</th>";
$html .= "<th style='width:$colName'>" . _("Name") . "</th>";
$html .= "<th style='width:$colAbbrev'>" . _("Abbrev") . "</th>";

if (!intval($seasonInfo['isnationalteams'])) {
  $html .= "<th style='width:$colClub'>" . _("Club") . "</th>";
}
if (intval($seasonInfo['isinternational'])) {
  $html .= "<th style='width:$colCountry'>" . _("Country") . "</th>";
}
$html .= "<th style='width:$colContact'>" . _("Contact person") . "</th>";
$html .= "<th style='width:$colRoster'>" . _("Roster") . "</th>";
$html .= "<th style='width:$colActions'></th></tr>\n";

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
  $html .= "<td style='width:$colSeed'><input class='input'  maxlength='4' style='width:$colSeed' name='seed$team_id' value='" . utf8entities($team['rank']) . "'/></td>";
  $html .= "<td style='width:$colName'><input class='input'  maxlength='50' style='width:$colName' name='name$team_id' value='" . utf8entities($team['name']) . "'/></td>";
  $html .= "<td style='width:$colAbbrev'><input class='input' maxlength='15' style='width:$colAbbrev' name='abbrev$team_id' value='" . utf8entities($team['abbreviation']) . "'/></td>";

  if (!intval($seasonInfo['isnationalteams'])) {
    $html .= "<td style='width:$colClub'><input class='input' maxlength='50' style='width:$colClub' name='club$team_id' value='" . utf8entities($team['clubname']) . "'/></td>";
  }

  if (intval($seasonInfo['isinternational'])) {
    if (!intval($seasonInfo['isnationalteams'])) {
      $html .= "<td style='width:$colCountry'>" . CountryDropListWithValues("country$team_id", "country$team_id", $teaminfo['country'], "80px") . "</td>";
    } else {
      $html .= "<td style='width:$colCountry'>" . CountryDropListWithValues("country$team_id", "country$team_id", $teaminfo['country'], "") . "</td>";
    }
  }
  $html .= "<td style='width:$colContact'>";

  $admins = getTeamAdmins($team['team_id']);

  for ($i = 0; $i < count($admins); $i++) {
    $user = $admins[$i];
    $html .= "<a href='?view=user/userinfo&amp;user=" . $user['userid'] . "'>" . utf8entities($user['name']) . "</a>";
    if ($i + 1 < count($admins))
      $html .= "<br/>";
  }

  $html .= "&nbsp;<a href='?view=admin/addteamadmins&amp;series=" . $row['series_id'] . "'>" . _("...") . "</a>";
  $html .= "</td>";

  $html .= "<td class='center' style='width:$colRoster'><a href='?view=user/teamplayers&amp;team=" . $team['team_id'] . "'>" . _("Roster") . "</a></td>";

  $html .= "<td style='width:$colActions'>";
  $html .= "<a href='?view=admin/addseasonteams&amp;team=$team_id'><img class='deletebutton' src='images/settings.png' alt='D' title='" . _("edit details") . "'/></a>";
  if (CanDeleteTeam($team['team_id'])) {
    $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $team['team_id'] . ");\"/>";
  }
  $html .= "</td>";
  $html .= "</tr>\n";
}

$total++;
$html .=  "<tr>";
$html .= "<td style='padding-top:15px; width:$colSeed'><input class='input' maxlength='4' style='width:$colSeed' name='seed0' value='$total'/></td>";
$html .= "<td style='padding-top:0px; width:$colName'><div id='teamAutoComplete' class='yui-skin-sam' style='width:$colName'><input class='input' maxlength='50' style='width:$colName' name='name0' id='name0' value=''/><div id='teamContainer' style='width:$colNameList'></div></div></td>";
$html .= "<td style='padding-top:15px; width:$colAbbrev'><input class='input' maxlength='15' style='width:$colAbbrev' name='abbrev0' value=''/></td>";
if (!intval($seasonInfo['isnationalteams'])) {
  $html .= "<td style='padding-top:0px; width:$colClub'><div id='clubAutoComplete' class='yui-skin-sam' style='width:$colClub'><input class='input' maxlength='50' style='width:$colClub' name='club0' id='club0' value=''/><div id='clubContainer' style='width:$colClubList'></div></div></td>";
}
if (intval($seasonInfo['isinternational'])) {
  if (!intval($seasonInfo['isnationalteams'])) {
    $html .= "<td style='padding-top:15px; width:$colCountry'>" . CountryDropListWithValues("country0", "country0", "", "80px") . "</td>";
  } else {
    $html .= "<td style='padding-top:15px; width:$colCountry'>" . CountryDropListWithValues("country0", "country0", "", "") . "</td>";
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
$html .= "<a href='?view=user/pdfscoresheet&amp;series=" . $row['series_id'] . "' target='_blank' rel='noopener'>" . _("Print team rosters") . "</a></p>";

//stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";

$html .= "</form>\n";
$html .= "<script type='text/javascript'>\n";
$html .= "YAHOO.autocomplete = function() {\n";
$html .= "  var teamDS = new YAHOO.util.LocalDataSource(teamNames);\n";
$html .= "  var teamAC = new YAHOO.widget.AutoComplete(\"name0\", \"teamContainer\", teamDS);\n";
$html .= "  teamAC.prehighlightClassName = \"yui-ac-prehighlight\";\n";
$html .= "  teamAC.useShadow = true;\n";
if (!intval($seasonInfo['isnationalteams'])) {
  $html .= "  var clubDS = new YAHOO.util.LocalDataSource(clubNames);\n";
  $html .= "  var clubAC = new YAHOO.widget.AutoComplete(\"club0\", \"clubContainer\", clubDS);\n";
  $html .= "  clubAC.prehighlightClassName = \"yui-ac-prehighlight\";\n";
  $html .= "  clubAC.useShadow = true;\n";
}
$html .= "  return {teamDS: teamDS, teamAC: teamAC};\n";
$html .= "}();\n";
$html .= "</script>\n";

echo $html;
contentEnd();
pageEnd();
?>
