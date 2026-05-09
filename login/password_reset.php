<?php

require_once __DIR__ . '/../lib/view.guard.php';
requireRoutedView('login/password_reset', '../index.php');

$title = _("Reset password");
$html = "";
$message = "";
$resetDone = false;

$token = "";
if (!empty($_POST['token'])) {
    $token = urldecode($_POST['token']);
} elseif (!empty($_GET['token'])) {
    $token = urldecode($_GET['token']);
}

$validTokenUser = false;
if (!empty($token)) {
    $validTokenUser = PasswordResetUIDByToken($token);
}

if (!empty($_POST['save'])) {
    $newPassword = $_POST['Password'] ?? "";
    $newPassword2 = $_POST['Password2'] ?? "";
    $error = 0;

    if (!$validTokenUser) {
        $message .= "<p>" . _("This password reset link is invalid or has expired.") . "</p>";
        $error = 1;
    }

    if (empty($newPassword) || strlen($newPassword) < 5 || strlen($newPassword) > 20) {
        $message .= "<p>" . _("Password is too short (min. 5 letters).") . "</p>";
        $error = 1;
    }

    $pswcheck = DBEscapeString($newPassword);
    if ($pswcheck != $newPassword) {
        $message .= "<p>" . _("Illegal characters in the password") . ".</p>";
        $error = 1;
    }

    if ($newPassword != $newPassword2) {
        $message .= "<p>" . _("Passwords do not match") . ".</p>";
        $error = 1;
    }

    if ($error == 0) {
        if (ConfirmPasswordReset($token, $newPassword)) {
            $message .= "<p>" . _("Password has been reset successfully. You can now log in with your new password.") . "</p>";
            $resetDone = true;
        } else {
            $message .= "<p>" . _("This password reset link is invalid or has expired.") . "</p>";
        }
    } else {
        $message .= "<p>" . _("Please correct the errors and try again.") . "</p>\n";
    }
}

if (empty($message)) {
    if (!$validTokenUser) {
        $message .= "<p>" . _("This password reset link is invalid or has expired.") . "</p>";
    } else {
        $message .= "<p>" . _("Enter your new password below.") . "</p>";
    }
}

$html .= $message;

if ($validTokenUser && !$resetDone) {
    $html .= "<form method='post' action='?view=login/password_reset'>\n";
    $html .= "<input type='hidden' name='token' value='" . urlencode($token) . "'/>\n";
    $html .= "<table cellpadding='8'>\n";
    $html .= "<tr><td class='infocell'>" . _("Password") . ":</td>\n";
    $html .= "<td><input type='password' class='input' maxlength='20' id='Password' name='Password' value=''/></td></tr>\n";
    $html .= "<tr><td class='infocell'>" . _("Repeat password") . ":</td>\n";
    $html .= "<td><input type='password' class='input' maxlength='20' id='Password2' name='Password2' value=''/></td></tr>\n";
    $html .= "<tr><td colspan='2' align='right'><br/>\n";
    $html .= "<input class='button' type='submit' name='save' value='" . _("Reset password") . "'/>\n";
    $html .= "</td></tr>\n";
    $html .= "</table>\n";
    $html .= "</form>";
}

showPage($title, $html);
