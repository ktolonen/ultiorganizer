<?php
if (is_file('install.php')) {
  //die("Delete install.php file from server!");
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

if (!isset($_SESSION['uid'])) {
  $_SESSION['uid'] = "anonymous";
  SetUserSessionData("anonymous");
}

require_once $include_prefix . 'lib/configuration.functions.php';

include_once 'localization.php';
setSessionLocale();

if (isset($_POST['myusername'])) {
  $view = iget("view");
  if (strpos($view, "mobile") === false)
    UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "FailRedirect");
  else
    UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "FailRedirectMobile");
}

if (!iget('view')) {
  header("location:?view=frontpage");
  exit();
} else {
  LogPageLoad(iget('view'));
}

global $serverConf;
$user = $_SESSION['uid'];

setSelectedSeason();

$viewPath = resolveViewPath(iget("view"), __DIR__, 'frontpage', array('index', 'localization', 'install'));

include $viewPath;

CloseConnection();
