<?php
if (IsRegistered($_SESSION['uid'])) {
  header("location:?view=frontpage");
}

$title = _("Login failed");
$userId = urldecode($_GET['user']);
$html = "";

if (isset($_POST['resetpassword'])) {
  $ret = UserResetPassword(urldecode($userId));
  if ($ret) {
    $html .= "<p>" . _("New password sent.") . "</p>";
  } else {
    $html .= "<p>" . sprintf(_("Resetting password for '%s' failed. Email address may be invalid. Password was not sent."), $userId) . "</p>";
  }
}

if (empty($html)) {
  $validuser = IsRegistered($userId);
  if ($validuser) {
    $html .= "<form method='post' action='?view=login_failed&amp;user=" . urlencode($userId) . "'>\n";
    $html .= "<p>" . _("Check the username and password.") . " \n";
    $html .= _("If you have forgotten your password, click the button below and a new password will be sent to your e-mail address given at registration.") . "</p>";
    $html .= "<p><input class='button' type='submit' name='resetpassword' value='" . _("Reset password") . "'/></p>\n";
    $html .= "</form>\n";
  } else {
    $html .= "<p>" . sprintf(_("Invalid username %s."), $userId) . "</p>\n";
  }
}
showPage($title, $html);
