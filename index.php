<?php

if (is_readable('conf/config.inc.php')) {
    include_once 'conf/config.inc.php';
} else {
    http_response_code(500);
    die("Missing configuration. Run install.php or setup conf/config.inc.php manually.");
}
//  ALLOW_INSTALL is for local development only; never enable it on a production server.
if (is_file('install.php') && (!defined('ALLOW_INSTALL') || !ALLOW_INSTALL)) {
    http_response_code(500);
    die("Security warning: remove install.php from the server.");
}

include_once 'lib/database.php';
OpenConnection();
global $include_prefix;
require_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'menufunctions.php';
include_once $include_prefix . 'view_ids.inc.php';
include_once $include_prefix . 'lib/user.functions.php';
include_once $include_prefix . 'lib/logging.functions.php';

include_once $include_prefix . 'lib/debug.functions.php';


startSecureSession();

// Include Live! by BULA
$liveEnableFile = __DIR__ . '/live/enable-live.php';
if (is_readable($liveEnableFile)) {
    include_once $liveEnableFile;
}

if (!isset($_SESSION['VISIT_COUNTER'])) {
    LogVisitor($_SERVER['REMOTE_ADDR']);
    $_SESSION['VISIT_COUNTER'] = true;
}

$rawView = iget('view');

if (!isset($_SESSION['uid'])) {
    $_SESSION['uid'] = "anonymous";
    SetUserSessionData("anonymous");
}

include_once $include_prefix . 'lib/season.functions.php';

include_once 'localization.php';
setSessionLocale();

if (isset($_POST['myusername'])) {
    $password = $_POST['mypassword'] ?? '';
    UserAuthenticate($_POST['myusername'], $password, "FailRedirect");
}

if (!$rawView) {
    header("location:?view=frontpage");
    exit();
}

global $serverConf;
$user = $_SESSION['uid'];

setSelectedSeason();
EnforcePrivateEventAccessForView($rawView);
EnforceSoftMaintenanceForView($rawView);

$viewPath = resolveViewPath($rawView, __DIR__, 'frontpage', ['index', 'localization', 'install']);
$viewToLog = preg_replace('/\\.php$/i', '', ltrim(str_replace(__DIR__, '', $viewPath), DIRECTORY_SEPARATOR));

// Admin and user pages print the event id back into their links and forms.
// Event ids are created only by superadmins, so refusing unknown ones keeps
// a crafted season parameter out of every page at once. Inaccessible events
// get the same answer, so the refusal does not reveal private event ids.
// "0" is let through because the pages read it as no event.
if (preg_match('#^(admin|user)/#', $viewToLog) && !empty($_GET['season'])
    && (!is_string($_GET['season']) || !SeasonExists($_GET['season']) || !CanAccessSeason($_GET['season']))) {
    http_response_code(404);
    showPage(_("Event not found"), "<h1>" . _("Event not found") . "</h1>");
    exit();
}

LogPageLoad($viewToLog);

// Whitelisted rather than stored as-is: uo_scoresheet_history.source is
// varchar(20), and a root-level view carries no segment at all.
$viewSource = strtok($viewToLog, '/');
if (!in_array($viewSource, ['admin', 'user'], true)) {
    $viewSource = 'user';
}
define('UO_APP_SOURCE', $viewSource);

if (!defined('UO_ROUTED_VIEW')) {
    define('UO_ROUTED_VIEW', true);
}
include $viewPath;

CloseConnection();
