<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/game.functions.php';

// function to search for missing spirit scores in played games (search by pool)
// returns an array of game id's
function SearchMissingSpiritByPool($poolId)
{
  return SpiritMissingGamesByPool($poolId);
}

// function to search for missing spirit scores in played games (search by division)
// returns an array of game id's
function SearchMissingSpiritBySeries($seriesId)
{
  return SpiritMissingGamesBySeries($seriesId);
}

// function to search for spirit scores of '$catval' in at least one item
// returns html code for table of matches
function TableSpiritSearchCat($season, $catval)
{
  $ret = "";
  $rows = SpiritToolRowsBySeason($season);
  $matches = array();
  foreach ($rows as $row) {
    if (
      (int)$row['cat1'] === (int)$catval || (int)$row['cat2'] === (int)$catval ||
      (int)$row['cat3'] === (int)$catval || (int)$row['cat4'] === (int)$catval ||
      (int)$row['cat5'] === (int)$catval
    ) {
      $matches[] = $row;
    }
  }

  if (!empty($matches)) {
    $ret .= "<p>" . _("List of teams that received a '") . $catval . _("' in at least one category:") . "</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>" . _("Division") . "</th>";
    $ret .= "<th class='center'>" . _("Pool") . "</th>";
    $ret .= "<th class='center'>" . _("Scores") . "</th>";
    $ret .= "<th class='center'>" . _("Total") . "</th>";
    $ret .= "<th>" . _("Given for") . "</th>";
    $ret .= "<th>" . _("Given by") . "</th>";
    $ret .= "<th class='center'>" . _("Link") . "</th>";
    $ret .= "</tr>";

    foreach ($matches as $row) {
      $ret .= "<tr>";
      $ret .= "<td>" . $row['division'] . "</td>";
      $ret .= "<td>" . $row['pool'] . "</td>";
      $scores = intval($row['cat1']) . " " . intval($row['cat2']) . " " . intval($row['cat3']) . " " . intval($row['cat4']) . " " . intval($row['cat5']);
      $ret .= "<td class='center'>" . $scores . "</td>";
      $ret .= "<td class='center'>" . $row['total'] . "</td>";
      $ret .= "<td>" . $row['givenfor'] . "</td>";
      $ret .= "<td>" . $row['givenby'] . "</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=" . $row['game_id'] . "'>" . _("Edit Spirit") . "</a></td>";
      $ret .= "</tr>";
    }

    $ret .= "</table>";
  } else {
    $ret = "<p>" . _("Nothing found.") . "</p>";
  }

  return $ret;
}

// function to search for multiple spirit scores of '$catval' in category number "$catnum"
// returns html code for table of matches
function TableSpiritSearchCatReps($season, $catnum, $catval)
{
  $ret = "";
  $rows = SpiritToolRowsBySeason($season);
  $catField = "cat" . (int)$catnum;
  $teamCounts = array();
  foreach ($rows as $row) {
    if (isset($row[$catField]) && (int)$row[$catField] === (int)$catval) {
      if (!isset($teamCounts[$row['team_id']])) {
        $teamCounts[$row['team_id']] = 0;
      }
      $teamCounts[$row['team_id']]++;
    }
  }

  $matches = array();
  foreach ($rows as $row) {
    if (
      isset($teamCounts[$row['team_id']]) && $teamCounts[$row['team_id']] > 1 &&
      isset($row[$catField]) && (int)$row[$catField] === (int)$catval
    ) {
      $matches[] = $row;
    }
  }

  if (!empty($matches)) {
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>" . _("Division") . "</th>";
    $ret .= "<th class='center'>" . _("Pool") . "</th>";
    $ret .= "<th class='center'>" . _("Scores") . "</th>";
    $ret .= "<th class='center'>" . _("Total") . "</th>";
    $ret .= "<th>" . _("Given for") . "</th>";
    $ret .= "<th>" . _("Given by") . "</th>";
    $ret .= "<th class='center'>" . _("Link") . "</th>";
    $ret .= "</tr>";

    foreach ($matches as $row) {
      $ret .= "<tr>";
      $ret .= "<td>" . $row['division'] . "</td>";
      $ret .= "<td>" . $row['pool'] . "</td>";
      $scores = intval($row['cat1']) . " " . intval($row['cat2']) . " " . intval($row['cat3']) . " " . intval($row['cat4']) . " " . intval($row['cat5']);
      $ret .= "<td class='center'>" . $scores . "</td>";
      $ret .= "<td class='center'>" . $row['total'] . "</td>";
      $ret .= "<td>" . $row['givenfor'] . "</td>";
      $ret .= "<td>" . $row['givenby'] . "</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=" . $row['game_id'] . "'>" . _("Edit Spirit") . "</a></td>";
      $ret .= "</tr>";
    }

    $ret .= "</table>";
  } else {
    $ret = "<p>" . _("Nothing found.") . "</p>";
  }

  return $ret;
}

