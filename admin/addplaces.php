<?php
include_once 'view_ids.inc.php';
include_once '../lib/database.php';
include_once '../lib/place.functions.php';
include_once 'lib/place.functions.php';
include_once 'builder.php';
$LAYOUT_ID = ADDPLACES;

//common page
pageTopHeadOpen();
include_once 'lib/disable_enter.js.inc';
pageTopHeadClose();
leftMenu($LAYOUT_ID);
contentStart();
OpenConnection();

$placeId=0;
$name="";
$info="";
$maplink="";

if(!empty($_GET["Id"]))
	$placeId = intval($_GET["Id"]);
	
//process itself on submit
if(!empty($_POST['save']) || !empty($_POST['add']))
	{
	$name=$_POST['name'];
	$info=$_POST['info'];
	$maplink=$_POST['maplink'];
	
	if(!empty($_POST['add']))
		$placeId = AddPlaceTemplate($name,$info,$maplink);
	else
		SetPlaceTemplate($placeId,$name,$info,$maplink);
	}


	
if($placeId)
	{
	$info = PlaceInfo($placeId);
	$name=$info['paikka'];
	$info=$info['info'];

	echo "<h2>Muokkaa pelipaikkaa</h2>\n";	
	echo "<form method='post' action='addplaces.php?Id=$placeId'>";
	}
else
	{
	echo "<h2>Lis&auml;&auml; pelipaikka</h2>\n";	
	echo "<form method='post' action='addplaces.php'>";
	}

echo "<table cellpadding='2px'>
	<tr><td class='infocell'>Nimi:</td>
		<td><input class='input' id='name' name='name' value='$name'/></td><td></td></tr>

	<tr><td class='infocell'>Lis&auml;tietoa:</td>
		<td><input class='input' id='info' name='info' value='$info'/></td>
		<td></td></tr>

	<tr><td class='infocell'>Karttalinkki:</td>
		<td><input class='input' id='maplink' name='maplink' value='$maplink'/></td>
		<td></td></tr>		
	";

	
echo "</table>";

if($placeId)	
	echo "<p><input class='button' name='save' type='submit' value='Tallenna'/>";
else
	echo "<p><input class='button' name='add' type='submit' value='Lis&auml;&auml;'/>";

echo "<input class='button' type='button' name='takaisin'  value='Takaisin' onclick=\"window.location.href='places.php'\"/></p>";
echo "</form>\n";

CloseConnection();

contentEnd();
pageEnd();
?>