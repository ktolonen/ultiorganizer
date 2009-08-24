<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
$style = urldecode($_GET["Style"]);
if(empty($style))
	$style='pelikone.css';
	
echo "<link rel='stylesheet' href='$style' type='text/css' />";
echo "<title>"._("Liitokiekkoliiton Pelikone")."</title>
	</head>
	<body>\n";

include_once '../lib/database.php';
include_once '../lib/common.functions.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once '../lib/team.functions.php';

$season = $_GET["Season"];
$tournament = $_GET["Tournament"];
$seriesId = 0;

OpenConnection();

if(!$season)
	$season = CurrenSeason();
	
	$tournaments = Timetable($tournament, $season);
	$prevTournament = "";
	echo "<table width='95%'>";
	
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
			echo "<tr><td style='width:100%'><table width='100%' class='pk_table'><tr><td class='pk_tournament_td1'>";
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
						echo "<tr><td style='width:15%' class='pk_tournament_td2'>", DefHourFormat($result['Aika']) ,"</td>";
						echo "<td style='width:36%' class='pk_tournament_td2'>". htmlentities($result['KNimi']) ."</td>
						<td style='width:3%' class='pk_tournament_td2'>-</td>
						<td style='width:36%' class='pk_tournament_td2'>". htmlentities($result['VNimi']) ."</td>";
						if((intval($result['Kotipisteet'])+intval($result['Vieraspisteet']))==0)
							echo "<td style='text-align: center;width:4%' class='pk_tournament_td2'>?</td>
								<td style='text-align: center;width:2%' class='pk_tournament_td2'>-</td>
								<td style='text-align: center;width:4%' class='pk_tournament_td2'>?</td>";
						else
							echo "<td style='text-align: center;width:4%' class='pk_tournament_td2'>". intval($result['Kotipisteet']) ."</td>
								<td style='text-align: center;width:2%' class='pk_tournament_td2'>-</td>
								<td style='text-align: center;width:4%' class='pk_tournament_td2'>". intval($result['Vieraspisteet']) ."</td>";
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
