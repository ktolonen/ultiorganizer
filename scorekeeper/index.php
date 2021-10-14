<?php
$include_prefix = "../";

//Open database connection
include_once '../lib/database.php';
OpenConnection();

include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/user.functions.php';
include_once $include_prefix . 'lib/facebook.functions.php';
include_once $include_prefix . 'lib/logging.functions.php';
include_once $include_prefix . 'lib/debug.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/series.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/standings.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/player.functions.php';

include_once $include_prefix . 'localization.php';
include_once $include_prefix . 'menufunctions.php';

if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	include_once $include_prefix . 'lib/twitter.functions.php';
}


//Session data
session_name("UO_SESSID");
session_start();



if (!isset($_SESSION['uid']) && iget('view') != "result") {
	$_SESSION['uid'] = "anonymous";
	SetUserSessionData("anonymous");
	header("location:?view=login");
}


if (isset($_POST['myusername'])) {
	UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "");
}

$user = $_SESSION['uid'];

if (!isset($_SESSION['VISIT_COUNTER'])) {
	LogVisitor($_SERVER['REMOTE_ADDR']);
	$_SESSION['VISIT_COUNTER'] = true;
}

setSessionLocale();
setSelectedSeason();
$_SESSION['userproperties']['selseason'] = CurrentSeason();

if (!iget('view')) {
	header("location:?view=login");
	exit();
} else {
	LogPageLoad(iget('view'));
}

if (!iget("view")) {
	$view = "login";
} else {
	$view = iget("view");
}

ob_start();
echo "<!DOCTYPE html>\n";
echo "<html>\n";
echo "<head>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1'>\n";
echo "<title>Scorekeeper</title>\n";
echo "<link rel='stylesheet' href='" . BASEURL . "/script/jquery/jquery.mobile-1.2.0.min.css'/>\n";

if (is_file($include_prefix . 'cust/' . CUSTOMIZATIONS . '/font.css')) {
	echo "<link rel=\"stylesheet\" href=\"" . $include_prefix . "cust/" . CUSTOMIZATIONS . "/font.css\" type=\"text/css\" />\n";
} else {
	echo "<link rel=\"stylesheet\" href=\"" . $include_prefix . "cust/default/font.css\" type=\"text/css\" />\n";
}

echo "<script src='" . BASEURL . "/script/jquery/jquery-1.8.3.min.js'></script>\n";
echo "<script src='" . BASEURL . "/script/jquery/jquery.mobile-1.2.0.min.js'></script>\n";
//echo "<script src='".BASEURL."/script/jquery/jquery-1.8.3.js'></script>\n";
//echo "<script src='".BASEURL."/script/jquery/jquery.mobile-1.2.0.js'></script>\n";
echo "<script src='" . BASEURL . "/script/ultiorganizer.js'></script>\n";
//include "../script/common.js.inc";

echo "</head>\n";
echo "<body>\n";
echo "<div data-role='page'>\n";
include $view . ".php";

echo "<div data-role='footer' class='ui-bar' data-position='fixed'>\n";
echo "<a href='" . BASEURL . "/' data-role='button' rel='external' data-icon='home'>" . _("Ultiorganizer") . "</a>";
if ($_SESSION['uid'] != "anonymous") {
	echo "<a href='?view=logout' data-role='button' data-icon='delete'>" . _("Logout") . "</a>";
}
echo "\n</div><!-- /footer -->\n\n";
echo "</div><!-- /page -->\n";

echo "</body>\n";
echo "</html>\n";
ob_end_flush();
CloseConnection();
