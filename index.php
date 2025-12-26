<?php
$serverName = $_SERVER['SERVER_NAME'] ?? ($_SERVER['HTTP_HOST'] ?? null);
$configPath = null;
if ($serverName && is_readable('conf/' . $serverName . '.config.inc.php')) {
  $configPath = 'conf/' . $serverName . '.config.inc.php';
} elseif (is_readable('conf/config.inc.php')) {
  $configPath = 'conf/config.inc.php';
}
if ($configPath) {
  include_once $configPath;
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
include_once $include_prefix . 'menufunctions.php';
include_once $include_prefix . 'view_ids.inc.php';
include_once $include_prefix . 'lib/user.functions.php';
include_once $include_prefix . 'lib/logging.functions.php';

include_once $include_prefix . 'lib/debug.functions.php';


startSecureSession();
if (!isset($_SESSION['VISIT_COUNTER'])) {
  LogVisitor($_SERVER['REMOTE_ADDR']);
  $_SESSION['VISIT_COUNTER'] = true;
}

$rawView = iget('view');

if (!isset($_SESSION['uid'])) {
  $_SESSION['uid'] = "anonymous";
  SetUserSessionData("anonymous");
}

require_once $include_prefix . 'lib/configuration.functions.php';

include_once 'localization.php';
setSessionLocale();

if (isset($_POST['myusername'])) {
  if (strpos($rawView, "mobile") === false)
    UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "FailRedirect");
  else
    UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "FailRedirectMobile");
}

if (!$rawView) {
  header("location:?view=frontpage");
  exit();
}

global $serverConf;
$user = $_SESSION['uid'];

setSelectedSeason();

$viewPath = resolveViewPath($rawView, __DIR__, 'frontpage', array('index', 'localization', 'install'));
$viewToLog = preg_replace('/\\.php$/i', '', ltrim(str_replace(__DIR__, '', $viewPath), DIRECTORY_SEPARATOR));
LogPageLoad($viewToLog);

include $viewPath;

CloseConnection();
