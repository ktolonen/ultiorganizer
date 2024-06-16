<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/timetable.functions.php';

$title = _("Standings") . " ";
$seriesScoreboard = false;
$print = 0;
$html = "";
$comment = "";

if (iget("season")) {
  $seasoninfo = SeasonInfo(iget("season"));
  $pools = SeasonPools($seasoninfo['season_id'], true, true);
  $title .= U_($seasoninfo['name']);
  $comment = CommentHTML(1, $seasoninfo['season_id']);
  $seriesScoreboard = true;
} else if (iget("series")) {
  $seriesinfo = SeriesInfo(iget("series"));
  $pools = SeriesPools($seriesinfo['series_id'], true);
  $title .= U_($seriesinfo['name']);
  $comment = CommentHTML(2, $seriesinfo['series_id']);
  $seriesScoreboard = true;
  $seasoninfo = SeasonInfo($seriesinfo['season']);
} else if (iget("pool")) {

  $poolinfo = PoolInfo(iget("pool"));
  $games = PoolGames($poolinfo['pool_id']);


  //if pool has only one game, show game's schoresheet if exist
  if (count($games) == 1 && $poolinfo['type'] == 1) {
    $game = $games[0];
    header("location:?view=gameplay&game=" . $game['game_id']);
    exit();
  }
  $pools[] = array(
    "pool_id" => $poolinfo['pool_id'],
    "name" => $poolinfo['name']
  );


  $seasoninfo = SeasonInfo($poolinfo['season']);
  $title .= utf8entities(U_($poolinfo['seriesname']) . ", " . U_($poolinfo['name']));
}
if (iget("print")) {
  $print = intval(iget("print"));
  $format = "paper";
}

$html .= $comment;

$prevseries = 0;
foreach ($pools as $pool) {

  $poolinfo = PoolInfo($pool['pool_id']);

  if ($prevseries && $prevseries != $poolinfo['series']) {
    $html .= scoreboard($prevseries, true);
    if (ShowDefenseStats()) {
      $html .= defenseboard($prevseries, true);
    }
  }
  $prevseries = $poolinfo['series'];
  $seriesName = U_($poolinfo['seriesname']) . ", " . U_($poolinfo['name']);

  $html .= "<h2>" . utf8entities($seriesName) . "</h2>";

  $html .= CommentHTML(3, $pool['pool_id']);

  if ($poolinfo['type'] == 1) {
    // round robin
    $html .= printRoundRobinPool($seasoninfo, $poolinfo);
  } elseif ($poolinfo['type'] == 2) {
    // playoff
    $html .= printPlayoffTree($seasoninfo, $poolinfo);
  } elseif ($poolinfo['type'] == 3) {
    // Swissdraw
    $html .= printSwissdraw($seasoninfo, $poolinfo);
  } elseif ($poolinfo['type'] == 4) {
    // Cross matches
    $html .= printCrossmatchPool($seasoninfo, $poolinfo);
  }

  if (!$seriesScoreboard && !$print) {
    $html .= scoreboard($pool['pool_id'], false);
    if (ShowDefenseStats()) {
      $html .= defenseboard($pool['pool_id'], false);
    }
  }
}
if ($seriesScoreboard && !$print) {
  $html .= scoreboard($prevseries, true);
}

$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace("/&Print=[0-1]/", "", $querystring);
if ($print) {
  $html .= "<hr/><div style='text-align:right'><a href='?" . utf8entities($querystring) . "'>" . _("Return") . "</a></div>";
} else {
  $html .= "<hr/>";
  $html .= "<div style='text-align:right'><a href='?" . utf8entities($querystring) . "&amp;print=1'>" . _("Printable version") . "</a></div>";
}

if ($print) {
  showPrintablePage($title, $html);
} else {
  showPage($title, $html);
}

