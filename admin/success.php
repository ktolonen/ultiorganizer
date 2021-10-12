<?php

$html = "";

//common page
$title = "";
$LAYOUT_ID = SUCCESS;
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();

if (isset($_SESSION['title'])) {
	$html .= "<p><b>" . $_SESSION['title'] . "</b></p>";
	unset($_SESSION['title']);
}

$html .= "<p>";

for ($i = 0; isset($_SESSION["var$i"]); $i++) {
	$html .= utf8entities($_SESSION["var$i"]) . "<br/>";
	unset($_SESSION["var$i"]);
}
$html .= "</p>";

if (isset($_SESSION['backurl'])) {
	$html .= "<p><input class='button' type='button' name='back'  value='" . _("Return") . "' onclick=\"window.location.href='" . $_SESSION['backurl'] . "'\"/></p>";
	unset($_SESSION['title']);
}

echo $html;
contentEnd();
pageEnd();
