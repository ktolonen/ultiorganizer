<?php

//Start session and load library. 

session_name("UO_SESSID");
session_start();
require_once('lib/twitteroauth/twitteroauth.php');
require_once('lib/configuration.functions.php');

if (!isset($_SESSION['TwitterConsumerKey'])) {
	$twitterconf = GetTwitterConf();
	$_SESSION['TwitterConsumerKey'] = $twitterconf['TwitterConsumerKey'];
	$_SESSION['TwitterConsumerSecret'] = $twitterconf['TwitterConsumerSecret'];
	$_SESSION['TwitterOAuthCallback'] = $twitterconf['TwitterOAuthCallback'];
}

//Build TwitterOAuth object with client credentials. 
$connection = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret']);

// Get temporary credentials.
$request_token = $connection->getRequestToken($_SESSION['TwitterOAuthCallback']);

//Save temporary credentials to session. 
$_SESSION['oauth_token'] = $token = $request_token['oauth_token'];
$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];

// If last connection failed don't display authorization link. 
switch ($connection->http_code) {
	case 200:
		// Build authorize URL and redirect user to Twitter.
		$url = $connection->getAuthorizeURL($token);
		header('Location: ' . $url);
		break;
	default:
		// Show notification if something went wrong. 
		$season = $_SESSION['season'];
		$_SESSION['title'] = _("Twitter configuration failed") . ":";
		$_SESSION["var0"] = _("Could not connect to Twitter. Refresh the page or try again later.");
		$_SESSION["var1"] = _("HTTP Error") . " " . $connection->http_code;
		$_SESSION['backurl'] = "?view=admin/twitterconf&amp;season=$season";
		header("location:?view=admin/failed");
}
