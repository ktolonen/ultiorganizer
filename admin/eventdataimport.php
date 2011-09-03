<?php
	
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';

$html = "";
$title = ("Event data import");
$seasonId = "";
$imported = false;

//check access rights before user can upload data into server
if(!empty($_GET['Season'])){
  $seasonId = $_GET["Season"];
  if(!isSeasonAdmin($seasonId)){die('Insufficient rights to import data');}
}else{
if(!isSuperAdmin()){die('Insufficient rights to import data');}
}
	
if (isset($_POST['add']) && isSuperAdmin()){
  if(is_uploaded_file($_FILES['restorefile']['tmp_name'])) {
		
		$templine = '';
		set_time_limit(300);
		$eventdatahandler = new EventDataXMLHandler();
		$eventdatahandler->XMLToEvent($_FILES['restorefile']['tmp_name'], $seasonId, "new");
		unlink($_FILES['restorefile']['tmp_name']);
		$imported = true;
	}
}elseif (isset($_POST['replace'])){
	if(is_uploaded_file($_FILES['restorefile']['tmp_name'])) {
		
		$templine = '';
		set_time_limit(300);
		$eventdatahandler = new EventDataXMLHandler();
	    $eventdatahandler->XMLToEvent($_FILES['restorefile']['tmp_name'], $seasonId, "replace");  
		unlink($_FILES['restorefile']['tmp_name']);
		$imported = true;
	}
}

//common page
ini_set("post_max_size", "30M");
ini_set("upload_max_filesize", "30M");
ini_set("memory_limit", -1 );

if($imported){
  $html .= "<p>"._("Data imported!")."</p>";
  unset($_POST['restore']);
  unset($_POST['replace']);
}

$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataimport&amp;Season=".$seasonId."'>\n";

$html .= "<p><span class='profileheader'>"._("Select file to import").": </span></p>\n";

$html .= "<p><input class='input' type='file' size='100' name='restorefile'/>";
$html .= "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'/></p>";

if(empty($seasonId)){
  $html .= "<p><input class='button' type='submit' name='add' value='"._("Import")."'/>";
  $html .= "<input class='button' type='button' name='return'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/seasons'\"/></p>";
}else{
  $html .= "<p>"._("This operation updates and adds event data in database with one from file. It will not delete any data or change user rights.")."</p>";
  $html .= "<p><input class='button' type='submit' name='replace' value='"._("Update")."'/>";
  $html .= "<input class='button' type='button' name='return'  value='"._("Return")."' onclick=\"window.location.href='?view=admin/seasonadmin&amp;season=".$seasonId."'\"/></p>";
}	

$html .= "</form>";

showPage(0, $title, $html);

?>