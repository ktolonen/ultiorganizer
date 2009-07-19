<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="pelikone.css" type="text/css" />
<title>Liitokiekkoliiton Pelikone</title>
</head>
<body>

<?php
include '../lib/database.php';
include '../lib/common.functions.php';
include '../lib/season.functions.php';
include '../lib/serie.functions.php';
include '../lib/team.functions.php';

$season = intval($_GET["Season"]);
$tournament = $_GET["Tournament"];
$seriesId = 0;

OpenConnection();

if(!$season)
	$season = CurrenSeason();
	
	$tournaments = Timetable($tournament, $season);
	$prevTournament = "";
	echo "<table>";
	
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		
		if($tournament['Turnaus'] != $prevTournament)
			{
			if($prevTournament != "")
				echo "<tr><td><hr/></td></tr>\n";
			echo "<tr><td><h1 class='pk_h1'>". htmlentities($tournament['Turnaus']) ."</h1></td></tr>\n";
			$prevTournament = $tournament['Turnaus'];
			}
			
		$places = TournamentPlaces($season, $seriesId, $tournament['Paikka_ID']);
		
		while($place = mysql_fetch_assoc($places))
			{
			echo "<tr><td><table width='100%' class='pk_table'><tr><td class='pk_tournament_td1'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ". htmlentities($tournament['Paikka']) ."</td></tr></table></td></tr>\n";
				
			$games = PlayedGames($season, $seriesId, $place['paikka_id']);
			if(mysql_num_rows($games))
				{
				echo "<tr><td><table width='100%' class='pk_table'>\n";
				
				while($game = mysql_fetch_assoc($games))
					{
					echo "<tr><th class='pk_tournament_th' colspan='7'>". htmlentities($game['Nimi']) ."</th></tr>";
					$results = SeriesGames($game['Sarja_ID'],$place['paikka_id']);
					
					while($result = mysql_fetch_assoc($results))
						{
						echo "<tr><td class='pk_tournament_td2'>", DefHourFormat($result['Aika']) ,"</td>";
						echo "<td class='pk_tournament_td2'>". htmlentities($result['KNimi']) ."</td><td class='pk_tournament_td2'>-</td><td class='pk_tournament_td2'>". htmlentities($result['VNimi']) ."</td>";
						echo "<td class='pk_tournament_td2'>". intval($result['Kotipisteet']) ."</td><td class='pk_tournament_td2'>-</td><td class='pk_tournament_td2'>". intval($result['Vieraspisteet']) ."</td>";
						echo "</tr>\n";
						}
					}
				echo "</table></td></tr>\n";
				}
			}
		}	
	echo "</table>";
	
CloseConnection();
?>
</body>
</html>