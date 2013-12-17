<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/timetable.functions.php';

$title = _("Game responsibilities");
$html = "";
$group = "all";

if(!empty($_GET["group"])) {
  $group  = $_GET["group"];
}

$html .= file_get_contents('script/disable_enter.js.inc');

$help = "<p>"._("Feed in the data for your game responsibilities").":</p>
	<ol>
		<li> "._("Result")." </li>
		<li> "._("Players in the game")." </li>
		<li> "._("Game score sheet")." </li>
	</ol>
	<p>"._("Check the game play after feeding in the score sheet").".</p>";

$html .= onPageHelpAvailable($help);
//content

if (isset($_GET['season'])) {
  $season = $_GET['season'];
} else {
  $season = CurrentSeason();
}
if (!empty($_GET["massinput"])) {
	$_SESSION['massinput'] = true;
	$mass = "1";
} else {
	$_SESSION['massinput'] = false;
	$mass = "0";
}

//process itself on submit
if (!empty($_POST['save'])) {
	$scores = array ();
	foreach ($_POST['scoreId'] as $key => $value) {
		$scores[$key]['gameid'] = $value;
	}
	foreach ($_POST['homescore'] as $key => $value) {
		$scores[$key]['home'] = $value;
	}
	foreach ($_POST['visitorscore'] as $key => $value) {
		$scores[$key]['visitor'] = $value;
	}
	foreach ($scores as $score) {
		$gameId = $score['gameid'];
		$game = GameInfo($gameId);
		if (empty($score['home'])) {
			if (empty($score['visitor']))
				if ($game['hasstarted'])
					GameClearResult($gameId);
		}elseif ((!empty($score['visitor']) || !empty($score['home'])) && (!$game['hasstarted'] || $game['isongoing'] || $game['homescore'] != $score['home'] || $game['visitorscore'] != $score['visitor'])) {
			GameSetResult($gameId, $score['home'], $score['visitor']);
		}
	}
}


$seasoninfo = SeasonInfo($season);
$groups = TimetableGrouping($season, "season", "all");
$html .= "<table width='100%'><tr><td>\n";
if(count($groups>1)){
  foreach($groups as $grouptmp){
    if($group==$grouptmp['reservationgroup']){
      $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."&amp;massinput=$mass'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
    }else{
      $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."&amp;massinput=$mass'>".U_($grouptmp['reservationgroup'])."</a>";
    }
    $html .= "&nbsp;&nbsp;&nbsp; ";
  }
  if($group=="all"){
    $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=all&amp;massinput=$mass'><span class='selgroupinglink'>"._("All")."</span></a>";
  }else{
    $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=all&amp;massinput=$mass'>"._("All")."</a>";
  }
  $html .= "</td>\n";
}

$html .= "</td><td style='text-align:right;'>";
if ($_SESSION ['massinput']) {
	$html .= "<a href='?view=user/respgames&amp;season=$season&amp;group=$group&amp;massinput=0'>" . _ ( "Just display values" ) . "</a>";
} else {
	$html .= "<a href='?view=user/respgames&amp;season=$season&amp;group=$group&amp;massinput=1'>" . _ ( "Mass input" ) . "</a>";
}
$html .= "</td></tr></table>\n";


$respGameArray = GameResponsibilityArray($season);

if(count($respGameArray) == 0) {
  $html .= "\n<p>"._("No game responsibilities").".</p>\n";
}else{
  $html .= "<noscript>
	<p><b>"._("Feeding in the score sheet requires JavaScript. Please enable JavaScript to continue!")."</b></p>
	</noscript>";	
}

$html .= "<form method='post' action='?view=user/respgames&amp;season=$season&amp;group=$group&amp;massinput=$mass'>";

