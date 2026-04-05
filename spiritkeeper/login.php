<?php
$pageTitle = _("Spiritkeeper");
$pageHtml = "";
$errors = "";
$nextView = GetString('nextview');
if (empty($nextView) && !empty($_POST['nextview'])) {
	$nextView = trim((string)$_POST['nextview']);
}
$gameId = GetInt('game');
if ($gameId <= 0 && !empty($_POST['game'])) {
	$gameId = (int)$_POST['game'];
}
$teamId = GetInt('team');
if ($teamId <= 0 && !empty($_POST['team'])) {
	$teamId = (int)$_POST['team'];
}

if (isset($_POST['login'])) {
	if (!isLoggedIn()) {
		$errors .= "<p class='warning'>" . _("Check the username and password.") . "</p>\n";
	} else {
		$targetView = !empty($nextView) ? $nextView : ($gameId > 0 ? 'editgame' : 'home');
		$location = '?view=' . urlencode($targetView);
		if ($gameId > 0) {
			$location .= '&game=' . $gameId;
		}
		if ($teamId > 0) {
			$location .= '&team=' . $teamId;
		}
		header("location:" . $location);
		return;
	}
} elseif (isLoggedIn()) {
	$targetView = !empty($nextView) ? $nextView : ($gameId > 0 ? 'editgame' : 'home');
	$location = '?view=' . urlencode($targetView);
	if ($gameId > 0) {
		$location .= '&game=' . $gameId;
	}
	if ($teamId > 0) {
		$location .= '&team=' . $teamId;
	}
	header("location:" . $location);
	return;
}

$pageHtml .= "<div class='card'>";
$pageHtml .= $errors;
$pageHtml .= "<form action='?view=login' method='post' data-ajax='false'>\n";
if (!empty($nextView)) {
	$pageHtml .= "<input type='hidden' name='nextview' value='" . utf8entities($nextView) . "'/>";
}
if ($gameId > 0) {
	$pageHtml .= "<input type='hidden' name='game' value='" . $gameId . "'/>";
}
if ($teamId > 0) {
	$pageHtml .= "<input type='hidden' name='team' value='" . $teamId . "'/>";
}
$pageHtml .= "<label for='myusername'>" . _("Username") . ":</label>";
$pageHtml .= "<input type='text' id='myusername' name='myusername' size='15'/>";
$pageHtml .= "<label for='mypassword'>" . _("Password") . ":</label>";
$pageHtml .= "<input type='password' id='mypassword' name='mypassword' size='15'/>";
$pageHtml .= "<div class='mobile-actions'>";
$pageHtml .= "<input type='submit' name='login' value='" . _("Login") . "'/>";
$pageHtml .= "</div>";
$pageHtml .= "</form>";
$pageHtml .= "</div>";
?>
