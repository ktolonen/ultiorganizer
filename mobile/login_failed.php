<?php
if(IsRegistered($_SESSION['uid'])){
  header("location:?view=mobile/respgames");
}

$title = _("Home");
$userId = urldecode($_GET['user']);
$html = "";

if(isset($_POST['resetpassword'])) {
  $ret=UserResetPassword(urldecode($userId));
  if($ret){
    $html .= "<p>"._("New password sent.")."</p>";
  }else{
    $html .= "<p>"._("Resetting password for '$userId' failed. Email address may be invalid. Password was not sent.")."</p>";
  }
}

if(empty($html)){
  $validuser = IsRegistered($userId);
  if($validuser){
    $html .= "<form method='post' action='?view=mobile/login_failed&amp;user=".urlencode($userId)."'>\n";
    $html .= "<p>"._("Check the username and password.")." \n";
    $html .= _("If you have forgot the password, click button below and new password is sent to e-mail address given on registration.")."</p>";
    $html .= "<p><input class='button' type='submit' name='resetpassword' value='"._("Reset password")."'/></p>\n";
    $html .= "</form>\n";
  }else{
    $html .= "<p>"._("Invalid username $userId.")."</p>\n";
  }
}
showPage($title, $html, true);
?>