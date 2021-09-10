<?php 
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/reservation.functions.php';
include_once $include_prefix.'lib/game.functions.php';
include_once $include_prefix.'lib/user.functions.php';
include_once $include_prefix.'lib/timetable.functions.php';

if (is_file('cust/'.CUSTOMIZATIONS.'/pdfprinter.php')) {
	include_once 'cust/'.CUSTOMIZATIONS.'/pdfprinter.php';
} else {
	include_once 'cust/default/pdfprinter.php';
}
$season = "";
$filter1 = "";
$filter2 = "";
$gameId = 0;
$teamId = 0;
$seriesId=0;

if(!empty($_GET["game"])) {
	$games = TimetableGames($_GET["game"],"game","all","place");
}

if(!empty($_GET["season"])) {
	$season = $_GET["season"];
}else{
	$season = CurrentSeason();
}

if(!empty($_GET["series"])) {
	$seriesId = $_GET["series"];
}

if(!empty($_GET["pool"])) {
	$poolId = $_GET["pool"];
	$games = TimetableGames($poolId, "pool", "all","time","");
}

if(!empty($_GET["filter1"])) {
	$filter1  = $_GET["filter1"];
}

if(!empty($_GET["filter2"])) {
	$filter2  = $_GET["filter2"];
}

if(!empty($_GET["reservation"])) {
	$gameResponsibilities = GameResponsibilities($season);
	$games = ResponsibleReservationGames($_GET["reservation"]=="none"?null:$_GET["reservation"], $gameResponsibilities);
}
if(!empty($_GET["group"])) {
	if($filter1=="coming"){
		$games = TimetableGames($season, "season", "coming", "places", $_GET["group"]);
	}else{
		$games = TimetableGames($season, "season", "all", "places", $_GET["group"]);
	}
}

if(!empty($_GET["team"])) {
	$teamId  = $_GET["team"];
}

$pdf = new PDF();


if($teamId){
  $teaminfo = TeamInfo($teamId);
  $players = array();
  if ($result = TeamPlayerList($teamId)) {
	while ($row = mysqli_fetch_assoc($result)) {
		$players[] = $row;
	  }
  }
  $pdf->PrintRoster($teaminfo['name'], $teaminfo['seriesname'], $teaminfo['poolname'], $players);
  
}elseif($seriesId){
  
    $teams = SeriesTeams($seriesId,true);
	
	foreach($teams as $team){
      $teaminfo = TeamInfo($team['team_id']);
      $players = array();
      if ($result = TeamPlayerList($team['team_id'])) {
	    while ($row = mysqli_fetch_assoc($result)) {
		  $players[] = $row;
	    }
      }
      $pdf->PrintRoster($teaminfo['name'], $teaminfo['seriesname'], $teaminfo['poolname'], $players);
	}
	
}else{
  $seasonname = SeasonName($season);
  
  while($gameRow = mysqli_fetch_assoc($games)) {
  
  	if($filter2=="teams"){
  		if(!$gameRow['hometeam'] || !$gameRow['visitorteam']){
  		continue;
  		}
  	}
  	
  	$sGid = $gameRow['game_id'];
  	//$sGid .= getChkNum($sGid);
  	
  	$homeplayers = array();
  		
  	$playerlist = TeamPlayerList($gameRow["hometeam"]);
  	$i=0;
  	while ($player = mysqli_fetch_assoc($playerlist)) {
  		$homeplayers[$i]['name'] = $player['firstname']." ".$player['lastname'];
  		$homeplayers[$i]['accredited'] = $player['accredited'];
  		$homeplayers[$i]['num'] = $player['num'];
  		$i++;
  	}
  	$visitorplayers = array();
  	$playerlist = TeamPlayerList($gameRow["visitorteam"]);
  	$i=0;
  	while ($player = mysqli_fetch_assoc($playerlist)) {
  		$visitorplayers[$i]['name'] = $player['firstname']." ".$player['lastname'];
  		$visitorplayers[$i]['accredited'] = $player['accredited'];
  		$visitorplayers[$i]['num'] = $player['num'];
  		$i++;
  	}
  	
  	$home = empty($gameRow["hometeamname"])?U_($gameRow["phometeamname"]):$gameRow["hometeamname"];
  	$visitor = empty($gameRow["visitorteamname"])?U_($gameRow["pvisitorteamname"]):$gameRow["visitorteamname"];
  	
  	$pdf->PrintScoreSheet(U_($seasonname),$sGid,
  		$home,
  		$visitor,
  		U_($gameRow['seriesname']) . ", ". U_($gameRow['poolname']),
  		$gameRow["time"], 
  		U_($gameRow["placename"])." "._("Field")." ".U_($gameRow['fieldname'])); 
  	$pdf->PrintPlayerList($homeplayers, $visitorplayers);
  }
}

$pdf->Output();

?>