function scoreboard($id, $seriesScoreboard)
{

  $ret = "";

  if ($seriesScoreboard) {
    $ret .= "<h2>" . _("Scoreboard leaders") . "</h2>\n";
    $ret .= "<table cellspacing='0' border='0' width='100%'>\n";
    $ret .= "<tr><th style='width:200px'>" . _("Player") . "</th><th style='width:200px'>" . _("Team") . "</th><th class='center'>" . _("Games") . "</th>
		<th class='center'>" . _("Assists") . "</th><th class='center'>" . _("Goals") . "</th><th class='center'>" . _("Tot.") . "</th></tr>\n";

    $scores = SeriesScoreBoard($id, "total", 10);
    while ($row = mysqli_fetch_assoc($scores)) {
      $ret .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
      $ret .= "<td>" . utf8entities($row['teamname']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['games']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['fedin']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['done']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['total']) . "</td></tr>\n";
    }

    $ret .= "</table>";
    $ret .= "<a href='?view=scorestatus&amp;series=" . $id . "'>" . _("Scoreboard") . "</a>";
  } else {
    $ret .= "<h2>" . _("Scoreboard leaders") . "</h2>\n";
    $ret .= "<table cellspacing='0' border='0' width='100%'>\n";
    $ret .= "<tr><th style='width:200px'>" . _("Player") . "</th><th style='width:200px'>" . _("Team") . "</th><th class='center'>" . _("Games") . "</th>
		<th class='center'>" . _("Assists") . "</th><th class='center'>" . _("Goals") . "</th><th class='center'>" . _("Tot.") . "</th></tr>\n";

    $poolinfo = PoolInfo($id);
    $pools = array();
    if ($poolinfo['type'] == 2) {
      //find out sub pools
      $pools[] = $id;
      $followers = PoolFollowersArray($poolinfo['pool_id']);
      $pools = array_merge($pools, $followers);
      $scores = PoolsScoreBoard($pools, "total", 5);
    } else {
      $scores = PoolScoreBoard($id, "total", 5);
    }

    while ($row = mysqli_fetch_assoc($scores)) {
      $ret .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
      $ret .= "<td>" . utf8entities($row['teamname']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['games']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['fedin']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['done']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['total']) . "</td></tr>\n";
    }

    $ret .= "</table>";
    if ($poolinfo['type'] == 2) {
      $ret .= "<a href='?view=scorestatus&amp;pools=" . implode(",", $pools) . "'>" . _("Scoreboard") . "</a>";
    } else {
      $ret .= "<a href='?view=scorestatus&amp;pool=" . $id . "'>" . _("Scoreboard") . "</a>";
    }
  }
  return $ret;
}


function defenseboard($id, $seriesDefenseboard)
{

  $ret = "";

  if ($seriesDefenseboard) {
    $ret .= "<h2>" . _("Defenseboard leaders") . "</h2>\n";
    $ret .= "<table cellspacing='0' border='0' width='100%'>\n";
    $ret .= "<tr><th style='width:200px'>" . _("Player") . "</th><th style='width:200px'>" . _("Team") . "</th><th class='center'>" . _("Games") . "</th>
		<th class='center'>" . _("Total defenses") . "</th></tr>\n";
    $poolinfo = PoolInfo($id);
    $defenses = SeriesDefenseBoard($poolinfo['series_id'], "deftotal", 10);
    while ($row = mysqli_fetch_assoc($defenses)) {
      $ret .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
      $ret .= "<td>" . utf8entities($row['teamname']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['games']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['deftotal']) . "</td></tr>\n";
    }

    $ret .= "</table>";
    $ret .= "<a href='?view=defensestatus&amp;series=" . $poolinfo['series_id'] . "'>" . _("Defenseboard") . "</a>";
  } else {
    $ret .= "<h2>" . _("Defenseboard leaders") . "</h2>\n";
    $ret .= "<table cellspacing='0' border='0' width='100%'>\n";
    $ret .= "<tr><th style='width:200px'>" . _("Player") . "</th><th style='width:200px'>" . _("Team") . "</th><th class='center'>" . _("Games") . "</th>
		<th class='center'>" . _("Total defenses") . "</th></tr>\n";

    $poolinfo = PoolInfo($id);
    $pools = array();
    if ($poolinfo['type'] == 2) {
      //find out sub pools
      $pools[] = $id;
      $followers = PoolFollowersArray($poolinfo['pool_id']);
      $pools = array_merge($pools, $followers);
      $scores = PoolsScoreBoardWithDefenses($pools, "deftotal", 5);
    } else {
      $scores = PoolScoreBoardWithDefenses($id, "deftotal", 5);
    }

    while ($row = mysqli_fetch_assoc($scores)) {
      $ret .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
      $ret .= "<td>" . utf8entities($row['teamname']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['games']) . "</td>";
      $ret .= "<td class='center'>" . intval($row['deftotal']) . "</td></tr>\n";
    }

    $ret .= "</table>";
    if ($poolinfo['type'] == 2) {
      $ret .= "<a href='?view=defensestatus&amp;pools=" . implode(",", $pools) . "'>" . _("Defenseboard") . "</a>";
    } else {
      $ret .= "<a href='?view=defensestatus&amp;pool=" . $id . "'>" . _("Defenseboard") . "</a>";
    }
  }
  return $ret;
}


