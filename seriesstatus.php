<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$LAYOUT_ID = SERIESTATUS;

$title = _("Statistics") . " ";
$viewUrl = "?view=seriesstatus";
$sort = "ranking";
$html = "";

if (!iget("series")) {
  $title = _("Statistics");
  $html .= "<h1>" . _("Series not found") . "</h1>";
  showPage($title, $html);
  return;
}

$seriesinfo = SeriesInfo(iget("series"));
if (!$seriesinfo) {
  $title = _("Statistics");
  $html .= "<h1>" . _("Series not found") . "</h1>";
  showPage($title, $html);
  return;
}
$viewUrl .= "&amp;series=" . $seriesinfo['series_id'];
$seasoninfo = SeasonInfo($seriesinfo['season']);
if (!$seasoninfo) {
  $title = _("Statistics");
  $html .= "<h1>" . _("Season not found") . "</h1>";
  showPage($title, $html);
  return;
}
$title .= U_($seriesinfo['name']);

if (iget("sort")) {
  $sort = iget("sort");
}

$teamstats = array();
$allteams = array();
$teams = SeriesTeams($seriesinfo['series_id']);
$teamStatsPoints = SeriesTeamStatsPoints($seriesinfo['series_id']);
$spiritAvg = SeriesSpiritBoard($seriesinfo['series_id']);
foreach ($teams as $team) {
  $statsRow = isset($teamStatsPoints[$team['team_id']]) ? $teamStatsPoints[$team['team_id']] : array(
    "games" => 0,
    "wins" => 0,
    "draws" => 0,
    "losses" => 0,
    "scores" => 0,
    "against" => 0
  );

  $teamstats['name'] = $team['name'];
  $teamstats['team_id'] = $team['team_id'];
  $teamstats['seed'] = $team['rank'];
  $teamstats['flagfile'] = $team['flagfile'];

  $teamstats['wins'] = intval($statsRow['wins']);
  $teamstats['games'] = intval($statsRow['games']);

  $teamstats['for'] = intval($statsRow['scores']);
  $teamstats['against'] = intval($statsRow['against']);

  $teamstats['losses'] = $teamstats['games'] - $teamstats['wins'];
  $teamstats['diff'] = $teamstats['for'] - $teamstats['against'];

  $teamstats['spirit'] = isset($spiritAvg[$team['team_id']]) ? $spiritAvg[$team['team_id']]['total'] : null;

  $teamstats['winavg'] = number_format(SafeDivide(intval($statsRow['wins']), intval($statsRow['games'])) * 100, 1);

  $teamstats['ranking'] = 0;
  $allteams[] = $teamstats;
}

$rankedteams  = SeriesRanking($seriesinfo['series_id']);
$rank = 0;
foreach ($rankedteams as $rteam) {
  $rank++;
  foreach ($allteams as $ateam) {
    if (isset($rteam['team_id']) && $ateam['team_id'] == $rteam['team_id'])
      $ateam['ranking'] = $rank;
  }
}


$html .= CommentHTML(2, $seriesinfo['series_id']);

$html .= "<h2>" . _("Division statistics:") . " " . utf8entities($seriesinfo['name']) . "</h2>";
$style = "";

$html .= "<table border='1' style='width:100%'>\n";
$html .= "<tr>";

if ($sort == "ranking") {
  mergesort($allteams, function ($a, $b) use ($sort) {
    $va = $a[$sort];
    $vb = $b[$sort];
    return $va == $vb ? 0 : ($va == null ? 1 : ($vb = null ? -1 : ($a[$sort] < $b[$sort] ? -1 : 1)));
  });
} else if ($sort == "name" || $sort == "pool" || $sort == "against" || $sort == "seed") {
  mergesort($allteams, function ($a, $b) use ($sort) {
    return $a[$sort] == $b[$sort] ? 0 : ($a[$sort] < $b[$sort] ? -1 : 1);
  });
} else {
  mergesort($allteams, function ($a, $b) use ($sort) {
    return $a[$sort] == $b[$sort] ? 0 : ($a[$sort] > $b[$sort] ? -1 : 1);
  });
}

if ($sort == "name") {
  $html .= "<th style='width:180px'>" . _("Team") . "</th>";
} else {
  $html .= "<th style='width:180px'><a class='thsort' href='" . $viewUrl . "&amp;Sort=name'>" . _("Team") . "</a></th>";
}

/*
 if($sort == "pool") {
 $html .= "<th style='width:200px'>"._("Pool")."</th>";
 }else{
 $html .= "<th style='width:200px'><a href='".$viewUrl."&amp;Sort=pool'>"._("Pool")."</a></th>";
 }
 */

if ($sort == "seed") {
  $html .= "<th class='center'>" . _("Seeding") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=seed'>" . _("Seeding") . "</a></th>";
}

