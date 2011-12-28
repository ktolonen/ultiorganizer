<?php
if(is_file('install.php')){
 die("Delete install.php file from server!");
}
 
include_once 'lib/database.php';
OpenConnection();
global $include_prefix;
include_once $include_prefix.'menufunctions.php';
include_once $include_prefix.'view_ids.inc.php';
include_once $include_prefix.'lib/user.functions.php';
include_once $include_prefix.'lib/facebook.functions.php';
include_once $include_prefix.'lib/logging.functions.php';

include_once $include_prefix.'lib/debug.functions.php';


session_start();
if (!isset($_SESSION['VISIT_COUNTER'])) {
  LogVisitor($_SERVER['REMOTE_ADDR']);
  $_SESSION['VISIT_COUNTER']=true;
}

if (!isset($_SESSION['uid'])) {
	$_SESSION['uid'] = "anonymous";
	SetUserSessionData("anonymous");
}

require_once $include_prefix.'lib/configuration.functions.php';

include_once 'localization.php';
setSessionLocale();

if (isset($_POST['myusername'])) {
	UserAuthenticate($_POST['myusername'], $_POST['mypassword'], "FailRedirect");
}

if (!isset($_GET['view'])) {
	header("location:?view=frontpage");
	exit();
}else{
    LogPageLoad($_GET['view']);
}

global $serverConf;
if (IsFacebookEnabled() && !empty($serverConf['FacebookAppId']) && !empty($serverConf['FacebookAppSecret'])) {
	//include_once 'lib/facebook/facebook.php';
	$fb_cookie = FBCookie($serverConf['FacebookAppId'], $serverConf['FacebookAppSecret']);
	if ($_SESSION['uid'] == "anonymous" && $fb_cookie) {
	  $_SESSION['uid'] = MapFBUserId($fb_cookie);
	  SetUserSessionData($_SESSION['uid']);
	}
}

$user = $_SESSION['uid'];

setSelectedSeason();

if (empty($_GET["view"]))
	$view = "frontpage";
else
	$view = $_GET["view"];


include $view.".php";

CloseConnection();
?>
