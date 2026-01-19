<?php
$html = "";
$errors = "";
if (isset($_POST['login'])) {
	if (!isset($_SESSION['uid']) || $_SESSION['uid'] == "anonymous") {
		$errors .= "<p class='warning'>" . _("Check the username and password.") . "</p>\n";
	} else {
		header("location:?view=respgames");
	}
} elseif (isset($_SESSION['uid']) && $_SESSION['uid'] != "anonymous") {
	header("location:?view=respgames");
}
$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Log in") . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";
$html .= "<div data-role='content'>\n";
$html .= $errors;
$html .= "<form action='?view=login' method='post' data-ajax='false'>\n";
$html .= "<label for='myusername'>" . _("Username") . ":</label>";
$html .= "<input type='text' id='myusername' name='myusername' size='15'/> ";
$html .= "<label for='mypassword'>" . _("Password") . ":</label>";
$html .= "<input type='password' id='mypassword' name='mypassword' size='15'/> ";
$html .= "<div class='form-actions'>";
$html .= "<input type='submit' name='login' value='" . _("Login") . "'/>";
$allowAnonResult = defined('ANONYMOUS_RESULT_INPUT') && ANONYMOUS_RESULT_INPUT;
if ($allowAnonResult) {
	$html .= "<a href='?view=result' data-role='button' data-ajax='false'>" . _("Quick add result") . "</a>";
}
$html .= "</div>";
$html .= "</form>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
