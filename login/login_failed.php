<?php
require_once __DIR__ . '/../lib/view.guard.php';
requireRoutedView('login/login_failed', '../index.php');

if (IsRegistered($_SESSION['uid'])) {
  header("location:?view=frontpage");
}

$title = _("Login failed");
$userId = isset($_GET['user']) ? urldecode($_GET['user']) : "";
$safeUserId = utf8entities($userId);
$html = "";

if (IsEmailDisabled() || IsSelfRegistrationDisabled()) {
  $html = "<p>" . _("Invalid username or password. Contact the system administrator if you need to reset your password.") . "</p>";
  showPage($title, $html);
  return;
}

if (isset($_POST['resetpassword'])) {
  $ret = UserResetPassword($userId);
  if ($ret) {
    $html .= "<p>" . _("Password reset link sent.") . "</p>";
  } else {
    $html .= "<p>" . sprintf(_("Could not send a password reset link for '%s'. The email address may be invalid."), $safeUserId) . "</p>";
  }
}

if (empty($html)) {
  $validuser = IsRegistered($userId);
  if ($validuser) {
    $html .= "<form method='post' action='?view=login/login_failed&amp;user=" . urlencode($userId) . "'>\n";
    $html .= "<p>" . _("Check your username and password.") . " \n";
    $html .= _("If you forgot your password, click the button below. A reset link will be sent to the email address you provided during registration.") . "</p>";
    $html .= "<p><input class='button' type='submit' name='resetpassword' value='" . _("Reset password") . "'/></p>\n";
    $html .= "</form>\n";
  } else {
    $html .= "<p>" . sprintf(_("Invalid username %s."), $safeUserId) . "</p>\n";
  }
}
showPage($title, $html);
