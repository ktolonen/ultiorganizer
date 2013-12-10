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

$html="";
$season = $_GET["season"];
$series = SeasonSeries($season);
$series_id = CurrentSeries($season);
$seasoninfo = SeasonInfo($season);

$title = utf8entities(SeasonName($season)).": "._("Games");
$group = "all";

if(!empty($_GET["group"])) {
	$group  = $_GET["group"];
}

$_SESSION['hide_played_pools'] = !empty($_SESSION['hide_played_pools']) ? $_SESSION['hide_played_pools'] : 0;
$_SESSION['hide_played_games'] = !empty($_SESSION['hide_played_games']) ? $_SESSION['hide_played_games'] : 0;
 
if(!empty($_GET["v"])) {
	$visibility = $_GET["v"];
	
	if($visibility=="pool"){
      $_SESSION['hide_played_pools'] = $_SESSION['hide_played_pools'] ? 0 : 1;
	}elseif($visibility=="game"){
      $_SESSION['hide_played_games'] = $_SESSION['hide_played_games'] ? 0 : 1;
	}
}
if (!empty($_GET["massinput"])) {
  $_SESSION['massinput'] = true;
} else {
  $_SESSION['massinput'] = false;
}

//process itself on submit
if(!empty($_POST['remove_x'])){
  $id = $_POST['hiddenDeleteId'];
  $ok = true;
  	
  //run some test to for safe deletion
  $goals = GameAllGoals($id);
  if(mysql_num_rows($goals)){
  	$html .= "<p class='warning'>"._("Game has")." ".mysql_num_rows($goals)." "._("goals").". "._("Goals must be removed before removing the team").".</p>";
  	$ok = false;
  }	
  if($ok){
  	DeleteGame($id);
  }
}elseif(!empty($_POST['save'])) {
//   for ($i=0; $i<count($_POST['gamenameEdited']); $i++) {
//   	if ($_POST['gamenameEdited'][$i] == "yes") {
//   		$id = $_POST['gameId'][$i];
//   		GameChangeName($id, $_POST["gn$i"]);
//   	}
//   }
  $scores = array();
  foreach ($_POST['scoreId'] as $key=>$value) {
    $scores[$key]['gameid'] = $value;
  }
  foreach ($_POST['homescore'] as $key=>$value) {
    $scores[$key]['home'] = $value;
  }
  foreach ($_POST['visitorscore'] as $key=>$value) {
    $scores[$key]['visitor'] = $value;
  }
  foreach ($scores as $score) {
    $gameId = $score['gameid'];
    $game = GameInfo($gameId);
    if (empty($score['home'])) {
      if (empty($score['visitor']))
        if ($game['hasstarted']) 
          GameClearResult($gameId);
    } elseif (!empty($score['visitor']) && (!$game['hasstarted'] || $game['isongoing'] 
        || $game['homescore'] != $score['home'] || $game['visitorscore']!=$score['visitor'])) {
      GameSetResult($gameId, $score['home'], $score['visitor']);
    }
  }
}

//common page
pageTopHeadOpen($title);
$html .= yuiLoad(array("utilities"));
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

foreach($series as $row){
  $menutabs[U_($row['name'])]="?view=admin/seasongames&season=".$season."&series=".$row['series_id'];
}
$menutabs[_("...")]="?view=admin/seasonseries&season=".$season;
pageMenu($menutabs,"?view=admin/seasongames&season=".$season."&series=".$series_id);

