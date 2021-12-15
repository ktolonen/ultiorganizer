<?php
include_once 'lib/search.functions.php';
include_once 'lib/season.functions.php';
$LAYOUT_ID = SELECT_USERROLE;
$title = _("Select user roles");

//common page

pageTopHeadOpen($title);
echo file_get_contents('script/rescalendar.inc');
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

if (!empty($_GET['user'])) {
	$target = "view=user/userinfo&amp;user=" . urlencode($_GET['user']);
} else {
	$target = "view=user/userinfo";
}
//content
echo "<h2>" . $title . "</h2>";
if ($_GET['userrole'] == 'superadmin') {
	echo "<h3>" . _("Administrator") . "</h3>\n";
	echo "<form method='post' action='?" . $target . "'>\n";
	echo "<p>";
	echo "<input type='hidden' name='userrole' value='superadmin'/>\n";
	echo "<input type='submit' name='selectuserrole' value='" . _("Select") . "'/>\n";
	echo "<input type='submit' name='cancel' value='" . _("Cancel") . "'/>\n";
	echo "</p>";
	echo "</form>\n";
} elseif ($_GET['userrole'] == 'translationadmin') {
	echo "<h3>" . _("Translation administrator") . "</h3>\n";
	echo "<form method='post' action='?" . $target . "'>\n";
	echo "<p>";
	echo "<input type='hidden' name='userrole' value='translationadmin'/>\n";
	echo "<input type='submit' name='selectuserrole' value='" . _("Select") . "'/>\n";
	echo "<input type='submit' name='cancel' value='" . _("cancel") . "'/>\n";
	echo "</p>";
	echo "</form>\n";
} elseif ($_GET['userrole'] == 'useradmin') {
	echo "<h3>" . _("User administrator") . "</h3>\n";
	echo "<form method='post' action='?" . $target . "'>\n";
	echo "<p>";
	echo "<input type='hidden' name='userrole' value='useradmin'/>\n";
	echo "<input type='submit' name='selectuserrole' value='" . _("Select") . "'/>\n";
	echo "<input type='submit' name='cancel' value='" . _("cancel") . "'/>\n";
	echo "</p>";
	echo "</form>\n";
} elseif ($_GET['userrole'] == 'teamadmin') {
	echo "<h3>" . _("Team contact person") . "</h3>";
	echo SearchTeam($target, array('userrole' => 'teamadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'accradmin') {
	echo "<h3>" . _("Accreditation official") . "</h3>";
	echo SearchTeam($target, array('userrole' => 'accradmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'seasonadmin') {
	echo "<h3>" . _("Event responsible") . "</h3>";
	echo SearchSeason($target, array('userrole' => 'seasonadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'seriesadmin') {
	echo "<h3>" . _("Division organizer") . "</h3>\n";
	echo SearchSeries($target, array('userrole' => 'seriesadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'resadmin') {
	echo "<h3>" . _("Scheduling right") . "</h3>\n";
	echo SearchReservation($target, array('userrole' => 'resadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'resgameadmin') {
	echo "<h3>" . _("Reservation game input responsible") . "</h3>\n";
	echo SearchReservation($target, array('userrole' => 'resgameadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'gameadmin') {
	echo "<h3>" . _("Reservation game input responsible") . "</h3>\n";
	echo SearchGame($target, array('userrole' => 'gameadmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
} elseif ($_GET['userrole'] == 'playeradmin') {
	echo "<h3>" . _("Player profile administrator") . "</h3>\n";
	echo SearchPlayer($target, array('userrole' => 'playeradmin'), array('selectuserrole' => _("Select"), 'cancel' => _("Cancel")));
}


contentEnd();
pageEnd();
