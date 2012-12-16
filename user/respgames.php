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

$groups = TimetableGrouping($season, "season", "all");
if(count($groups>1)){
  $html .= "<p>\n";
  foreach($groups as $grouptmp){
    if($group==$grouptmp['reservationgroup']){
      $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
    }else{
      $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=".urlencode($grouptmp['reservationgroup'])."'>".U_($grouptmp['reservationgroup'])."</a>";
    }
    $html .= "&nbsp;&nbsp;&nbsp; ";
  }
  if($group=="all"){
    $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=all'><span class='selgroupinglink'>"._("All")."</span></a>";
  }else{
    $html .= "<a class='groupinglink' href='?view=user/respgames&amp;season=$season&amp;group=all'>"._("All")."</a>";
  }
  $html .= "</p>\n";
}

$respGameArray = GameResponsibilityArray($season);

if(count($respGameArray) == 0) {
  $html .= "\n<p>"._("No game responsibilities").".</p>\n";
}else{
  $html .= "<noscript>
	<p><b>"._("Feeding in the score sheet requires JavaScript. Please enable JavaScript to continue!")."</b></p>
	</noscript>";	
}

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
    $html .= "<h1>". utf8entities($tournament) ."</h1>\n";
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
      $html .= "<td style='width:5%'>". intval($game['homescore']) ."</td><td style='width:2%'>-</td><td style='width:5%'>". intval($game['visitorscore']) ."</td>";
      if (intval($game['goals'])>0) {
        $html .= "<td style='width:15%'><a href='?view=gameplay&amp;game=". $game['game_id'] ."'>"._("Game play")."</a></td>";
      } else {
        $html .= "<td style='width:15%'>es</td>";
      }
      if($game['hometeam'] && $game['visitorteam']){
        $html .= "<td style='white-space: nowrap' class='right'><a href='?view=user/addresult&amp;game=".$gameId."'>"._("Result")."</a> | ";
        $html .= "<a href='?view=user/addplayerlists&amp;game=".$gameId."'>"._("Players")."</a> | ";
        $html .= "<a href='?view=user/addscoresheet&amp;game=$gameId'>"._("Score sheet")."</a>";
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

showPage($title, $html);
?>
