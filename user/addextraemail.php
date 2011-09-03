<?php
include_once $include_prefix.'lib/common.functions.php';

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
if(!empty($_POST['add'])) {
	$newEmail=$_POST['Email'];
	$error = 0;
	$message = "";
	
	if(empty($newEmail)) {
		$message .= "<p>"._("Email can not be empty").".</p>";
		$error = 1;
	}
	
	if (!validEmail($newEmail)) {
		$message .= "<p>"._("Invalid email address").".</p>";
		$error = 1;
	}
	
	if (emailUsed($newEmail)) {
		$message .= "<p>"._("Email address already registered").".</p>";
		$error = 1;
	}
	
	if ($error == 0) {
		if (AddExtraEmailRequest($userid, $newEmail)) {
			$message .= "<p>"._("Confirmation email has been sent to the email address provided").".</p>\n";
			$mailsent = true;
		}
	} else {
		$message .= "<p>"._("Correct the errors and try again").".</p>\n";
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

$LAYOUT_ID = REGISTER;
$title = _("Register");
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//help
$help = "<p>"._("Extra emails").":</p>
	<ol>
		<li> "._("Fill in the extra email address below").". </li>
		<li> "._("Confirmation email will be sent to the email address provided").". </li>
		<li> "._("Follow the instructions in the e-mail to confirm the address").".</li>
	</ol>";
onPageHelpAvailable($help);

//content
echo $message;

if (!$confirmed && !$mailsent) {
	echo "<form method='post' action='?view=user/addextraemail&amp;user=".$userid;
	echo "'>\n";	
	echo "<table cellpadding='8px'>
		<tr><td class='infocell'>"._("Email").":</td>
			<td><input type='text' class='input' maxlength='512' id='Email' name='Email' size='40' value='";
	if (isset($_POST['Email'])) echo $_POST['Email'];
	echo "'/></td></tr>";
	
	echo "<tr><td colspan = '2' align='right'><br/>
	      <input class='button' type='submit' name='add' value='"._("Add")."' />
	      </td></tr>\n";	
			
	echo "</table>\n";
	echo "</form>";
}

//common end
contentEnd();
pageEnd();
?>
