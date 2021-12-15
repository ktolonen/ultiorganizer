<?php
include_once 'lib/pool.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';
include_once 'lib/statistical.functions.php';

$body = @file_get_contents('php://input');
//alternative way for IIS if above command fail
//set in php.ini: always_populate_raw_post_data = On
//$body = $HTTP_RAW_POST_DATA; 

$series = explode("|", $body);
foreach ($series as $seriesStr) {
	$teams = explode(":", $seriesStr);
	//echo $seriesStr."\n";
	for ($i = 0; $i < count($teams); $i++) {
		if (!empty($teams[$i])) {
			SetTeamSeasonStanding($teams[$i], $i + 1);
		}
	}
}

echo _("Standings saved");
