<?php
include_once 'lib/series.functions.php';

$seriesId = intval($_GET["series"]);
$seasonInfo = SeasonInfo(SeriesSeasonId($seriesId));
$teams = SeriesTeams($seriesId,true); 
$backurl = utf8entities($_SERVER['HTTP_REFERER']);

$html = "";
if (isset($_POST['save'])){
	$backurl = utf8entities($_POST['backurl']);
	//revalidate
	for($i=0;$i<count($teams);$i++){
		$teamId=$_POST["team$i"];
		$seed=$_POST["seed$i"];
		SetTeamSeeding($seriesId, $teamId, $seed);
	}
}

$teams = SeriesTeams($seriesId,true);

//common page
$title = _("Division seeding");
$LAYOUT_ID = SERIESSEEDING;
pageTopHeadOpen($title);
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

$series = SeasonSeries($seasonInfo['season_id']);
foreach($series as $row){
  $menutabs[U_($row['name'])]="?view=admin/seriesseeding&season=".$seasonInfo['season_id']."&series=".$row['series_id'];
}
$menutabs[_("...")]="?view=admin/seasonseries&season=".$seasonInfo['season_id'];
pageMenu($menutabs);

$html .= "<form method='post' action='?view=admin/seriesseeding&amp;series=$seriesId'>";
$html .= "<h1>".utf8entities(U_(SeriesName($seriesId)))."</h1>";
$html .=  "<table border='0' cellpadding='4px'>\n";

$html .= "<tr>";
$html .= "<th>"._("Seed")."</th>";
$html .= "<th>"._("Name")."</th>";
if(!intval($seasonInfo['isnationalteams'])){		
	$html .= "<th>"._("Club")."</th>";
}
if(intval($seasonInfo['isinternational'])){
	$html .= "<th>"._("Country")."</th>";
}

$html .= "<th></th></tr>\n";

$i=0;

foreach($teams as $team){

	$html .= "<tr>";
	$html .= "<td><input class='input' maxlength='3' size='2' name='seed$i' value='".$team['rank']."'/></td>";
	$html .= "<td><input type='hidden' name='team$i' value='".$team['team_id']."'/>".utf8entities($team['name'])."</td>";
	if(!intval($seasonInfo['isnationalteams'])){		
		$html .= "<td>".utf8entities($team['clubname'])."</td>";
	}
	if(intval($seasonInfo['isinternational'])){
		$html .= "<td>".utf8entities(U_($team['countryname']))."</td>";
	}

	$html .= "</tr>\n";
	$i++;
}
$html .= "</table>";
$html .= "<p><input class='button' type='submit' name='save' value='"._("Save")."'/>";
$html .= "<input class='button' type='button' name='back'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/></p>";
$html .= "<div><input type='hidden' name='backurl' value='$backurl'/></div>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
?>