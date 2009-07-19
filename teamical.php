<?php
include 'view_ids.inc.php';
include 'lib/database.php';
include 'lib/common.functions.php';
include 'lib/season.functions.php';
include 'lib/serie.functions.php';
include 'lib/team.functions.php';
include 'builder.php';

$seriesId = intval($_GET["Series"]);
$teamId = intval($_GET["Team"]);
$print = intval($_GET["Print"]);

OpenConnection();
$season = TeamSeason($teamId);
$arrayYear = strtok($season, "."); 
$arraySeason = strtok(".");

echo "BEGIN:VCALENDAR\n";
echo "VERSION:2.0\n";
echo "PRODID: Suomen Liitokiekkoliitto - Pelikone\n\n";

$tournaments = ComingTournaments($season);
	
while($tournament = mysql_fetch_assoc($tournaments))
	{
	$games = TeamComingGames($teamId, $tournament['Paikka_ID']);

	while($game = mysql_fetch_assoc($games))
		{
		echo "\nBEGIN:VEVENT";
		echo "\nSUMMARY:". $game['KNimi'] ."-". $game['VNimi'];
		echo "\nDESCRIPTION:". $tournament['Turnaus'];
		echo "\nLOCATION: ". $tournament['Paikka'];
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