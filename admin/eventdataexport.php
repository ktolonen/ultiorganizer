<?php

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';

$html = "";
$title = ("Event data export");
$seasonId = "";

if(!empty($_POST['season'])||!empty($_GET["Season"])){
	if(!empty($_POST['season'])){
		$seasonId = $_POST['season'];
	}else{
		$seasonId = $_GET["Season"];
	}
	$filename = $seasonId.".xml";
	header("Pragma: public");
	header("Expires: -1");
	header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	header("Cache-Control: public"); 
	header("Content-Description: File Transfer");
	header("Content-Disposition: attachment; filename=$filename;");
	header("Content-Transfer-Encoding: binary");
	
	$eventdatahandler = new EventDataXMLHandler();
	$data = $eventdatahandler->EventToXML($seasonId);
	header("Content-Length: ".strlen($data));
	echo $data;
}


//season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataexport'>\n";

$html .= "<p>".("Select event").": <select class='dropdown' name='season'>\n";

$seasons = Seasons();
		
while($row = mysql_fetch_assoc($seasons)){
	$html .= "<option class='dropdown' value='". $row['season_id'] . "'>". utf8entities($row['name']) ."</option>";
}

$html .= "</select></p>\n";
$html .= "<p><input class='button' type='submit' name='select' value='".("Select")."'/></p>";

$html .= "</form>";

if(empty($seasonId))
showPage(0, $title, $html);

?>