<?php
include_once 'lib/season.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/statistical.functions.php';

$title = _("Statistics");
$html = "";
$list = "teamstandings";
$sort = "";

if (iget("list")) {
  $list = iget("list");
}
if (iget("sort")) {
  $sort = iget("sort");
}

//content
$menutabs[_("Events' Standings")] = "?view=statistics&list=teamstandings";
$menutabs[_("Events' Scoreboards")] = "?view=statistics&list=playerscoreboard";
$menutabs[_("Alltime Scoreboards")] = "?view=statistics&list=playerscoresall";
$html .= pageMenu($menutabs, "", false);

if ($list == "teamstandings") {
  $html .= "<h1>" . _("Team Standings") . "</h1>\n";
  $seasontypes = SeasonTypes();
  $serietypes = SeriesTypes();
  $countall = 0;

  foreach ($seasontypes as $seasontype) {
    $seasons = SeasonsByType($seasontype);
    if (count($seasons) < 1) {
      continue;
    }
    $html .= "<h2>" . U_($seasontype) . "</h2>\n";

    foreach ($serietypes as $seriestype) {
      $serstats = SeriesStatisticsByType($seriestype, $seasontype);
      if (count($serstats) < 1) {
        continue;
      }
      $html .= "<h3>" . U_($seriestype) . "</h3>\n";
      $html .= "<table style='width:100%' border='1'><tr>
				<th>" . _("Event") . "</th><th>" . _("Gold") . "</th><th>" . _("Silver") . "</th><th>" . _("Bronze") . "</th></tr>\n";

      foreach ($seasons as $season) {
        $standings = TeamStandings($season['season_id'], $seriestype);
        if (!count($standings)) {
          continue;
        }
        ++$countall;
        $html .= "<tr>";
        $html .= "<td style='width:16%'><a href='?view=eventstatus&amp;season=" . urlencode($season['season_id']) .
          "&amp;list=bystandings'>" . utf8entities(U_($season['name'])) . "</a></td>";

        for ($i = 0; $i < count($standings) && $i < 3; $i++) {
          $html .= "<td style='width:28%'>";
          if (intval($standings[$i]['country']) > 0) {
            $html .= "&nbsp;<img height='10' src='images/flags/tiny/" . $standings[$i]['flagfile'] . "' alt=''/>&nbsp;";
          }
          $html .= "<a href='?view=teamcard&amp;team=" . $standings[$i]['team_id'] . "'>" . utf8entities($standings[$i]['teamname']) . "</a></td>";
        }
        $html .= "</tr>\n";
      }
      $html .= "</table>\n";
    }
  }
  if ($countall == 0) {
    $html .= "<p>" . _("Season statistics have not yet been computed") . "</p>";
  }
} elseif ($list == "playerscoreboard") {
  $html .= "<h1>" . _("Scoreboard TOP 3") . "</h1>\n";
  $seasontypes = SeasonTypes();
  $serietypes = SeriesTypes();

  foreach ($seasontypes as $seasontype) {
    $seasons = SeasonsByType($seasontype);
    if (count($seasons) < 1) {
      continue;
    }
    $html .= "<h2>" . U_($seasontype) . "</h2>\n";

    foreach ($serietypes as $seriestype) {
      $serstats = SeriesStatisticsByType($seriestype, $seasontype);
      if (count($serstats) < 1) {
        continue;
      }
      $html .= "<h3>" . U_($seriestype) . "</h3>\n";
      $html .= "<table border='1' width='100%'><tr>
				<th>" . _("Event") . "</th><th>" . _("First") . "</th><th>" . _("Second") . "</th><th>" . _("Third") . "</th></tr>\n";

      foreach ($seasons as $season) {
        $scores = AlltimeScoreboard($season['season_id'], $seriestype);
        if (!count($scores)) {
          continue;
        }
        $html .= "<tr>";
        $html .= "<td style='width:16%%'><a href='?view=scorestatus&Series=" . $scores[0]['series'] . "'>" . utf8entities(U_($season['name'])) . "</a></td>";

        for ($i = 0; $i < count($scores) && $i < 3; $i++) {
          $html .= "<td style='width:28%'>";
          $html .= "<a href='?view=playercard&amp;player=" . $scores[$i]['player_id'] . "'>";
          $html .= utf8entities($scores[$i]['firstname'] . " " . $scores[$i]['lastname']) . "</a>";
          $html .= "<br/>";
          //$html .= "<a href='?view=teamcard&amp;team=".$scores[$i]['team']."'>".utf8entities($scores[$i]['teamname'])."</a>";
          $html .= utf8entities($scores[$i]['teamname']);
          $html .= "<br/>";
          $html .= $scores[$i]['passes'] . "+" . $scores[$i]['goals'] . "=" . $scores[$i]['total'];
          $html .= "</td>";
        }
        $html .= "</tr>\n";
      }
      $html .= "</table>\n";
    }
  }
} elseif ($list == "playerscoresall") {
  $viewUrl = "?view=statistics&list=playerscoresall&amp;";
  $html .= "<h1>" . _("All time scoreboard TOP 100") . "</h1>\n";
  $scores = ScoreboardAllTime(100, "", "", "", $sort);
  $html .= "<table border='1' width='100%'><tr>
				<th>#</th><th>" . _("Name") . "</th><th>" . _("Latest event / team") . "</th><th class='center'><a class='thsort' href='" . $viewUrl . "sort=games'>" . _("Games") . "</a></th>
				<th class='center'><a class='thsort' href='" . $viewUrl . "sort=pass'>" . _("Passes") . "</a></th><th class='center'><a class='thsort' href='" . $viewUrl . "sort=goal'>" . _("Goals") . "</a>
				</th><th class='center'><a class='thsort' href='" . $viewUrl . "sort=total'>" . _("Total") . "</a></th></tr>\n";
  $i = 1;
  foreach ($scores as $row) {
    $html .= "<tr>\n";
    $html .= "<td>" . $i++ . ".</td>";
    $html .= "<td>";
    $html .= "<a href='?view=playercard&amp;profile=" . $row['profile_id'] . "'>";
    $player = PlayerProfile($row['profile_id']);
    $html .= utf8entities($player['firstname'] . " " . $player['lastname']) . "</a>";
    $html .= "</td>";
    $html .= "<td>" . utf8entities(SeriesSeasonName($row['last_series'])) . " / " . utf8entities(TeamName($row['last_team'])) . "</td>";
    $html .= "<td class='center'>" . $row['gamestotal'] . "</td>";
    $html .= "<td class='center'>" . $row['passestotal'] . "</td>";
    $html .= "<td class='center'>" . $row['goalstotal'] . "</td>";
    $html .= "<td class='center'>" . $row['total'] . "</td>";
    $html .= "</tr>\n";
  }

  $html .= "</table>\n";

  $seasontypes = SeasonTypes();
  $serietypes = SeriesTypes();


  foreach ($seasontypes as $seasontype) {
    $seasons = SeasonsByType($seasontype);
    if (count($seasons) < 1) {
      continue;
    }
    $html .= "<h2>" . U_($seasontype) . "</h2>\n";

    foreach ($serietypes as $seriestype) {
      $serstats = SeriesStatisticsByType($seriestype, $seasontype);
      if (count($serstats) < 1) {
        continue;
      }
      $html .= "<h3>" . U_($seriestype) . "</h3>\n";

      $scores = ScoreboardAllTime(30, $seasontype, $seriestype, "", $sort);
      $html .= "<table border='1' width='100%'><tr>
						<th>#</th><th>" . _("Name") . "</th><th>" . _("Latest event / team") . "</th><th class='center'>" . _("Games") . "</th>
						<th class='center'>" . _("Passes") . "</th><th class='center'>" . _("Goals") . "</th><th class='center'>" . _("Total") . "</th></tr>\n";
      $i = 1;
      foreach ($scores as $row) {
        $html .= "<tr>\n";
        $html .= "<td>" . $i++ . ".</td>";
        $html .= "<td>";
        $html .= "<a href='?view=playercard&amp;profile=" . $row['profile_id'] . "'>";
        $player = PlayerProfile($row['profile_id']);
        $html .= utf8entities($player['firstname'] . " " . $player['lastname']) . "</a>";
        $html .= "</td>";
        $html .= "<td>" . utf8entities(SeriesSeasonName($row['last_series'])) . " / " . utf8entities(TeamName($row['last_team'])) . "</td>";
        $html .= "<td class='center'>" . $row['gamestotal'] . "</td>";
        $html .= "<td class='center'>" . $row['passestotal'] . "</td>";
        $html .= "<td class='center'>" . $row['goalstotal'] . "</td>";
        $html .= "<td class='center'>" . $row['total'] . "</td>";
        $html .= "</tr>\n";
      }
      $html .= "</table>\n";
    }
  }
}

showPage($title, $html);
