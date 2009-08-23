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

echo "<h2>Nykyinen kausi ($season)</h2>\n";
echo "<p>Valitse nykyinen kausi. Huom. vaikuttaa koko pelikoneen toimintaan!</p>\n";
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
echo "<p><input class='button' type='submit' name='save' value='Tallenna' /></p>";
/*
echo "<hr/>\n";
echo "<h2>Lis‰‰ uusi kausi</h2>";
echo "<table border='0'>";
echo "<tr><td>Tunnus: </td><td><input class='input' size='10' name='newseasonid'/></td></tr>";
echo "<tr><td>Nimi: </td><td><input class='input' size='50' name='newseasonname'/></td></tr></table>";
echo "<p><input class='button' type='submit' name='add' value='Lis‰‰' /></p>";
echo "<hr/>\n";
echo "<h2>Vanhat kaudet</h2>\n";

echo "<table border='0' cellpadding='4px'>\n";

echo "<tr><th></th><th>Tunnus</th><th>Nimi</th></tr>\n";

mysql_data_seek($seasons,0);
while($row = mysql_fetch_assoc($seasons))
	{
	$arrayYear = strtok($row['kausi'], "."); 
	$arraySeason = strtok(".");
	echo "<tr><td style='text-align: center;'><input type='checkbox' name='delcheck[]' value='".$row['kausi']."'/></td>";
	echo "<td>".$row['kausi']."</td>";
	
	if ($arraySeason == "1")
		{
		echo "<td>Kes&auml; $arrayYear</td>";
		}
	elseif ($arraySeason == "2")
		{
		echo "<td>Talvi $arrayYear</td>";
		}
	else
		{
		echo "<td>-</td>";
		}
	echo "</tr>\n";	
	
	}

echo "</table>
<p><input class='button' name='remove' type='submit' value='Poista valitut'/></p>";
*/
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>