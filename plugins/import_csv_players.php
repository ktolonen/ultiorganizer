<?php
ob_start();
?>
<!--
[CLASSIFICATION]
category=database
type=import
format=csv
security=superadmin
customization=all

[DESCRIPTION]
title = "Import Players from CSV file"
description = "CSV file format: firstname,lastname,number,team name."
-->
<?php
ob_end_clean();
if (!isSuperAdmin()){die('Insufficient user rights');}
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$html = "";
$title = ("Import Players from CSV file");
$seasonId = "";

if(!empty($_POST['season'])){
	$seasonId = $_POST['season'];
}

if (isset($_POST['import'])) {

	$utf8 = !empty($_POST['utf8']);
	$seriesId = $_POST['seriesid'];
	$separator = $_POST['separator'];
	$teams = SeriesTeams($seriesId);

	if(is_uploaded_file($_FILES['file']['tmp_name'])) {
    	$row = 1;
		if (($handle = fopen($_FILES['file']['tmp_name'], "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
				$first = $utf8 ? $data[0] : utf8_encode($data[0]);
				$last = $utf8 ? $data[1] : utf8_encode($data[1]);
				$number = $utf8 ? $data[2] : utf8_encode($data[2]);
				$team = $utf8 ? $data[3] : utf8_encode($data[3]);
				$teamId = -1;
				foreach($teams as $t){
					if($t['name']==$team){
						$teamId = $t['team_id'];
						break;
					}
				}
				if($teamId!=-1){
					$id = AddPlayer($teamId,$first,$last,"",$number);
				}
				
			}
			fclose($handle);
		}
	}else{
		$html .= "<p>". ("There was an error uploading the file, please try again!"). "</p>";
	
	}
}

//season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=plugins/import_csv_players'>\n";

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

	$html .= "<p>".("CSV separator").": <input class='input' maxlength='1' size='1' name='separator' value=','/></p>\n";

	$html .= "<p>".("Select file to import").":<br/>\n";
	$html .= "<input class='input' type='file' size='100' name='file'/><br/>\n";
	$html .= "<input class='input' type='checkbox' name='utf8' /> ".("File in UTF-8 format")."</p>";
	$html .= "<p><input class='button' type='submit' name='import' value='".("Import")."'/></p>";
	$html .= "<div>";
	$html .= "<input type='hidden' name='MAX_FILE_SIZE' value='50000000' />\n";
	$html .= "<input type='hidden' name='season' value='$seasonId' />\n";
	$html .= "</div>\n";
}

$html .= "</form>";

showPage($title, $html);
?>
