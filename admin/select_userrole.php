<?php

include_once __DIR__ . '/auth.php';
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
} elseif ($_GET['userrole'] == 'teamadmin') {
    echo "<h3>" . _("Team contact person") . "</h3>";
    echo SearchTeam($target, ['userrole' => 'teamadmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'accradmin') {
    echo "<h3>" . _("Accreditation official") . "</h3>";
    echo SearchTeam($target, ['userrole' => 'accradmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'seasonadmin') {
    echo "<h3>" . _("Event responsible") . "</h3>";
    echo SearchSeason($target, ['userrole' => 'seasonadmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'spiritadmin') {
    echo "<h3>" . _("Spirit admin") . "</h3>";
    echo SearchSeason($target, ['userrole' => 'spiritadmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'seriesadmin') {
    echo "<h3>" . _("Division organizer") . "</h3>\n";
    echo SearchSeries($target, ['userrole' => 'seriesadmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'resadmin') {
    echo "<h3>" . _("Scheduling right") . "</h3>\n";
    echo SearchReservation($target, ['userrole' => 'resadmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'resgameadmin') {
    echo "<h3>" . _("Reservation game input responsible") . "</h3>\n";
    echo SearchReservation($target, ['userrole' => 'resgameadmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'gameadmin') {
    echo "<h3>" . _("Reservation game input responsible") . "</h3>\n";
    echo SearchGame($target, ['userrole' => 'gameadmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
} elseif ($_GET['userrole'] == 'playeradmin') {
    echo "<h3>" . _("Player profile administrator") . "</h3>\n";
    echo SearchPlayer($target, ['userrole' => 'playeradmin'], ['selectuserrole' => _("Select"), 'cancel' => _("Cancel")]);
}


contentEnd();
pageEnd();
