<?php
include_once 'menufunctions.php';
$html = "";
global $serverConf;

//common page
$title = _("Visitor statistics");
pageTopHeadOpen($title);
pageTopHeadClose($title, false);
leftMenu(0);
contentStart();
if (isSuperAdmin()) {
	if (isset($_POST['reset_visitors'])) {
		LogResetVisitorCounter();
	}
	if (isset($_POST['reset_pageloads'])) {
		LogResetPageLoadCounter();
	}

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

	$html .= "<h3>" . _("Maintenance") . "</h3>";
	$visitorReset = !empty($serverConf['VisitorCounterResetAt']) ? $serverConf['VisitorCounterResetAt'] : _("Never");
	$pageLoadReset = !empty($serverConf['PageLoadCounterResetAt']) ? $serverConf['PageLoadCounterResetAt'] : _("Never");
	$html .= "<p>" . sprintf(_("Visitor counter last reset: %s"), utf8entities($visitorReset)) . "</p>";
	$html .= "<p>" . sprintf(_("Page load counter last reset: %s"), utf8entities($pageLoadReset)) . "</p>";
	$html .= "<form method='post' action='?view=admin/visitors' onsubmit=\"return confirm('" . _("Are you sure you want to reset the visitor counter?") . "');\">";
	$html .= "<p><input class='button' type='submit' name='reset_visitors' value='" . _("Reset visitor counter") . "' /></p>";
	$html .= "</form>";
	$html .= "<form method='post' action='?view=admin/visitors' onsubmit=\"return confirm('" . _("Are you sure you want to reset the page load counter?") . "');\">";
	$html .= "<p><input class='button' type='submit' name='reset_pageloads' value='" . _("Reset page load counter") . "' /></p>";
	$html .= "</form>";
} else {
	$html .= "<p>" . _("User credentials does not match") . "</p>\n";
}
echo $html;

contentEnd();
pageEnd();