function printSwissdraw($seasoninfo, $poolinfo)
{
  // prints Swiss draw standing

  $ret = "";
  $style = "";

  if ($poolinfo['played']) {
    $style = "class='playedpool'";
  }
  $ret .= "<table $style border='2' width='100%'>\n";
  $ret .= "<tr><th>#</th><th style='width:200px'>" . _("Team") . "</th>";
  $ret .= "<th class='center'>" . _("Games") . "</th>";
  $ret .= "<th class='center'>" . _("Victory Points") . "</th>";
  $ret .= "<th class='center'>" . _("Opponent VPs") . "</th>";
  $ret .= "<th class='center'>" . _("Margin") . "</th>";
  $ret .= "<th class='center'>" . _("Goals") . "</th>";
  $ret .= "</tr>\n";

  $standings = PoolTeams($poolinfo['pool_id'], "rank");

  if (count($standings)) {
    foreach ($standings as $row) {
      //			$stats = TeamStatsByPool($poolinfo['pool_id'], $row['team_id']);
      $vp = TeamVictoryPointsByPool($poolinfo['pool_id'], $row['team_id']);
      //			$points=TeamPointsByPool($poolinfo['pool_id'], $row['team_id']);
      $flag = "";
      if (intval($seasoninfo['isinternational'])) {
        $flag = "<img height='10' src='images/flags/tiny/" . $row['flagfile'] . "' alt=''/> ";
      }
      $ret .= "<tr><td>" . $row['activerank'] . "</td>";
      $ret .= "<td>&nbsp;$flag<a href='?view=teamcard&amp;team=" . $row['team_id'] . "'>" . utf8entities(U_($row['name'])) . "</a></td>";
      $ret .= "<td class='center'>" . intval($vp['games']) . "</td>";
      $ret .= "<td class='center'>" . intval($vp['victorypoints']) . "</td>";
      $ret .= "<td class='center'>" . intval($vp['oppvp']) . "</td>";
      $ret .= "<td class='center'>" . intval($vp['margin']) . "</td>";
      $ret .= "<td class='center'>" . intval($vp['score']) . "</td>";
      $ret .= "</tr>\n";
    }
  } else {
    $teams = PoolSchedulingTeams($poolinfo['pool_id']);
    foreach ($teams as $row) {
      $ret .= "<tr><td>-</td>";
      $ret .= "<td>" . utf8entities(U_($row['name'])) . "</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "</tr>\n";
    }
  }
  $ret .= "</table>\n";

  $ret .= "<table width='100%'>\n";
  if ($poolinfo['mvgames'] == 0 || $poolinfo['mvgames'] == 2) {
    $mvgames = PoolMovedGames($poolinfo['pool_id']);
    foreach ($mvgames as $game) {
      $ret .= GameRow($game, false, false, false, false, false, true);
    }
  }
  $games = TimetableGames($poolinfo['pool_id'], "pool", "all", "series");
  while ($game = mysqli_fetch_assoc($games)) {
    //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
    $ret .= GameRow($game, false, false, false, false, false, true);
  }
  $ret .= "</table>\n";

  $ret .= "<p><a href='?view=games&amp;pool=" . $poolinfo['pool_id'] . ".&amp;singleview=1'>" . _("Schedule") . "</a><br/></p>";
  return $ret;
}


