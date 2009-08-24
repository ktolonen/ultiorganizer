<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once 'lib/season.functions.php';
include_once 'builder.php';
$LAYOUT_ID = SEASONS;


//content
OpenConnection();

//process itself on submit
if(!empty($_POST['remove']))
	{
	}

if(!empty($_POST['add']))
	{
	}

if(!empty($_POST['save']))
	{
	
	$selseason = $_POST['curseason'];
	if(!empty($selseason))
		SeasonSetCurrent($selseason);
	}

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();
echo "<form method='post' action='seasons.php'>";

$season = CurrenSeason();

echo "<h2>"._("Nykyinen kausi")." ($season)</h2>\n";
echo "<p>"._("Valitse nykyinen kausi").". "._("Huom. vaikuttaa koko pelikoneen toimintaan!")."</p>\n";
echo "<p><select class='dropdown' name='curseason'>";

$seasons = Seasons();

while($row = mysql_fetch_assoc($seasons))
	{
	if($row['kausi']==$season)
		echo "<option class='dropdown' selected='selected' value='". $row['kausi'] ."'>". $row['kausi'] ."</option>";
	else
		echo "<option class='dropdown' value='". $row['kausi'] ."'>". $row['kausi'] ."</option>";
	}
	
echo "</select></p>";
echo "<p><input class='button' type='submit' name='save' value='"._("Tallenna")."' /></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>