$html .= "<table width='100%'><tr><td>";
if($_SESSION['hide_played_pools']){
  $html .= "<a href='?view=admin/seasongames&amp;season=$season&amp;group=$group&amp;v=pool'>"._("Show played pools")."</a> ";
}else{
  $html .= "<a href='?view=admin/seasongames&amp;season=$season&amp;group=$group&amp;v=pool'>"._("Hide played pools")."</a> ";
}
if($_SESSION['hide_played_games']){
  $html .= "| <a href='?view=admin/seasongames&amp;season=$season&amp;group=$group&amp;v=game'>"._("Show played games")."</a> ";
}else{
  $html .= "| <a href='?view=admin/seasongames&amp;season=$season&amp;group=$group&amp;v=game'>"._("Hide played games")."</a> ";
}
$html .= "</td><td style='text-align:right;'>";
if ($_SESSION ['massinput']) {
  $html .= "<a href='?view=admin/seasongames&amp;season=$season&amp;group=$group'>" . _ ( "Just display values" ) . "</a></td></tr></table>";
} else {
  $html .= "<a href='?view=admin/seasongames&amp;season=$season&amp;group=$group&amp;massinput=1'>" . _ ( "Mass input" ) . "</a></td></tr></table>";
}
$html .= "<form method='post' action='?view=admin/seasongames&amp;season=$season&amp;group=$group'>";
/*
$groups = TimetableGrouping($season, "season", "all");
if(count($groups>1)){
	$html .= "<p>\n";	
	foreach($groups as $grouptmp){
		if($group==$grouptmp['reservationgroup']){
			$html .= "<a class='groupinglink' href='?view=admin/seasongames&amp;season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
		}else{
			$html .= "<a class='groupinglink' href='?view=admin/seasongames&amp;season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'>".U_($grouptmp['reservationgroup'])."</a>";
		}
		$html .= "&nbsp;&nbsp;&nbsp; ";
	}
	if($group=="all"){
		$html .= "<a class='groupinglink' href='?view=admin/seasongames&amp;season=$season&amp;group=all'><span class='selgroupinglink'>"._("All")."</span></a>";
	}else{
		$html .= "<a class='groupinglink' href='?view=admin/seasongames&amp;season=$season&amp;group=all'>"._("All")."</a>";
	}
	$html .= "&nbsp;&nbsp;&nbsp; ";
	
	if($group=="unscheduled"){
		$html .= "<a class='groupinglink' href='?view=admin/seasongames&amp;season=$season&amp;group=unscheduled'><span class='selgroupinglink'>"._("Unscheduled")."</span></a>";
	}else{
		$html .= "<a class='groupinglink' href='?view=admin/seasongames&amp;season=$season&amp;group=unscheduled'>"._("Unscheduled")."</a>";
	}
	$html .= "</p>\n";	
}
*/

$pools = SeriesPools($series_id);

$html .= "<table class='admintable'>\n";

