<?php
include_once 'view_ids.inc.php';
include_once 'lib/database.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$homedir = dirname(__FILE__);
$LAYOUT_ID = SEASONLIST;

//common page
pageTop();
leftMenu($LAYOUT_ID);
contentStart();

//content
echo "\n<h1>Vanhat kaudet</h1>\n";

OpenConnection();

$seasons = Seasons();


while($season = mysql_fetch_assoc($seasons))
	{
	$arrayYear = strtok($season['kausi'], "."); 
	$arraySeason = strtok(".");
	
	if ($arraySeason == "1")
		{
		echo "<h3>Kes&auml; $arrayYear</h3>";
		}
	elseif ($arraySeason == "2")
		{
		echo "<h3>Talvi $arrayYear</h3>";
		}
	else
		{
		echo "<h3>".$season['kausi']."</h3>";
		}
	echo "<p><a href='Teams.php?Season=".$season['kausi']."'>Joukkueet</a></p>";
	
	$series = Series($season['kausi']);
	
	echo "<table>";
	
	while($serie = mysql_fetch_assoc($series))
		{
		echo "<tr><td><a href='seriestatus.php?Serie=".$serie['sarja_id']."
			'>".$serie['nimi']."</a></td></tr>";
		}
		echo "</table>";
	
	}
CloseConnection();
?>
<p><a href="javascript:history.go(-1);">Palaa</a></p>
<?php
contentEnd();
pageEnd();
?>
