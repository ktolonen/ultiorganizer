<?php
include_once $include_prefix . 'lib/common.functions.php';
$title = _("Register");
$html = "";
$message = "";

if (!empty($_GET['user'])) {
	if (($_GET['user']) != $_SESSION['uid'] && !hasEditUsersRight()) {
		die('Insufficient rights to change user info');
	} else {
		$userid = $_GET['user'];
	}
} else {
	$userid = $_SESSION['uid'];
}

$mailsent = false;
if (!empty($_POST['add'])) {
	$newEmail = $_POST['Email'];
	$error = 0;
	$message = "";

	if (empty($newEmail)) {
		$message .= "<p>" . _("Email can not be empty") . ".</p>";
		$error = 1;
	}

	if (!validEmail($newEmail)) {
		$message .= "<p>" . _("Invalid email address") . ".</p>";
		$error = 1;
	}

	if (emailUsed($newEmail)) {
		$message .= "<p>" . _("Email address already registered") . ".</p>";
		$error = 1;
	}

	if ($error == 0) {
		if (AddExtraEmailRequest($userid, $newEmail)) {
			$message .= "<p>" . _("Confirmation email has been sent to the email address provided") . ".</p>\n";
			$mailsent = true;
		}
	} else {
		$message .= "<p>" . _("Correct the errors and try again") . ".</p>\n";
	}
}

$confirmed = false;
if (!empty($_GET['token'])) {
	if (ConfirmEmail($_GET['token'])) {
		$message = _("Extra email address was confirmed successfully");
		$confirmed = true;
	} else {
		$message = _("Confirming extra email address failed");
	}
}

$html .= file_get_contents('script/disable_enter.js.inc');

//help
$help = "<p>" . _("Extra emails") . ":</p>
	<ol>
		<li> " . _("Fill in the extra email address below") . ". </li>
		<li> " . _("Confirmation email will be sent to the email address provided") . ". </li>
		<li> " . _("Follow the instructions in the e-mail to confirm the address") . ".</li>
	</ol>";
$html .= onPageHelpAvailable($help);

//content
$html .= $message;

if (!$confirmed && !$mailsent) {
	$html .= "<form method='post' action='?view=user/addextraemail&amp;user=" . $userid;
	$html .= "'>\n";
	$html .= "<table cellpadding='8px'>
		<tr><td class='infocell'>" . _("Email") . ":</td>
			<td><input type='text' class='input' maxlength='512' id='Email' name='Email' size='40' value='";
	if (isset($_POST['Email'])) $html .= $_POST['Email'];
	$html .= "'/></td></tr>";

	$html .= "<tr><td colspan = '2' align='right'><br/>
	      <input class='button' type='submit' name='add' value='" . _("Add") . "' />
	      </td></tr>\n";

	$html .= "</table>\n";
	$html .= "</form>";
}

showPage($title, $html);
