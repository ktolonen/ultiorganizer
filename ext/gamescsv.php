<?php
include_once 'localization.php';
include_once '../lib/timetable.functions.php';

$season = iget("season");
$encoding = 'UTF-8';
$separator = ',';

if (iget('enc')) {
	$encoding = iget('enc');
}
if (iget('sep')) {
	$separator = iget('sep');
}

$data = TimetableToCsv($season, $separator);
$data = mb_convert_encoding($data, $encoding, 'UTF-8');
CloseConnection();
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Length: " . strlen($data));
header("Content-type: text/x-csv");
header("Content-Disposition: attachment; filename=games.csv");
echo $data;