if ($sort == "ranking") {
  $html .= "<th class='center'>" . _("Ranking") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=ranking'>" . _("Ranking") . "</a></th>";
}

if ($sort == "games") {
  $html .= "<th class='center'>" . _("Games") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=games'>" . _("Games") . "</a></th>";
}

if ($sort == "wins") {
  $html .= "<th class='center'>" . _("Wins") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=wins'>" . _("Wins") . "</a></th>";
}

if ($sort == "losses") {
  $html .= "<th class='center'>" . _("Losses") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=losses'>" . _("Losses") . "</a></th>";
}

if ($sort == "for") {
  $html .= "<th class='center'>" . _("Goals for") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=for'>" . _("Goals for") . "</a></th>";
}

if ($sort == "against") {
  $html .= "<th class='center'>" . _("Goals against") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=against'>" . _("Goals against") . "</a></th>";
}

if ($sort == "diff") {
  $html .= "<th class='center'>" . _("Goals diff") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=diff'>" . _("Goals diff") . "</a></th>";
}

if ($sort == "winavg") {
  $html .= "<th class='center'>" . _("Win-%") . "</th>";
} else {
  $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=winavg'>" . _("Win-%") . "</a></th>";
}
if ((isset($seasoninfo['spiritmode']) && $seasoninfo['spiritmode'] > 0) && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seriesinfo['season']))) {
  if ($sort == "spirit") {
    $html .= "<th class='center'>" . _("Spirit points") . "</th>";
  } else {
    $html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;Sort=spirit'>" . _("Spirit points") . "</a></th>";
  }
}

$html .= "</tr>\n";

foreach ($allteams as $stats) {
  $html .= "<tr>";
  $flag = "";
  if (intval($seasoninfo['isinternational'])) {
    $flag = "<img height='10' src='images/flags/tiny/" . $stats['flagfile'] . "' alt=''/> ";
  }
  if ($sort == "name") {
    $html .= "<td class='highlight'>$flag<a href='?view=teamcard&amp;team=" . $stats['team_id'] . "'>" . utf8entities(U_($stats['name'])) . "</a></td>";
  } else {
    $html .= "<td>$flag<a href='?view=teamcard&amp;team=" . $stats['team_id'] . "'>" . utf8entities(U_($stats['name'])) . "</a></td>";
  }
  /*
   if($sort == "pool") {
   $html .= "<td class='highlight'>",utf8entities(U_($stats['pool'])),"</td>";
   }else{
   $html .= "<td>",utf8entities(U_($stats['pool'])),"</td>";
   }
   */
  if ($sort == "seed") {
    $html .= "<td class='center highlight'>" . intval($stats['seed']) . ".</td>";
  } else {
    $html .= "<td class='center'>" . intval($stats['seed']) . ".</td>";
  } {
    $rank = $stats['ranking'];
    if ($rank == null)
      $rank = "-";
    else
      $rank = intval($rank);
    if ($sort == "ranking") {
      $html .= "<td class='center highlight'>" . $rank . "</td>";
    } else {
      $html .= "<td class='center'>" . $rank . "</td>";
    }
  }

  if ($sort == "games") {
    $html .= "<td class='center highlight'>" . intval($stats['games']) . "</td>";
  } else {
    $html .= "<td class='center'>" . intval($stats['games']) . "</td>";
  }
  if ($sort == "wins") {
    $html .= "<td class='center highlight'>" . intval($stats['wins']) . "</td>";
  } else {
    $html .= "<td class='center'>" . intval($stats['wins']) . "</td>";
  }
  if ($sort == "losses") {
    $html .= "<td class='center highlight'>" . intval($stats['losses']) . "</td>";
  } else {
    $html .= "<td class='center'>" . intval($stats['losses']) . "</td>";
  }
  if ($sort == "for") {
    $html .= "<td class='center highlight'>" . intval($stats['for']) . "</td>";
  } else {
    $html .= "<td class='center'>" . intval($stats['for']) . "</td>";
  }
  if ($sort == "against") {
    $html .= "<td class='center highlight'>" . intval($stats['against']) . "</td>";
  } else {
    $html .= "<td class='center'>" . intval($stats['against']) . "</td>";
  }
  if ($sort == "diff") {
    $html .= "<td class='center highlight'>" . intval($stats['diff']) . "</td>";
  } else {
    $html .= "<td class='center'>" . intval($stats['diff']) . "</td>";
  }

  if ($sort == "winavg") {
    $html .= "<td class='center highlight'>" . $stats['winavg'] . "%</td>";
  } else {
    $html .= "<td class='center'>" . $stats['winavg'] . "%</td>";
  }

  if ((isset($seasoninfo['spiritmode']) && $seasoninfo['spiritmode'] > 0) && ($seasoninfo['showspiritpoints'] || isSeasonAdmin($seriesinfo['season']))) {
    if ($sort == "spirit") {
      $html .= "<td class='center highlight'>" . ($stats['spirit'] ? number_format($stats['spirit'], 2) : "-") . "</td>";
    } else {
      $html .= "<td class='center'>" . ($stats['spirit'] ? number_format($stats['spirit'], 2) : "-") . "</td>";
    }
  }

  $html .= "</tr>\n";
}
$html .= "</table>\n";
$html .= "<a href='?view=poolstatus&amp;series=" . $seriesinfo['series_id'] . "'>" . _("Show all pools") . "</a>";
$html .= "<h2>" . _("Scoreboard leaders") . "</h2>\n";
$html .= "<table cellspacing='0' border='0' width='100%'>\n";
$html .= "<tr><th style='width:200px'>" . _("Player") . "</th><th style='width:200px'>" . _("Team") . "</th><th class='center'>" . _("Games") . "</th>
<th class='center'>" . _("Assists") . "</th><th class='center'>" . _("Goals") . "</th><th class='center'>" . _("Tot.") . "</th></tr>\n";