function printRoundRobinPool($seasoninfo, $poolinfo)
{

  $ret = "";
  $style = "";

  if ($poolinfo['played']) {
    $style = "style='font-weight: bold;'";
  }
  $ret .= "<table $style border='2' width='100%'>\n";
  $ret .= "<tr><th>#</th><th style='width:200px'>" . _("Team") . "</th>";
  $ret .= "<th class='center'>" . _("Games") . "</th>";
  $ret .= "<th class='center'>" . _("Wins") . "</th>";
  if ($poolinfo['drawsallowed'])
    $ret .= "<th class='center'>" . _("Draws") . "</th>";
  $ret .= "<th class='center'>" . _("Losses") . "</th>";
  $ret .= "<th class='center'>" . _("Goals for") . "</th>";
  $ret .= "<th class='center'>" . _("against") . "</th>";
  $ret .= "<th class='center'>" . _("diff.") . "</th>";
  $ret .= "</tr>\n";

  $standings = PoolTeams($poolinfo['pool_id'], "rank");
  $teams = PoolSchedulingTeams($poolinfo['pool_id']);
  $continuationpools = array();
  $gamesplayed = PoolTotalPlayedGames($poolinfo['pool_id']);

  if (!$poolinfo['continuingpool'] || count($standings) >= count($teams)) {
    $i = 1;
    foreach ($standings as $row) {
      $stats = TeamStatsByPool($poolinfo['pool_id'], $row['team_id']);
      $points = TeamPointsByPool($poolinfo['pool_id'], $row['team_id']);
      $movetopool = PoolGetMoveToPool($poolinfo['pool_id'], $i);
      $flag = "";
      if (intval($seasoninfo['isinternational'])) {
        $flag = "<img height='10' src='images/flags/tiny/" . $row['flagfile'] . "' alt=''/> ";
      }
      $colorcoding = "";
      if ($movetopool) {
        //$iebackground="filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#FFFFFF,endColorstr=#".$movetopool['color'].");";
        $colorcoding = "background-color:#" . $movetopool['color'] . ";background-color:" . RGBtoRGBa($movetopool['color'], 0.3) . ";color:#" . textColor($movetopool['color']);
        $ret .= "<tr>";
        $continuationpools[] = $movetopool;
      } else {
        $ret .= "<tr>";
      }
      if ($gamesplayed > 0) {
        $ret .= "<td><div style='$colorcoding'>" . $row['activerank'] . "</div></td>";
      } else {
        $ret .= "<td><div style='$colorcoding'>-</div></td>";
      }
      $ret .= "<td><div>&nbsp;$flag<a href='?view=teamcard&amp;team=" . $row['team_id'] . "'>" . utf8entities($row['name']) . "</a></div></td>";
      $ret .= "<td class='center'><div>" . intval($stats['games']) . "</div></td>";
      $ret .= "<td class='center'><div>" . intval($stats['wins']) . "</div></td>";
      if ($poolinfo['drawsallowed'])
        $ret .= "<td class='center'><div>" . intval($stats['draws']) . "</div></td>";
      $ret .= "<td class='center'><div>" . intval($stats['losses']) . "</div></td>";
      // $ret .= "<td class='center'><div>".(intval($stats['games'])-intval($stats['wins']))."</div></td>";
      $ret .= "<td class='center'><div>" . intval($points['scores']) . "</div></td>";
      $ret .= "<td class='center'><div>" . intval($points['against']) . "</div></td>";
      $ret .= "<td class='center'><div>" . (intval($points['scores']) - intval($points['against'])) . "</div></td>";
      $ret .= "</tr>\n";
      $i++;
    }
  } else {

    $i = 1;
    foreach ($teams as $row) {
      $realteam = PoolTeamFromStandings($poolinfo['pool_id'], $i);
      $movetopool = PoolGetMoveToPool($poolinfo['pool_id'], $i);
      $colorcoding = "";
      if ($movetopool) {
        //$iebackground="background:transparent;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr=#FFFFFF,endColorstr=#".$movetopool['color'].");zoom: 1;";
        $colorcoding = "background-color:#" . $movetopool['color'] . ";background-color:" . RGBtoRGBa($movetopool['color'], 0.3) . ";color:#" . textColor($movetopool['color']);
        $ret .= "<tr>";
        $continuationpools[] = $movetopool;
      } else {
        $ret .= "<tr>";
      }
      $ret .= "<td style='$colorcoding'>-</td>";
      if ($realteam) {
        $flag = "";
        if (intval($seasoninfo['isinternational'])) {
          $flag = "<img height='10' src='images/flags/tiny/" . $realteam['flagfile'] . "' alt=''/> ";
        }
        $ret .= "<td><div>&nbsp;$flag<a href='?view=teamcard&amp;team=" . $realteam['team_id'] . "'>" . utf8entities($realteam['name']) . "</a></div></td>";
      } else {
        $ret .= "<td>" . utf8entities(U_($row['name'])) . "</td>";
      }

      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "<td class='center'>-</td>";
      $ret .= "</tr>\n";
      $i++;
    }
  }
  $ret .= "</table>\n";

  if (count($continuationpools)) {
    $ret .= "<table width='100%'><tr>\n";
    $prev = "";
    $width = 100 / count($continuationpools);
    foreach ($continuationpools as $cpool) {
      if ($cpool['topool'] != $prev) {
        $ret .= "<td style='background-color:#" . $cpool['color'] . ";background-color:" . RGBtoRGBa($cpool['color'], 0.3) . ";color:#" . textColor($cpool['color']) . ";width:" . $width . "%'>";
        if ($cpool['visible']) {
          $ret .= "<a href='?view=poolstatus&amp;pool=" . $cpool['topool'] . "'>" . utf8entities(U_($cpool['name'])) . "</a>";
        } else {
          $ret .= utf8entities(U_($cpool['name']));
        }
        $ret .= "</td>";
        $prev = $cpool['topool'];
      }
    }
    $ret .= "</tr></table>\n";
  }
  $ret .= "<table width='100%'>\n";
  if ($poolinfo['mvgames'] == 0 || $poolinfo['mvgames'] == 2) {
    $mvgames = PoolMovedGames($poolinfo['pool_id']);
    foreach ($mvgames as $game) {
      $ret .= GameRow($game, false, false, false, false, false, true);
    }
  }
  $games = TimetableGames($poolinfo['pool_id'], "pool", "all", "series");
  while ($game = mysqli_fetch_assoc($games)) {
    //function GameRow($game, $date=false, $time=true, $field=true, $series=false,$pool=false,$info=true)
    $ret .= GameRow($game, false, false, false, false, false, true);
  }
  $ret .= "</table>\n";

  $ret .= "<p><a href='?view=games&amp;pool=" . $poolinfo['pool_id'] . "&amp;singleview=1'>" . _("Schedule") . "</a><br/></p>";
  return $ret;
}

