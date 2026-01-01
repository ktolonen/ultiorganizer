<?php
include_once __DIR__ . '/auth.php';

$html = "";
$print = 0;
if (!empty($_GET["print"])) {
	$print = intval($_GET["print"]);
}
//common page
$title = _("Helps");
$LAYOUT_ID = HELP;
pageTop($title, $print);
leftMenu($LAYOUT_ID, true, $print);
contentStart();

$html .= file_get_contents('locale/' . getSessionLocale() . '/LC_MESSAGES/help.html');

$querystring = $_SERVER['QUERY_STRING'];
$querystring = preg_replace('/(&|^)print=[^&]*/i', '', $querystring);
$querystring = ltrim($querystring, '&');
if ($print) {
	$html .= "<hr/><div style='text-align:right'><a href='?" . utf8entities($querystring) . "'>" . _("Return") . "</a></div>";
} else {
	$html .= "<hr/><div style='text-align:right'><a href='?" . utf8entities($querystring) . "&amp;print=1'>" . _("Printable version") . "</a></div>";
}

echo $html;
contentEnd();
pageEnd();
