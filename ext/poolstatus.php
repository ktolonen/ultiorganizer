<?php
include_once 'localization.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="-1" />
  <?php

  $style = iget("style");
  if (empty($style))
    $style = 'pelikone.css';

  echo "<link rel='stylesheet' href='$style' type='text/css' />";
  echo "<title>" . _("Ultiorganizer") . "</title>";
  ?>
</head>

<body>
  <?php
  include_once '../lib/season.functions.php';
  include_once '../lib/series.functions.php';
  include_once '../lib/team.functions.php';
  include_once '../lib/game.functions.php';

  $poolId = intval(iget("pool"));
  $poolinfo = PoolInfo($poolId);

  $season = iget("season");
  $seasoninfo = SeasonInfo($season);

  if ($poolinfo['type'] == 1) {
    echo "<table class='pk_table'>\n";
    echo "<tr><th class='pk_ser_th'>#</th><th class='pk_ser_th'>" . _("Team") . "</th>";
    echo "<th class='pk_ser_th'>" . _("Games") . "</th>";
    echo "<th class='pk_ser_th'>" . _("Wins") . "</th>";
    echo "<th class='pk_ser_th'>" . _("Losses") . "</th>";
    echo "<th class='pk_ser_th'>" . _("Goals for") . "</th>";
    echo "<th class='pk_ser_th'>" . _("Goals against") . "</th>";
    echo "<th class='pk_ser_th'>" . _("Goal diff") . "</th>";
    echo "</tr>\n";

    $standings = PoolTeams($poolinfo['pool_id'], "rank");

    foreach ($standings as $row) {
      $stats = TeamStatsByPool($poolinfo['pool_id'], $row['team_id']);
      $points = TeamPointsByPool($poolinfo['pool_id'], $row['team_id']);
      $flag = "";
      if (intval($seasoninfo['isinternational'])) {
        $flag = "<img height='10' src='../images/flags/tiny/" . $row['flagfile'] . "' alt=''/> ";
      }
      echo "<tr><td class='pk_ser_td2'>" . $row['activerank'] . "</td>";
      echo "<td class='pk_ser_td1'>&nbsp;$flag", utf8entities(U_($row['name'])), "</td>";
      echo "<td class='pk_ser_td2'>" . intval($stats['games']) . "</td>";
      echo "<td class='pk_ser_td2'>" . intval($stats['wins']) . "</td>";
      echo "<td class='pk_ser_td2'>", intval($stats['games']) - intval($stats['wins']), "</td>";
      echo "<td class='pk_ser_td2'>" . intval($points['scores']) . "</td>";
      echo "<td class='pk_ser_td2'>" . intval($points['against']) . "</td>";
      echo "<td class='pk_ser_td2'>", (intval($points['scores']) - intval($points['against'])), "</td>";
      echo "</tr>\n";
    }
    echo "</table>\n";
  } elseif ($poolinfo['type'] == 2) {
    $pools = array();
    $pools[] = $poolinfo['pool_id'];

    //find out total rounds played
    $followers = PoolFollowersArray($poolinfo['pool_id']);
    $pools = array_merge($pools, $followers);
    $rounds = count($pools);

    //find out total teams in pool
    $teams = PoolTeams($poolinfo['pool_id']);
    if (count($teams) == 0) {
      $teams = PoolSchedulingTeams($poolinfo['pool_id']);
    }
    $totalteams = count($teams);

    $html = PlayoffTemplate($totalteams, $rounds, $poolinfo['playoff_template']);
    if (empty($html)) {
      $html = "<p>" . _("No playoff tree template found.") . "</p>\n";
    }

    $round = 0;
    foreach ($pools as $poolId) {
      $pool = PoolInfo($poolId);
      $pseudoteams = false;
      $teams = PoolTeams($pool['pool_id'], "seed");
      if (count($teams) == 0) {
        $teams = PoolSchedulingTeams($pool['pool_id']);
        $pseudoteams = true;
      }

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
      $html = str_replace("[round " . ($round + 1) . "]", $roundname, $html);

      $winners = 0;
      $losers = 0;
      $games = 0;
      for ($i = 1; $i <= $totalteams; $i++) {
        $team = $teams[$i - 1];
        $name = "";
        if (intval($seasoninfo['isinternational']) && !empty($team['flagfile'])) {
          $name .= "<img height='10' src='../images/flags/tiny/" . $team['flagfile'] . "' alt=''/> ";
        }
        $name .= $team['name'];
        $movefrom = PoolGetMoveFrom($pool['pool_id'], $i);
        if ($pseudoteams && $round > 0) {
          $realteam = PoolTeamFromStandings($movefrom['frompool'], $movefrom['fromplacing']);
          if ($realteam['team_id']) {
            $gamesleft = TeamPoolGamesLeft($realteam['team_id'], $movefrom['frompool']);

            if (count($gamesleft) == 0) {
              $name = "";
              if (intval($seasoninfo['isinternational']) && !empty($realteam['flagfile'])) {
                $name .= "<img height='10' src='../images/flags/tiny/" . $realteam['flagfile'] . "' alt=''/> ";
              }
              $name .= "<i>" . utf8entities($realteam['name']) . "</i>";
            }
          }
        }

        //teams
        if ($round == 0) {
          $html = str_replace("[team $i]", $name, $html);
        } else {
          if ($movefrom['fromplacing'] % 2 == 1) {
            $winners++;
            $html = str_replace("[winner $round/$winners]", $name, $html);
          } else {
            $losers++;
            $html = str_replace("[loser $round/$losers]", $name, $html);
          }
        }

        //games printed after home team printed
        if ($i % 2 == 1) {
          $games++;
          $game = "&nbsp;";
          if (!$pseudoteams) {
            $results = GameHomeTeamResults($team['team_id'], $pool['pool_id']);
            foreach ($results as $res) {
              $game .= $res['homescore'] . "-" . $res['visitorscore'];
            }
          }
          $html = str_replace("[game " . ($round + 1) . "/$games]", $game, $html);
        }
      }
      $round++;
    }

    //placements
    $html = str_replace("[placement]", _("Placement"), $html);
    for ($i = 1; $i <= $totalteams; $i++) {
      $placement = PoolPlacementString($pool['pool_id'], $i);
      $team = PoolTeamFromStandings($pool['pool_id'], $i);
      $gamesleft = TeamPoolGamesLeft($team['team_id'], $pool['pool_id']);

      if (count($gamesleft) == 0) {
        $placementname = "";
        if (intval($seasoninfo['isinternational']) && !empty($team['flagfile'])) {
          $placementname .= "<img height='10' src='../images/flags/tiny/" . $team['flagfile'] . "' alt=''/> ";
        }
        $placementname .= "<b>" . U_($placement) . "</b> " . utf8entities($team['name']) . "";
      } else {
        $placementname = U_($placement);
      }
      $html = str_replace("[placement $i]", $placementname, $html);
    }
    $html = str_replace("<td", "<td class='pk_playoff_td1' ", $html);
    echo $html;
  }

  CloseConnection();
  ?>
</body>

</html>