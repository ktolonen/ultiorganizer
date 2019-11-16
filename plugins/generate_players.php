<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=generator
format=any
security=superadmin
customization=all

[DESCRIPTION]
title = "Player generator"
description = "Generate players and add them into team roster."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$html = "";
$title = ("Player generator");
$seasonId = "";

if(!empty($_POST['season'])){
	$seasonId = $_POST['season'];
}

if (isset($_POST['generate'])) {

	$accreditate = !empty($_POST['accreditate']);
	$seriesId = $_POST['seriesid'];
	$amount = intval($_POST['amount'])+1;
	$teams = SeriesTeams($seriesId);

	foreach($teams as $t){
		for($i=1;$i<$amount;$i++){
			
			$id = AddPlayer($t['team_id'],"Player $i","Ultimate","",$i);
		}
	}
}

//season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=plugins/generate_players'>\n";

if(empty($seasonId)){
	$html .= "<p>".("Select event").": <select class='dropdown' name='season'>\n";

	$seasons = Seasons();
			
	while($row = mysqli_fetch_assoc($seasons)){
		$html .= "<option class='dropdown' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
	}

	$html .= "</select></p>\n";
	$html .= "<p><input class='button' type='submit' name='select' value='".("Select")."'/></p>";
}else{

	$html .= "<p>".("Select division").":	<select class='dropdown' name='seriesid'>\n";
	$series = SeasonSeries($seasonId);
	foreach($series as $row){
		$html .= "<option class='dropdown' value='".utf8entities($row['series_id'])."'>". utf8entities($row['name']) ."</option>";
	}
	$html .= "</select></p>\n";

	$html .= "<p>".("Number of players to generate for a team").": <input class='input' maxlength='2' size='2' name='amount' value='20'/></p>\n";

	$html .= "<p>";
	$html .= "<input class='input' type='checkbox' name='accreditate' /> ".("Accreditate players")."</p>";
	$html .= "<p><input class='button' type='submit' name='generate' value='".("Generate")."'/></p>";
	$html .= "<div>";
	$html .= "<input type='hidden' name='season' value='$seasonId' />\n";
	$html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