// function to search for spirit scores (total) higher or lower than a threshold
// returns html code for table of matches
function TableSpiritSearchTotal($season, $th, $higher = true)
{
  $ret = "";

  $op = $higher ? '>' : '<';
  $rows = SpiritToolRowsBySeason($season);
  $matches = array();
  foreach ($rows as $row) {
    $total = (int)$row['total'];
    if (($higher && $total > (int)$th) || (!$higher && $total < (int)$th)) {
      $matches[] = $row;
    }
  }

  if (!empty($matches)) {
    $ret .= "<p>" . _("List of teams that received a score '") . $op . $th . "'</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th class='center'>" . _("Division") . "</th>";
    $ret .= "<th class='center'>" . _("Pool") . "</th>";
    $ret .= "<th class='center'>" . _("Scores") . "</th>";
    $ret .= "<th class='center'>" . _("Total") . "</th>";
    $ret .= "<th>" . _("Given for") . "</th>";
    $ret .= "<th>" . _("Given by") . "</th>";
    $ret .= "<th class='center'>" . _("Link") . "</th>";
    $ret .= "</tr>";

    foreach ($matches as $row) {
      $ret .= "<tr>";
      $ret .= "<td>" . $row['division'] . "</td>";
      $ret .= "<td>" . $row['pool'] . "</td>";
      $scores = intval($row['cat1']) . " " . intval($row['cat2']) . " " . intval($row['cat3']) . " " . intval($row['cat4']) . " " . intval($row['cat5']);
      $ret .= "<td class='center'>" . $scores . "</td>";
      $ret .= "<td class='center'>" . $row['total'] . "</td>";
      $ret .= "<td>" . $row['givenfor'] . "</td>";
      $ret .= "<td>" . $row['givenby'] . "</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=" . $row['game_id'] . "'>" . _("Edit Spirit") . "</a></td>";
      $ret .= "</tr>";
    }

    $ret .= "</table>";
  } else {
    $ret = "<p>" . _("Nothing found for '") . $op . $th . "'.</p>";
  }

  return $ret;
}

