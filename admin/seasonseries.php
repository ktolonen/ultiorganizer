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
		echo "<p class='warning'>"._("Sarjassa pelaa")." ".mysql_num_rows($teams)." "._("joukkuetta").". "._("Joukkueet pit&auml;&auml; poistaa ennen sarjan poistamista").".</p>";
		$ok = false;
		}
	$games = SerieTotalPlayedGames($id);
	if(mysql_num_rows($games))
		{
		echo "<p class='warning'>"._("Sarjaa on jo pelattu").". "._("Et voi poistaa t&auml;t&auml; sarjaa poistamatta pelej&auml;").".</p>";
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

echo "<h2>"._("Sarjat")."</h2>\n";

echo "<table border='0' cellpadding='4px'>\n";

echo "<tr><th>"._("Nimi")."</th>
	<th>"._("Jatkosarja")."</th>
	<th>"._("N&auml;yt&auml; joukkueet")."</th>
	<th></th><th></th></tr>\n";

$series = SeasonSeriesInfo($season);

while($row = mysql_fetch_assoc($series))
	{
	$continuationSerie = intval($row['jatkosarja'])?_("kyll&auml;"):_("ei");
	$showteams = intval($row['showteams'])?_("kyll&auml;"):_("ei");
	
	echo "<tr>";
	echo "<td>".$row['nimi']."</td>";
	echo "<td class='center'>$continuationSerie</td>";
	echo "<td class='center'>$showteams</td>";
	echo "<td class='center'><input class='button' type='button' name='edit'  value='"._("Muokkaa")."' onclick=\"window.location.href='addseasonseries.php?Season=$season&amp;Serie=".$row['sarja_id']."'\"/></td>";
	echo "<td class='center'><input class='button' type='submit' name='remove' value='"._("Poista")."' onclick=\"setId(".$row['sarja_id'].");\"/></td>";
	echo "</tr>\n";	
	}

echo "</table><p><input class='button' name='add' type='button' value='"._("Lis&auml;&auml;")."' onclick=\"window.location.href='addseasonseries.php?Season=$season'\"/></p>";

//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>
