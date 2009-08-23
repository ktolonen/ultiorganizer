<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'lib/team.functions.php';
include_once 'builder.php';

header("Content-Type: text/Calendar; charset=utf-8");

$teamId = intval($_GET["Team"]);

OpenConnection();
$season = TeamSeason($teamId);
$arrayYear = strtok($season, "."); 
$arraySeason = strtok(".");

echo "BEGIN:VCALENDAR\n";
echo "VERSION:2.0\n";
echo "PRODID: "._("Suomen Liitokiekkoliitto")." - "._("Pelikone")."\n\n";

$tournaments = ComingTournaments($season);
	
while($tournament = mysql_fetch_assoc($tournaments))
	{
	$games = TeamComingGames($teamId, $tournament['Paikka_ID']);

	while($game = mysql_fetch_assoc($games))
		{
		echo "\nBEGIN:VEVENT";
		echo "\nSUMMARY:". utf8_encode($game['KNimi'] ."-". $game['VNimi']);
		echo "\nDESCRIPTION:". utf8_encode($tournament['Turnaus']);
		echo "\nLOCATION: ". utf8_encode($tournament['Paikka']);
		echo "\nDTSTART;TZID=Europe/Helsinki:". TimeToIcal($game['Aika']);
		if($arraySeason == "2")
			echo "\nDURATION: P1H";
		else
			echo "\nDURATION: P2H";

		echo "\nEND:VEVENT\n";
		}
	}
echo "\nEND:VCALENDAR\n";
CloseConnection();
?>