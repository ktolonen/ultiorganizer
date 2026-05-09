<?php

require_once __DIR__ . '/lib/view.guard.php';
requireRoutedView('logout');

$title = _("Log out");
$html = "";

ClearUserSessionData();

$html .= "<h1>" . _("You have logged out") . "</h1>";

showPage($title, $html);
