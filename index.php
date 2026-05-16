<?php

if (is_readable('conf/config.inc.php')) {
    include_once 'conf/config.inc.php';
} else {
    http_response_code(500);
    die("Missing configuration. Run install.php or setup conf/config.inc.php manually.");
}

if (is_file('install.php') && (!defined('ALLOW_INSTALL') || !ALLOW_INSTALL)) {
    http_response_code(500);
    die("install.php must be removed or ALLOW_INSTALL=true for local setup.");
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
    if (strpos($rawView, "mobile") === false) {
        UserAuthenticate($_POST['myusername'], $password, "FailRedirect");
    } else {
        UserAuthenticate($_POST['myusername'], $password, "FailRedirectMobile");
    }
}

if (!$rawView) {
    header("location:?view=frontpage");
    exit();
}

global $serverConf;
$user = $_SESSION['uid'];

setSelectedSeason();
EnforceSoftMaintenanceForView($rawView);

$viewPath = resolveViewPath($rawView, __DIR__, 'frontpage', ['index', 'localization', 'install']);
$viewToLog = preg_replace('/\\.php$/i', '', ltrim(str_replace(__DIR__, '', $viewPath), DIRECTORY_SEPARATOR));
LogPageLoad($viewToLog);

if (!defined('UO_ROUTED_VIEW')) {
    define('UO_ROUTED_VIEW', true);
}
include $viewPath;

CloseConnection();
