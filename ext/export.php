<?php

include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/configuration.functions.php';

$LAYOUT_ID = EXTEXPORT;
$title = _("Data export");
$encoding = "UTF-8";
$separator = ",";
$season = CurrentSeason();

if (isset($_POST['change'])) {
	$separator = $_POST['separator'];
	$encoding = $_POST['encoding'];
	$season = $_POST['season'];
}

//common page
pageTop($title, false);
leftMenu($LAYOUT_ID);
contentStart();

echo "<h2>"._("CSV-files")."</h2>\n";
echo "<p>"._("Get comma separated UTF-8 encoded files by clicking links below.");
echo " "._("You can also change encoding and separator.")."</p>\n";
echo "<p>". SeasonName($season)."<br/>";
echo "<a href='ext/gamescsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("All scheduled games")."</a><br/>";
echo "<a href='ext/resultscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("All results")."</a><br/>";
echo "<a href='ext/playerscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("Player statistics")."</a><br/>";
echo "<a href='ext/teamscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("Team statistics")."</a><br/>";
echo "<a href='ext/poolscsv.php?Season=$season&amp;Enc=$encoding&amp;Sep=$separator'>&raquo; "._("Pool standings")."</a><br/>";
echo "</p>";

echo "<form method='post' action='?view=ext/export'>\n";
echo "<p>"._("Select event").":	<select class='dropdown' name='season'>\n";

$seasons = Seasons();
		
while($row = mysql_fetch_assoc($seasons)){
  if($row['season_id'] == $season)
  	echo "<option class='dropdown' selected='selected' value='". $row['season_id'] . "'>". utf8entities($row['name']) ."</option>";
  else
  	echo "<option class='dropdown' value='". $row['season_id'] . "'>". utf8entities($row['name']) ."</option>";
 }

echo "</select></p>\n";

echo "<p>".("Select encoding").": <select class='dropdown' name='encoding'>\n";
$encodings = array("UTF-8","ISO-8859-15","Windows-1251","Windows-1252");
foreach ($encodings as $enc){
	if($enc==$encoding){
		echo "<option class='dropdown' selected='selected' value='$enc'>". utf8entities($enc) ."</option>";
	}else{
		echo "<option class='dropdown' value='$enc'>". utf8entities($enc) ."</option>";
	}
}
echo "</select></p>\n";
echo "<p>".("CSV separator").": <input class='input' maxlength='1' size='1' name='separator' value='$separator'/></p>\n";
echo "<p><input class='button' type='submit' name='change' value='".("Change")."'/></p>";
echo "</form>";

contentEnd();
pageEnd();
?>