function printPlayoffTree($seasoninfo, $poolinfo)
{

  $ret = "";
  $pools = array();
  $pools[] = $poolinfo['pool_id'];

  //find out total rounds played
  $followers = PoolPlayoffFollowersArray($poolinfo['pool_id']);

  if (count($followers) == 0) {
    $followers = PoolFollowersArray($poolinfo['pool_id']);
  }
  $pools = array_merge($pools, $followers);
  $rounds = count($pools);

  //find out total teams in pool
  $teams = PoolTeams($poolinfo['pool_id']);
  $steams = PoolSchedulingTeams($poolinfo['pool_id']);
  if (count($teams) < count($steams)) {
    $teams = $steams;
    $totalteams = count($steams);
  } else {
    $totalteams = count($teams);
  }

  global $include_prefix;

  $template = PlayoffTemplate($totalteams, $rounds, $poolinfo['playoff_template']);
  if (empty($template)) {
    $ret .= "<p>" . _("No playoff tree template found.") . "</p>\n";
  }
  $notemplate = "";

  $round = 0;
  foreach ($pools as $poolId) {
    $pool = PoolInfo($poolId);

    //find out round name
    switch (count($pools) - $round) {
      case 1:
        $roundname = U_("Finals");
        break;
      case 2:
        $roundname = U_("Semifinals");
        break;
      case 3:
        $roundname = U_("Quarterfinals");
        break;
      default:
        $roundname = U_("Round") . " " . ($round + 1);
        break;
    }

    if (empty($template)) {
      $notemplate .= "<h4>" . _("Round") . " " . ($round + 1) . "</h4>\n";

      if (empty($pool)) {
        $notemplate .= "<p>???</p>";
      } else {
        $notemplate .= "<table width='100%'>\n";
        $games = TimetableGames($pool['pool_id'], "pool", "all", "series");
        while ($game = mysqli_fetch_assoc($games)) {
          $notemplate .= GameRow($game, false, false, false, false, false, true);
        }
        $notemplate .= "</table>\n";
      }
    } else {
      $template = str_replace("[round " . ($round + 1) . "]", $roundname, $template);
    }

    $winners = 0;
    $losers = 0;
    $games = 0;
    for ($i = 1; $i <= $totalteams; $i++) {

      if (!isset($pool['pool_id'])) {
        continue;
      }
      $team = PoolTeamFromInitialRank($pool['pool_id'], $i);
      $movefrom = PoolGetMoveFrom($pool['pool_id'], $i);

      $name = "";
      $byeName = "";
      $previousRoundByeName = "";
      //find out team name
      if (isset($team['team_id'])) {
        if (intval($seasoninfo['isinternational']) && !empty($team['flagfile'])) {
          $name .= "<img height='10' src='images/flags/tiny/" . $team['flagfile'] . "' alt=''/> ";
        }
        $name .= "<a href='?view=teamcard&amp;team=" . $team['team_id'] . "'>" . utf8entities($team['name']) . "</a>";
      } else {
        $realteam = PoolTeamFromStandings($movefrom['frompool'], $movefrom['fromplacing']);
        $gamesleft = array();
        if (isset($realteam['team_id'])) {
          $gamesleft = TeamPoolGamesLeft($realteam['team_id'], $movefrom['frompool']);
        }
        $frompoolinfo = PoolInfo($movefrom['frompool']);
        $isodd = is_odd($totalteams) && $i == $totalteams;
        if (isset($realteam['team_id']) && $frompoolinfo['played'] && count($gamesleft) == 0 && !$isodd) {
          if (intval($seasoninfo['isinternational']) && !empty($realteam['flagfile'])) {
            $name .= "<img height='10' src='images/flags/tiny/" . $realteam['flagfile'] . "' alt=''/> ";
          }
          $name .= "<i>" . utf8entities($realteam['name']) . "</i>";
        } else {
          $sname = SchedulingNameByMoveTo($pool['pool_id'], $i);
          $name .= utf8entities(U_($sname['name']));
        }
      }


      if (isset($team['team_id'])) {
        $gamesinpool = TeamPoolGames($team['team_id'], $pool['pool_id']);
        if (mysqli_num_rows($gamesinpool) == 0) { // that's the BYE team
          $byeName = $name; // save its name
          //$ret .= $round." ".$name." ".$pool['pool_id']." ".$team['team_id']."<br>";
        }
      }
      //update team name to template
      $theName = $name;
      if ($round == 0) {
        $template = str_replace("[team $i]", $name, $template);
      } else {
        if ($movefrom['fromplacing'] == $totalteams && $totalteams % 2 == 1) { // Assuming the BYE team is always last in a pool
          $winners = ceil($movefrom['fromplacing'] / 2);

          $template = str_replace("[winner $round/$winners]", $previousRoundByeName, $template);
          $theName = $previousRoundByeName;
        } elseif ($movefrom['fromplacing'] % 2 == 1) {
          $winners = ceil($movefrom['fromplacing'] / 2);
          $template = str_replace("[winner $round/$winners]", $name, $template);
        } else {
          $losers = ceil($movefrom['fromplacing'] / 2);
          $template = str_replace("[loser $round/$losers]", $name, $template);
        }
      }
      //update game results
      if ($i % 2 == 1) {
        $games++;
        $game = "";
        if (isset($team['team_id'])) {
          $results = GameHomeTeamResults($team['team_id'], $pool['pool_id']);
          $reverse = false;
          if (!$results) {
            $results = GameVisitorTeamResults($team['team_id'], $pool['pool_id']);
            $reverse = true;
          }
          foreach ($results as $res) {
            if ($reverse) {
              $dummy = $res['homescore'];
              $res['homescore'] = $res['visitorscore'];
              $res['visitorscore'] = $dummy;
            }
            if ($res['scoresheet'] && !$res['isongoing']) {
              $game .= "<a href='?view=gameplay&amp;game=" . $res['game_id'] . "'>";
              $game .= $res['homescore'] . "-" . $res['visitorscore'] . "</a> ";
            } elseif (GameHasStarted($res) > 0 && !$res['isongoing']) {
              $game .= $res['homescore'] . "-" . $res['visitorscore'];
            } elseif (!empty($res['gamename'])) {
              $game .= "<span class='lowlight'>" . utf8entities(U_($res['gamename'])) . "</span>";
            }
          }
        }
        if (empty($game) && isset($sname['scheduling_id'])) {
          $results = GameHomePseudoTeamResults($sname['scheduling_id'], $pool['pool_id']);
          foreach ($results as $res) {
            if (!empty($res['gamename'])) {
              $game .= "<span class='lowlight'>" . utf8entities(U_($res['gamename'])) . "</span>";
            }
          }
        }

        if (empty($game)) {
          //$game = "&nbsp;";
          $game .= "<span class='lowlight'>" . _("Game") . " " . $games . "</span>";
        }
        $template = str_replace("[game " . ($round + 1) . "/$games]", $game, $template);
      }
    }
    if ($totalteams % 2 == 1) {
      $previousRoundByeName = $byeName; // save previous pool Bye name
    }
    $round++;
  }

  //placements
  $notemplate .= "<h4>" . _("Placement") . "</h4>\n";
  $notemplate .= "<table width='100%'>\n";

  $template = str_replace("[placement]", _("Placement"), $template);
  for ($i = 1; $i <= $totalteams; $i++) {
    $placementname = "";
    if (!isset($pool['pool_id'])) {
      continue;
    }
    if (empty($pool))
      $gamesleft = -1;
    else {
      $team = PoolTeamFromStandings($pool['pool_id'], $i);
      if(!empty($team)){
        $gamesleft = count(TeamPoolGamesLeft($team['team_id'], $pool['pool_id']));
      }
    }
    $teampart = "";
    $unknown = "";

    if (!PoolMoveExist($pool['pool_id'], $i)) {
      $placement = PoolPlacementString($pool['pool_id'], $i);
      $placementname = "<b>" . U_($placement) . "</b> ";
      if ($gamesleft == 0) {
        if (intval($seasoninfo['isinternational']) && !empty($team['flagfile'])) {
          $teampart .= "<img height='10' src='images/flags/tiny/" . $team['flagfile'] . "' alt=''/> ";
        }
        $teampart .= utf8entities($team['name']);
      } else {
        $unknown = "<i>" . _("???") . "</i>";
      }
    } else {
      $movetopool = PoolGetMoveToPool($pool['pool_id'], $i);
      $placementname .= "<a href='?view=poolstatus&amp;pool=" . $movetopool['topool'] . "'>&raquo; " . utf8entities(U_($movetopool['name'])) . "</a>&nbsp; ";

      if ($gamesleft == 0) {
        if (intval($seasoninfo['isinternational']) && !empty($team['flagfile'])) {
          $teampart .= "<img height='10' src='images/flags/tiny/" . $team['flagfile'] . "' alt=''/> ";
        }

        $teampart .= utf8entities($team['name']);
      } else {
        $unknown = "<i>" . _("???") . "</i>";
      }
    }

    if (empty($template)) {
      $notemplate .= "<tr><td>" . $placementname . "</td><td>" . $teampart . $unknown . "</td></tr>\n";
    } else {
      $template = str_replace("[placement $i]", $placementname . $teampart, $template);
    }
  }
  if (empty($template)) {
    $notemplate .= "</table>\n";
    $ret .= $notemplate;
  } else {
    $ret .= $template;
  }
  $ret .= "<p><a href='?view=games&amp;pools=" . implode(",", $pools) . "&amp;singleview=1'>" . _("Schedule") . "</a><br/></p>";
  return $ret;
}

