<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/series.functions.php';

$LAYOUT_ID = RESPONSETEAMS;
$title = _("team responsibilities");

if (isset($_GET['Season'])) {
	$season = $_GET['Season'];
} else {
	$season = CurrentSeason();
}
$seasoninfo = SeasonInfo($season);

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//content
echo "<h1>"._("Team responsibilities")."</h1>";
echo "<table width='500px'>";
echo "<tr>";
echo "<th>"._("Team")."</th>";
echo "<th>"._("Division")."</th>";
echo "<th>"._("Roster")."</th>";
echo "<th>"._("Team profile")."</th>";
if(!intval($seasoninfo['isnationalteams'])){
	echo "<th>"._("Club profile")."</th>";
}
echo "</tr>";

if (isset($_SESSION['userproperties']['userrole']['teamadmin'])) {
	foreach ($_SESSION['userproperties']['userrole']['teamadmin'] as $team => $param) {
		$teaminfo = TeamInfo($team);
		if($teaminfo['season']==$seasoninfo['season_id']){
			
			echo "<tr>";
			echo "<td>".utf8entities($teaminfo['name'])."</td>";
			echo "<td>".utf8entities($teaminfo['seriesname'])."</td>";
			
			echo "<td><a href='?view=user/teamplayers&amp;Team=$team'>"._("Players")."</a></td>";
			echo "<td><a href='?view=user/teamprofile&amp;Team=$team'>"._("Team card")."</a></td>";
			if(!intval($seasoninfo['isnationalteams'])){
				echo "<td><a href='?view=user/clubprofile&amp;Team=$team'>"._("Club card")."</a></td>";
			}
			echo "</tr>";
		}
	}
}
echo "</table>";

//common end
contentEnd();
pageEnd();
?>
