<?php
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/series.functions.php';

$LAYOUT_ID = EDITSTANDING;

$season = $_GET["season"];
$poolId = $_GET["pool"];
$teamId = $_GET["team"];

$title = _("Edit");

$backurl = utf8entities($_SERVER['HTTP_REFERER']);
//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

	
//process itself on submit
if(!empty($_POST['save']))
	{
	$backurl = utf8entities($_POST['backurl']);
	$rank = 0;
	$activerank = 0;
	if(!empty($_POST['rank']))
		$rank = $_POST['rank'];
	
	if(!empty($_POST['activerank']))
		$activerank = $_POST['activerank'];
		
	SetTeamSerieRank($teamId,$poolId,$rank,$activerank);
	}

echo "<form method='post' action='?view=admin/editstanding&amp;season=$season&amp;pool=$poolId&amp;team=$teamId'>";
$info = TeamPoolInfo($teamId, $poolId);

echo "<h2>"._("Standing")."</h2>\n";

echo "<table cellpadding='2px'>
	<tr><td class='infocell'>"._("Standing").":</td>
		<td><input class='input' size='5' id='activerank' name='activerank' value='".utf8entities($info['activerank'])."'/></td><td></td></tr>

	<tr><td class='infocell'>"._("Seed").":</td>
		<td><input class='input' size='5' id='rank' name='rank' value='".utf8entities($info['poolrank'])."'/></td>
		<td></td></tr>";

	
echo "</table>";

echo "<p><input class='button' name='save' type='submit' value='"._("Save")."'/></p>";

echo "<h2>"._("Games")."</h2>\n";

$games=TeamSerieGames($teamId,$poolId);
if(mysqli_num_rows($games))
	{
	echo "<table border='0' cellpadding='4px' width='400px'>\n";
	while($row = mysqli_fetch_assoc($games))
		{
		echo "<tr>";
		echo "<td>".DefWeekDateFormat($row['time']) ."</td>";
		echo "<td>".utf8entities(TeamName($row['hometeam']))."</td>";
		echo "<td>-</td>";
		echo "<td>". utf8entities(TeamName($row['visitorteam'])) ."</td>";
		echo "<td>". intval($row['homescore']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($row['visitorscore']) ."</td>";
		echo "<td class='center'><a href='?view=admin/editgame&amp;season=$season&amp;game=".$row['game_id']."'>"._("edit")."</a></td>";
		echo "</tr>\n";	
		}
	echo "</table>";
	}
echo "<p><input type='hidden' name='backurl' value='$backurl'/>";		
echo "<input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/></p>";
echo "</form>\n";

contentEnd();
pageEnd();
?>