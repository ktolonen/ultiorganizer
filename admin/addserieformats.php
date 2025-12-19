<?php
include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = ADDSERIEFORMATS;

$title = _("Edit");
$html = "";

$poolId = 0;
//pool parameters
$pp = array(
	"name" => "",
	"season_id" => "",
	"type" => "0",
	"ordering" => "A",
	"visible" => "0",
	"continuingpool" => "0",
	"alkupoolt" => "",
	"teams" => "0",
	"mvgames" => "1",
	"timeoutlen" => "0",
	"halftime" => "0",
	"winningscore" => "0",
	"timecap" => "0",
	"timeslot" => "0",
	"scorecap" => "0",
	"played" => "0",
	"addscore" => "0",
	"halftimescore" => "0",
	"timeouts" => "0",
	"timeoutsper" => "game",
	"timeoutsovertime" => "0",
	"timeoutstimecap" => "0",
	"betweenpointslen" => "0",
	"forfeitscore" => "0",
	"forfeitagainst" => "0",
	"drawsallowed" => "0"
);

if (isset($_GET["template"])) {
	$poolId = intval($_GET["template"]);
}
//process itself on submit
if (!empty($_POST['save']) || !empty($_POST['add'])) {
	$pp['name'] = empty($_POST['name']) ? "no name" : $_POST['name'];
	$pp['timeoutlen'] = intval($_POST['timeoutlength']);
	$pp['halftime'] = intval($_POST['halftimelength']);
	$pp['winningscore'] = intval($_POST['gameto']);
	$pp['timecap'] = intval($_POST['timecap']);
	$pp['timeslot'] = intval($_POST['timeslot']);
	$pp['scorecap'] = intval($_POST['pointcap']);
	$pp['addscore'] = intval($_POST['extrapoint']);
	$pp['halftimescore'] = intval($_POST['halftimepoint']);
	$pp['timeouts'] = intval($_POST['timeouts']);
	$pp['timeoutsper'] = $_POST['timeoutsfor'];
	$pp['timeoutsovertime'] = intval($_POST['timeoutsOnOvertime']);
	$pp['timeoutstimecap'] = intval($_POST['timeoutsOnOvertime']);
	$pp['betweenpointslen'] = intval($_POST['timebetweenPoints']);

	if (!empty($_POST['drawsallowed']))
		$pp['drawsallowed'] = 1;
	else
		$pp['drawsallowed'] = 0;

	if (!empty($_POST['add'])) {
		$poolId = AddPoolTemplate($pp);
	} else {
		SetPoolTemplate($poolId, $pp);
	}
}

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
$html .= yuiLoad(array("utilities", "datasource", "autocomplete"));
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

if ($poolId) {
	$info = PoolTemplateInfo($poolId);
	$pp['name'] = $info['name'];
	$pp['type'] = $info['type'];
	$pp['teams'] = $info['teams'];
	$pp['timeoutlen'] = $info['timeoutlen'];
	$pp['halftime'] = $info['halftime'];
	$pp['winningscore'] = $info['winningscore'];
	$pp['timecap'] = $info['timecap'];
	$pp['timeslot'] = $info['timeslot'];
	$pp['scorecap'] = $info['scorecap'];
	$pp['addscore'] = $info['addscore'];
	$pp['halftimescore'] = $info['halftimescore'];
	$pp['timeouts'] = $info['timeouts'];
	$pp['timeoutsper'] = $info['timeoutsper'];
	$pp['timeoutsovertime'] = $info['timeoutsovertime'];
	$pp['timeoutstimecap'] = $info['timeoutstimecap'];
	$pp['betweenpointslen'] = $info['betweenpointslen'];
	$pp['continuingpool'] = $info['continuingpool'];
	$pp['mvgames'] = $info['mvgames'];
	$pp['forfeitagainst'] = $info['forfeitagainst'];
	$pp['forfeitscore'] = $info['forfeitscore'];
	$pp['drawsallowed'] = $info['drawsallowed'];

	$html .= "<h2>" . _("Edit rule template") . "</h2>\n";
	$html .= "<form method='post' action='?view=admin/addserieformats&amp;template=$poolId'>";
} else {
	$html .= "<h2>" . _("Add rule template") . "</h2>\n";
	$html .= "<form method='post' action='?view=admin/addserieformats'>";
}

