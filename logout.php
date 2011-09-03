<?php
$LAYOUT_ID = LOGOUT;
$title = _("Logout");
ClearUserSessionData();

//common page
pageTopHeadOpen($title);
global $serverConf;
if (IsFacebookEnabled()) {
	echo "<script type=\"text/javascript\">
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
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

//content
echo "<h1>"._("You have logged out")."</h1>";

contentEnd();
pageEnd();
?>
