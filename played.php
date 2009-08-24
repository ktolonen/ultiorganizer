<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'lib/team.functions.php';
include_once 'builder.php';

$seriesId=0;
$teamId=0;
$season=0;

if(!empty($_GET["Series"]))
	$seriesId = intval($_GET["Series"]);
if(!empty($_GET["Team"]))
	$teamId = intval($_GET["Team"]);

if($teamId)
	$LAYOUT_ID = SEASONPLAYED;
elseif($seriesId)
	$LAYOUT_ID = SERIEPLAYED;
else
	$LAYOUT_ID = TEAMPLAYED;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content
OpenConnection();

if($teamId)
	{
	$season = TeamSeason($teamId);
	$tournaments = PlayedTournaments($season);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>"._("Ei pelattuja pelej&auml;").".</p>\n";	
		}
		
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		$games = TeamTournamentGames($teamId, $tournament['Paikka_ID']);
				
		if(mysql_num_rows($games))
			{
			if($tournament['Turnaus'] != $prevTournament)
				{
				if($prevTournament != "")
					echo "<hr/>\n";
				echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>\n";				
				$prevTournament = $tournament['Turnaus'];
				}

			echo "<table cellpadding='2' border='0' cellspacing='0' style='width:450px'>";
			echo "<tr><th align='left' colspan='9'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
			echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
			echo "</th></tr>\n";
			
			while($game = mysql_fetch_assoc($games))
				{
				echo "<tr><td style='width:6%'>", DefHourFormat($game['Aika']) ,"</td>";
				echo "<td style='width:20%'>". htmlentities($game['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:20%'>". htmlentities($game['VNimi']) ."</td>";
				if((intval($game['Kotipisteet'])+intval($game['Vieraspisteet']))==0)
					echo "<td style='width:5%'>?</td><td style='width:2%'>-</td><td style='width:5%'>?</td>";
				else
					echo "<td style='width:5%'>". intval($game['Kotipisteet']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($game['Vieraspisteet']) ."</td>";
				if (intval($game['Maaleja'])>0)
					echo "<td style='width:15%'><a href='gameplay.php?Game=". $game['Peli_ID'] ."'>"._("Pelin kulku")."</a></td>";
				else
					echo "<td style='width:15%'>"._("es")."</td>";

				echo"<td style='width:15%'><a href='gamecard.php?Team1=". htmlentities($game['kId']) ."&amp;Team2=". htmlentities($game['vId']) . "'>";
				echo _("Pelihistoria")."</a></td>";					
				echo "</tr>";
				}
			echo "</table>";
			}
		}
	}
elseif($seriesId)
	{
	$tournaments = SeriesPlayedTournaments($seriesId);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>"._("Ei pelattuja pelej&auml;").".</p>\n";	
		}
		
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		
		if($tournament['Turnaus'] != $prevTournament)
			{
			if($prevTournament != "")
				echo "<hr/>\n";
			echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>";
			$prevTournament = $tournament['Turnaus'];
			}
			
		$places = TournamentPlaces($season, $seriesId, $tournament['Paikka_ID']);
		
		while($place = mysql_fetch_assoc($places))
			{
			echo "<table cellpadding='2' border='0' cellspacing='0' style='width:450px'>";
			echo "<tr><th align='left' colspan='9'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
			echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
			echo "</th></tr>\n";
				
			$games = PlayedGames($season, $seriesId, $place['paikka_id']);
			if(mysql_num_rows($games))
				{
				//echo "<table cellpadding='2' border='0' cellspacing='0' style='width:500px'>\n";
				
				while($game = mysql_fetch_assoc($games))
					{
					//echo "<tr><th colspan='8' align='left'><b>". htmlentities($game['Nimi']) ."</b></th></tr>";
					$results = SeriesGames($game['Sarja_ID'],$place['paikka_id']);
					
					while($result = mysql_fetch_assoc($results))
						{
						echo "<tr><td style='width:10%'>", DefHourFormat($result['Aika']) ,"</td>";
						echo "<td style='width:25%'>". htmlentities($result['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:25%'>". htmlentities($result['VNimi']) ."</td>";
						if((intval($result['Kotipisteet'])+intval($result['Vieraspisteet']))==0)
							echo "<td style='width:5%'>?</td><td style='width:2%'>-</td><td style='width:10%'>?</td>";
						else
							echo "<td style='width:5%'>". intval($result['Kotipisteet']) ."</td><td style='width:2%'>-</td><td style='width:10%'>". intval($result['Vieraspisteet']) ."</td>";
						if (intval($result['Maaleja'])>0)
							echo "<td style='width:15%'><a href='gameplay.php?Game=". $result['Peli_ID'] ."'>"._("Pelin kulku")."</a></td>";
						else
							echo "<td style='width:15%'>"._("es")."</td>";

						echo "</tr>";
						}
					}
				}
			echo "</table>";
			}
		echo "<p></p>\n";
		}
	}
else
	{
	$season = CurrenSeason();
	$tournaments = PlayedTournaments($season);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>"._("Ei pelattuja pelej&auml;").".</p>\n";	
		}
		
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		
		if($tournament['Turnaus'] != $prevTournament)
			{
			if($prevTournament != "")
				echo "<hr/>\n";
			echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>";
			$prevTournament = $tournament['Turnaus'];
			}
			
		$places = TournamentPlaces($season, $seriesId, $tournament['Paikka_ID']);
		
		while($place = mysql_fetch_assoc($places))
			{
			echo "<table border='0'><tr><td class='placeheader'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
			echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
			echo "</td></tr></table>\n";
				
			$games = PlayedGames($season, $seriesId, $place['paikka_id']);
			if(mysql_num_rows($games))
				{
				echo "<table cellpadding='2' border='0' cellspacing='0' style='width:500px'>\n";
				
				while($game = mysql_fetch_assoc($games))
					{
					echo "<tr><th colspan='8' align='left'><b>". htmlentities($game['Nimi']) ."</b></th></tr>";
					$results = SeriesGames($game['Sarja_ID'],$place['paikka_id']);
					
					while($result = mysql_fetch_assoc($results))
						{
						echo "<tr><td style='width:10%'>", DefHourFormat($result['Aika']) ,"</td>";
						echo "<td style='width:25%'>". htmlentities($result['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:25%'>". htmlentities($result['VNimi']) ."</td>";
						if((intval($result['Kotipisteet'])+intval($result['Vieraspisteet']))==0)
							echo "<td style='width:5%'>?</td><td style='width:2%'>-</td><td style='width:10%'>?</td>";
						else
							echo "<td style='width:5%'>". intval($result['Kotipisteet']) ."</td><td style='width:2%'>-</td><td style='width:10%'>". intval($result['Vieraspisteet']) ."</td>";
							
						if (intval($result['Maaleja'])>0)
							echo "<td style='width:15%' align='right'><a href='gameplay.php?Game=". $result['Peli_ID'] ."'>"._("Pelin kulku")."</a></td>";
						else
							echo "<td style='width:15%' align='right'>"._("es")."</td>";

						//echo"<td style='width:15%'><a href='gamecard.php?Team1=". htmlentities($result['kId']) ."&amp;Team2=". htmlentities($result['vId']) . "'>"._("Otteluhistoria")."</a></td>";					
						echo "</tr>";
						}
					}
				echo "</table>";
				}
			}
		echo "<p></p>\n";
		}	
	}
CloseConnection();
echo "<p><a href='javascript:history.go(-1);'>"._("Palaa")."</a></p>";
contentEnd();
pageEnd();
?>
