<?php
include_once 'menufunctions.php';
include_once 'lib/search.functions.php';
include_once 'lib/reservation.functions.php';
$urlparams = "";
$season = "";

if(!empty($_GET["Series"])) {
	$urlparams = "Series=".intval($_GET["Series"]);
} elseif(!empty($_GET["Pool"])) {
	$urlparams = "Pool=".intval($_GET["Pool"]);
} elseif(!empty($_GET["Season"])) {
	$urlparams = "Season=".$_GET["Season"];
	$season = $_GET["Season"];
}

if(!empty($_POST['remove_x'])){
	$id = $_POST['hiddenDeleteId'];
	RemoveReservation($id,$season);
	$_POST['searchreservation']="1";//do not hide search results
}
if (isset($_POST['schedule']) && isset($_POST['reservations'])) {
	//$url = "location:?view=admin/scheduling_grid&Reservations=".implode(",", $_POST['reservations']);
	$url = "location:?view=admin/schedule&Reservations=".implode(",", $_POST['reservations']);
	if(!empty($urlparams)){
		$url .= "&".$urlparams;
	}
	header($url);
	exit();
}

//common page
$title = _("Game location reservations");
$LAYOUT_ID = RESERVATIONS;
pageTopHeadOpen($title);

echo file_get_contents('script/rescalendar.inc');
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

echo "<p><a href='?view=admin/addreservation&amp;Season=".$season."'>"._("Add reservation")."</a> | ";
echo "<a href='?view=admin/locations&amp;Season=".$season."'>"._("Add location")."</a></p>";

$searchItems = array();
$searchItems[] = 'searchstart';
$searchItems[] = 'searchend';
$searchItems[] = 'searchgroup';
$searchItems[] = 'searchlocation';

$hidden = array();
foreach ($searchItems as $name) {
	if (isset($_POST[$name])) {
		$hidden[$name] = $_POST[$name]; 
	}	
}

$url = "view=admin/reservations";
if(!empty($urlparams)){
	$url .= "&amp;".$urlparams;
}

echo SearchReservation($url, $hidden, array('schedule' => _("Schedule selected")));

contentEnd();
pageEnd();
?>