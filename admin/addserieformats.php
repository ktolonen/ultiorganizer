<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/season.functions.php';
include_once '../lib/serie.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/serie.functions.php';
include_once 'builder.php';
$LAYOUT_ID = ADDSERIEFORMATS;



//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();

$serieId=0;
$name="";
$timeoutlength="0";
$halftimelength="0";
$gameto="0";
$timecap="0";
$pointcap="0";
$extrapoint="0";
$halftimepoint="0";
$timeouts="0";
$timeoutsfor="game";
$timeoutsOnOvertime="0";
$timeoutsAfter="0";
$timebetweenPoints="0";
$continuationserie="0";

if(!empty($_GET["Id"]))
	$serieId = intval($_GET["Id"]);
	
//process itself on submit
if(!empty($_POST['save']) || !empty($_POST['add']))
	{
	$name=$_POST['name'];
	$timeoutlength=intval($_POST['timeoutlength']);
	$halftimelength=intval($_POST['halftimelength']);
	$gameto=intval($_POST['gameto']);
	$timecap=intval($_POST['timecap']);
	$pointcap=intval($_POST['pointcap']);
	$extrapoint=intval($_POST['extrapoint']);
	$halftimepoint=intval($_POST['halftimepoint']);
	$timeouts=intval($_POST['timeouts']);
	$timeoutsfor=$_POST['timeoutsfor'];
	$timeoutsOnOvertime=intval($_POST['timeoutsOnOvertime']);
	$timeoutsAfter=intval($_POST['timeoutsOnOvertime']);
	$timebetweenPoints=intval($_POST['timebetweenPoints']);
	$continuationserie=intval($_POST['continuationserie']);
	
	if(!empty($_POST['add']))
		$serieId = AddSerieTemplate($name,$timeoutlength,$halftimelength,$gameto,$timecap,
			$pointcap,$extrapoint,$halftimepoint,$timeouts,$timeoutsfor,$timeoutsOnOvertime,$timeoutsAfter,$timebetweenPoints,$continuationserie);
	else
		SetSerieTemplate($serieId,$name,$timeoutlength,$halftimelength,$gameto,$timecap,
			$pointcap,$extrapoint,$halftimepoint,$timeouts,$timeoutsfor,$timeoutsOnOvertime,$timeoutsAfter,$timebetweenPoints,$continuationserie);
	}


	
if($serieId)
	{
	$info = SerieInfo($serieId);
	$name=$info['nimi'];
	$timeoutlength=$info['aikalisa'];
	$halftimelength=$info['puoliaika'];
	$gameto=$info['pelipist'];
	$timecap=$info['aikakatto'];
	$pointcap=$info['pistekatto'];
	$extrapoint=$info['lisapist'];
	$halftimepoint=$info['puoliaikapist'];
	$timeouts=$info['aikailisia'];
	$timeoutsfor=$info['aikalisiaper'];
	$timeoutsOnOvertime=$info['aikalisiayliajalla'];
	$timeoutsAfter=$info['aikalisiaikarajan'];
	$timebetweenPoints=$info['pisteidenvali'];
	$continuationserie=$info['jatkosarja'];

	echo "<h2>"._("Muokkaa sarjaformaattia")."</h2>\n";	
	echo "<form method='post' action='addserieformats.php?Id=$serieId'>";
	}
else
	{
	echo "<h2>"._("Lis&auml;&auml; sarjaformaatti")."</h2>\n";	
	echo "<form method='post' action='addserieformats.php'>";
	}
	


echo "<table cellpadding='2px'>
	<tr><td class='infocell'>"._("Nimi").":</td>
		<td><input class='input' id='name' name='name' value='$name'/></td><td></td></tr>


	<tr><td class='infocell'>"._("Pelit pisteeseen").":</td>
		<td><input class='input' id='gameto' name='gameto' value='$gameto'/></td>
		<td></td></tr>

	<tr><td class='infocell'>"._("Puoliaika").":</td>
		<td><input class='input' id='halftimelength' name='halftimelength' value='$halftimelength'/></td>
		<td>"._("minuuttia")."</td></tr>		

	<tr><td class='infocell'>"._("Puoliaika pisteess&auml;").":</td>
		<td><input class='input' id='halftimepoint' name='halftimepoint' value='$halftimepoint'/></td>
		<td></td></tr>		

		
	<tr><td class='infocell'>"._("Aikakatto").":</td>
		<td><input class='input' id='timecap' name='timecap' value='$timecap'/></td>
		<td>minuuttia</td></tr>		
		
	<tr><td class='infocell'>"._("Pistekatto").":</td>
		<td><input class='input' id='pointcap' name='pointcap' value='$pointcap'/></td>
		<td>pistett&auml;</td></tr>

	<tr><td class='infocell'>"._("Lis&auml;pisteet aikarajan t&auml;ytytty&auml;").":</td>
		<td><input class='input' id='extrapoint' name='extrapoint' value='$extrapoint'/></td>
		<td>pistett&auml;</td></tr>

		
	<tr><td class='infocell'>"._("Pisteiden v&auml;linenaika").":</td>
		<td><input class='input' id='timebetweenPoints' name='timebetweenPoints' value='$timebetweenPoints'/></td>
		<td>sekuntia</td></tr>
		
	<tr><td class='infocell'>"._("Aikalisi&auml;").":</td>
		<td><input class='input' id='timeouts' name='timeouts' value='$timeouts'/></td>
		<td>
		<select class='dropdown' name='timeoutsfor'>";
		if($timeoutsfor=="game" || $timeoutsfor=="")
			echo "<option class='dropdown' selected='selected' value='game'>"._("per peli")."</option>";
		else
			echo "<option class='dropdown' value='game'>"._("per peli")."</option>";
		
		if($timeoutsfor=="half")	
			echo "<option class='dropdown' selected='selected' value='half'>"._("per puoliaika")."</option>";
		else 
			echo "<option class='dropdown' value='half'>"._("per puoliaika")."</option>";
			
echo "	</select>
		</td></tr>

	<tr><td class='infocell'>"._("Aikalis&auml;n kesto").":</td>
		<td><input class='input' id='timeoutlength' name='timeoutlength' value='$timeoutlength'/></td>
		<td>"._("sekuntia")."</td></tr>

	<tr><td class='infocell'>"._("Aikalisi&auml; lis&auml;ajalla").":</td>
		<td><input class='input' id='timeoutsOnOvertime' name='timeoutsOnOvertime' value='$timeoutsOnOvertime'/></td>
		<td>"._("per joukkue")."</td></tr>

	<tr><td class='infocell'>"._("Jatkosarja").":</td>
		<td><input class='input' id='continuationserie' name='continuationserie' value='$continuationserie'/></td>
		<td></td></tr>
	";

	
echo "</table>";

if($serieId)	
	echo "<p><input class='button' name='save' type='submit' value='"._("Tallenna")."'/>";
else
	echo "<p><input class='button' name='add' type='submit' value='"._("Lis&auml;&auml;")."'/>";

echo "<input class='button' type='button' name='takaisin'  value='Takaisin' onclick=\"window.location.href='serieformats.php'\"/></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>