$first = true;
foreach ($respGameArray as $tournament => $resArray) {
  if($group != "all" && $tournament != $group){
    continue;
  }
  if($first) {
    $first = false;
  } else {
    $html .= "<hr/>\n";
  }
  if($group == "all"){
    $html .= "<h2>". utf8entities($tournament) ."</h2>\n";
  }

  foreach($resArray as $resId => $gameArray) {
    $html .= "<table cellpadding='2' border='0' style='width:100%'>";
    $html .= "<tr><th class='left' colspan='8'>";
    $html .= DefWeekDateFormat($gameArray['starttime']) ." ";
    if($resId)
    $html .= "<a class='thlink' href='?view=reservationinfo&amp;reservation=".$resId."'>". $gameArray['locationname'] ."</a>";
    else
    $html .= _("No location");
    $html .= "</th>\n<th class='right' colspan='2'>";
    if($resId)
    $html .= "<a class='thlink' href='?view=user/pdfscoresheet&amp;reservation=".$resId."&amp;season=".$season."'>"._("Print scoresheets")."</a>";
    $html .= "</th></tr>\n";

    foreach ($gameArray as $gameId => $game) {
      if (!is_numeric($gameId)) {
        continue;
      }
      $html .= "<tr><td style='width:6%'>". DefHourFormat($game['time']) ."</td>";
      if($game['hometeam'] && $game['visitorteam']){
        $html .= "<td style='width:20%'>". utf8entities($game['hometeamname']) ."</td><td style='width:2%'>-</td><td style='width:20%'>". utf8entities($game['visitorteamname']) ."</td>";
      }else{
        $html .= "<td style='width:20%'>". utf8entities($game['phometeamname']) ."</td><td style='width:2%'>-</td><td style='width:20%'>". utf8entities($game['pvisitorteamname']) ."</td>";
      }
      
      if ($_SESSION['massinput']) {
      	$html .= "<td colspan='3'  style='width:18%'>
      		<input type='hidden' id='scoreId" . $gameId . "' name='scoreId[]' value='$gameId'/>
      		<input type='text' size='3' maxlength='5' value='" . intval($game['homescore']) . "' id='homescore$gameId' name='homescore[]' onkeypress='ChgResult(" . $gameId . ")'/> - 
      		<input type='text' size='3' maxlength='5' value='" . intval($game['visitorscore']) . "' id='visitorscore$gameId' name='visitorscore[]' onkeypress='ChgResult(" . $gameId . ")'/></td>";
      } else {
      	$html .= "<td style='width:8%'>". intval($game['homescore']) ."</td><td style='width:2%'>-</td><td style='width:8%'>". intval($game['visitorscore']) ."</td>";
      }
      if (intval($game['hasstarted'])>0) {
        $html .= "<td style='width:9%'><a href='?view=gameplay&amp;game=". $game['game_id'] ."'>"._("Game play")."</a></td>";
      } else {
        $html .= "<td style='width:9%'></td>";
      }
      if($game['hometeam'] && $game['visitorteam']){
        $html .= "<td style='white-space: nowrap' class='right'><a href='?view=user/addresult&amp;game=".$gameId."'>"._("Result")."</a> | ";
        $html .= "<a href='?view=user/addplayerlists&amp;game=".$gameId."'>"._("Players")."</a> | ";
        $html .= "<a href='?view=user/addscoresheet&amp;game=$gameId'>"._("Scoresheet")."</a>";
        if($seasoninfo['spiritpoints'] && isSeasonAdmin($seasoninfo['season_id'])){
          $html .= " | <a href='?view=user/addspirit&amp;game=$gameId'>"._("Spirit")."</a>";
        }
        
        if(ShowDefenseStats())
        {
          $html .= " | <a href='?view=user/adddefensesheet&amp;game=$gameId'>"._("Defense sheet")."</a>";
        }
        $html .= "</td>";
      }
      $html .= "</tr>";
    }
    $html .= "</table>";

  }
}

if ($_SESSION['massinput']) {
	$html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
}


showPage($title, $html);
?>
