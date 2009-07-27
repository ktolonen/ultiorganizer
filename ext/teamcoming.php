<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<?php
$style = urldecode($_GET["Style"]);
if(empty($style))
	$style='pelikone.css';
	
echo "<link rel='stylesheet' href='$style' type='text/css' />";
?>
<title>Liitokiekkoliiton Pelikone</title>
</head>
<body>

<?php
include_once '../lib/database.php';
include_once '../lib/common.functions.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once '../lib/team.functions.php';

$teamId = intval($_GET["Team"]);

OpenConnection();

if($teamId)
	{
	$season = TeamSeason($teamId);
	$tournaments = ComingTournaments($season);
	$prevTournament = "";
	if(!mysql_num_rows($tournaments))
		{
		echo "\n<p>Ei tulevia pelej&auml;.</p>\n";
		}
	else
		{
		echo "<table>";
		while($tournament = mysql_fetch_assoc($tournaments))
			{
			$games = TeamComingGames($teamId, $tournament['Paikka_ID']);
					
			if(mysql_num_rows($games))
				{
				if($tournament['Turnaus'] != $prevTournament)
					{
					if($prevTournament != "")
						echo "<tr><td><hr/></td></tr>\n";
					echo "<tr><td><h1 class='pk_h1'>". htmlentities($tournament['Turnaus']) ."</h1></td></tr>\n";				
					$prevTournament = $tournament['Turnaus'];
					}

				echo "<tr><td><table width='100%' class='pk_table'>";
				echo "<tr><th class='pk_teamplayed_th' colspan='7'>";
				echo DefWeekDateFormat($tournament['AikaAlku']) ." ". htmlentities($tournament['Paikka']);
				echo "</th></tr>\n";
				
				while($game = mysql_fetch_assoc($games))
					{
					echo "<tr><td class='pk_teamplayed_td'>", DefHourFormat($game['Aika']) ,"</td>";
					echo "<td class='pk_teamplayed_td'>". htmlentities($game['KNimi']) ."</td><td class='pk_teamplayed_td'>-</td><td class='pk_teamplayed_td'>". htmlentities($game['VNimi']) ."</td>";
					echo "<td class='pk_teamplayed_td'>". intval($game['Kotipisteet']) ."</td><td class='pk_teamplayed_td'>-</td><td class='pk_teamplayed_td'>". intval($game['Vieraspisteet']) ."</td>";
					echo "</tr>";
					}
				echo "</table></td></tr>";
				}
			}
		echo "</table>";
		}
	}
CloseConnection();
?>
</body>
</html>