foreach ($pools as $pool) {
  
  $poolinfo = PoolInfo($pool['pool_id']);
  if ($_SESSION['hide_played_pools'] && $poolinfo['played']) {
    continue;
  }
  
  $games = TimetableGames($pool['pool_id'], "pool", "all", "time", $group);
  
  $html .= "<tr><th colspan='7'>" . utf8entities(U_($pool['name'])) . "</th>";
  $html .= "<th class='right'><a class='thlink' href='?view=user/pdfscoresheet&amp;season=$season&amp;pool=" . $pool['pool_id'] . "'>" . _("Print scoresheets") . "</a></th>";
  $html .= "<th></th></tr>";
  
  while ($game = mysql_fetch_assoc($games)) {
    $i = $game['game_id'];
    
    if (GameHasStarted($game)) {
      if ($_SESSION['hide_played_games']) {
        continue;
      }
      // $html .= "<tr class='tablelowlight'>";
    }
    
    $html .= "<tr class='admintablerow'>";
    
    $html .= "<td style='width:250px'>" . ShortDate($game['starttime']) . " " . DefHourFormat($game['time']) . "<br/>";
    $html .= utf8entities($game['placename']) . " " . utf8entities($game['fieldname']) . "</td>";
    // $html .= " (". DefWeekDateFormat($game['starttime']) ." ". DefHourFormat($game['starttime'])."-";
    // $html .= DefHourFormat($game['endtime']) .")</th>";
    
    // $html .= "<td class='left' style='width:10%'>";
    // $html .= "<input type='hidden' id='gamenameEdited".$i."' name='gamenameEdited[]' value='no'/>\n";
    // $html .= "<input type='hidden' name='gameId[]' value='".$game['game_id']."'/>\n";
    // $html .= "<input type='text' size='10' maxlength='50' value='".utf8entities(U_($game['gamename']))."' name='gn$i' onkeypress='ChgName(".$i.")'/>";
    // $html .= "</td>";
    
    if ($game['hometeam']) {
      $html .= "<td style='width:200px'>" . utf8entities(TeamName($game['hometeam'])) . "</td>";
    }else {
      $html .= "<td class='lowlight' style='width:200px'>" . utf8entities(U_($game['phometeamname'])) . "</td>";
    }
    $html .= "<td style='width:5px'>-</td>";
    if ($game['visitorteam']) {
      $html .= "<td style='width:200px'>" . utf8entities(TeamName($game['visitorteam'])) . "</td>";
    }else {
      $html .= "<td class='lowlight' style='width:200px'>" . utf8entities(U_($game['pvisitorteamname'])) . "</td>";
    }
    
    // $html .= "<td class='left' style='white-space: nowrap'>".utf8entities(U_($game['seriesname'])).", ". utf8entities(U_($game['poolname']))."</td>";
    
    // $html .= "<td class='center'><a href='?view=admin/editgame&amp;season=$season&amp;game=".$game['game_id']."'>"._("edit")."</a></td>";
    if ($_SESSION['massinput']) {
      $html .= "<td style='width:25px'><input type='hidden' id='scoreId" . $i . "' name='scoreId[]' value='$i'/>" . "<input type='text' size='3' maxlength='5' value='" . intval($game['homescore']) . "' id='homescore$i' name='homescore[]' onkeypress='ChgResult(" . $i . ")'/></td>" . "<td style='width:5px'>-</td><td style='width:25px'><input type='text' size='3' maxlength='5' value='" . intval($game['visitorscore']) . "' id='visitorscore$i' name='visitorscore[]' onkeypress='ChgResult(" . $i . ")'/></td>";
    }else {
      if (GameHasStarted($game)) {
        if ($game['isongoing'])
          $html .= "<td style='width:25px'><em>" . intval($game['homescore']) . "</em></td><td style='width:5px'>-</td><td style='width:25px'><em>" . intval($game['visitorscore']) . "</em></td>";
        else
          $html .= "<td style='width:25px'>" . intval($game['homescore']) . "</td><td style='width:5px'>-</td><td style='width:25px'>" . intval($game['visitorscore']) . "</td>";
        // $html .= "<td style='width:15%'><a href='?view=gameplay&amp;game=". $game['game_id'] ."'>".intval($game['homescore']) ." - ". intval($game['visitorscore'])."</a></td>";
      }else {
        // $html .= "<td colspan='3'></td>";
        // $html .= "<td style='width:15%'>? - ?</td>";
        $html .= "<td style='width:25px'>?</td><td style='width:5px'>-</td><td style='width:25px'>?</td>";
      }
      if ($game['hometeam'] && $game['visitorteam']) {
        $html .= "<td style='width:300px' class='right'><a href='?view=user/addresult&amp;game=" . $game['game_id'] . "'>" . _("Result") . "</a> | ";
        $html .= "<a href='?view=user/addplayerlists&amp;game=" . $game['game_id'] . "'>" . _("Players") . "</a> | ";
        $html .= "<a href='?view=user/addscoresheet&amp;game=" . $game['game_id'] . "'>" . _("Scoresheet") . "</a>";
        if ($seasoninfo['spiritpoints']) {
          $html .= " | <a href='?view=user/addspirit&amp;game=" . $game['game_id'] . "'>" . _("Spirit") . "</a>";
        }
        if (ShowDefenseStats()) {
          $html .= " | <a href='?view=user/adddefensesheet&amp;game=" . $game['game_id'] . "'>" . _("Defensesheet") . "</a>";
        }
        $html .= "</td>";
      }else {
        $html .= "<td style='width:300px'></td>";
      }
    }
    $html .= "<td style='width:60px;'>";
    $html .= "<a href='?view=admin/editgame&amp;season=$season&amp;game=" . $game['game_id'] . "'><img class='deletebutton' src='images/settings.png' alt='D' title='" . _("edit details") . "'/></a>";
    
    if (CanDeleteGame($game['game_id'])) {
      $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $game['game_id'] . ");\"/>";
    }
    $html .= "</td>\n";
    
    $html .= "</tr>\n";
  }
}
$html .= "</table>";

