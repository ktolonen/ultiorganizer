<?php
if(IsRegistered($_SESSION['uid'])){
  header("location:?view=frontpage");  
}

$LAYOUT_ID = HOME;
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
	
//common page

pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();

//content
if(empty($html)){
	$validuser = IsRegistered($userId);
	if($validuser){
		$html .= "<form method='post' action='?view=login_failed&amp;user=".urlencode($userId)."'>\n";
		$html .= "<p>"._("Check the username and password.")." \n";
		$html .= _("If you have forgot the password, click button belowe and new password is sent to e-mail address given on registration.")."</p>";
		$html .= "<p><input class='button' type='submit' name='resetpassword' value='"._("Reset password")."'/></p>\n"; 
		$html .= "</form>\n";
	}else{
		$html .= "<p>"._("Invalid username.")."</p>\n";
	}
}
echo $html;

contentEnd();
pageEnd();
?>