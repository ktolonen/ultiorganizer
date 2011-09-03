<?php
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/timetable.functions.php';
include_once 'lib/yui.functions.php';

$LAYOUT_ID = SEASONGAMES;

$season = $_GET["Season"];
$title = utf8entities(SeasonName($season)).": "._("Games");
$group = "all";

if(!empty($_GET["group"])) {
	$group  = $_GET["group"];
}



//common page
pageTopHeadOpen($title);
echo yuiLoad(array("utilities"));
?>
<script type="text/javascript">
<!--
function setId(id) 
	{
	var input = document.getElementById("hiddenDeleteId");
	input.value = id;
	}
function ChgName(index) {
	YAHOO.util.Dom.get('gamenameEdited' + index).value = 'yes';
	YAHOO.util.Dom.get("save").disabled = false;
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
  $goals = GameAllGoals($id);
  if(mysql_num_rows($goals)){
  	echo "<p class='warning'>"._("Game has")." ".mysql_num_rows($goals)." "._("goals").". "._("Goals must be removed before removing the team").".</p>";
  	$ok = false;
  }	
  if($ok){
  	DeleteGame($id);
  }
}elseif(!empty($_POST['save'])) {
  for ($i=0; $i<count($_POST['gamenameEdited']); $i++) {
  	if ($_POST['gamenameEdited'][$i] == "yes") {
  		$id = $_POST['gameId'][$i];
  		GameChangeName($id, $_POST["gn$i"]);
  	}
  }
}

echo "<form method='post' action='?view=admin/seasongames&amp;Season=$season&amp;group=$group'>";
$groups = TimetableGrouping($season, "season", "all");
if(count($groups>1)){
	echo "<p>\n";	
	foreach($groups as $grouptmp){
		if($group==$grouptmp['reservationgroup']){
			echo "<a class='groupinglink' href='?view=admin/seasongames&amp;Season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
		}else{
			echo "<a class='groupinglink' href='?view=admin/seasongames&amp;Season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'>".U_($grouptmp['reservationgroup'])."</a>";
		}
		echo "&nbsp;&nbsp;&nbsp; ";
	}
	if($group=="all"){
		echo "<a class='groupinglink' href='?view=admin/seasongames&amp;Season=$season&amp;group=all'><span class='selgroupinglink'>"._("All")."</span></a>";
	}else{
		echo "<a class='groupinglink' href='?view=admin/seasongames&amp;Season=$season&amp;group=all'>"._("All")."</a>";
	}
	echo "&nbsp;&nbsp;&nbsp; ";
	
	if($group=="unscheduled"){
		echo "<a class='groupinglink' href='?view=admin/seasongames&amp;Season=$season&amp;group=unscheduled'><span class='selgroupinglink'>"._("Unscheduled")."</span></a>";
	}else{
		echo "<a class='groupinglink' href='?view=admin/seasongames&amp;Season=$season&amp;group=unscheduled'>"._("Unscheduled")."</a>";
	}
	echo "</p>\n";	
}

