<?php
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = SEASONSTANDINGS;

$season = $_GET["Season"];
$title = utf8entities(SeasonName($season)).": "._("Pool standings");

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setDeleteId(pool, team) 
	{
	var input = document.getElementById("PoolDeleteId");
	input.value = pool;
	var input = document.getElementById("TeamDeleteId");
	input.value = team;
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

	
//process itself on submit
if(!empty($_POST['remove_x'])){
	$pool = $_POST['PoolDeleteId'];
	$team = $_POST['TeamDeleteId'];
	if(CanDeleteTeamFromPool($pool, $team)){
	  PoolDeleteTeam($pool, $team);
	}
}

echo "<form method='post' action='?view=admin/seasonstandings&amp;Season=$season'>";
$seasoninfo = SeasonInfo($season);
$pools = SeasonPools($season);
if(!count($pools)){
  echo "<p>"._("Add pools first")."</p>\n";
}

$tour = "";
foreach($pools as $spool){

	$standings = PoolTeams($spool['pool_id'], "rank");
	$poolinfo = PoolInfo($spool['pool_id']);
	echo "<h2>".utf8entities(U_($spool['seriesname']).", ". U_($spool['poolname']))."</h2>";
	
	if($poolinfo['type']==3){// Swissdraw
		if(count($standings)){
			
			$style = "";
			
			if($poolinfo['played']){
				$style = "class='playedpool'";
			}
	
			echo "<table $style border='0' width='600px'>
				<tr><th>"._("Pos.")."</th>
				<th>"._("Team")."</th>";
			echo "<th class='center'>"._("Games")."</th>";
			echo "<th class='center'>"._("Victory Points")."</th>";
			echo "<th class='center'>"._("Opponent VPs")."</th>";
			echo "<th class='center'>"._("Margin")."</th>";
			echo "<th class='center'>"._("Goals scored")."</th>";
			if($seasoninfo['spiritpoints']){
				echo "<th class='center'>"._("Spirit points")."</th>";
			}
			echo "<th class='center'></th></tr>";
	
			foreach($standings as $row)	{
				$vp = TeamVictoryPointsByPool($spool['pool_id'], $row['team_id']);
				
				echo "<tr>";
				echo "<td>".intval($row['activerank'])."</td>";
				echo "<td><a href='?view=admin/editstanding&amp;Season=$season&amp;Pool=".$spool['pool_id']."&amp;Team=".$row['team_id']."'>",utf8entities($row['name']),"</a></td>";
				
				echo "<td class='center'>".intval($vp['games'])."</td>";
				echo"<td class='center'>".intval($vp['victorypoints'])."</td>";
				echo "<td class='center'>".intval($vp['oppvp'])."</td>";
				echo "<td class='center'>".intval($vp['margin'])."</td>";
				echo "<td class='center'>".intval($vp['score'])."</td>";
				if($seasoninfo['spiritpoints']){
					echo "<td class='center'>",number_format(SafeDivide(intval($vp['spirit']), intval($vp['games'])),1),"</td>";
				}
				
				if(CanDeleteTeamFromPool($spool['pool_id'], $row['team_id']))
					echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setDeleteId(".$spool['pool_id'] .",". $row['team_id'].");\"/></td>";
				echo "</tr>";
				}
			echo "</table>";
		}	
	}else{ 
		// regular pool or playoff
		if(count($standings)){
			
			$style = "";
			
			if($poolinfo['played']){
				$style = "class='playedpool'";
			}
	
			echo "<table $style border='0' width='600px'>
				<tr><th>"._("Pos.")."</th>
				<th>"._("Team")."</th>";
			echo "<th class='center'>"._("Games")."</th>";
			echo "<th class='center'>"._("Wins")."</th>";
			echo "<th class='center'>"._("Losses")."</th>";
			echo "<th class='center'>"._("Goals for")."</th>";
			echo "<th class='center'>"._("Goals against")."</th>";
			echo "<th class='center'>"._("Goal diff")."</th>";
			if($seasoninfo['spiritpoints']){
				echo "<th class='center'>"._("Spirit points")."</th>";
			}
			echo "<th class='center'></th></tr>";
	
			foreach($standings as $row) {
				$stats = TeamStatsByPool($spool['pool_id'], $row['team_id']);
				$points = TeamPointsByPool($spool['pool_id'], $row['team_id']);
				
				echo "<tr>";
				echo "<td>".intval($row['activerank'])."</td>";
				echo "<td><a href='?view=admin/editstanding&amp;Season=$season&amp;Pool=".$spool['pool_id']."&amp;Team=".$row['team_id']."'>",utf8entities($row['name']),"</a></td>";
				
				echo "<td class='center'>".intval($stats['games'])."</td>";
				echo"<td class='center'>".intval($stats['wins'])."</td>";
				echo "<td class='center'>",intval($stats['games'])-intval($stats['wins']),"</td>";
				echo "<td class='center'>".intval($points['scores'])."</td>";
				echo "<td class='center'>".intval($points['against'])."</td>";
				echo "<td class='center'>",(intval($points['scores'])-intval($points['against'])),"</td>";
				if($seasoninfo['spiritpoints']){
					echo "<td class='center'>",number_format(SafeDivide(intval($points['spirit']), intval($stats['games'])),1),"</td>";
				}
				
				if(CanDeleteTeamFromPool($spool['pool_id'], $row['team_id']))
					echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setDeleteId(".$spool['pool_id'] .",". $row['team_id'].");\"/></td>";
				echo "</tr>";
				}
			echo "</table>";
		}
	}
		
	if(!$poolinfo['played'] && PoolIsMoveFromPoolsPlayed($spool['pool_id']) && !PoolIsAllMoved($spool['pool_id'])){
		echo "<div class='highlight'><b><a href='?view=admin/serieteams&amp;Season=$season&amp;Series=".$spool['series_id']."&amp;Pool=".$spool['pool_id']."'>"._("Move teams")."</a></b></div>";
	}
}

//stores id to delete
echo "<p><input type='hidden' id='PoolDeleteId' name='PoolDeleteId'/></p>";
echo "<p><input type='hidden' id='TeamDeleteId' name='TeamDeleteId'/></p>";
echo "</form>\n";

contentEnd();
pageEnd();
?>