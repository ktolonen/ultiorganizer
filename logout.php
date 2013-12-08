<?php
$title = _("Logout");
$html = "";

ClearUserSessionData();

$html .= "<h1>"._("You have logged out")."</h1>";

if (IsFacebookEnabled()) {
  $html .= "<script type=\"text/javascript\">
<!--
window.onload = function() {
	FB.getLoginStatus(function(response) {
	  	if (response.session) {
	  		FB.logout(function(loresp) {});
		}
	});
};
//-->
</script>";
}

showPage($title, $html);

?>