$games = TimetableGames($season, "season", "all","places",$group);
$prevreservationgroup = "";
$prevlocation = 0;
$prevfield= "";
$tableopen = false;
$i=0;
while($game = mysql_fetch_assoc($games)){

    //show also games without field reservation
	if(empty($game['place_id'])){
		$game['place_id']=-1;
		$game['fieldname']="-";
		$game['reservationgroup'] = _("Unscheduled");		
	}
	
	if($prevreservationgroup != $game['reservationgroup']){
		if($tableopen){
			echo "</table>";
			$tableopen = false;
		}
		if($group=="all"){
			echo "<h2>".utf8entities($game['reservationgroup'])."</h2>";
		}
		echo "<p>"._("Print scoresheets from games").": ";
		echo "<a href='?view=user/pdfscoresheet&amp;Season=".$season."&amp;group=".urlencode($game['reservationgroup'])."'>"._("All listed")."</a>";
		echo "&nbsp;|&nbsp;";
		echo "<a href='?view=user/pdfscoresheet&amp;Season=".$season."&amp;group=".urlencode($game['reservationgroup'])."&amp;filter1=coming'>"._("Not played")."</a>";
		echo "&nbsp;|&nbsp;";
		echo "<a href='?view=user/pdfscoresheet&amp;Season=".$season."&amp;group=".urlencode($game['reservationgroup'])."&amp;filter1=coming&amp;filter2=teams'>"._("Not played with Teams")."</a>";
		echo "</p>";
		$prevreservationgroup = $game['reservationgroup'];
		$prevlocation=0;
		$prevfield= "";
	}
	if($game['place_id']!=$prevlocation || $game['fieldname']!=$prevfield){
		if($tableopen){
			echo "</table>";
			$tableopen = false;
		}
		echo "<table border='0' cellpadding='4px' style='width:100%'>\n";
		echo "<tr><th colspan='5'>".utf8entities($game['placename'])." "._("Field")." ".utf8entities($game['fieldname']);
		echo " (". DefWeekDateFormat($game['starttime']) ." ". DefHourFormat($game['starttime'])."-";
		echo DefHourFormat($game['endtime']) .")</th>";

		echo "<th colspan='6' class='right'><a href='?view=admin/schedule&amp;Reservations=".$game['reservation_id']."'>"._("Add games")."</a></th>";	
		echo "</tr>";
		$prevlocation=$game['place_id'];
		$prevfield = $game['fieldname'];
		$tableopen=true;
	}

		echo "<tr>";

		echo "<td style='width:10%'>".DefHourFormat($game['time']) ."</td>";
		
		echo "<td class='left'>";
		echo "<input type='hidden' id='gamenameEdited".$i."' name='gamenameEdited[]' value='no'/>\n";
		echo "<input type='hidden' name='gameId[]' value='".$game['game_id']."'/>\n";
		echo "<input type='text' size='15' maxlength='50' value='".utf8entities(U_($game['gamename']))."' name='gn$i' onkeypress='ChgName(".$i.")'/>";
		echo "</td>";
				
		if($game['hometeam']){
			echo "<td style='width:30%'>".utf8entities(TeamName($game['hometeam']))."</td>";
		}else{
			echo "<td class='lowlight' style='width:30%'>".utf8entities(U_($game['phometeamname']))."</td>";
		}
		echo "<td>-</td>";
		if($game['visitorteam']){
			echo "<td style='width:30%'>". utf8entities(TeamName($game['visitorteam'])) ."</td>";
		}else{
			echo "<td class='lowlight' style='width:30%'>".utf8entities(U_($game['pvisitorteamname']))."</td>";
		}
		
		echo "<td class='left' style='white-space: nowrap'>".utf8entities(U_($game['seriesname'])).", ". utf8entities(U_($game['poolname']))."</td>";
		
		echo "<td class='center'><a href='?view=admin/editgame&amp;Season=$season&amp;Game=".$game['game_id']."'>"._("edit")."</a></td>";
		
		if($game['hometeam'] && $game['visitorteam']){
			echo "<td style='width:5%'>". intval($game['homescore']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($game['visitorscore']) ."</td>";
		}else{
			echo "<td colspan='3'></td>";
		}
		if(CanDeleteGame($game['game_id'])){
			echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$game['game_id'].");\"/></td>";		
		}
		echo "</tr>\n";	
$i++;
}

if($tableopen){
	echo "</table>";
	$tableopen = false;
}
		
if($group=="unscheduled"){		
	$games = SeasonGamesNotScheduled($season);
	if(count($games))
		{
		echo "<table border='0' cellpadding='4px' width='100%'>\n";
		foreach($games as $row)
			{
			echo "<tr>";
    		echo "<td class='left'>";
    		echo "<input type='hidden' id='gamenameEdited".$i."' name='gamenameEdited[]' value='no'/>\n";
    		echo "<input type='hidden' name='gameId[]' value='".$game['game_id']."'/>\n";
    		echo "<input type='text' size='15' maxlength='50' value='".utf8entities(U_($game['gamename']))."' name='gn$i' onkeypress='ChgName(".$i.")'/>";
    		echo "</td>";
		
			if($row['hometeam']){
				echo "<td style='width:30%'>".utf8entities(TeamName($row['hometeam']))."</td>";
			}else{
				echo "<td class='lowlight' style='width:30%'>".utf8entities(U_($row['phometeamname']))."</td>";
			}
			echo "<td>-</td>";
			if($row['visitorteam']){
				echo "<td style='width:30%'>". utf8entities(TeamName($row['visitorteam'])) ."</td>";
			}else{
				echo "<td class='lowlight' style='width:30%'>".utf8entities(U_($row['pvisitorteamname']))."</td>";
			}
			echo "<td class='left' style='white-space: nowrap'>".utf8entities(U_($row['seriesname'])).", ". utf8entities(U_($row['poolname']))."</td>";
			echo "<td class='center'><a href='?view=admin/editgame&amp;Season=$season&amp;Game=".$row['game_id']."'>"._("edit")."</a></td>";
			if(CanDeleteGame($row['game_id'])){
				echo "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$row['game_id'].");\"/></td>";		
			}
			echo "</tr>\n";	
			}
		echo "</table>";
		}
}	
//stores id to delete
if($i>0){
  echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>";
  echo "<input disabled='disabled' id='save' class='button' name='save' type='submit' value='"._("Save game names")."'/></p>";
  echo "</form>\n";
  echo "<p><a href='?view=admin/reservations&amp;Season=$season'>"._("Reservation management")."</a></p>";
}
contentEnd();
pageEnd();
?>