$html .= "<table cellpadding='2'>
			<tr><td class='infocell'>" . _("Name") . ":</td>
			<td>" . TranslatedField("name", $pp['name'], "150", "30") . "</td><td></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Game points") . ":</td>
		<td><input class='input' id='gameto' name='gameto' value='" . utf8entities($pp['winningscore']) . "'/></td>
		<td></td></tr>
		
	<tr><td class='infocell'>" . _("Half-time") . ":</td>
		<td><input class='input' id='halftimelength' name='halftimelength' value='" . utf8entities($pp['halftime']) . "'/></td>
		<td>" . _("minutes") . "</td></tr>		

	<tr><td class='infocell'>" . _("Half-time at point") . ":</td>
		<td><input class='input' id='halftimepoint' name='halftimepoint' value='" . utf8entities($pp['halftimescore']) . "'/></td>
		<td></td></tr>		
		
	<tr><td class='infocell'>" . _("Time cap") . ":</td>
		<td><input class='input' id='timecap' name='timecap' value='" . utf8entities($pp['timecap']) . "'/></td>
		<td>" . _("minutes") . "</td></tr>		
	
	<tr><td class='infocell'>" . _("Time slot") . ":</td>
		<td><input class='input' id='timeslot' name='timeslot' value='" . utf8entities($pp['timeslot']) . "'/></td>
		<td>" . _("minutes") . "</td></tr>		
		
	<tr><td class='infocell'>" . _("Point cap") . ":</td>
		<td><input class='input' id='pointcap' name='pointcap' value='" . utf8entities($pp['scorecap']) . "'/></td>
		<td>" . _("points") . "</td></tr>

	<tr><td class='infocell'>" . _("Additional points after time cap") . ":</td>
		<td><input class='input' id='extrapoint' name='extrapoint' value='" . utf8entities($pp['addscore']) . "'/></td>
		<td>" . _("points") . "</td></tr>

		
	<tr><td class='infocell'>" . _("Time between points") . ":</td>
		<td><input class='input' id='timebetweenPoints' name='timebetweenPoints' value='" . utf8entities($pp['betweenpointslen']) . "'/></td>
		<td>" . _("seconds") . "</td></tr>
		
	<tr><td class='infocell'>" . _("Time-outs") . ":</td>
		<td><input class='input' id='timeouts' name='timeouts' value='" . utf8entities($pp['timeouts']) . "'/></td>
		<td>
		<select class='dropdown' name='timeoutsfor'>";

if ($pp['timeoutsper'] == "game" || $pp['timeoutsper'] == "") {
	$html .= "<option class='dropdown' selected='selected' value='game'>" . _("per game") . "</option>";
} else {
	$html .= "<option class='dropdown' value='game'>" . _("per game") . "</option>";
}

if ($pp['timeoutsper'] == "half") {
	$html .= "<option class='dropdown' selected='selected' value='half'>" . _("per half") . "</option>";
} else {
	$html .= "<option class='dropdown' value='half'>" . _("per half") . "</option>";
}

$html .= "	</select>
		</td></tr>
	<tr><td class='infocell'>" . _("Time-out duration") . ":</td>
		<td><input class='input' id='timeoutlength' name='timeoutlength' value='" . utf8entities($pp['timeoutlen']) . "'/></td>
		<td>" . _("seconds") . "</td></tr>

	<tr><td class='infocell'>" . _("Time-outs on overtime") . ":</td>
		<td><input class='input' id='timeoutsOnOvertime' name='timeoutsOnOvertime' value='" . utf8entities($pp['timeoutsovertime']) . "'/></td>
		<td>" . _("per team") . "</td></tr>";

$html .= "<tr><td class='infocell'>" . _("Draws allowed") . ":</td>";
if (intval($pp['drawsallowed']))
	$html .= "<td><input class='input' type='checkbox' id='drawsallowed' name='drawsallowed' checked='checked'/></td>";
else
	$html .= "<td><input class='input' type='checkbox' id='drawsallowed' name='drawsallowed' /></td>";
$html .= "<td></td></tr>";

$html .= "</table>";

if ($poolId) {
	$html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
} else {
	$html .= "<p><input class='button' name='add' type='submit' value='" . _("Add") . "'/>";
}

$html .= "<input class='button' type='button' name='back'  value='" . _("Back") . "' onclick=\"window.location.href='?view=admin/serieformats'\"/></p>";
$html .= "</form>\n";
$html .= TranslationScript("name");

echo $html;
contentEnd();
pageEnd();
