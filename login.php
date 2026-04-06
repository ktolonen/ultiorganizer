<?php
require_once __DIR__ . '/lib/view.guard.php';
requireRoutedView('login');

$html = "";
$title = _("Login");


if (isset($_SESSION['uid'])) {
  $user = $_SESSION['uid'];
} else {
  $user = "anonymous";
}

if ($user == 'anonymous') {
  if (!ReadOnlyServer()) {
    $html .= "<p>" . _("Log in with your existing account.") . "</p>";
    $html .= "<form action='index.php' method='post'>";
    $html .= "<label for='myusername'>" . _("Username") . ":</label>&nbsp;";
    $html .= "<input class='input' type='text' id='myusername' name='myusername' size='10' style='border:1px solid #555555' autofocus />&nbsp;";
    $html .= "<label for='mypassword'>" . _("Password") . ":</label>&nbsp;";
    $html .= "<input class='input' type='password' id='mypassword' name='mypassword' size='10' style='border:1px solid #555555'/>&nbsp;";
    $html .= "<input class='button' type='submit' name='login' value='" . _("Login") . "' style='border:1px solid #000000'/>";
    $html .= "</form>";
  }
} else {
  $userinfo = UserInfo($user);
  $html .= "<p>" . _("User") . ": <a href='?view=user/userinfo'>" . utf8entities($userinfo['name']) . "</a></p>";
  $html .= "<p><a href='?view=logout'><button>" . _("Logout") . "</button></a></p>";
}


showPage($title, $html);
