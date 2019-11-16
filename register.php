<?php
include_once $include_prefix.'lib/common.functions.php';

$html = "";
$message = "";
$title = _("Register");
$html .= file_get_contents('script/disable_enter.js.inc');

$mailsent = false;
if(!empty($_POST['save'])) {
  $newUsername=$_POST['UserName'];
  $newPassword=$_POST['Password'];
  $newName=$_POST['Name'];
  $newEmail=$_POST['Email'];
  $error = 0;
  $message = "";
  if(empty($newUsername)|| strlen($newUsername) < 3 || strlen($newUsername) > 20)
  {
    $message .= "<p>"._("Username is too short (min. 3 letters)").".</p>";
    $error = 1;
  }
  if (IsRegistered($newUsername))
  {
    $message .=  "<p>"._("The username is already in use").".</p>";
    $error = 1;
  }
  if(empty($newPassword) || strlen($newPassword) <5 || strlen($newPassword) > 20)
  {
    $message .=  "<p>"._("Password is too short (min. 5 letters).").".</p>";
    $error = 1;
  }
  if(empty($newName))
  {
    $message .= "<p>"._("Name can not be empty").".</p>";
    $error = 1;
  }

  if(empty($newEmail)) {
    $message .= "<p>"._("Email can not be empty").".</p>";
    $error = 1;
  }

  if (!validEmail($newEmail)) {
    $message .= "<p>"._("Invalid email address").".</p>";
    $error = 1;
  }

  $uidcheck = DBEscapeString($newUsername);

  if($uidcheck != $newUsername || preg_match('/[ ]/', $newUsername) || preg_match('/[^a-z0-9._]/i', $newUsername))
  {
    $message .= "<p>"._("User id may not have spaces or special characters").".</p>";
    $error = 1;
  }

  $pswcheck = DBEscapeString($newPassword);

  if($pswcheck != $newPassword)
  {
    $message .= "<p>"._("Illegal characters in the password").".</p>";
    $error = 1;
  }
  if($pswcheck != $_POST['Password2']) {
    $message .= "<p>"._("Passwords do not match").".</p>";
    $error = 1;
  }

  if ($error == 0) {
    if (AddRegisterRequest($newUsername, $newPassword, $newName, $newEmail)) {
      $message .= "<p>"._("Confirmation e-mail has been sent to the email address provided. You have to follow the link in the mail to finalize registration, before you can use the account.")."</p>\n";
      $mailsent = true;
    }
  } else {
    $message .= "<p>"._("Correct the errors and try again").".</p>\n";
  }
}
$confirmed = false;
if (!empty($_GET['token'])) {
  $userid = RegisterUIDByToken($_GET['token']);
  if (ConfirmRegister($_GET['token'])) {
    SetUserSessionData($userid);
    AddEditSeason($userid, CurrentSeason());
    $message = _("Registration was confirmed successfully");
    $confirmed = true;
  } else {
    $message = _("Confirming registration failed");
  }
}

//help
$help = "<p>"._("Registration is only needed for event organizers, team contact persons and players needing to create or change data in system.")." ";
$help .= _("Registration process:")."</p>
	<ol>
		<li> "._("Fill registration information in fields below.")."</li>
		<li> "._("Confirmation mail will be sent immediately to the email address provided. (Note that confirmation mail can be incorrectly filterd as spam by e-mail client and in this case you can find the mail from spam -folder instead of inbox.)")."</li>
		<li> "._("Follow the link in the mail to confirm registration.")."</li>
	</ol>";

$help .= "<a href='?view=privacy'>"._("Privacy Policy")."</a>";
$help .= "<hr/>";

//content

if(empty($message)){
  $html .= $help;
}else{
  $html .= $message;
}

if (!$confirmed && !$mailsent) {
  $html.= "<form method='post' action='?view=register";
  $html.= "'>\n";
  $html.= "<table cellpadding='8'>
		<tr><td class='infocell'>"._("Name").":</td>
			<td><input type='text' class='input' maxlength='256' id='Name' name='Name' value='";
  if (isset($_POST['Name'])) $html.= $_POST['Name'];
  $html.= "'/></td></tr>
		<tr><td class='infocell'>"._("Username").":</td>
			<td><input type='text' class='input' maxlength='20' id='UserName' name='UserName' value='";
  if (isset($_POST['UserName'])) $html.= $_POST['UserName'];
  $html.= "'/></td></tr>
		<tr><td class='infocell'>"._("Password").":</td>
			<td><input type='password' class='input' maxlength='20' id='Password' name='Password' value='";
  if (isset($_POST['Password'])) $html.= $_POST['Password'];
  $html.= "'/></td></tr>
		<tr><td class='infocell'>"._("Repeat password").":</td>
			<td><input type='password' class='input' maxlength='20' id='Password2' name='Password2' value='";
  if (isset($_POST['Password'])) $html.= $_POST['Password'];
  $html.= "'/></td></tr>
		<tr><td class='infocell'>"._("Email").":</td>
			<td><input type='text' class='input' maxlength='512' id='Email' name='Email' size='40' value='";
  if (isset($_POST['Email'])) $html.= $_POST['Email'];
  $html.= "'/></td></tr>";

  $html.= "<tr><td colspan = '2' align='right'><br/>
	      <input class='button' type='submit' name='save' value='"._("Register")."' />
	      </td></tr>\n";

  	
  $html.= "</table>\n";
  $html.= "</form>";
}


showPage($title, $html);

?>
