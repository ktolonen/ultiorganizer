<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = ADDSERIEFORMATS;

$title = _("Edit");

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$poolId=0;
//pool parameters
$pp = array(
	"name"=>"",
	"season_id"=>"",
	"type"=>"0",
	"ordering"=>"A",
	"visible"=>"0",
	"continuingpool"=>"0",
	"alkupoolt"=>"",
	"teams"=>"0",
	"mvgames"=>"1",
	"timeoutlen"=>"0",
	"halftime"=>"0",
	"winningscore"=>"0",
	"timecap"=>"0",
	"timeslot"=>"0",
	"scorecap"=>"0",
	"played"=>"0",
	"addscore"=>"0",
	"halftimescore"=>"0",
	"timeouts"=>"0",
	"timeoutsper"=>"game",
	"timeoutsovertime"=>"0",
	"timeoutstimecap"=>"0",
	"betweenpointslen"=>"0",
	"forfeitscore"=>"0",
	"forfeitagainst"=>"0");
	
if(!empty($_GET["Id"]))
	$poolId = intval($_GET["Id"]);
	
//process itself on submit
if(!empty($_POST['save']) || !empty($_POST['add']))
	{
	$pp['name']=$_POST['name'];
	$pp['type']=$_POST['type'];
	$pp['teams']=intval($_POST['teams']);
	$pp['timeoutlen']=intval($_POST['timeoutlength']);
	$pp['halftime']=intval($_POST['halftimelength']);
	$pp['winningscore']=intval($_POST['gameto']);
	$pp['timecap']=intval($_POST['timecap']);
	$pp['timeslot']=intval($_POST['timeslot']);
	$pp['scorecap']=intval($_POST['pointcap']);
	$pp['addscore']=intval($_POST['extrapoint']);
	$pp['halftimescore']=intval($_POST['halftimepoint']);
	$pp['timeouts']=intval($_POST['timeouts']);
	$pp['timeoutsper']=$_POST['timeoutsfor'];
	$pp['timeoutsovertime']=intval($_POST['timeoutsOnOvertime']);
	$pp['timeoutstimecap']=intval($_POST['timeoutsOnOvertime']);
	$pp['betweenpointslen']=intval($_POST['timebetweenPoints']);
	$pp['mvgames']=intval($_POST['mvgames']);
	$pp['forfeitscore']=intval($_POST['forfeitscore']);
	$pp['forfeitagainst']=intval($_POST['forfeitagainst']);
	
	if(!empty($_POST['continuationserie']))
		$pp['continuingpool']=1;
	else
		$pp['continuingpool']=0;
	
	if(!empty($_POST['add']))
		$poolId = AddPoolTemplate($pp);
	else
		SetPoolTemplate($poolId,$pp);
	}
	
if($poolId)
	{
	$info = PoolTemplateInfo($poolId);
	$pp['name']=$info['name'];
	$pp['type']=$info['type'];
	$pp['teams']=$info['teams'];
	$pp['timeoutlen']=$info['timeoutlen'];
	$pp['halftime']=$info['halftime'];
	$pp['winningscore']=$info['winningscore'];
	$pp['timecap']=$info['timecap'];
	$pp['timeslot']=$info['timeslot'];
	$pp['scorecap']=$info['scorecap'];
	$pp['addscore']=$info['addscore'];
	$pp['halftimescore']=$info['halftimescore'];
	$pp['timeouts']=$info['timeouts'];
	$pp['timeoutsper']=$info['timeoutsper'];
	$pp['timeoutsovertime']=$info['timeoutsovertime'];
	$pp['timeoutstimecap']=$info['timeoutstimecap'];
	$pp['betweenpointslen']=$info['betweenpointslen'];
	$pp['continuingpool']=$info['continuingpool'];
	$pp['mvgames']=$info['mvgames'];
	$pp['forfeitagainst']=$info['forfeitagainst'];
	$pp['forfeitscore']=$info['forfeitscore'];
	

	echo "<h2>"._("Edit pool format")."</h2>\n";	
	echo "<form method='post' action='?view=admin/addserieformats&amp;Id=$poolId'>";
	}
