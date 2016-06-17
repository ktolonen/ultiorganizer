<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';
include_once $include_prefix.'lib/timetable.functions.php';

$title = _("Game responsibilities");
$group = "all";
$tab = 0;

$html = "";

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
	<p>"._("Click on Mass input to input multiple results at once, then Save").".</p>
	<p>"._("Check the game play after feeding in the score sheet").".</p>";

$html .= onPageHelpAvailable($help);
//content

if (isset($_GET['season'])) {
  $season = $_GET['season'];
} else {
  $season = CurrentSeason();
}
if (isset($_GET['series'])) {
  $series_id = $_GET['series'];
} else {
  $series_id = null;
}
$series = SeasonSeries($season);


$hidestarted = -1;
$hide="none";
if(!empty($_GET["hidden"])) {
  $hidestarted = ($_GET["hidden"] == "started")?1:0;
  $hide=$_GET['hidden'];
}

if (!empty($_GET["massinput"])) {
	$_SESSION['massinput'] = true;
	$mass = "1";
} else {
	$_SESSION['massinput'] = false;
	$mass = "0";
}

//process itself on submit
$feedback = "";
if (!empty($_POST['save'])) {
	$feedback = GameProcessMassInput($_POST);
}

foreach($series as $row){
  $menutabs[U_($row['name'])]="?view=user/respgames&season=".$season."&series=".$row['series_id'];
}
$menutabs[_("...")]="?view=user/respgames&season=".$season;
$html .= pageMenu($menutabs, respgameslink($season, $series_id, $group, $hide, $mass, false), false);


$seasoninfo = SeasonInfo($season);
$groups = TimetableGrouping($season, "season", "all");
$html .= "<table width='100%'><tr><td>\n";

function respgameslink($season, $series_id, $group, $hide, $mass, $htmlentities=true) {
  if($hide=="none")
    $hide = "";
  else
    $hide = "&amp;hidden=".$hide;
  $ret = "?view=user/respgames&amp;season=$season" . ($series_id ? "&amp;series=$series_id" : "") .
       "&amp;group=$group$hide&amp;massinput=$mass";
  return $ret;
}

if(count($groups)>0){
  foreach($groups as $grouptmp){
    if($group==$grouptmp['reservationgroup']){
      $html .= "<a class='groupinglink' tabindex='".++$tab."' href='".respgameslink($season, $series_id, urlencode($grouptmp['reservationgroup']), $hide, $mass)."'><span class='selgroupinglink'>".U_($grouptmp['reservationgroup'])."</span></a>";
    }else{
      $html .= "<a class='groupinglink' tabindex='".++$tab."' href='".respgameslink($season, $series_id, urlencode($grouptmp['reservationgroup']), $hide, $mass)."'>".U_($grouptmp['reservationgroup'])."</a>";
    }
    $html .= "&nbsp;&nbsp;&nbsp; ";
  }
  if($group=="all"){
    $html .= "<a class='groupinglink' tabindex='".++$tab."' href='".respgameslink($season, $series_id, "all", $hide, $mass)."'><span class='selgroupinglink'>"._("All")."</span></a>";
  }else{
    $html .= "<a class='groupinglink' tabindex='".++$tab."' href='".respgameslink($season, $series_id, "all", $hide, $mass)."'>"._("All")."</a>";
  }
}
$html .= "</td>\n";

$html .="<td>";
if ($hidestarted != 1) {
  $html .= "<a href='".respgameslink($season, $series_id, $group, "started", $mass)."' tabindex='".++$tab."'>"._("Hide played games")."</a> ";
}
if ($hidestarted != 0) {
  if ($hidestarted != 1)
    $html .= "| ";
  $html .= "<a href='".respgameslink($season, $series_id, $group, "future", $mass)."' tabindex='".++$tab."'>"._("Hide future games")."</a> ";
}
if($hidestarted != -1){
  $html .= "| <a href='".respgameslink($season, $series_id, $group, "none", $mass)."' tabindex='".++$tab."'>"._("Show all games")."</a> ";
}
$html .= "</td>\n";

