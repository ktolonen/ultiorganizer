<?php

require_once __DIR__ . '/lib/view.guard.php';
requireRoutedView('scorestatus');

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';

$title = _("Scoreboard");
$html = "";

$poolId = 0;
$poolIds = [];
$seriesId = 0;
$teamId = 0;
$sort = "total";
$scores = null;
$rankColumnWidth = "5%";
$playerColumnWidth = "27%";
$teamColumnWidth = "20%";
$numericColumnWidth = "6%";

if (iget("pool")) {
    $poolId = intval(iget("pool"));
    $title = $title . ": " . utf8entities(U_(PoolName($poolId)));
}
if (iget("pools")) {
    $poolIds = array_filter(array_map('intval', explode(",", iget("pools"))), function ($val) {
        return $val > 0;
    });
    $title = $title . ": " . utf8entities(U_(PoolName($poolId)));
}
if (iget("series")) {
    $seriesId = intval(iget("series"));
    $title = $title . ": " . utf8entities(U_(SeriesName($seriesId)));
}
if (iget("team")) {
    $teamId = intval(iget("team"));
    $title = $title . ": " . utf8entities(TeamName($teamId));
}
if (iget("sort")) {
    $sort = iget("sort");
}

$html .= "<h1>" . _("Scoreboard") . "</h1>\n";
$html .= "<table style='width:100%' cellpadding='1' border='1'>";

$viewUrl = "?view=scorestatus&amp;";
if ($teamId) {
    $viewUrl .= "Team=$teamId&amp;";
}
if ($poolId) {
    $viewUrl .= "Pool=$poolId&amp;";
}
if (count($poolIds)) {
    $viewUrl .= "Pools=" . implode(",", $poolIds) . "&amp;";
}
if ($seriesId) {
    $viewUrl .= "Series=$seriesId&amp;";
}

$html .= "<tr>\n";
$html .= "<th style='width:" . $rankColumnWidth . "'>#</th>";
if ($sort == "name") {
    $html .= "<th style='width:" . $playerColumnWidth . "'>" . _("Player") . "</th>";
} else {
    $html .= "<th style='width:" . $playerColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=name'>" . _("Player") . "</a></th>";
}
if ($sort == "team") {
    $html .= "<th style='width:" . $teamColumnWidth . "'><b>" . _("Team") . "</b></th>";
} else {
    $html .= "<th style='width:" . $teamColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=team'>" . _("Team") . "</a></th>";
}
if ($sort == "games") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Games") . "</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=games'>" . _("Games") . "</a></th>";
}
if ($sort == "pass") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Assists") . "</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=pass'>" . _("Assists") . "</a></th>";
}
if ($sort == "passavg") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Ast") . " Avg.</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=passavg'>" . _("Ast") . " Avg.</a></th>";
}
if ($sort == "goal") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Goals") . "</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=goal'>" . _("Goals") . "</a></th>";
}
if ($sort == "goalavg") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Gls") . " Avg.</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=goalavg'>" . _("Gls") . " Avg.</a></th>";
}

if ($sort == "callahan") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Call.") . "</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=callahan'>" . _("Call.") . "</a></th>";
}

if ($sort == "total") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Tot.") . "</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=total'>" . _("Tot.") . "</a></th>";
}
if ($sort == "totalavg") {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><b>" . _("Tot.") . " Avg.</b></th>";
} else {
    $html .= "<th class='center' style='width:" . $numericColumnWidth . "'><a class='thsort' href='" . $viewUrl . "sort=totalavg'>" . _("Tot.") . " Avg.</a></th>";
}
$html .= "</tr>";

if ($teamId) {
    if (count($poolIds)) {
        $scores = TeamScoreBoardArray($teamId, $poolIds, $sort, 0);
    } else {
        $scores = TeamScoreBoardArray($teamId, $poolId, $sort, 0);
    }
} elseif ($poolId) {
    $scores = PoolScoreBoardArray($poolId, $sort, 0);
} elseif (count($poolIds)) {
    $scores = PoolsScoreBoardArray($poolIds, $sort, 0);
} elseif ($seriesId) {
    $scores = SeriesScoreBoardArray($seriesId, $sort, 0);
}
$i = 1;
if ($scores) {
    foreach ($scores as $row) {
        $html .= "<tr>";
        $html .= "<td>" . $i++ . "</td>";
        if ($sort == "name") {
            $html .= "<td class='highlight'><a href='?view=playercard&amp;series=$poolId&amp;player=" . $row['player_id'] . "'>";
            $html .= utf8entities($row['firstname'] . " " . $row['lastname']);
            $html .= "</a></td>";
        } else {
            $html .= "<td><a href='?view=playercard&amp;series=$poolId&amp;player=" . $row['player_id'] . "'>";
            $html .= utf8entities($row['firstname'] . " " . $row['lastname']);
            $html .= "</a></td>";
        }
        if ($sort == "team") {
            $html .= "<td class='highlight'>" . utf8entities($row['teamname']) . "</td>";
        } else {
            $html .= "<td>" . utf8entities($row['teamname']) . "</td>";
        }
        if ($sort == "games") {
            $html .= "<td class='center highlight'>" . intval($row['games']) . "</td>";
        } else {
            $html .= "<td class='center'>" . intval($row['games']) . "</td>";
        }
        if ($sort == "pass") {
            $html .= "<td class='center highlight'>" . intval($row['fedin']) . "</td>";
        } else {
            $html .= "<td class='center'>" . intval($row['fedin']) . "</td>";
        }
        if ($sort == "passavg") {
            $html .= "<td class='center highlight'>" . sprintf("%.2f", floatval($row['fedinavg'])) . "</td>";
        } else {
            $html .= "<td class='center'>" . sprintf("%.2f", floatval($row['fedinavg'])) . "</td>";
        }
        if ($sort == "goal") {
            $html .= "<td class='center highlight'>" . intval($row['done']) . "</td>";
        } else {
            $html .= "<td class='center'>" . intval($row['done']) . "</td>";
        }
        if ($sort == "goalavg") {
            $html .= "<td class='center highlight'>" . sprintf("%.2f", floatval($row['doneavg'])) . "</td>";
        } else {
            $html .= "<td class='center'>" . sprintf("%.2f", floatval($row['doneavg'])) . "</td>";
        }

        if ($sort == "callahan") {
            $html .= "<td class='center highlight'>" . intval($row['callahan']) . "</td>";
        } else {
            $html .= "<td class='center'>" . intval($row['callahan']) . "</td>";
        }

        if ($sort == "total") {
            $html .= "<td class='center highlight'>" . intval($row['total']) . "</td>";
        } else {
            $html .= "<td class='center'>" . intval($row['total']) . "</td>";
        }
        if ($sort == "totalavg") {
            $html .= "<td class='center highlight'>" . sprintf("%.2f", floatval($row['totalavg'])) . "</td></tr>";
        } else {
            $html .= "<td class='center'>" . sprintf("%.2f", floatval($row['totalavg'])) . "</td></tr>";
        }
    }
}

$html .= "</table>";
showPage($title, $html);