// function to search for spirit scores (average) higher or lower than a threshold in any category
// returns html code for table of matches
function TableSpiritSearchCatAvg($season, $th, $higher = true)
{
  $ret = "";

  $op = $higher ? '>' : '<';
  $rows = SpiritToolRowsBySeason($season);
  $teams = array();
  foreach ($rows as $row) {
    $teamId = (int)$row['team_id'];
    if (!isset($teams[$teamId])) {
      $teams[$teamId] = array(
        'seriesname' => $row['division'],
        'teamname' => $row['givenfor'],
        'sum1' => 0,
        'sum2' => 0,
        'sum3' => 0,
        'sum4' => 0,
        'sum5' => 0,
        'games' => 0
      );
    }
    $teams[$teamId]['sum1'] += (int)$row['cat1'];
    $teams[$teamId]['sum2'] += (int)$row['cat2'];
    $teams[$teamId]['sum3'] += (int)$row['cat3'];
    $teams[$teamId]['sum4'] += (int)$row['cat4'];
    $teams[$teamId]['sum5'] += (int)$row['cat5'];
    $teams[$teamId]['games']++;
  }

  $matches = array();
  foreach ($teams as $team) {
    if ($team['games'] <= 0) {
      continue;
    }
    $team['cat1'] = round($team['sum1'] / $team['games'], 2);
    $team['cat2'] = round($team['sum2'] / $team['games'], 2);
    $team['cat3'] = round($team['sum3'] / $team['games'], 2);
    $team['cat4'] = round($team['sum4'] / $team['games'], 2);
    $team['cat5'] = round($team['sum5'] / $team['games'], 2);
    $team['total'] = round(($team['sum1'] + $team['sum2'] + $team['sum3'] + $team['sum4'] + $team['sum5']) / $team['games'], 2);
    $matchesThreshold = ($higher && ($team['cat1'] > $th || $team['cat2'] > $th || $team['cat3'] > $th || $team['cat4'] > $th || $team['cat5'] > $th))
      || (!$higher && ($team['cat1'] < $th || $team['cat2'] < $th || $team['cat3'] < $th || $team['cat4'] < $th || $team['cat5'] < $th));
    if ($matchesThreshold) {
      $matches[] = $team;
    }
  }

  if (!empty($matches)) {
    $ret .= "<p>" . _("List of teams that received an average '") . $op . $th . _("' in at least one category:") . "</p>";
    $ret .= "<table width='100%'><tr>";
    $ret .= "<th>" . _("Division") . "</th>";
    $ret .= "<th>" . _("Team") . "</th>";
    $ret .= "<th class='center'>" . _("Rules") . "</th>";
    $ret .= "<th class='center'>" . _("Fouls") . "</th>";
    $ret .= "<th class='center'>" . _("Fair") . "</th>";
    $ret .= "<th class='center'>" . _("Attitude") . "</th>";
    $ret .= "<th class='center'>" . _("Comm") . "</th>";
    $ret .= "<th class='center'>" . _("Total") . "</th>";
    $ret .= "</tr>";

    foreach ($matches as $row) {
      $ret .= "<tr>";
      $ret .= "<td>" . $row['seriesname'] . "</td>";
      $ret .= "<td>" . $row['teamname'] . "</td>";
      $ret .= "<td class='center'>" . $row['cat1'] . "</td>";
      $ret .= "<td class='center'>" . $row['cat2'] . "</td>";
      $ret .= "<td class='center'>" . $row['cat3'] . "</td>";
      $ret .= "<td class='center'>" . $row['cat4'] . "</td>";
      $ret .= "<td class='center'>" . $row['cat5'] . "</td>";
      $ret .= "<td class='center'>" . $row['total'] . "</td>";
      $ret .= "</tr>";
    }

    $ret .= "</table>";
  } else {
    $ret = "<p>" . _("Nothing found for '") . $op . $th . "'.</p>";
  }

  return $ret;
}

function TableSpiritSearchComments($season)
{
  $ret = "";
  $rows = SpiritToolRowsBySeason($season);
  $matches = array();
  foreach ($rows as $row) {
    if (strlen(trim((string)$row['comments'])) > 0) {
      $matches[] = $row;
    }
  }
  usort($matches, function ($a, $b) {
    return strcmp((string)$b['time'], (string)$a['time']);
  });

  if (!empty($matches)) {
    $ret .= "<p>" . _("List of teams that received a comment in a game") . "</p>";
    $ret .= "<table width='100%' border=1><tr>";
    $ret .= "<th class='center'>" . _("Division") . "</th>";
    $ret .= "<th class='center'>" . _("Pool") . "</th>";
    $ret .= "<th class='center'>" . _("Scores") . "</th>";
    $ret .= "<th class='center'>" . _("Total") . "</th>";
    $ret .= "<th class='center'>" . _("Comment") . "</th>";
    $ret .= "<th>" . _("Given for") . "</th>";
    $ret .= "<th>" . _("Given by") . "</th>";
    $ret .= "<th class='center'>" . _("Link") . "</th>";
    $ret .= "</tr>";

    foreach ($matches as $row) {
      $ret .= "<tr>";
      $ret .= "<td>" . $row['division'] . "</td>";
      $ret .= "<td>" . $row['pool'] . "</td>";
      $scores = intval($row['cat1']) . " " . intval($row['cat2']) . " " . intval($row['cat3']) . " " . intval($row['cat4']) . " " . intval($row['cat5']);
      $ret .= "<td class='center' style='white-space: nowrap;'>" . $scores . "</td>";
      $ret .= "<td class='center'>" . $row['total'] . "</td>";
      $ret .= "<td class='center'>" . $row['comments'] . "</td>";
      $ret .= "<td>" . $row['givenfor'] . "</td>";
      $ret .= "<td>" . $row['givenby'] . "</td>";
      $ret .= "<td><a href='?view=user/addspirit&amp;game=" . $row['game_id'] . "'>" . _("Edit Spirit") . "</a></td>";
      $ret .= "</tr>";
    }

    $ret .= "</table>";
  } else {
    $ret = "<p>" . _("Nothing found.") . "</p>";
  }

  return $ret;
}

