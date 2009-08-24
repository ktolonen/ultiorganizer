<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once '../lib/team.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = SEASONTEAMS;



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
	$games = TeamGames($id);
	if(mysql_num_rows($games))
		{
		echo "<p class='warning'>"._("Joukkueella on")." ".mysql_num_rows($games)." "._("peliä").". ".("Pelit pit&auml;&auml; poistaa ennen joukkueen poistamista").".</p>";
		$ok = false;
		}
		
	$players = TeamPlayerList($id);
	if(mysql_num_rows($players))
		{
		echo "<p class='warning'>"._("Joukkueessa on")." ".mysql_num_rows($players)." "._("pelaajaa").". "._("Pelaajat pit&auml;&auml; poistaa ennen joukkueen poistamista").".</p>";
		$ok = false;
		}
		
	if($ok)
		DeleteTeam($id);
	}

echo "<form method='post' action='seasonteams.php?Season=$season'>";

echo "<h2>"._("Joukkueet")."</h2>\n";

echo "<table border='0' cellpadding='4px'>\n";

echo "<tr><th>"._("Nimi")."</th>
	<th>"._("Sarja")."</th>
	<th>"._("Yhteyshenkilö")."</th>
	<th></th><th></th></tr>\n";

$series = Series($season);

while($row = mysql_fetch_assoc($series))
	{
	
	$teams = TeamsByName($row['sarja_id']);

	while($team = mysql_fetch_assoc($teams))
		{
		echo "<tr>";
		echo "<td>".$team['Nimi']."</td>";
		echo "<td>".$row['nimi']."</td>";
		echo "<td>".$team['Seura']."</td>";
		echo "<td class='center'><input class='button' type='button' name='edit'  value='"._("Muokkaa")."' onclick=\"window.location.href='addseasonteams.php?Season=$season&amp;Team=".$team['Joukkue_ID']."'\"/></td>";
		echo "<td class='center'><input class='button' type='submit' name='remove' value='"._("Poista")."' onclick=\"setId(".$team['Joukkue_ID'].");\"/></td>";
		echo "</tr>\n";	
		}
	if(mysql_num_rows($teams))
		echo "<tr><td colspan='5' class='menuseparator'></td></tr>\n";

	}

$teams = SeasonTeams();
while($team = mysql_fetch_assoc($teams))
	{
	echo "<tr>";
	echo "<td>".$team['Nimi']."</td>";
	echo "<td>-</td>";
	echo "<td>".$team['Seura']."</td>";
	echo "<td class='center'><input class='button' type='button' name='edit'  value='"._("Muokkaa")."' onclick=\"window.location.href='addseasonteams.php?Season=$season&amp;Team=".$team['Joukkue_ID']."'\"/></td>";
	echo "<td class='center'><input class='button' type='submit' name='remove' value='"._("Poista")."' onclick=\"setId(".$team['Joukkue_ID'].");\"/></td>";
	echo "</tr>\n";	
	}	
	
echo "</table><p><input class='button' name='add' type='button' value='"._("Lis&auml;&auml;")."' onclick=\"window.location.href='addseasonteams.php?Season=$season'\"/></p>";

//stores id to delete
echo "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>
