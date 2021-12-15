<?php
include '../../lib/series.functions.php';

function printSeries($season,$series) {
	restHeader(_("Ultiorganizer")." ".U_($seasonInfo['name'])." - "._("Division"));
	printList($series, urlencode($season)."/series/", 'series_id', true);
	
	$actions = standardActions(true); 
	$actions["filter=active"] = _("Valid divisions");
	restFooter($actions);
}

if (count($elements) == 2) {
	if (isset($filter)) {
		if ($filter == "active") {
			$series = SeasonSeries($seasonId,true);
		}
	} else {
		$series = SeasonSeries($season);
		printSeries($season, $series);
	}
} else {
	$series = $elements[2];
	$seriesInfo = SeriesInfo($series);
	if (count($elements) == 3) {
		restHeader(_("Division").": ".U_($seriesInfo['name']));
		printItem($seriesInfo);
		echo "				<ul>
					<li><a href='?".urlencode($season)."/series/".$series."/pools'>"._("Pools")."</a></li>
					<li><a href='?".urlencode($season)."/series/".$series."/teams'>"._("Teams")."</a></li>
					</ul>\n";
		$actions = standardActions(false);
		restFooter($actions);
	} else {
		include $elements[3].'.php';
	}
	
}
?>