function TableSOTGURLs($season)
{
  $query = sprintf(
    "SELECT s.name AS series, t.name AS team, t.sotg_token AS token FROM uo_team AS t
    JOIN uo_series AS s on t.series=s.series_id
    WHERE s.season='%s'
    ORDER BY s.name, t.name",
    $season
  );
  $tokens = DBQuery($query);

  $baseURL = rtrim(BASEURL, "/");

  $ret = "<table class='tdtools-table'>";
  $ret .= "<tr>";
  $ret .= "<th class='center'>Division</th>";
  $ret .= "<th class='center'>Team</th>";
  $ret .= "<th class='center'>SOTG URL</th>";
  $ret .= "<tr>";

  foreach ($tokens as $token) {
    $fullURL = empty($token['token']) ? "" : $baseURL . "/sotg/?token=" . $token['token'];
    $ret .= "<tr>";
    $ret .= "<td>" . $token['series'] . "</td>";
    $ret .= "<td>" . $token['team'] . "</td>";
    $ret .= "<td><a href='" . $fullURL . "'>" . $fullURL . "</a></td>";
    $ret .= "</tr>";
  }

  $ret .= "</table>";

  return $ret;
}

function GenerateSOTGTokens($season, $filter = "onlymissing")
{
  if ($filter == "onlymissing") {
    $query = sprintf(
      "UPDATE uo_team AS t
      JOIN uo_series AS s on t.series=s.series_id
      SET t.sotg_token=MD5(t.team_id+RAND())
      WHERE s.season='%s' AND t.sotg_token IS NULL",
      $season
    );
    DBQuery($query);

    return "<p>Total number of new tokens generated: " . (int)DBAffectedRows() . "</p>";
  }

  return "<p>Invalid filter.</p>";
}

$season = GetString("season");

$title = _("Spirit");
$html = "";

$html .= "<h1>" . $title . "</h1>\n";

