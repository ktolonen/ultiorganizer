<?php
include_once $include_prefix.'lib/season.functions.php';

$LAYOUT_ID = CONTACTS;
if (empty($_GET['Season'])) {
	die(_("Event mandatory"));
}
$season = $_GET['Season'];
$links = getEditSeasonLinks();
if (!isset($links[$season]['?view=user/contacts&amp;Season='.$season])) {
	die(_("Inadequate user rights"));
}

$title = _("Contacts");
//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();

echo "<h2>"._("Contacts")."</h2>";

echo "<div><a href='mailto:";
$resp = SeasonTeamAdmins($season,true);
foreach($resp as $user){
	echo utf8entities($user['email']).";";
}
echo "'>"._("Mail to everyone registered for the event")."</a></div>";

echo "<h3>"._("Contact to Event organizer")."</h3>";
$admins = SeasonAdmins($season);
echo "<ul>";
foreach($admins as $user){
	if(!empty($user['email'])){
		echo "<li> <a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>";
		echo " (".utf8entities($user['name']).")</li>\n";;
	}
}
echo "</ul>\n";

echo "<h3>"._("Contact to Teams")."</h3>";

$series = SeasonSeries($season);
foreach($series as $row){
		
	echo "<p><b>".utf8entities(U_($row['name']))."</b></p>";
	$resp = SeriesTeamResponsibles($row['series_id']);
	echo "<div><a href='mailto:";
	foreach($resp as $user){
		echo utf8entities($user['email']).";";
	}
	echo "'>"._("Mail to teams in")." ".U_($row['name'])." "._("division")."</a></div>";
	
	$teams = SeriesTeams($row['series_id']);
	echo "<ul>";
	foreach($teams as $team){
		echo "<li>".utf8entities($team['name']).":";
		$admins = GetTeamAdmins($team['team_id']);
		while($user = mysql_fetch_assoc($admins)){
			if(!empty($user['email'])){
				echo " <a href='mailto:".utf8entities($user['email'])."'>".utf8entities($user['email'])."</a>";
				echo " (".utf8entities($user['name']).")";
			}
		}
		echo "</li>\n";
	}
	echo "</ul>\n";
}


contentEnd();
pageEnd();
?>