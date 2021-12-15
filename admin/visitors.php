<?php
include_once 'menufunctions.php';
$html = "";

//common page
$title = _("Visitor statistics");
pageTopHeadOpen($title);
pageTopHeadClose($title, false);
leftMenu(0);
contentStart();
if (isSuperAdmin()) {
	$visitors = LogGetVisitorCount();
	$pageloads = LogGetPageLoads();
	$loadstotal = 0;

	foreach ($pageloads as $page) {
		$loadstotal += $page['loads'];
	}

	$html .= "<h3>" . _("Summary") . "</h3>";
	$html .= "<table cellpadding='3'>";
	$html .= "<tr>";
	$html .= "<td>" . _("Visitors") . "</td><td>" . $visitors['visitors'] . "</td>";
	$html .= "</tr>";
	$html .= "<tr>";
	$html .= "<td>" . _("Visits") . "</td><td>" . $visitors['visits'] . "</td>";
	$html .= "</tr>";
	$html .= "<tr>";
	$html .= "<td>" . _("Page loads") . "</td><td>" . $loadstotal . "</td>";
	$html .= "</tr>";
	$html .= "</table>";

	$html .= "<h3>" . _("Pageload per page") . "</h3>";
	$html .= "<table cellpadding='3'>";
	foreach ($pageloads as $page) {
		$html .= "<tr>";
		$html .= "<td>" . utf8entities($page['page']) . "</td><td>" . $page['loads'] . "</td>";
		$html .= "</tr>";
	}
	$html .= "</table>";
} else {
	$html .= "<p>" . _("User credentials does not match") . "</p>\n";
}
echo $html;

contentEnd();
pageEnd();
