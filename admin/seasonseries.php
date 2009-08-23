<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = SEASONSERIES;



//common page
pageTopHeadOpen();
?>
<script type="text/javascript">
<!--
function setId(id) 
	{
	var input = document.getElementById("hiddenDeleteId");
	input.value = id;
	}
//-->
</script>
<?php
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();

$season = $_GET["Season"];
	
//process itself on submit
if(!empty($_POST['remove']))
	{
	$id = $_POST['hiddenDeleteId'];
	$ok = true;
		
	//run some test to for safe deletion
	$teams = Teams($id);
	if(mysql_num_rows($teams))
		{
		echo "<p class='warning'>Sarjassa pelaa ".mysql_num_rows($teams)." joukkuetta. Joukkueet pit&auml;&auml; poistaa ennen sarjan poistamista.</p>";
		$ok = false;
		}
	$games = SerieTotalPlayedGames($id);
	if(mysql_num_rows($games))
		{
		echo "<p class='warning'>Sarjaa on jo pelattu. Et voi poistaa t&auml;t&auml; sarjaa poistamatta pelej&auml;.</p>";
		$ok = false;
		}
		
	if($ok)
		DeleteSerie($id);
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
	
echo "<form method='post' action='seasonseries.php?Season=$season'>";

echo "<h2>Sarjat</h2>\n";

echo "<table border='0' cellpadding='4px'>\n";

echo "<tr><th>Nimi</th>
	<th>Jatkosarja</th>
	<th>N&auml;yt&auml; joukkueet</th>
	<th></th><th></th></tr>\n";

$series = SeasonSeriesInfo($season);

while($row = mysql_fetch_assoc($series))
	{
	$continuationSerie = intval($row['jatkosarja'])?"kyll&auml;":"ei";
	$showteams = intval($row['showteams'])?"kyll&auml;":"ei";
	
	echo "<tr>";
	echo "<td>".$row['nimi']."</td>";
	echo "<td class='center'>$continuationSerie</td>";
	echo "<td class='center'>$showteams</td>";
	echo "<td class='center'><input class='button' type='button' name='edit'  value='muokkaa' onclick=\"window.location.href='addseasonseries.php?Season=$season&amp;Serie=".$row['sarja_id']."'\"/></td>";
	echo "<td class='center'><input class='button' type='submit' name='remove' value='poista' onclick=\"setId(".$row['sarja_id'].");\"/></td>";
	echo "</tr>\n";	
	}

echo "</table><p><input class='button' name='add' type='button' value='Lis&auml;&auml; uusi' onclick=\"window.location.href='addseasonseries.php?Season=$season'\"/></p>";

//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>