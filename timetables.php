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

if($teamId)
	$LAYOUT_ID = SEASONTIMETABLES;
else
	$LAYOUT_ID = TEAMTIMETABLES;
	
//common page
pageTop($print);
leftMenu($LAYOUT_ID,$print);
contentStart();

//content
OpenConnection();

// team games
if($teamId)
	{
	$season = TeamSeason($teamId);
	$tournaments = ComingTournaments($season);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>Ei tulevia pelej&auml;.</p>\n";	
		}
		
	while($tournament = mysql_fetch_assoc($tournaments))
		{
		$games = TeamComingGames($teamId, $tournament['Paikka_ID']);
		
		if(mysql_num_rows($games))
			{
			if($tournament['Turnaus'] != $prevTournament)
				{
				if($prevTournament != "")
					echo "<hr/>\n";
				
				echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>\n";				
				$prevTournament = $tournament['Turnaus'];
				}
								
			echo "<table cellpadding='2' border='0' cellspacing='0' width='400px'>";
			echo "<tr><th align='left' colspan='5'>";
			echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
			echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
			echo "</th></tr>\n";
			
			while($game = mysql_fetch_assoc($games))
				{
				echo "<tr><td style='width:10%'>". DefHourFormat($game['Aika']) ."</td>";
				echo "<td style='width:25%'>". htmlentities($game['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:25%'>". htmlentities($game['VNimi']) ."</td>";
				if(!$print)
					echo"<td style='width:15%' align='right'><a href='gamecard.php?Team1=". htmlentities($game['kId']) ."&amp;Team2=". htmlentities($game['vId']) . "'>otteluhistoria </a></td>";
				echo "</tr>\n";
				}
			echo "</table>\n";
			}
		}
		
	}
// season games	
else
	{
	$season = CurrenSeason();
	$tournaments = ComingTournaments($season);
	$prevTournament = "";
	
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>Ei tulevia pelej&auml;.</p>\n";	
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
				
		echo "<table border='0'><tr><td class='placeheader'>";
		echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
		echo "<a href='placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
		echo "</td></tr></table>\n";
		
		$series = SeriesPlayed($tournament['Paikka_ID'], $season);			
		if(mysql_num_rows($series))
			{
			echo "<table cellpadding='2' border='0' cellspacing='0' width='400px'>\n";
			
			while($serie = mysql_fetch_assoc($series))
				{
				$games = SeriesGames($serie['Sarja_ID'],$tournament['Paikka_ID']);		
				
				echo "<tr><th colspan='5' align='left'>". htmlentities($serie['Nimi']) ."</th></tr>";
				
				while($game = mysql_fetch_assoc($games))
					{
					echo "<tr><td style='width:10%'>". DefHourFormat($game['Aika']) ."</td>";
					echo "<td style='width:25%'>". htmlentities($game['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:25%'>". htmlentities($game['VNimi']) ."</td>";
					if(!$print)
						echo"<td style='width:15%' align='right'><a href='gamecard.php?Team1=". htmlentities($game['kId']) ."&amp;Team2=". htmlentities($game['vId']) . "'>otteluhistoria </a></td>";
					echo "</tr>\n";
					}
				}
				
				echo "</table>";
			}
			echo "<p></p>\n";
		}
	}
if($print)
	echo "<hr/><div style='text-align:right'><a href='timetables.php?Team=$teamId&amp;Series=$seriesId'>Takaisin</a></div>";
elseif($teamId && mysql_num_rows($tournaments))
	{
	echo "<hr/><div style='text-align:left;float: left;clear: left'><a href='teamical.php?Team=$teamId'>iCalendar -muodossa </a></div>";
	echo "<div style='text-align:right'><a href='timetables.php?Team=$teamId&amp;Series=$seriesId&amp;Print=1'>Tulostettava versio</a></div>";
	}
else
	{
	if(mysql_num_rows($tournaments))
		echo "<hr/><div style='text-align:right'><a href='timetables.php?Team=$teamId&amp;Series=$seriesId&amp;Print=1'>Tulostettava versio</a></div>";
	}
	
CloseConnection();
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>
<?php
contentEnd();
pageEnd();
?>
