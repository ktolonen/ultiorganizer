<?php
$include_prefix = "../";

//Open database connection
include_once '../lib/database.php';
OpenConnection();

include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/user.functions.php';
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


//Session data
startSecureSession();



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

$rawView = iget('view');
if (!$rawView) {
	header("location:?view=login");
	exit();
}

// Resolve view script with format/deny & path checks.
$viewPath = resolveViewPath($rawView, __DIR__, 'login', array('index'));
LogPageLoad($rawView);

ob_start();
echo "<!DOCTYPE html>\n";
echo "<html>\n";
echo "<head>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1'>\n";
echo "<title>Scorekeeper</title>\n";
echo "<link rel='stylesheet' href='" . BASEURL . "/scorekeeper/scorekeeper.css'/>\n";

if (is_file($include_prefix . 'cust/' . CUSTOMIZATIONS . '/font.css')) {
	echo "<link rel=\"stylesheet\" href=\"" . $include_prefix . "cust/" . CUSTOMIZATIONS . "/font.css\" type=\"text/css\" />\n";
} else {
	echo "<link rel=\"stylesheet\" href=\"" . $include_prefix . "cust/default/font.css\" type=\"text/css\" />\n";
}

echo "<script src='" . BASEURL . "/script/ultiorganizer.js'></script>\n";
//include "../script/common.js.inc";

echo "</head>\n";
echo "<body>\n";
echo "<div data-role='page'>\n";
include $viewPath;

echo "<div data-role='footer' class='ui-bar' data-position='fixed'>\n";
echo "<a class='footer-compact' href='" . BASEURL . "/' data-role='button' rel='external' data-icon='home'>" . _("Ultiorganizer") . "</a>";
if ($_SESSION['uid'] != "anonymous") {
	echo "<a class='footer-compact' href='?view=logout' data-role='button' data-icon='delete'>" . _("Logout") . "</a>";
}
echo "\n</div><!-- /footer -->\n\n";
echo "</div><!-- /page -->\n";

echo "</body>\n";
echo "</html>\n";
ob_end_flush();
CloseConnection();
