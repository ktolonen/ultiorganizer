<?php
include_once 'localization.php';
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="Pragma" content="no-cache"/>
<meta http-equiv="Expires" content="-1"/>
<?php
include_once '../lib/common.functions.php';
include_once '../lib/season.functions.php';
include_once '../lib/series.functions.php';
include_once '../lib/team.functions.php';
include_once '../lib/timetable.functions.php';

$style = iget("style");
if(empty($style))
	$style='pelikone.css';
	
echo "<link rel='stylesheet' href='$style' type='text/css' />";
echo "<title>"._("Ultiorganizer")."</title>";
?>
</head>
<body>
<?php 

$season = iget("season");
$group = iget("tournament");

if(!$season) {
	$season = CurrentSeason();
}
$games = TimetableGames($season, "season", "all","places",$group);

echo ExtTournamentView($games);
CloseConnection();
?>
</body>
</html>