if (!empty($season) && hasSpiritToolsRight($season)) {
  $seasonInfo = SeasonInfo($season);
  if (empty($seasonInfo['spiritmode'])) {
    $html .= "<p>" . _("Spirit scoring is disabled for this event.") . "</p>";
    $html .= "<p><a href='?view=admin/addseasons&amp;season=" . urlencode($season) . "'>&raquo; " . _("Open event settings") . "</a></p>";
    showPage($title, $html);
    return;
  }

  $html .= "<div class='tdtools-box bg-td1'>";
  $html .= "<h2>" . _("Settings") . "</h2>";
  $html .= "<p>" . _("Manage event-level spirit visibility, comments, submission locking, and scoring mode in a dedicated settings view.") . "</p>";
  $html .= "<p><a href='?view=admin/spiritsettings&amp;season=" . urlencode($season) . "'>&raquo; " . _("Open Spirit Settings") . "</a></p>";
  $html .= "</div>";

  if (isset($_POST['game'])) {
    $gameId = intval($_POST['game']);
    if (!$gameId || !GamePool($gameId)) {
      $html .= "<p class='warning'>" . _("Invalid game number.") . "</p>";
    } else {
      header("Location: ?view=user/addspirit&game=$gameId");
    }
  }

  $html .= "<hr />";

  $html .= "<h2>" . _("Direct Link") . "</h2>";
  $html .= "<div class='tdtools-box bg-td1'>";
  $html .= "<p>" . _("Enter the game ref. # on the paper spirit sheet for a direct link: ") . "</p>";
  $html .= "<p><form method='POST' action='?view=admin/spirit&amp;season=$season'>";
  $html .= "<input class='input' type='text' size='10' maxlength='10' name='game'/>";
  $html .= "<button class='button' type='submit'>" . _("Edit Spirit") . "</button> ";
  $html .= "</form></p>";
  $html .= "</div>";

  $html .= "<hr/><h2>" . _("SOTG Tools") . "</h2>";

  $html .= "<div class='tdtools-box bg-td2'>";
  $html .= "<p><strong>" . _("URLs for teams (for entering SOTG scores online)") . "</strong></p>";
  $html .= "<p><form method='POST' action='?view=admin/spirit&amp;season=$season'>";
  $html .= "<button class='button' type='submit' name='getsotgurls' value='all'>" . _("Show URLs for all teams") . "</button> ";
  $html .= "<button class='button' type='submit' name='generatesotgtokens' value='onlymissing'>" . _("Generate Tokens") . " (" . _("only missing") . ")" . "</button> ";
  $html .= "</form></p>";

  if (isset($_POST['getsotgurls'])) {
    $html .= TableSOTGURLs($season);
  }

  if (isset($_POST['generatesotgtokens'])) {
    $html .= GenerateSOTGTokens($season, $_POST['generatesotgtokens']);
  }

  $html .= "</div>";

  $html .= "<div class='tdtools-box bg-td2'>";
  $html .= "<p>" . _("To search for played games that are <b>missing SOTG scores</b>, press one of these buttons: ") . "</p>";
  $html .= "<p><form method='POST' action='?view=admin/spirit&amp;season=$season'>";
  $html .= "<button class='button' type='submit' name='missingsotgpool' value='search'>" . _("Search Missing by Pool") . "</button> ";
  $html .= "<button class='button' type='submit' name='missingsotgdiv' value='search'>" . _("Search Missing by Division") . "</button> ";
  $html .= "</form></p>";

  if (isset($_POST['missingsotgpool'])) {
    $allpools = SeasonPools($season);
    $nonemissing = true;
    foreach ($allpools as $pool) {
      $games = SearchMissingSpiritByPool($pool['pool_id']);
      if (!empty($games)) {
        $nonemissing = false;
        $html .= "<p>" . _("Pool") . ": <b>" . $pool['poolname'] . "</b> (" . $pool['seriesname'] . ")</p><table width='100%'>";
        foreach ($games as $game) {
          $visitorStyle = empty($game['homesotg']) ? 'color: red; font-weight: bold;' : '';
          $homeStyle = empty($game['visitorsotg']) ? 'color: red; font-weight: bold;' : '';
          $html .= "<tr><td class='right' style='width: 10%; $homeStyle'>" . $game['home'] . "</td><td class='center' style='width: 2%'>" . $game['homescore'] . "</td><td class='center' style='width: 1%'>-</td><td class='center' style='width: 2%'>" . $game['visitorscore'] . "</td><td style='width: 10%; $visitorStyle'>" . $game['visitor'] . "</td><td class='center' style='width: 20%'>" . $game['time'] . "</td><td style='width: 10%'><a href='?view=user/addspirit&amp;game=" . $game['game_id'] . "'>" . _("Edit Spirit") . "</a></td></tr>";
        }
        $html .= "</table>";
      }
    }
    if ($nonemissing) {
      $html .= "<p>" . _("No played games found missing SOTG scores!") . "</p>";
    }
  }

  if (isset($_POST['missingsotgdiv'])) {
    $allseries = SeasonSeries($season);
    $nonemissing = true;
    foreach ($allseries as $series) {
      $games = SearchMissingSpiritBySeries(intval($series['series_id']));
      if (!empty($games)) {
        $nonemissing = false;
        $html .= "<p>" . _("Division") . ": <b>" . $series['name'] . "</b></p><table width='100%'>";
        foreach ($games as $game) {
          $visitorStyle = empty($game['homesotg']) ? 'color: red; font-weight: bold;' : '';
          $homeStyle = empty($game['visitorsotg']) ? 'color: red; font-weight: bold;' : '';
          $html .= "<tr><td style='width: 10%'>" . $game['poolname'] . "</td><td class='right' style='width: 10%; $homeStyle'>" . $game['home'] . "</td><td class='center' style='width: 2%'>" . $game['homescore'] . "</td><td class='center' style='width: 1%'>-</td><td class='center' style='width: 2%'>" . $game['visitorscore'] . "</td><td style='width: 10%; $visitorStyle'>" . $game['visitor'] . "</td><td class='center' style='width: 20%'>" . $game['time'] . "</td><td style='width: 10%'><a href='?view=user/addspirit&amp;game=" . $game['game_id'] . "'>" . _("Edit Spirit") . "</a></td></tr>";
        }
        $html .= "</table>";
      }
    }
    if ($nonemissing) {
      $html .= "<p>" . _("No played games found missing SOTG scores!") . "</p>";
    }
  }

  $html .= "</div>";

  $html .= "<div class='tdtools-box bg-td2'>";
  $html .= "<p>" . _("To search for teams that <b>received a '0' or '4'</b> in at least one category, use these buttons: ") . "</p>";
  $html .= "<p><form method='POST' action='?view=admin/spirit&amp;season=$season'>";
  $html .= "<button class='button' type='submit' name='sotgzeros' value='search'>" . _("Search 0's") . "</button> ";
  $html .= "<button class='button' type='submit' name='sotgfours' value='search'>" . _("Search 4's") . "</button> ";
  $html .= "</form></p>";

  if (isset($_POST['sotgzeros'])) {
    $html .= TableSpiritSearchCat($season, 0);
  }

  if (isset($_POST['sotgfours'])) {
    $html .= TableSpiritSearchCat($season, 4);
  }

  $html .= "</div>";

  $html .= "<div class='tdtools-box bg-td2'>";
  $html .= "<form method='POST' action='?view=admin/spirit&amp;season=$season'>";
  $html .= "<p>" . _("Search for teams that received a") . "&nbsp;";
  $html .= "<input type='radio' name='sotgrepsval' value='0'>0&nbsp;</button>";
  $html .= "<input type='radio' name='sotgrepsval' value='1' checked>1&nbsp;</button>";
  $html .= "<input type='radio' name='sotgrepsval' value='2'>2&nbsp;</button>";
  $html .= "<input type='radio' name='sotgrepsval' value='3'>3&nbsp;</button>";
  $html .= "<input type='radio' name='sotgrepsval' value='4'>4&nbsp;</button>";
  $html .= "</p><p>" . _("<b>more than once</b>, in this category") . ":&nbsp;";
  $html .= "<button class='button' type='submit' name='sotgreps' value='1'>" . _("Rules") . "</button> ";
  $html .= "<button class='button' type='submit' name='sotgreps' value='2'>" . _("Fouls") . "</button> ";
  $html .= "<button class='button' type='submit' name='sotgreps' value='3'>" . _("Fair") . "</button> ";
  $html .= "<button class='button' type='submit' name='sotgreps' value='4'>" . _("Attitude") . "</button> ";
  $html .= "<button class='button' type='submit' name='sotgreps' value='5'>" . _("Comm") . "</button> ";
  $html .= "</p></form>";

  if (isset($_POST['sotgreps'])) {
    $html .= "<p>" . _("Showing results for ");
    switch (intval($_POST['sotgreps'])) {
      case 1:
        $html .= _("Rules");
        break;
      case 2:
        $html .= _("Fouls");
        break;
      case 3:
        $html .= _("Fair");
        break;
      case 4:
        $html .= _("Attitude");
        break;
      case 5:
        $html .= _("Comm");
        break;
    }
    $html .= " = " . intval($_POST['sotgrepsval']) . "</p>";
    $html .= TableSpiritSearchCatReps($season, intval($_POST['sotgreps']), intval($_POST['sotgrepsval']));
  }

  $html .= "</div>";

  $html .= "<div class='tdtools-box bg-td2'>";
  $html .= "<p>" . _("To search for teams that received high/low <b>total scores</b> enter a threshold and press the appropriate button:") . "</p>";
  $html .= "<p><form method='POST' action='?view=admin/spirit&amp;season=$season'>";
  $html .= "<button class='button' type='submit' name='sotgop' value='lower'>" . _("Lower than") . "</button> ";
  $html .= "<input class='input' type='text' size='10' maxlength='10' name='sotgth'/>";
  $html .= "<button class='button' type='submit' name='sotgop' value='higher'>" . _("Higher than") . "</button> ";
  $html .= "</form></p>";

  if (isset($_POST['sotgth'])) {
    $sotgth = intval($_POST['sotgth']);
    $sotgop = $_POST['sotgop'];
    if (!$sotgth || ($sotgth < 1) || ($sotgth > 20)) {
      $html .= "<p class='warning'>" . _("Invalid threshold! Please use a number between 1 and 20.") . "</p>";
    } else {
      switch ($sotgop) {
        case 'higher':
          $html .= TableSpiritSearchTotal($season, $sotgth, true);
          break;
        case 'lower':
          $html .= TableSpiritSearchTotal($season, $sotgth, false);
          break;
      }
    }
  }
  $html .= "</div>";

  $html .= "<div class='tdtools-box bg-td2'>";
  $html .= "<p>" . _("To search for teams that have a low/high <b>average score</b> in any category enter a threshold and press the appropriate button:") . "</p>";
  $html .= "<p><form method='POST' action='?view=admin/spirit&amp;season=$season'>";
  $html .= "<button class='button' type='submit' name='sotgAvgOp' value='lower'>" . _("Lower than") . "</button> ";
  $html .= "<input class='input' type='text' size='10' maxlength='10' name='sotgAvgTh'/>";
  $html .= "<button class='button' type='submit' name='sotgAvgOp' value='higher'>" . _("Higher than") . "</button> ";
  $html .= "</form></p>";

  if (isset($_POST['sotgAvgTh'])) {
    $sotgAvgTh = floatval(strtr($_POST['sotgAvgTh'], ',', '.'));
    $sotgAvgOp = $_POST['sotgAvgOp'];
    if (!$sotgAvgTh || ($sotgAvgTh < 0.1) || ($sotgAvgTh > 3.9)) {
      $html .= "<p class='warning'>" . _("Invalid threshold! Please use a number between 0.1 and 3.9.") . "</p>";
    } else {
      switch ($sotgAvgOp) {
        case 'higher':
          $html .= TableSpiritSearchCatAvg($season, $sotgAvgTh, true);
          break;
        case 'lower':
          $html .= TableSpiritSearchCatAvg($season, $sotgAvgTh, false);
          break;
      }
    }
  }
  $html .= "</div>";

  $html .= "<div class='tdtools-box bg-td2' id='sotgComments'>";
  $html .= "<p>" . _("Press the button to search for spirit comments") . "</p>";
  $html .= "<p><form method='POST' action='?view=admin/spirit&amp;season=$season#sotgComments'>";
  $html .= "<button class='button' type='submit' name='sotgComments' value='sotgComments'>" . _("Show Comments") . "</button> ";
  $html .= "</form></p>";

  if (isset($_POST['sotgComments'])) {
    $html .= TableSpiritSearchComments($season);
  }
  $html .= "</div>";
} else {
  $html .= "<p>" . _("Insufficient user rights") . "</p>";
}

showPage($title, $html);
?>
