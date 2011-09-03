<?php
include_once $include_prefix.'lib/search.functions.php';
include_once $include_prefix.'lib/season.functions.php';
$LAYOUT_ID = SELECT_POOLSELECTOR;
$title = _("Choose pools to show");

//common page

pageTop($title, false);
leftMenu($LAYOUT_ID);
contentStart();

if (!empty($_GET['user'])) {
	$target = "view=user/userinfo&amp;user=".urlencode($_GET['user']);
} else {
	$target = "view=user/userinfo";
}
//content
echo "<h2>".$title."</h2>";
if ($_GET['selectortype'] == 'currentseason') {
	echo "<h3>"._("Current event")." (".utf8entities(CurrentSeasonName()).")</h3>\n";
	echo "<form method='post' action='?".$target."'>\n";
	echo "<div><input type='hidden' name='selectortype' value='currentseason'/>\n";
	echo "<input class='button' type='submit' name='selectpoolselector' value='"._("Select")."'/></div>\n";
	echo "</form>\n";	
} elseif ($_GET['selectortype'] == 'team') {
	echo "<h3>"._("Team pools")."</h3>";
	echo SearchTeam($target, array('selectortype' => 'team'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['selectortype'] == 'season') {
	echo "<h3>"._("Event")."</h3>";
	echo SearchSeason($target, array('selectortype' => 'season'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['selectortype'] == 'series') {
	echo "<h3>"._("Division")."</h3>\n";
	echo SearchSeries('view=user/userinfo', array('selectortype' => 'series'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['selectortype'] == 'pool') {
	echo "<h3>"._("Division")."</h3>";
	echo SearchPool($target, array('selectortype' => 'pool'), array('selectpoolselector' => _("Select"), 'cancel' => _("Cancel")));
}

contentEnd();
pageEnd();
?>