$scores = SeriesScoreBoard($seriesinfo['series_id'], "total", 10);
while ($row = mysqli_fetch_assoc($scores)) {
  $html .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
  $html .= "<td>" . utf8entities($row['teamname']) . "</td>";
  $html .= "<td class='center'>" . intval($row['games']) . "</td>";
  $html .= "<td class='center'>" . intval($row['fedin']) . "</td>";
  $html .= "<td class='center'>" . intval($row['done']) . "</td>";
  $html .= "<td class='center'>" . intval($row['total']) . "</td></tr>\n";
}

$html .= "</table>";
$html .= "<a href='?view=scorestatus&amp;series=" . $seriesinfo['series_id'] . "'>" . _("Scoreboard") . "</a>";


if (ShowDefenseStats()) {
  $html .= "<h2>" . _("Defenseboard leaders") . "</h2>\n";
  $html .= "<table cellspacing='0' border='0' width='100%'>\n";
  $html .= "<tr><th style='width:200px'>" . _("Player") . "</th><th style='width:200px'>" . _("Team") . "</th><th class='center'>" . _("Games") . "</th>
	<th class='center'>" . _("Total defenses") . "</th></tr>\n";


  $defenses = SeriesDefenseBoard($seriesinfo['series_id'], "deftotal", 10);
  while ($row = mysqli_fetch_assoc($defenses)) {
    $html .= "<tr><td>" . utf8entities($row['firstname'] . " " . $row['lastname']) . "</td>";
    $html .= "<td>" . utf8entities($row['teamname']) . "</td>";
    $html .= "<td>" . _("Games") . "</td>";
    $html .= "<td class='center'>" . intval($row['games']) . "</td>";
    $html .= "<td class='center'>" . intval($row['deftotal']) . "</td></tr>\n";
  }

  $html .= "</table>";
  $html .= "<a href='?view=defensestatus&amp;series=" . $seriesinfo['series_id'] . "'>" . _("Defenseboard") . "</a>";
}

if ($seasoninfo['showspiritpoints']) { // TODO total
  $categories = SpiritCategories($seasoninfo['spiritmode']);
  $html .= "<h2>" . _("Spirit points average per category") . "</h2>\n";

  $html .= "<table cellspacing='0' border='0' width='100%'>\n";
  $html .= "<tr><th style='width:150px'>" . _("Team") . "</th>";
  $html .= "<th>" . _("Games") . "</th>";
  foreach ($categories as $cat) {
    if ($cat['index'] > 0)
      $html .= "<th class='center'>" . _($cat['index']) . "</th>";
  }
  $html .= "<th class='center'>" . _("Tot.") . "</th>";
  $html .= "</tr>\n";

  foreach ($spiritAvg as $teamAvg) {
    $html .= "<td>" . utf8entities($teamAvg['teamname']) . "</td>";
    $html .= "<td>" . $teamAvg['games'] . "</td>";
    foreach ($categories as $cat) {
      if ($cat['index'] > 0 && isset($teamAvg[$cat['category_id']])) {
        if ($cat['factor'] != 0)
          $html .= "<td class='center'><b>" . number_format($teamAvg[$cat['category_id']], 2) . "</b></td>";
        else
          $html .= "<td class='center'>" . number_format($teamAvg[$cat['category_id']], 2) . "</td>";
      }
    }
    $html .= "<td class='center'><b>" . number_format($teamAvg['total'], 2) . "</b></td>";
    $html .= "</tr>\n";
  }
  $html .= "</table>";

  $html .= "<ul>";
  foreach ($categories as $cat) {
    if ($cat['index'] > 0)
      $html .= "<li>" . $cat['index'] . " " . $cat['text'] . "</li>";
  }
  $html .= "</ul>\n";
}


showPage($title, $html);
