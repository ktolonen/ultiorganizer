<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = ADDSEASONSERIES;


//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
pageTopHeadClose();
OpenConnection();

$serieId=0;
$season=0;

//serie parameters
$sp = array(
	"nimi"=>"",
	"kausi"=>"",
	"luokat"=>"0",
	"showteams"=>"0",
	"jatkosarja"=>"0",
	"alkusarjat"=>"",
	"joukkueita"=>"0",
	"mvgames"=>"0",
	"aikalisa"=>"0",
	"puoliaika"=>"0",
	"pelipist"=>"0",
	"aikakatto"=>"0",
	"pistekatto"=>"0",
	"showserstat"=>"0",
	"lisapist"=>"0",
	"puoliaikapist"=>"0",
	"aikailisia"=>"0",
	"aikalisiaper"=>"game",
	"aikalisiayliajalla"=>"0",
	"aikalisiaikarajan"=>"0",
	"pisteidenvali"=>"0");

if(!empty($_GET["Serie"]))
	$serieId = intval($_GET["Serie"]);

if(!empty($_GET["Season"]))
	$season = $_GET["Season"];

//process itself on submit
if(!empty($_POST['add']))
	{
	if(!empty($_POST['name']))
		{
		$serieId = SerieFromSerieTemplate($season, $_POST['name'], $_POST['template']);
		}
	else
		{
		echo "<p>Sarjan nimi on pakollinen tieto!</p>";
		}
	}
if(!empty($_POST['save']))
	{
	$ok=true;
	$sp['nimi']=$_POST['name'];
	$sp['aikalisa']=intval($_POST['timeoutlength']);
	$sp['puoliaika']=intval($_POST['halftimelength']);
	$sp['pelipist']=intval($_POST['gameto']);
	$sp['aikakatto']=intval($_POST['timecap']);
	$sp['pistekatto']=intval($_POST['pointcap']);
	$sp['lisapist']=intval($_POST['extrapoint']);
	$sp['puoliaikapist']=intval($_POST['halftimepoint']);
	$sp['aikailisia']=intval($_POST['timeouts']);
	$sp['aikalisiaper']=$_POST['timeoutsfor'];
	$sp['aikalisiayliajalla']=intval($_POST['timeoutsOnOvertime']);
	$sp['aikalisiaikarajan']=intval($_POST['timeoutsOnOvertime']);
	$sp['pisteidenvali']=intval($_POST['timebetweenPoints']);
	
	if(!empty($_POST['showteams']))
		$sp['showteams']=1;
	else
		$sp['showteams']=0;
	
	if(!empty($_POST['showserstat']))
		$sp['showserstat']=1;
	else
		$sp['showserstat']=0;

	if(!empty($_POST['continuationserie']))
		$sp['jatkosarja']=1;
	else
		$sp['jatkosarja']=0;
		
	if($ok)
		SetSerie($serieId,$sp);
	}
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();
//if serieid is empty, then add new serie	
if(!$serieId)
	{
	echo "<h2>Lis&auml;&auml; sarja</h2>\n";	
	echo "<form method='post' action='addseasonseries.php?Season=$season'>";
	echo "<table cellpadding='2px'>
			<tr><td class='infocell'>Nimi:</td>
			<td><input class='input' id='name' name='name' value=''/></td><td></td></tr>
			";
	echo "<tr><td class='infocell'>Pohja:</td>
		<td><select class='dropdown' name='template'>";

	$templates = SerieTemplates();
		
	while($row = mysql_fetch_assoc($templates))
		{
		echo "<option class='dropdown' value='". $row['sarja_id'] . "'>". $row['nimi'] ."</option>";
		}
	echo "</select></td></tr>";
	
	/*
	echo "<p><select class='dropdown' name='season'>";

	$seasons = Seasons();

	while($row = mysql_fetch_assoc($seasons))
		{
		if($row['kausi']==$season)
			echo "<option class='dropdown' selected='selected' value='". $row['kausi'] ."'>". $row['kausi'] ."</option>";
		else
			echo "<option class='dropdown' value='". $row['kausi'] ."'>". $row['kausi'] ."</option>";
		}
	echo "</select></p>";
	*/

	
	echo "</table>
		  <p><input class='button' name='add' type='submit' value='Lis&auml;&auml;'/>
		  <input class='button' type='button' name='takaisin'  value='Takaisin' onclick=\"window.location.href='seasonseries.php?Season=$season'\"/></p>
		  </form>";
	}