if ($_SESSION['massinput']) {
  $html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
}
  
/*
while($game = mysql_fetch_assoc($games)){

    //show also games without field reservation
	//if(empty($game['place_id'])){
	//	$game['place_id']=-1;
	//	$game['fieldname']="-";
	//	$game['reservationgroup'] = _("Unscheduled");		
	//}
	
	//if($prevreservationgroup != $game['reservationgroup']){
		if($tableopen){
			$html .= "</table>";
			$tableopen = false;
		}
		//if($group=="all"){
		//	$html .= "<h2>".utf8entities($game['reservationgroup'])."</h2>";
		//}
		//$html .= "<p>"._("Print scoresheets from games").": ";
		//$html .= "<a href='?view=user/pdfscoresheet&amp;season=".$season."&amp;group=".urlencode($game['reservationgroup'])."'>"._("All listed")."</a>";
		//$html .= "&nbsp;|&nbsp;";
		//$html .= "<a href='?view=user/pdfscoresheet&amp;season=".$season."&amp;group=".urlencode($game['reservationgroup'])."&amp;filter1=coming'>"._("Not played")."</a>";
		//$html .= "&nbsp;|&nbsp;";
		//$html .= "<a href='?view=user/pdfscoresheet&amp;season=".$season."&amp;group=".urlencode($game['reservationgroup'])."&amp;filter1=coming&amp;filter2=teams'>"._("Not played with Teams")."</a>";
		//$html .= "</p>";
		//$prevreservationgroup = $game['reservationgroup'];
		//$prevlocation=0;
		//$prevfield= "";
	//}
	//if($game['place_id']!=$prevlocation || $game['fieldname']!=$prevfield){
		//if($tableopen){
		//	$html .= "</table>";
		//	$tableopen = false;
		//}
		$html .= "<table border='0' cellpadding='4px' style='width:100%'>\n";
		$html .= "<tr><th colspan='5'>".utf8entities($game['placename'])." "._("Field")." ".utf8entities($game['fieldname']);
		$html .= " (". DefWeekDateFormat($game['starttime']) ." ". DefHourFormat($game['starttime'])."-";
		$html .= DefHourFormat($game['endtime']) .")</th>";

		$html .= "<th colspan='6' class='right'><a href='?view=admin/schedule&amp;Reservations=".$game['reservation_id']."'>"._("Add games")."</a></th>";	
		$html .= "</tr>";
		$prevlocation=$game['place_id'];
		$prevfield = $game['fieldname'];
		$tableopen=true;
	}

		$html .= "<tr>";

		$html .= "<td style='width:10%'>".DefHourFormat($game['time']) ."</td>";
		
		$html .= "<td class='left'>";
		$html .= "<input type='hidden' id='gamenameEdited".$i."' name='gamenameEdited[]' value='no'/>\n";
		$html .= "<input type='hidden' name='gameId[]' value='".$game['game_id']."'/>\n";
		$html .= "<input type='text' size='15' maxlength='50' value='".utf8entities(U_($game['gamename']))."' name='gn$i' onkeypress='ChgName(".$i.")'/>";
		$html .= "</td>";
				
		if($game['hometeam']){
			$html .= "<td style='width:30%'>".utf8entities(TeamName($game['hometeam']))."</td>";
		}else{
			$html .= "<td class='lowlight' style='width:30%'>".utf8entities(U_($game['phometeamname']))."</td>";
		}
		$html .= "<td>-</td>";
		if($game['visitorteam']){
			$html .= "<td style='width:30%'>". utf8entities(TeamName($game['visitorteam'])) ."</td>";
		}else{
			$html .= "<td class='lowlight' style='width:30%'>".utf8entities(U_($game['pvisitorteamname']))."</td>";
		}
		
		$html .= "<td class='left' style='white-space: nowrap'>".utf8entities(U_($game['seriesname'])).", ". utf8entities(U_($game['poolname']))."</td>";
		
		$html .= "<td class='center'><a href='?view=admin/editgame&amp;season=$season&amp;game=".$game['game_id']."'>"._("edit")."</a></td>";
		
		if($game['hometeam'] && $game['visitorteam']){
			$html .= "<td style='width:5%'>". intval($game['homescore']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($game['visitorscore']) ."</td>";
		}else{
			$html .= "<td colspan='3'></td>";
		}
		if(CanDeleteGame($game['game_id'])){
			$html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$game['game_id'].");\"/></td>";		
		}
		$html .= "</tr>\n";	
$i++;
}
*/
/*
if($tableopen){
	$html .= "</table>";
	$tableopen = false;
}
	
if($group=="unscheduled"){		
	$games = SeasonGamesNotScheduled($season);
	if(count($games))
		{
		$html .= "<table border='0' cellpadding='4px' width='100%'>\n";
		foreach($games as $row)
			{
			$html .= "<tr>";
    		$html .= "<td class='left'>";
    		$html .= "<input type='hidden' id='gamenameEdited".$i."' name='gamenameEdited[]' value='no'/>\n";
    		$html .= "<input type='hidden' name='gameId[]' value='".$game['game_id']."'/>\n";
    		$html .= "<input type='text' size='15' maxlength='50' value='".utf8entities(U_($game['gamename']))."' name='gn$i' onkeypress='ChgName(".$i.")'/>";
    		$html .= "</td>";
		
			if($row['hometeam']){
				$html .= "<td style='width:30%'>".utf8entities(TeamName($row['hometeam']))."</td>";
			}else{
				$html .= "<td class='lowlight' style='width:30%'>".utf8entities(U_($row['phometeamname']))."</td>";
			}
			$html .= "<td>-</td>";
			if($row['visitorteam']){
				$html .= "<td style='width:30%'>". utf8entities(TeamName($row['visitorteam'])) ."</td>";
			}else{
				$html .= "<td class='lowlight' style='width:30%'>".utf8entities(U_($row['pvisitorteamname']))."</td>";
			}
			$html .= "<td class='left' style='white-space: nowrap'>".utf8entities(U_($row['seriesname'])).", ". utf8entities(U_($row['poolname']))."</td>";
			$html .= "<td class='center'><a href='?view=admin/editgame&amp;season=$season&amp;game=".$row['game_id']."'>"._("edit")."</a></td>";
			if(CanDeleteGame($row['game_id'])){
				$html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='"._("X")."' onclick=\"setId(".$row['game_id'].");\"/></td>";		
			}
			$html .= "</tr>\n";	
			}
		$html .= "</table>";
		}
}	
*/
//stores id to delete
//if($i>0){
  $html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/>";
  //$html .= "<input disabled='disabled' id='save' class='button' name='save' type='submit' value='"._("Save game names")."'/></p>";
  $html .= "</form>\n";
  $html .= "<hr/>";
  $html .= "<p><a href='?view=admin/reservations&amp;season=$season'>"._("Reservation management")."</a></p>";
//}
echo $html;
contentEnd();
pageEnd();
?>