$html .= "</td><td style='text-align:right;' tabindex='".++$tab."'>";
if ($_SESSION ['massinput']) {
	$html .= "<a class='button' href='".respgameslink($season, $series_id, $group, $hide, "0")."'>" . _ ( "Just display values" ) . "</a>";
} else {
	$html .= "<a class='button' href='".respgameslink($season, $series_id, $group, $hide, "1")."'>" . _ ( "Mass input" ) . "</a>";
}
$html .= "</td></tr></table>\n";


$respGameArray = GameResponsibilityArray($season, $series_id);

if(count($respGameArray) == 0) {
  $html .= "\n<p>"._("No game responsibilities").".</p>\n";
}else{
  $html .= "<noscript>
	<p><b>"._("Feeding in the score sheet requires JavaScript. Please enable JavaScript to continue!")."</b></p>
	</noscript>";	
}

$html .= "<form method='post' action='".respgameslink($season, $series_id, $group, $hide, $mass)."'>";

$first = true;
foreach ($respGameArray as $reservationgroup => $resArray) {
  if($group != "all" && $reservationgroup != $group){
    continue;
  }

  if($first) {
    $first = false;
  } else {
    $html .= "<hr/>\n";
  }
  if($group == "all" && !empty($reservationgroup)){
    $html .= "<h2>". utf8entities($reservationgroup) ."</h2>\n";
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
      if (($hidestarted==1  && GameHasStarted($game)) || ($hidestarted==0 && !GameHasStarted($game))) {
          continue;
      }
      
      $html .= "<tr><td>". DefHourFormat($game['time']) ."</td>";
      if($game['hometeam'] && $game['visitorteam']){
        $html .= "<td style='width:20%' >". utf8entities($game['hometeamname']) ."</td><td>-</td><td style='width:20%'>". utf8entities($game['visitorteamname']) ."</td>";
      }else{
        $html .= "<td style='width:20%'>". utf8entities($game['phometeamname']) ."</td><td>-</td><td style='width:20%'>". utf8entities($game['pvisitorteamname']) ."</td>";
      }
      
      if ($_SESSION['massinput']) {
      	$html .= "<td colspan='3' style='white-space: nowrap'>
      		<input type='hidden' id='scoreId" . $gameId . "' name='scoreId[]' value='$gameId'/>
      		<input type='text' style='width:5ex' size='2' maxlength='3' value='" . (is_null($game['homescore'])?"":intval($game['homescore'])) . "' id='homescore$gameId' name='homescore[]' oninput='confirmLeave(this, true, null);' tabindex='".++$tab."'/> 
      		<input type='text' style='width:5ex' size='2' maxlength='3' value='" . (is_null($game['visitorscore'])?"":intval($game['visitorscore'])) . "' id='visitorscore$gameId' name='visitorscore[]' oninput='confirmLeave(this, true, null);' tabindex='".++$tab."'/></td>";
      } else {
      	$html .= "<td>". intval($game['homescore']) ."</td><td>-</td><td>". intval($game['visitorscore']) ."</td>";
      }
      if (intval($game['hasstarted'])>0) {
        $html .= "<td><a href='?view=gameplay&amp;game=". $game['game_id'] ."'>"._("Game play")."</a></td>";
      } else {
        $html .= "<td></td>";
      }
      if($game['hometeam'] && $game['visitorteam']){
        $html .= "<td class='right'><a href='?view=user/addresult&amp;game=".$gameId."'>"._("Result")."</a> | ";
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
	$html .= "<input class='button' name='save' type='submit' value='" . _("Save") . "' onclick='confirmLeave(null, false, null);' tabindex='".++$tab."'/>";
}
$html .= $feedback;


showPage($title, $html);
?>
