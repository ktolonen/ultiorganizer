<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once '../lib/team.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = SERIETEAMS;



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
	
function toggleField(checkbox, fieldid) 
	{
    var input = document.getElementById(fieldid);
	input.disabled = !checkbox.checked;
	}
//-->
</script>
<?php
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();

if(!empty($_GET["Serie"]))
	$serieId = intval($_GET["Serie"]);

if(!empty($_GET["Season"]))
	$season = $_GET["Season"];

	
//process itself on submit
if(!empty($_POST['save']))
	{
	
	$teams = Teams($serieId);
	
	//Remove un-checked teams
	while($team = mysql_fetch_assoc($teams))
		{
		$found=false;
		foreach($_POST["selcheck"] as $selId) 
			{
			if($team['Joukkue_ID']==$selId)
					{
					$found=true;
					break;
					}	
			}
		if(!$found)
			SerieGameDeleteTeam($serieId, $team['Joukkue_ID']);
		}
	
	if(!empty($_POST["selcheck"]))
		{
		foreach($_POST["selcheck"] as $selId) 
			{
			$found=false;
			$rank = 0;
			if(!empty($_POST["rank$selId"]))
				$rank = $_POST["rank$selId"];
			$teams = Teams($serieId);
			while($team = mysql_fetch_assoc($teams))
				{
				if($team['Joukkue_ID']==$selId)
					{
					$found=true;
					break;
					}	
				}
			if($found)
				SerieGameSetTeam($serieId,$selId,$rank);
			else
				SerieGameAddTeam($serieId,$selId,$rank);

			}
		}
	}

echo "<form method='post' action='serieteams.php?Serie=$serieId&amp;Season=$season'>";

echo "<h1>".htmlentities(SerieName($serieId))."</h1>\n";
echo "<h2>"._("Valitse joukkueet").":</h2>\n";


echo "<table border='0' cellpadding='4px'>\n";

echo "<tr><th>"._("Pelaa")."</th>
	<th>"._("Rank")."</th>
	<th>"._("Nimi")."</th>
	<th>"._("Seura")."</th></tr>\n";


$teams = Teams($serieId);

while($team = mysql_fetch_assoc($teams))
	{
	echo "<tr>";
	echo "<td style='text-align: center;'>
	<input onchange=\"toggleField(this,'rank".$team['Joukkue_ID']."');\"  type='checkbox' name='selcheck[]' checked='checked' value='".$team['Joukkue_ID']."'/></td>";
	echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' 
		name='rank".$team['Joukkue_ID']."' id='rank".$team['Joukkue_ID']."' style='WIDTH: 20px' maxlength='2' size='2' value='".$team['Rank']."'/></td>";
	echo "<td>".htmlentities($team['Nimi'])."</td>";
	echo "<td>".htmlentities($team['Seura'])."</td>";
	echo "</tr>\n";	
	}
if(mysql_num_rows($teams))
	echo "<tr><td colspan='5' class='menuseparator'></td></tr>\n";

$teams = SeasonTeams();
while($team = mysql_fetch_assoc($teams))
	{
	echo "<tr>";
	echo "<td style='text-align: center;'>
	<input onchange=\"toggleField(this,'rank".$team['Joukkue_ID']."');\"  type='checkbox' name='selcheck[]' value='".$team['Joukkue_ID']."'/></td>";
	echo "<td><input onkeyup=\"javascript:this.value=this.value.replace(/[^0-9]/g, '');\" class='input' 
		name='rank".$team['Joukkue_ID']."' id='rank".$team['Joukkue_ID']."' style='WIDTH: 20px' maxlength='2' size='2' disabled='disabled' value=''/></td>";
	echo "<td>".htmlentities($team['Nimi'])."</td>";
	echo "<td>".htmlentities($team['Seura'])."</td>";
	echo "<td></td>";
	echo "</tr>\n";	
	}
if(mysql_num_rows($teams))	
	echo "<tr><td colspan='5' class='menuseparator'></td></tr>\n";

echo "<tr><td colspan='5' class='menuseparator'><input class='button' name='add' type='button' value='"._("Lis&auml;&auml;")."' onclick=\"window.location.href='addseasonteams.php?Serie=$serieId&amp;Season=$season'\"/></td></tr>";
	
echo "</table>";



echo "<p><input class='button' name='save' type='submit' value='"._("Tallenna")."'/>";
echo "<input class='button' type='button' name='takaisin'  value='"._("Palaa")."' onclick=\"window.location.href='addseasonseries.php?Serie=$serieId&amp;Season=$season'\"/></p>";

echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>
