<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';

if (!empty($_GET["season"])) {
  if (!isSeasonAdmin($_GET["season"]) && !isSuperAdmin()) {
    die("Insufficient user rights");
  }
} elseif (!isSuperAdmin()) {
  die("Insufficient user rights");
}

$html = "";
$emailRequired = !IsEmailDisabled();
if (!empty($_POST['save'])) {
  $newUsername = $_POST['UserName'];
  $newPassword = $_POST['Password'];
  $newName = $_POST['Name'];
  $newEmail = trim($_POST['Email']);
  $error = 0;
  $message = "";
  if (empty($newUsername) || strlen($newUsername) < 3 || strlen($newUsername) > 50) {
    $html .= "<p>" . _("Username is too short (min. 3 letters)") . ".</p>";
    $error = 1;
  }
  if (IsRegistered($newUsername)) {
    $html .=  "<p>" . _("The username is already in use") . ".</p>";
    $error = 1;
  }
  if (empty($newPassword) || strlen($newPassword) < 5 || strlen($newPassword) > 20) {
    $html .=  "<p>" . _("Password is too short (min. 5 letters).") . ".</p>";
    $error = 1;
  }
  if (empty($newName)) {
    $html .= "<p>" . _("Name cannot be empty") . ".</p>";
    $error = 1;
  }

  if ($emailRequired && empty($newEmail)) {
    $html .= "<p>" . _("Email cannot be empty") . ".</p>";
    $error = 1;
  }

  if (!empty($newEmail) && !validEmail($newEmail)) {
    $html .= "<p>" . _("Invalid email address") . ".</p>";
    $error = 1;
  }

  $uidcheck = DBEscapeString($newUsername);

  if ($uidcheck != $newUsername || preg_match('/[ ]/', $newUsername) /*|| preg_match('/[^a-z0-9._]/i', $newUsername)*/) {
    $html .= "<p>" . _("User ID may not have spaces or special characters") . ".</p>";
    $error = 1;
  }

  $pswcheck = DBEscapeString($newPassword);

  if ($pswcheck != $newPassword) {
    $html .= "<p>" . _("Illegal characters in the password") . ".</p>";
    $error = 1;
  }

  if ($error == 0) {
    $created = false;
    if (IsEmailDisabled()) {
      $created = CreateUserAccount($newUsername, $newPassword, $newName, $newEmail, "added by administrator");
    } elseif (AddRegisterRequest($newUsername, $newPassword, $newName, $newEmail)) {
      $created = ConfirmRegisterUID($newUsername);
    }

    if ($created) {
      foreach (CurrentSeasons() as $seasonInfo) {
        $seasonId = $seasonInfo['season_id'];
        if (hasEditUsersRight() || isSeasonAdmin($seasonId)) {
          AddEditSeason($newUsername, $seasonId);
        }
      }

      if (hasEditUsersRight()) {
        header('location:?view=user/userinfo&user=' . urlencode($newUsername) . '&created=1');
        exit;
      } else {
        $redirect = '?view=admin/adduser&createduser=' . urlencode($newUsername);
        if (!empty($_GET['season'])) {
          $redirect .= '&season=' . urlencode($_GET['season']);
        }
        header('location:' . $redirect);
        exit;
      }
    } else {
      $html .= "<p>" . _("Adding the user failed. Please contact the system administrator.") . "</p>\n";
    }
  } else {
    $html .= "<p>" . _("Correct the errors and try again") . ".</p>\n";
  }
}

$LAYOUT_ID = REGISTER;
$title = _("Add new user");
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

if (!empty($_GET['createduser'])) {
  $html .= "<p>" . _("Added new user") . "<br/>\n";
  $html .= _("Username") . ": " . utf8entities($_GET['createduser']) . "<br/>\n";
  if (IsEmailDisabled()) {
    $html .= _("Deliver the password to the user manually.") . "<br/>\n";
  }
  $html .= "</p>\n";
}

$html .= "<form method='post' action='?view=admin/adduser";
$html .= !empty($_GET['season']) ? "&amp;season=" . urlencode($_GET['season']) : "";
$html .= "'>\n";
$html .= "<table cellpadding='8'>
		<tr><td class='infocell'>" . _("Name") . ":</td>
			<td><input type='text' class='input' maxlength='256' id='Name' name='Name' value='";
if (isset($_POST['Name'])) $html .= $_POST['Name'];
$html .= "'/></td></tr>
		<tr><td class='infocell'>" . _("Username") . ":</td>
			<td><input type='text' class='input' maxlength='50' id='UserName' name='UserName' value='";
if (isset($_POST['UserName'])) $html .= $_POST['UserName'];
$html .= "'/></td></tr>
		<tr><td class='infocell'>" . _("Password") . ":</td>
			<td><input type='text' class='input' maxlength='20' id='Password' name='Password' value='";
if (isset($_POST['Password'])) $html .= $_POST['Password'];
else $html .= UserCreateRandomPassword();
$html .= "'/></td></tr>
		<tr><td class='infocell'>" . _("Email") . ":</td>
			<td><input type='text' class='input' maxlength='512' id='Email' name='Email' size='40' value='";
if (isset($_POST['Email'])) $html .= $_POST['Email'];
$html .= "'/>";
if (!$emailRequired) {
  $html .= "&nbsp;<span class='note'>" . _("Optional when email is disabled.") . "</span>";
}
$html .= "</td></tr>";

$html .= "<tr><td colspan = '2' align='right'><br/>
	      <input class='button' type='submit' name='save' value='" . _("Add") . "' />
	      </td></tr>\n";

$html .= "</table>\n";
$html .= "</form>";

echo $html;

//common end
contentEnd();
pageEnd();