else
	{
	$info = SerieInfo($serieId);
	$sp['nimi']=$info['nimi'];
	$sp['aikalisa']=$info['aikalisa'];
	$sp['puoliaika']=$info['puoliaika'];
	$sp['pelipist']=$info['pelipist'];
	$sp['aikakatto']=$info['aikakatto'];
	$sp['pistekatto']=$info['pistekatto'];
	$sp['lisapist']=$info['lisapist'];
	$sp['puoliaikapist']=$info['puoliaikapist'];
	$sp['aikailisia']=$info['aikailisia'];
	$sp['aikalisiaper']=$info['aikalisiaper'];
	$sp['aikalisiayliajalla']=$info['aikalisiayliajalla'];
	$sp['aikalisiaikarajan']=$info['aikalisiaikarajan'];
	$sp['pisteidenvali']=$info['pisteidenvali'];
	$sp['jatkosarja']=$info['jatkosarja'];
	$sp['kausi']=$info['kausi'];
	$sp['showserstat']=$info['showserstat'];
	$sp['showteams']=$info['showteams'];
	$sp['alkusarjat']=$info['alkusarjat'];
	
	echo "<h2>Muokkaa sarjaa:</h2>\n";	
	echo "<form method='post' action='addseasonseries.php?Serie=$serieId&amp;Season=$season'>";
	

	echo "<table cellpadding='2px'>
		<tr><td class='infocell'>Nimi:</td>
			<td><input class='input' id='name' name='name' value='".$sp['nimi']."'/></td><td></td></tr>";

	echo "<tr><td class='infocell'>Kausi:</td>
			<td><input class='input' id='newseason' name='newseason' value='".$sp['kausi']."'/></td>
			<td></td></tr>";

	echo "<tr><td class='infocell'>N&auml;yt&auml; joukkueet:</td>";
	if(intval($sp['showteams']))
		echo "<td><input class='input' type='checkbox' id='showteams' name='showteams' checked='checked'/></td>";
	else
		echo "<td><input class='input' type='checkbox' id='showteams' name='showteams' /></td>";
	echo "<td></td></tr>";

	echo "<tr><td class='infocell'>N&auml;yt&auml; sijoitukset:</td>";
	if(intval($sp['showserstat']))
		echo "<td><input class='input' type='checkbox' id='showserstat' name='showserstat' checked='checked'/></td>";
	else
		echo "<td><input class='input' type='checkbox' id='showserstat' name='showserstat' /></td>";
	echo "<td></td></tr>";
	
	echo "<tr><td class='infocell'>Alkusarjat:</td>
			<td><input class='input' id='baseseries' name='baseseries' value='".$sp['alkusarjat']."'/></td>
			<td></td></tr>";
			
	echo "<tr><td class='infocell'>Jatkosarja:</td>";
	if(intval($sp['jatkosarja']))
		echo "<td><input class='input' type='checkbox' id='continuationserie' name='continuationserie' checked='checked'/></td>";
	else
		echo "<td><input class='input' type='checkbox' id='continuationserie' name='continuationserie' /></td>";
	echo "<td></td></tr>";

	
	echo "</table>";

	echo "<h2>Joukkueet:</h2>";	
	
		
	$teams = Teams($serieId);
	if(mysql_num_rows($teams))
		{
		echo "<table width='75%' cellpadding='4px'><tr><th>Nimi</th><th>Seura</th></tr>\n";
			
		while($team = mysql_fetch_assoc($teams))
			{
			echo "<tr>";
			echo "<td>".htmlentities($team['Nimi'])."</td>";
			echo "<td>".htmlentities($team['Seura'])."</td>";
			echo "</tr>\n";	
			}
		echo "</table>";
		}
	echo "<p><input class='button' name='add' type='button' value='Valitse...' onclick=\"window.location.href='serieteams.php?Serie=$serieId&amp;Season=$season'\"/></p>";	
	echo "<h2>Sarjaformaatti (valitusta pohjasta):</h2>";
	
	echo "<table cellpadding='2px'>			
		<tr><td class='infocell'>Ottelut pisteeseen:</td>
			<td><input class='input' id='gameto' name='gameto' value='".$sp['pelipist']."'/></td>
			<td></td></tr>

		<tr><td class='infocell'>Puoliaika:</td>
			<td><input class='input' id='halftimelength' name='halftimelength' value='".$sp['puoliaika']."'/></td>
			<td>minuuttia</td></tr>		

		<tr><td class='infocell'>Puoliaika pisteess&auml;:</td>
			<td><input class='input' id='halftimepoint' name='halftimepoint' value='".$sp['puoliaikapist']."'/></td>
			<td></td></tr>		

			
		<tr><td class='infocell'>Aikakatto:</td>
			<td><input class='input' id='timecap' name='timecap' value='".$sp['aikakatto']."'/></td>
			<td>minuuttia</td></tr>		
			
		<tr><td class='infocell'>Pistekatto:</td>
			<td><input class='input' id='pointcap' name='pointcap' value='".$sp['pistekatto']."'/></td>
			<td>pistett&auml;</td></tr>

		<tr><td class='infocell'>Lis&auml;pisteet aikarajan t&auml;ytytty&auml;:</td>
			<td><input class='input' id='extrapoint' name='extrapoint' value='".$sp['lisapist']."'/></td>
			<td>pistett&auml;</td></tr>

			
		<tr><td class='infocell'>Pisteiden v&auml;linenaika:</td>
			<td><input class='input' id='timebetweenPoints' name='timebetweenPoints' value='".$sp['pisteidenvali']."'/></td>
			<td>sekuntia</td></tr>
			
		<tr><td class='infocell'>Aikalisi&auml;:</td>
			<td><input class='input' id='timeouts' name='timeouts' value='".$sp['aikailisia']."'/></td>
			<td>
			<select class='dropdown' name='timeoutsfor'>";
			if($sp['aikalisiaper']=="game" || $sp['aikalisiaper']=="")
				echo "<option class='dropdown' selected='selected' value='game'>per ottelu</option>";
			else
				echo "<option class='dropdown' value='game'>per ottelu</option>";
			
			if($sp['aikalisiaper']=="half")	
				echo "<option class='dropdown' selected='selected' value='half'>per puoliaika</option>";
			else 
				echo "<option class='dropdown' value='half'>per puoliaika</option>";
				
	echo "	</select>
			</td></tr>

		<tr><td class='infocell'>Aikalis&auml;n kesto:</td>
			<td><input class='input' id='timeoutlength' name='timeoutlength' value='".$sp['aikalisa']."'/></td>
			<td>sekuntia</td></tr>

		<tr><td class='infocell'>Aikalisi&auml; lis&auml;ajalla:</td>
			<td><input class='input' id='timeoutsOnOvertime' name='timeoutsOnOvertime' value='".$sp['aikalisiayliajalla']."'/></td>
			<td>kappaletta per joukkue</td></tr>


		";

		
	echo "</table>";

	echo "<p><input class='button' name='save' type='submit' value='Tallenna'/>";
	echo "<input class='button' type='button' name='takaisin'  value='Takaisin' onclick=\"window.location.href='seasonseries.php?Season=$season'\"/></p>";
	echo "</form>\n";
	}
CloseConnection();

contentEnd();
pageEnd();
?>