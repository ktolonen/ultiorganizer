<?php
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
$html = "";
mobilePageTop(_("Game responsibilities"));

$season = CurrentSeason();
$reservationgroup = "";
$location = "";
$showall = false;
$day="";
$dayPar="";
$locationPar="";
$allPar="";
$massPar="";
$rgPar="";

if(isset($_GET['rg'])){
	$reservationgroup = urldecode($_GET['rg']);
	$rgPar = "&amp;rg=".urlencode($reservationgroup);
}

if(isset($_GET['loc'])){
	$location = urldecode($_GET['loc']);
	$locationPar = "&amp;loc=".urlencode($location);
}

if(isset($_GET['day'])){
	$day = urldecode($_GET['day']);
	$dayPar = "&amp;day=".urlencode($day);
}

if(isset($_GET['all'])){
	$showall = intval($_GET['all']);
	$allPar = "&amp;all=1";
}

if (!empty($_GET["massinput"])) {
  $_SESSION['massinput'] = true;
  $mass = "1";
  $massPar = "&amp;massinput=1";
} else {
  $_SESSION['massinput'] = false;
  $mass = "0";
  $massPar = "&amp;massinput=0";
}

//process itself on submit
$feedback = "";
if (!empty($_POST['save'])) {
	$feedback = GameProcessMassInput($_POST);
}


$respGameArray = GameResponsibilityArray($season);
$html .= "<form action='?".utf8entities($_SERVER['QUERY_STRING'])."' method='post'>\n"; 
$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td>\n";

if(count($respGameArray) == 0) {
	$html .= "<p>"._("No game responsibilities").".</p>\n";	
} else	{
	$prevdate="";
	$prevrg = "";
	$prevloc = "";
	if ($_SESSION['massinput']) {
		$html .= "<p><a href='?view=mobile/respgames$allPar$rgPar$locationPar$dayPar&amp;massinput=0'>" . _("Just display values") . "</a></p>";
	}else {
		$html .= "<p><a href='?view=mobile/respgames$allPar$rgPar$locationPar$dayPar&amp;massinput=1'>" . _("Mass input") . "</a></p>";
	}
	
	foreach ($respGameArray as $tournament => $resArray) {
		foreach($resArray as $resId => $gameArray) {
			foreach ($gameArray as $gameId => $game) {
				if (!is_numeric($gameId)) {
					continue;
				}
				
				if($showall){
					if(!empty($prevdate) && $prevdate != JustDate($game['time'])){
						$html .= "</td></tr><tr><td>\n";
						$html .= "<hr/>\n";
						$html .= "</td></tr><tr><td>\n";
					}
					$html .= gamerow($gameId, $game, $mass);
					$prevdate = JustDate($game['time']);
					continue;
				}
				
				if($prevrg != $game['reservationgroup']){
					$html .= "</td></tr><tr><td>\n";
					if($reservationgroup == $game['reservationgroup']){
						$html .= "<b>".utf8entities($game['reservationgroup'])."</b>";
					}else{
						$html .= "+ <a href='?view=mobile/respgames&amp;rg=".urlencode($game['reservationgroup'])."$massPar'>".utf8entities($game['reservationgroup'])."</a>";
					}
					$html .= "</td></tr><tr><td>\n";
					$prevrg = $game['reservationgroup'];
				}

				if ($reservationgroup == $game['reservationgroup']) {

					$gameloc = $game['location']."#".$game['fieldname'];
					
					if($prevloc != $gameloc){
						$html .= "</td></tr><tr><td>\n";
						if($location == $gameloc && $day==JustDate($game['starttime'])){
							$html .= "&nbsp;&nbsp;<b>". utf8entities($game['locationname']) . " " . _("Field") . " " . utf8entities($game['fieldname'])."</b>";
						}else{
							$html .= "&nbsp;+<a href='?view=mobile/respgames&amp;rg=".urlencode($game['reservationgroup'])."&amp;loc=".urlencode($gameloc)."&amp;day=".urlencode(JustDate($game['starttime']))."$massPar'>";
							$html .= utf8entities($game['locationname']) . " " . _("Field") . " " . utf8entities($game['fieldname'])."</a>";
						}
						
						$html .= "</td></tr><tr><td>\n";
						$prevloc = $gameloc;
					}
					
					if ($location == $gameloc && $day == JustDate($game['starttime'])) {
						$html .= gamerow($gameId, $game, $mass);
					}
				}

			}
		}
	}
}
$html .= "</td></tr>\n";
if ($_SESSION['massinput']) {
	$html .= "<tr><td><input class='button' name='save' type='submit' value='" . _("Save") . "' onclick='confirmLeave(null, false, null);'/></td></tr>\n";
}
if ($feedback)
  $html .="<tr><td>".$feedback."</td></tr>";

$html .= "<tr><td><hr/>\n";
if($showall){
	$html .= "<a href='?view=mobile/respgames'>"._("Group games")."</a>";
}else{
	$html .= "<a href='?view=mobile/respgames&amp;all=1'>"._("Show all")."</a>";
}
$html .= "</td></tr></table>\n";
$html .= "</form>";

echo $html;
		
pageEnd();

function gamerow($gameId, $game, $mass){
	$ret = "&nbsp;&nbsp;&nbsp;&nbsp;";
	$ret .= DefTimeFormat($game['time']) ." ";
	if($game['hometeam'] && $game['visitorteam']){
		$ret .= utf8entities($game['hometeamname']) ." - ". utf8entities($game['visitorteamname']) ." ";

		if ($mass=="1") {
			$ret .= "<input type='hidden' id='scoreId" . $gameId . "' name='scoreId[]' value='$gameId'/>
			<input type='text' size='3' maxlength='3' style='width:4ex' value='" . (is_null($game['homescore'])?"":intval($game['homescore'])) . "' id='homescore$gameId' name='homescore[]' oninput='confirmLeave(this, true, null);' /> -
			<input type='text' size='3' maxlength='3' style='width:4ex' value='" . (is_null($game['visitorscore'])?"":intval($game['visitorscore'])) . "' id='visitorscore$gameId' name='visitorscore[]' oninput='confirmLeave(this, true, null);' />";
			// $ret .= "<input class='button' name='saveOne' type='submit' value='" . _("Save") . "' onPress='setSaved(".$gameID.")'/></td></tr><tr><td>\n";
		}elseif(GameHasStarted($game)){
			$ret .=  "<a style='white-space: nowrap' href='?view=mobile/gameplay&amp;game=".$gameId."'>".intval($game['homescore']) ." - ". intval($game['visitorscore'])."</a>";
		}else{
			$ret .= intval($game['homescore']) ." - ". intval($game['visitorscore']);
		}
		$ret .= "</td></tr><tr><td>\n";
		$ret .= "&nbsp;&nbsp;&nbsp;&nbsp;";
		$ret .=  "<a style='white-space: nowrap' href='?view=mobile/addresult&amp;game=".$gameId."'>"._("Result")."</a> | ";
		$ret .=  "<a style='white-space: nowrap' href='?view=mobile/addplayerlists&amp;game=".$gameId."&amp;team=".$game['hometeam']."'>"._("Players")."</a> | ";
		$ret .=  "<a style='white-space: nowrap' href='?view=mobile/addscoresheet&amp;game=$gameId'>"._("Scoresheet")."</a>";
		$ret .= "</td></tr><tr><td>\n";
	}else{
		$ret .= utf8entities($game['phometeamname']) ." - ". utf8entities($game['pvisitorteamname']) ." ";
		$ret .= "</td></tr><tr><td>\n";
	}
	return $ret;
}
?>
