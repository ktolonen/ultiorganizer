<?php

/**
 * @file
 * Take the user when they return from Twitter. Get access tokens.
 * Verify credentials and redirect to based on response from Twitter.
 */

//Start session and load libs
session_name("UO_SESSID");
session_start();
require_once '../lib/database.php';
//open database connection
OpenConnection();
require_once('../lib/twitteroauth/twitteroauth.php');
require_once('../lib/user.functions.php');
require_once('../lib/configuration.functions.php');


$season = $_SESSION['season'];

/* If the oauth_token is old redirect. */
if (isset($_REQUEST['oauth_token']) && $_SESSION['oauth_token'] !== $_REQUEST['oauth_token']) {
	$_SESSION['oauth_status'] = 'oldtoken';
	$_SESSION['title'] = _("Twitter configuration failed") . ":";
	$_SESSION["var0"] = _("The oauth_token is old");
	$_SESSION['backurl'] = "?view=admin/twitterconf&amp;season=$season";
	header("location:../?view=admin/failed");
}

// Create TwitteroAuth object with app key/secret and token key/secret from default phase 
$connection = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);

//Request access tokens from twitter 
$access_token = $connection->getAccessToken($_REQUEST['oauth_verifier']);

//Save the access tokens.
SetTwitterKey($access_token, $_SESSION['purpose'], $_SESSION['id']);
//$_SESSION['access_token'] = $access_token;

CloseConnection();

//Remove no longer needed request tokens 
unset($_SESSION['oauth_token']);
unset($_SESSION['oauth_token_secret']);
unset($_SESSION['season']);
unset($_SESSION['purpose']);
unset($_SESSION['id']);

// If HTTP response is 200 account is correctly set
if (200 == $connection->http_code) {
	$_SESSION['title'] = _("Twitter configuration done") . "!";
	$_SESSION['backurl'] = "?view=admin/twitterconf&amp;season=$season";
	header("location:../?view=admin/success");
} else {
	//Show error page
	$_SESSION['title'] = _("Twitter configuration failed") . ":";
	$_SESSION["var0"] = _("HTTP Error") . " " . $connection->http_code;
	$_SESSION['backurl'] = "?view=admin/twitterconf&amp;season=$season";
	header("location:../?view=admin/failed");
}
