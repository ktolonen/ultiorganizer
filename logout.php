<?php
$title = _("Logout");
$html = "";

ClearUserSessionData();

$html .= "<h1>" . _("You have logged out") . "</h1>";

showPage($title, $html);
