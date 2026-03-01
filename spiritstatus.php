<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';

$LAYOUT_ID = SERIESTATUS;

$title = _("Spirit");
$html = "";
$spsort = "total";

if (!iget("series")) {
	$html .= "<h1>" . _("Series not found") . "</h1>";
	showPage($title, $html);
	return;
}

$seriesinfo = SeriesInfo(iget("series"));
if (!$seriesinfo) {
	$html .= "<h1>" . _("Series not found") . "</h1>";
	showPage($title, $html);
	return;
}

$seasoninfo = SeasonInfo($seriesinfo['season']);
if (!$seasoninfo) {
	$html .= "<h1>" . _("Season not found") . "</h1>";
	showPage($title, $html);
	return;
}

$title .= ": " . U_($seriesinfo['name']);
$viewUrl = "?view=spiritstatus&amp;series=" . (int)$seriesinfo['series_id'];
if (iget("spsort")) {
	$spsort = iget("spsort");
} elseif (iget("sort")) {
	$spsort = iget("sort");
}
$html .= "<h2>" . _("SOTG Scores:") . " " . utf8entities($seriesinfo['name']) . "</h2>";

if (!ShowSpiritScoresForSeason($seasoninfo)) {
	$html .= "<p>" . _("Spirit points are not visible.") . "</p>";
	showPage($title, $html);
	return;
}

// add comment from Spirit Director for Women Masters division at WUGC16
if (($seriesinfo['season'] == "WUGC16") && ((int)$seriesinfo['series_id'] === 5)) {
	$html .= "<p><strong>Important Note:</strong> After the conclusion of the Women's Masters Final, the Spirit Award was presented to New Zealand as they had the highest average total Spirit score at the time. Subsequent placing games played after the final due to rain delays have been recorded in the final scores.</p>";
}

$categories = SpiritCategories($seasoninfo['spiritmode']);
$scoreCategories = array();
foreach ($categories as $cat) {
	if ((int)$cat['index'] > 0) {
		$scoreCategories[] = $cat;
	}
}

$spiritAvg = array_values(SeriesSpiritBoard($seriesinfo['series_id']));
$spiritTotAvg = SeriesSpiritBoardTotalAverages($seriesinfo['series_id'], false);
$allowedSorts = array("team", "games", "total");
foreach ($scoreCategories as $cat) {
	$allowedSorts[] = "cat_" . (int)$cat['category_id'];
}
if (!in_array($spsort, $allowedSorts, true)) {
	$spsort = "total";
}

usort($spiritAvg, function ($a, $b) use ($spsort) {
	if ($spsort === "team") {
		return strcasecmp((string)$a['teamname'], (string)$b['teamname']);
	}

	if ($spsort === "games") {
		$av = isset($a['games']) ? (float)$a['games'] : 0.0;
		$bv = isset($b['games']) ? (float)$b['games'] : 0.0;
	} elseif ($spsort === "total") {
		$av = isset($a['total']) ? (float)$a['total'] : 0.0;
		$bv = isset($b['total']) ? (float)$b['total'] : 0.0;
	} else {
		$categoryId = (int)substr($spsort, 4);
		$av = isset($a[$categoryId]) ? (float)$a[$categoryId] : -1.0;
		$bv = isset($b[$categoryId]) ? (float)$b[$categoryId] : -1.0;
	}

	if ($av == $bv) {
		return strcasecmp((string)$a['teamname'], (string)$b['teamname']);
	}
	return ($av < $bv) ? 1 : -1;
});

$html .= "<table class='teams-table' cellspacing='0' border='0' width='100%'>\n";
$html .= "<tr>";
if ($spsort === "team") {
	$html .= "<th style='width:35%'>" . _("Team") . "</th>";
} else {
	$html .= "<th style='width:35%'><a class='thsort' href='" . $viewUrl . "&amp;spsort=team'>" . _("Team") . "</a></th>";
}
if ($spsort === "games") {
	$html .= "<th class='center' style='width:8%'>" . _("Games") . "</th>";
} else {
	$html .= "<th class='center' style='width:8%'><a class='thsort' href='" . $viewUrl . "&amp;spsort=games'>" . _("Games") . "</a></th>";
}
foreach ($scoreCategories as $cat) {
	$sortKey = "cat_" . (int)$cat['category_id'];
	if ($spsort === $sortKey) {
		$html .= "<th class='center'>" . utf8entities(_($cat['text'])) . "</th>";
	} else {
		$html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;spsort=" . urlencode($sortKey) . "'>" . utf8entities(_($cat['text'])) . "</a></th>";
	}
}
if ($spsort === "total") {
	$html .= "<th class='center'>" . _("Tot.") . "</th>";
} else {
	$html .= "<th class='center'><a class='thsort' href='" . $viewUrl . "&amp;spsort=total'>" . _("Tot.") . "</a></th>";
}
$html .= "</tr>\n";

if (empty($spiritAvg)) {
	$html .= "<tr><td colspan='" . (3 + count($scoreCategories)) . "'>" . _("No spirit scores yet.") . "</td></tr>\n";
} else {
	foreach ($spiritAvg as $teamAvg) {
		$html .= "<tr>";
		$html .= "<td>" . utf8entities($teamAvg['teamname']) . "</td>";
		$html .= "<td class='center'>" . (isset($teamAvg['games']) ? (int)$teamAvg['games'] : 0) . "</td>";
		foreach ($scoreCategories as $cat) {
			$categoryId = $cat['category_id'];
			if (isset($teamAvg[$categoryId])) {
				$html .= "<td class='center'>" . number_format((float)$teamAvg[$categoryId], 2) . "</td>";
			} else {
				$html .= "<td class='center'>-</td>";
			}
		}
		$html .= "<td class='center'><b>" . number_format((float)$teamAvg['total'], 2) . "</b></td>";
		$html .= "</tr>\n";
	}

	$html .= "<tr><td colspan='" . (3 + count($scoreCategories)) . "'>&nbsp;</td></tr>\n";
	$html .= "<tr style='font-weight: bold;'>";
	$html .= "<td>" . _("Average of all games") . "</td>";
	$html .= "<td class='center'>-</td>";
	foreach ($scoreCategories as $cat) {
		$categoryId = $cat['category_id'];
		$index = (int)$cat['index'];
		$keyByCategory = $categoryId;
		$keyByIndex = 'cat' . $index;
		$value = null;
		if (isset($spiritTotAvg[$keyByCategory])) {
			$value = $spiritTotAvg[$keyByCategory];
		} elseif (isset($spiritTotAvg[$keyByIndex])) {
			$value = $spiritTotAvg[$keyByIndex];
		}
		if (is_null($value)) {
			$html .= "<td class='center'>-</td>";
		} else {
			$html .= "<td class='center'>" . number_format((float)$value, 2) . "</td>";
		}
	}
	if (!isset($spiritTotAvg['total']) || is_null($spiritTotAvg['total'])) {
		$html .= "<td class='center'>-</td>";
	} else {
		$html .= "<td class='center'>" . number_format((float)$spiritTotAvg['total'], 2) . "</td>";
	}
	$html .= "</tr>\n";
}

$html .= "</table>\n";

showPage($title, $html);
?>
