<?php
include_once 'view_ids.inc.php';
include_once 'builder.php';
include_once '../lib/database.php';
include_once '../lib/team.functions.php';
include_once '../lib/common.functions.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';

include_once 'lib/user.functions.php';
$LAYOUT_ID = RESPONSEGAMES;

//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
//content
OpenConnection();

$userinfo = UserInfo($_SESSION['id']);
$team_id = $userinfo['team'];

$season = TeamSeason($team_id);
$tournaments = PlayedTournaments($season);
$prevTournament = "";

if(!mysql_num_rows($tournaments))
	{
	echo "\n<p>"._("Ei vastuupelej&auml;").".</p>\n";	
	}
else
	{
	echo "\n
	<div style='width:600px'>
	<p>"._("Sy&ouml;t&auml; vastuullasi oleviin peleihin").":</p>
	<ol>
		<li> "._("Tulos")." "._("(jos ei l&auml;hetetty tekstiviestill&auml;)")." </li>
		<li> "._("Peliss&auml; pelanneet pelaajat")." </li>
		<li> "._("Pelin p&ouml;yt&auml;kirja")." </li>
	</ol>
	<p>"._("Tarkista t&auml;m&auml;n j&auml;lkeen sy&ouml;tt&auml;m&auml;si p&ouml;yt&auml;kirjan oikeellisuus 'pelin kulku' linkist&auml;").".</p>
	<noscript> 
	<p><b>"._("P&ouml;yt&auml;kirjoja sy&ouml;tt&auml;ess&auml; tarvitaan JavaScript-komentoja. Aktivoi selaimen JavaScript tuki jatkaaksesi!")."</b></p>
	</noscript> 
	</div><hr/>";	
	}
	
while($tournament = mysql_fetch_assoc($tournaments))
	{
	$games = TeamResponsibleGames($team_id, $tournament['Paikka_ID']);
			
	if(mysql_num_rows($games))
		{
		if($tournament['Turnaus'] != $prevTournament)
			{
			if($prevTournament != "")
				echo "<hr/>\n";
			echo "<h1>". htmlentities($tournament['Turnaus']) ."</h1>\n";				
			$prevTournament = $tournament['Turnaus'];
			}

		
		echo "<table cellpadding='2' border='0' style='width:600px'>";
		echo "<tr><th align='left' colspan='11'>";
		echo DefWeekDateFormat($tournament['AikaAlku']) ." ";
		echo "<a href='../placeinfo.php?Place=".$tournament['Paikka_ID']."'>". htmlentities($tournament['Paikka']) ."</a>";
		echo "</th></tr>\n";
		
		while($game = mysql_fetch_assoc($games))
			{
			echo "<tr><td style='width:6%'>", DefHourFormat($game['Aika']) ,"</td>";
			echo "<td style='width:20%'>". htmlentities($game['KNimi']) ."</td><td style='width:2%'>-</td><td style='width:20%'>". htmlentities($game['VNimi']) ."</td>";
			echo "<td style='width:5%'>". intval($game['Kotipisteet']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($game['Vieraspisteet']) ."</td>";
			if (intval($game['Maaleja'])>0)
					echo "<td style='width:15%'><a href='../gameplay.php?Game=". $game['Peli_ID'] ."'>pelin kulku</a></td>";
				else
					echo "<td style='width:15%'>es</td>";
			echo "<td><input class='button' type='button' name='result'  value='tulos' onclick=\"window.location.href='addresult.php?Game=".$game['Peli_ID']."'\"/></td>";
			echo "<td><input class='button' type='button' name='playerlist' value='pelaajat' onclick=\"window.location.href='addplayerlists.php?Game=".$game['Peli_ID']."'\"/></td>";
			echo "<td><input class='button' type='button' name='minutes' value='p&ouml;yt&auml;kirja' onclick=\"window.location.href='addscoresheet.php?Game=".$game['Peli_ID']."'\"/></td>";
			echo "</tr>";
			}
		echo "</table>";
		
		}
	}

CloseConnection();
//common end
contentEnd();
pageEnd();
?>