else
	{
	echo "<h2>"._("Add pool format")."</h2>\n";	
	echo "<form method='post' action='?view=admin/addserieformats'>";
	}

	echo "<table cellpadding='2px'>
			<tr><td class='infocell'>"._("Name").":</td>
			<td>".TranslatedField("name", $pp['name'], "150", "30")."</td><td></td></tr>\n";
	echo "<tr><td class='infocell'>"._("Type").":</td><td>";
	
	echo "<select class='dropdown' name='type'>";
	if($pp['type']=="1")
		echo "<option class='dropdown' selected='selected' value='1'>"._("Division")."</option>";
	else
		echo "<option class='dropdown' value='1'>"._("Division")."</option>";
			
	if($pp['type']=="2")
		echo "<option class='dropdown' selected='selected' value='2'>"._("Play-off")."</option>";
	else
		echo "<option class='dropdown' value='2'>"._("Play-off")."</option>";
				
	if($pp['type']=="3")
		echo "<option class='dropdown' selected='selected' value='3'>"._("Swissdraw")."</option>";
	else
		echo "<option class='dropdown' value='3'>"._("Swissdraw")."</option>";
				
	echo "</select></td></tr>";

	echo "<tr><td class='infocell'>"._("Teams").":</td>
		<td><input class='input' id='teams' name='teams' value='".$pp['teams']."'/></td>
		<td></td></tr>";
		
	echo "<tr><td class='infocell'>"._("Game points").":</td>
		<td><input class='input' id='gameto' name='gameto' value='".$pp['winningscore']."'/></td>
		<td></td></tr>
		
	<tr><td class='infocell'>"._("Half-time").":</td>
		<td><input class='input' id='halftimelength' name='halftimelength' value='".$pp['halftime']."'/></td>
		<td>"._("minutes")."</td></tr>		

	<tr><td class='infocell'>"._("Half-time at point").":</td>
		<td><input class='input' id='halftimepoint' name='halftimepoint' value='".$pp['halftimescore']."'/></td>
		<td></td></tr>		

		
	<tr><td class='infocell'>"._("Time cap").":</td>
		<td><input class='input' id='timecap' name='timecap' value='".$pp['timecap']."'/></td>
		<td>"._("minutes")."</td></tr>		
	
	<tr><td class='infocell'>"._("Time slot").":</td>
		<td><input class='input' id='timeslot' name='timeslot' value='".$pp['timeslot']."'/></td>
		<td>"._("minutes")."</td></tr>		
		
	<tr><td class='infocell'>"._("Point cap").":</td>
		<td><input class='input' id='pointcap' name='pointcap' value='".$pp['scorecap']."'/></td>
		<td>"._("points")."</td></tr>

	<tr><td class='infocell'>"._("Additional points after time cap").":</td>
		<td><input class='input' id='extrapoint' name='extrapoint' value='".$pp['addscore']."'/></td>
		<td>"._("points")."</td></tr>

		
	<tr><td class='infocell'>"._("Time between points").":</td>
		<td><input class='input' id='timebetweenPoints' name='timebetweenPoints' value='".$pp['betweenpointslen']."'/></td>
		<td>"._("seconds")."</td></tr>
		
	<tr><td class='infocell'>"._("Time-outs").":</td>
		<td><input class='input' id='timeouts' name='timeouts' value='".$pp['timeouts']."'/></td>
		<td>
		<select class='dropdown' name='timeoutsfor'>";
		if($pp['timeoutsper']=="game" || $pp['timeoutsper']=="")
			echo "<option class='dropdown' selected='selected' value='game'>"._("per game")."</option>";
		else
			echo "<option class='dropdown' value='game'>"._("per game")."</option>";
		
		if($pp['timeoutsper']=="half")	
			echo "<option class='dropdown' selected='selected' value='half'>"._("per half")."</option>";
		else 
			echo "<option class='dropdown' value='half'>"._("per half")."</option>";
			
echo "	</select>
		</td></tr>

	<tr><td class='infocell'>"._("Time-out duration").":</td>
		<td><input class='input' id='timeoutlength' name='timeoutlength' value='".$pp['timeoutlen']."'/></td>
		<td>"._("seconds")."</td></tr>

	<tr><td class='infocell'>"._("Time-outs on overtime").":</td>
		<td><input class='input' id='timeoutsOnOvertime' name='timeoutsOnOvertime' value='".$pp['timeoutsovertime']."'/></td>
		<td>"._("per team")."</td></tr>";
	echo "<tr><td class='infocell'>"._("Continuing pool").":</td>";
	
	if(intval($pp['continuingpool']))
		echo "<td><input class='input' type='checkbox' id='continuationserie' name='continuationserie' checked='checked'/></td>";
	else
		echo "<td><input class='input' type='checkbox' id='continuationserie' name='continuationserie' /></td>";
		
	echo "<td></td></tr>";
	echo "<tr><td class='infocell'>"._("Games to move").":</td><td>";
	
	echo "<select class='dropdown' name='mvgames'>";
	if($pp['mvgames']=="0")
		echo "<option class='dropdown' selected='selected' value='0'>"._("All")."</option>";
	else
		echo "<option class='dropdown' value='0'>"._("All")."</option>";

	if($pp['mvgames']=="1")
		echo "<option class='dropdown' selected='selected' value='1'>"._("None")."</option>";
	else
		echo "<option class='dropdown' value='1'>"._("None")."</option>";
		
	if($pp['mvgames']=="2")
		echo "<option class='dropdown' selected='selected' value='2'>"._("Mutual")."</option>";
	else
		echo "<option class='dropdown' value='2'>"._("Mutual")."</option>";
		
	echo "</select></td></tr>";

	echo "
	<tr><td class='infocell'>"._("Forfeit/BYE against").":</td>
		<td><input class='input' id='forfeitagainst' name='forfeitagainst' value='".$pp['forfeitagainst']."'/></td>
		<td>"._("points for the team giving up / BYE")."</td></tr>

	<tr><td class='infocell'>"._("Forfeit/BYE score").":</td>
		<td><input class='input' id='forfeitscore' name='forfeitscore' value='".$pp['forfeitscore']."'/></td>
		<td>"._("points for their remaining opponent")."</td></tr>";
	
	
echo "</table>";

if($poolId)	
	echo "<p><input class='button' name='save' type='submit' value='"._("Save")."'/>";
else
	echo "<p><input class='button' name='add' type='submit' value='"._("Add")."'/>";

echo "<input class='button' type='button' name='back'  value='"._("Back")."' onclick=\"window.location.href='?view=admin/serieformats'\"/></p>";
echo "</form>\n";
echo TranslationScript("name");
contentEnd();
pageEnd();
?>
