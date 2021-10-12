<?php
$LAYOUT_ID = FBPUBLISH;

//common page
$userid = $_SESSION['uid'];
$playerid = $_GET['player'];
$latestId = PlayerLatestId($playerid);
$playerInfo = PlayerInfo($latestId);
$title = _("Manage player Facebook feeds for") . " " . utf8entities($playerInfo['firstname'] . " " . $playerInfo['lastname']);

if (IsFacebookEnabled()) {
	$fb_cookie = FBCookie($serverConf['FacebookAppId'], $serverConf['FacebookAppSecret']);
	$fb_props = getFacebookUserProperties($userid);
	if (!FBLoggedIn($fb_cookie, $fb_props)) {
		die("Must be logged in to manage publishing");
	}
}

if (isset($_POST['publish'])) {
	$linktitle = _("Player card") . ": ";
	if ($playerInfo['num']) {
		$linktitle .= "#" . $playerInfo['num'] . " ";
	}
	$linktitle .= utf8entities($playerInfo['firstname'] . " " . $playerInfo['lastname']);

	$params = array(
		"link" => GetURLBase() . "?view=playercard&Player=" . $latestId,
		"message" => $_POST['publishmessage'],
		"name" => $linktitle
	);
	FacebookFeedPost($fb_props, $params);
}
if (isset($_POST['save'])) {
	$pubEvents = array();
	$pubMessages = array();
	foreach ($events as $event => $message) {
		if (isset($_POST['publish' . $event])) {
			$pubEvents[] = $event;
		}
		$pubMessages[$event] = $_POST['message' . $event];
	}
	SetFacebookPublishing($userid, $playerid, $pubEvents, $pubMessages);
	$fb_props = getFacebookUserProperties($userid);
}

pageTopHeadOpen($title);
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();
//help
$help = "<p>" . _("Facebook feed messages") . ":</p>
	<ol>
		<li> " . _("Messages will be published on your feed when the event happens") . " </li>
		<li> " . _("Placeholders will be replaced from with information from the events") . " </li>
		<li> " . _("The available placeholders are the following:") . " 
		<ul>
			<li><b>\$team</b> " . _("Player's team name") . "</li>
			<li><b>\$opponent</b> " . _("Opponent team name") . "</li>
			<li><b>\$teamscore</b> " . _("Player's team score") . "</li>
			<li><b>\$opponentscore</b> " . _("Opponent team score") . "</li>
			<li><b>\$passername</b> " . _("Name of the player passing the goal (goal events only)") . "</li>
			<li><b>\$scorername</b> " . _("Name of the player catching the goal (goal events only)") . "</li>
		</ul></li>
	</ol>";

onPageHelpAvailable($help);

echo "<h2>" . _("Facebook publishing options") . "</h2>\n";
echo "<form method='post' action='?view=user/facebookpublishing&amp;player=$playerid'>\n";
echo "<table>\n";
global $events;
global $eventTranslations;
foreach ($events as $event => $message) {
	echo "<tr><th>" . _("Publish event") . " " . $eventTranslations[$event] . "</th><td>";
	echo "<input class='input' type='checkbox' name='publish$event' ";
	if ($fb_props['facebookplayer'][$playerid][$event]) {
		echo "checked='checked'";
	}

	echo "/> " . "</td></tr>\n";
	echo "<tr><th>" . _("Message") . "</th><td><textarea name='message$event' rows='3' cols='50'>" . $fb_props['facebookplayer'][$playerid][$event . "message"] . "</textarea></td></tr>\n";
}
echo "</table><input type='submit' name='save' value='" . _("Save") . "'/></form>\n";
echo "<h2>" . _("Publish player card") . "</h2>\n";
echo "<form method='post' action='?view=user/facebookpublishing&amp;player=$playerid'>\n";
echo "<table>\n";
echo "<tr><th>" . _("Message") . "</th><td><textarea name='publishmessage' rows='3' cols='50'>" . _("Check out my player profile in ultiorganizer") . "</textarea></td></tr>\n";
echo "</table><input type='submit' name='publish' value='" . _("Publish") . "'/></form>\n";

contentEnd();
pageEnd();
