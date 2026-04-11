<?php
require_once __DIR__ . '/../lib/view.guard.php';
requireRoutedView('mobile/index', '../index.php');

include_once 'lib/common.functions.php';
$html = "";

if (isset($_POST['login'])) {
	if (!isset($_SESSION['uid']) || $_SESSION['uid'] == "anonymous") {
		$html .= "<p class='warning'>" . _("Check the username and password.") . "</p>\n";
	} else {
		header("location:?view=mobile/respgames");
	}
} elseif (isset($_SESSION['uid']) && $_SESSION['uid'] != "anonymous") {
	header("location:?view=mobile/respgames");
}

mobilePageTop(_("Log in"));

// echo $html;

mobilePageEnd("view=mobile/respgames");
