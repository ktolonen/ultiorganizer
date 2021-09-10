<?php

include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/configuration.functions.php';

$title = _("Data export");
$html = "";

$encoding = "UTF-8";
$separator = ",";
$season = CurrentSeason();

if (isset($_POST['change'])) {
	$separator = $_POST['separator'];
	$encoding = $_POST['encoding'];
	$season = $_POST['season'];
}

$html .= "<h2>"._("CSV-files")."</h2>\n";
$html .= "<p>"._("Get comma separated UTF-8 encoded files by clicking links below.");
$html .= " "._("You can also change encoding and separator.")."</p>\n";
$html .= "<p>". SeasonName($season)."<br/>";
$html .= "<a href='ext/gamescsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("All scheduled games")."</a><br/>";
$html .= "<a href='ext/resultscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("All results")."</a><br/>";
$html .= "<a href='ext/playerscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("Player statistics")."</a><br/>";
$html .= "<a href='ext/teamscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("Team statistics")."</a><br/>";
$html .= "<a href='ext/poolscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("Pool standings")."</a><br/>";
$html .= "</p>";

$html .= "<form method='post' action='?view=ext/export'>\n";
$html .= "<p>"._("Select event").":	<select class='dropdown' name='season'>\n";

$seasons = Seasons();
		
while($row = mysqli_fetch_assoc($seasons)){
  if($row['season_id'] == $season)
  	$html .= "<option class='dropdown' selected='selected' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
  else
  	$html .= "<option class='dropdown' value='".utf8entities($row['season_id'])."'>". utf8entities($row['name']) ."</option>";
 }

$html .= "</select></p>\n";

$html .= "<p>".("Select encoding").": <select class='dropdown' name='encoding'>\n";
$encodings = array("UTF-8","ISO-8859-15","Windows-1251","Windows-1252");
foreach ($encodings as $enc){
	if($enc==$encoding){
		$html .= "<option class='dropdown' selected='selected' value='$enc'>". utf8entities($enc) ."</option>";
	}else{
		$html .= "<option class='dropdown' value='$enc'>". utf8entities($enc) ."</option>";
	}
}
$html .= "</select></p>\n";
$html .= "<p>".("CSV separator").": <input class='input' maxlength='1' size='1' name='separator' value='$separator'/></p>\n";
$html .= "<p><input class='button' type='submit' name='change' value='".("Change")."'/></p>";
$html .= "</form>";

showPage($title, $html);
?>