function printCrossmatchPool($seasoninfo, $poolinfo)
{

  $ret = "";
  $style = "";

  if ($poolinfo['played']) {
    $style = "style='font-weight: bold;'";
  }

  $ret .= "<table $style width='100%'>\n";


  $games = TimetableGames($poolinfo['pool_id'], "pool", "all", "crossmatch");
  $i = 0;
  $pos = 1;
  $winnerpools = array();
  $loserpools = array();

  while ($game = mysqli_fetch_assoc($games)) {
    $i++;
    $winnerspool = PoolGetMoveToPool($poolinfo['pool_id'], $pos);
    $winnerpoolstyle = "background-color:#" . $winnerspool['color'] . ";background-color:" . RGBtoRGBa($winnerspool['color'], 0.3) . ";color:#" . textColor($winnerspool['color']);
    $winnerpools[$winnerspool['topool']] = $winnerspool['color'];

    $loserspool = PoolGetMoveToPool($poolinfo['pool_id'], $pos + 1);
    $loserpoolstyle = "background-color:#" . $loserspool['color'] . ";background-color:" . RGBtoRGBa($loserspool['color'], 0.3) . ";color:#" . textColor($loserspool['color']);
    $loserspools[$loserspool['topool']] = $loserspool['color'];

    $ret .= "<tr>";
    $ret .= "<td class='center' style='" . $winnerpoolstyle . "'></td>";
    $ret .= "<td class='center' style='" . $loserpoolstyle . "'></td>";
    $ret .= "<td style='width:10%'>" . _("Game") . " $i " . "</td>";
    $ret .= "<td></td>";

    // $goals = intval($game['homescore'])+intval($game['visitorscore']);

    if (GameHasStarted($game) && !intval($game['isongoing']) && $game['hometeam'] && $game['visitorteam']) {
      if (intval($game['homescore']) > intval($game['visitorscore'])) {
        $ret .= "<td style='" . $winnerpoolstyle . "'><a href='?view=teamcard&amp;team=" . $game['hometeam'] . "'>" . utf8entities($game['hometeamname']) . "</a></td>\n";
        $ret .= "<td class='center'>-</td>\n";
        $ret .= "<td style='" . $loserpoolstyle . "'><a href='?view=teamcard&amp;team=" . $game['visitorteam'] . "'>" . utf8entities($game['visitorteamname']) . "</a></td>\n";
      } elseif (intval($game['homescore']) < intval($game['visitorscore'])) {
        $ret .= "<td style='" . $loserpoolstyle . "'><a href='?view=teamcard&amp;team=" . $game['hometeam'] . "'>" . utf8entities($game['hometeamname']) . "</a></td>\n";
        $ret .= "<td class='center'>-</td>\n";
        $ret .= "<td style='" . $winnerpoolstyle . "'><a href='?view=teamcard&amp;team=" . $game['visitorteam'] . "'>" . utf8entities($game['visitorteamname']) . "</a></td>\n";
      } else {
        $ret .= "<td style='" . $loserpoolstyle . "'><a href='?view=teamcard&amp;team=" . $game['hometeam'] . "'>" . utf8entities($game['hometeamname']) . "</a></td>\n";
        $ret .= "<td class='center'>-</td>\n";
        $ret .= "<td style='" . $loserpoolstyle . "'><a href='?view=teamcard&amp;team=" . $game['visitorteam'] . "'>" . utf8entities($game['visitorteamname']) . "</a></td>\n";
      }
    } else {
      if ($game['hometeam']) {
        $ret .= "<td><a href='?view=teamcard&amp;team=" . $game['hometeam'] . "'>" . utf8entities($game['hometeamname']) . "</a></td>\n";
      } else {
        $ret .= "<td>" . utf8entities($game['phometeamname']) . "</td>\n";
      }
      $ret .= "<td class='center'>-</td>\n";
      if ($game['visitorteam']) {
        $ret .= "<td><a href='?view=teamcard&amp;team=" . $game['visitorteam'] . "'>" . utf8entities($game['visitorteamname']) . "</a></td>\n";
      } else {
        $ret .= "<td>" . utf8entities($game['pvisitorteamname']) . "</td>\n";
      }
    }

    if (!GameHasStarted($game)) {
      $ret .= "<td>?</td>\n";
      $ret .= "<td>-</td>\n";
      $ret .= "<td>?</td>\n";
    } else {
      if ($game['isongoing'])
        $ret .= "<td><em>" . intval($game['homescore']) . "</em></td><td>-</td><td><em>" . intval($game['visitorscore']) . "</em></td>\n";
      else
        $ret .= "<td>" . intval($game['homescore']) . "</td><td>-</td><td>" . intval($game['visitorscore']) . "</td>\n";
    }

    if (!intval($game['isongoing'])) {
      if (intval($game['scoresheet'])) {
        $ret .= "<td class='right'>&nbsp;<a href='?view=gameplay&amp;game=" . $game['game_id'] . "'>";
        $ret .= _("Game play") . "</a></td>\n";
      } else {
        $ret .= "<td class='left'></td>\n";
      }
    } else {
      if (intval($game['scoresheet'])) {
        $ret .= "<td class='right'>&nbsp;&nbsp;<a href='?view=gameplay&amp;game=" . $game['game_id'] . "'>";
        $ret .= _("Ongoing") . "</a></td>\n";
      } else {
        $ret .= "<td class='right'>&nbsp;&nbsp;" . _("Ongoing") . "</td>\n";
      }
    }
    $ret .= "</tr>\n";
    $pos += 2;
  }
  $ret .= "</table>\n";

  $ret .= "<table style='white-space: nowrap' cellpadding='2' width='100%'><tr>\n";

  $ret .= "<td>" . _("Winners continues in:") . "</td>";
  foreach ($winnerpools as $winnerId => $color) {
    $ret .= "<td style='background-color:#" . $color . ";background-color:" . RGBtoRGBa($color, 0.3) . ";color:#" . textColor($color) . ";width:" . (50 / count($winnerpools)) . "%'>";
    if ($winnerspool['visible']) {
      $ret .= "<a href='?view=poolstatus&amp;pool=" . $winnerId . "'>" . utf8entities(U_(PoolName($winnerId))) . "</a>";
    } else {
      $ret .= utf8entities(U_(PoolName($winnerId)));
    }
    $ret .= "</td>";
  }

  $ret .= "<td>" . _("Losers continues in:") . "</td>";
  foreach ($loserspools as $loserId => $color) {
    $ret .= "<td style='background-color:#" . $color . ";background-color:" . RGBtoRGBa($color, 0.3) . ";color:#" . textColor($color) . ";width:" . (50 / count($loserspools)) . "%'>";
    if ($loserspool['visible']) {
      $ret .= "<a href='?view=poolstatus&amp;pool=" . $loserId . "'>" . utf8entities(PoolName($loserId)) . "</a>";
    } else {
      $ret .= utf8entities(PoolName($loserId));
    }
    $ret .= "</td>";
  }
  $ret .= "</tr></table>\n";

  $ret .= "<p><a href='?view=games&amp;pool=" . $poolinfo['pool_id'] . "&amp;singleview=1'>" . _("Schedule") . "</a><br/></p>";

  return $ret;
}
