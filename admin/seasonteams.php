<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

$LAYOUT_ID = SEASONTEAMS;
$html = "";
$season = $_GET["Season"];
$title = utf8entities(SeasonName($season)).": "._("Teams");
$seasonInfo = SeasonInfo($season);

//common page
pageTopHeadOpen($title);
?>
<script type="text/javascript">
<!--
function setId(id){
	var input = document.getElementById("hiddenDeleteId");
	input.value = id;
}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
	
//process itself on submit
if(!empty($_POST['remove_x'])){
	$id = $_POST['hiddenDeleteId'];
	$ok = true;
		
	//run some test to for safe deletion
	$games = TeamGames($id);
	if(mysql_num_rows($games)){
		$html .= "<p class='warning'>"._("Team has")." ".mysql_num_rows($games)." "._("game").". ".("Pelit pit&auml;&auml; poistaa ennen joukkueen poistamista").".</p>";
		$ok = false;
	}
		
	$players = TeamPlayerList($id);
	if(mysql_num_rows($players)){
		$html .= "<p class='warning'>"._("Team has")." ".mysql_num_rows($players)." "._("players").". "._("Players must be removed before removing the team").".</p>";
		$ok = false;
	}
		
	if($ok)
		DeleteTeam($id);
}

$html .= "<form method='post' action='?view=admin/seasonteams&amp;Season=$season'>";

$series = SeasonSeries($season);

foreach($series as $row){
	$html .= "<h2>".utf8entities($row['name'])."</h2>\n";
	
	$html .= "<table border='0' cellpadding='2px'>\n";

	$html .= "<tr><th>#</th>";
	$html .= "<th style='width:20%'>"._("Name")."</th>";
	$html .= "<th  style='width:20%'>"._("Pool")."</th>";
	if(!intval($seasonInfo['isnationalteams'])){
		$html .= "<th  style='width:20%'>"._("Club")."</th>";
	}
	if(intval($seasonInfo['isinternational'])){
		$html .= "<th style='width:10%'>"._("Country")."</th>";
	}
	
	$html .= "<th style='width:10%;white-space: nowrap;'>"._("Roster")."</th><th>"._("Contact person")."</th><th></th></tr>\n";

	$teams = SeriesTeams($row['series_id'],true);
	
	foreach($teams as $team){
		$teaminfo = TeamFullInfo($team['team_id']);
		$poolname = U_($team['poolname']);
		if(!empty($team['name'])){
			if (intval($seasonInfo['isnationalteams'])) {
				$teamname = utf8entities(U_($team['name']));
			} else {
				$teamname = utf8entities($team['name']);
			}
		}else{
			$teamname = _("No name");
		}
		$html .= "<tr>";
		$html .= "<td>".$team['rank']."</td>";
		$html .= "<td><a href='?view=admin/addseasonteams&amp;Season=$season&amp;Team=".$team['team_id']."&amp;Series=".$row['series_id']."'>".$teamname."</a></td>";
		$html .= "<td>".utf8entities($poolname)."</td>";
				
		if(!intval($seasonInfo['isnationalteams'])){
			$html .= "<td>".utf8entities($team['clubname'])."</td>";
		}
		if(intval($seasonInfo['isinternational'])){
		    if($team['countryname']){
			  $html .= "<td>".utf8entities(_($team['countryname']))."</td>";
		    }else{
		      $html .= "<td>-</td>";
		    }
		}
	
		$html .= "<td class='center'><a href='?view=user/teamplayers&amp;Team=".$team['team_id']."'>"._("Roster")."</a></td>";			
		$admins = getTeamAdmins($team['team_id']);
		if(mysql_num_rows($admins)){
			$html .= "<td>";
			while($row1 = mysql_fetch_assoc($admins)){
				$html .= "<a href='?view=user/userinfo&amp;user=".$row1['userid']."'>".utf8entities($row1['name'])."</a>";
		
				if($row1['email'])
					$html .= "&nbsp;<a href='mailto:".$row1['email']."'>@</a>";
				$html .= "<br/>";
			}
			$html .= "</td>";
		}else{
			$html .= "<td>-</td>";
		}

		if(CanDeleteTeam($team['team_id'])){
			$html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$team['team_id'].");\"/></td>";
		}
		$html .= "</tr>\n";	
	}
	$html .= "</table><p><input class='button' name='add' type='button' value='"._("Add")."' onclick=\"window.location.href='?view=admin/addseasonteams&amp;Season=$season&amp;Series=".$row['series_id']."'\"/></p>";
	$html .= "<p><a href='?view=user/pdfscoresheet&amp;series=".$row['series_id']."'>"._("Print team rosters")."</a></p>";	
}
//stores id to delete
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";

$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
?>