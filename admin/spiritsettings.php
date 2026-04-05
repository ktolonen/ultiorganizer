<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';

$seasonId = GetString("season");
$title = _("Spirit Settings");
$html = "";

if (empty($seasonId) || !isSeasonAdmin($seasonId)) {
	$html .= "<p>" . _("Insufficient user rights") . "</p>";
	showPage($title, $html);
	return;
}

$seasonInfo = SeasonInfo($seasonId);
if (!$seasonInfo) {
	$html .= "<p>" . _("Event not found") . "</p>";
	showPage($title, $html);
	return;
}

$seasonComment = CommentRaw(1, $seasonId);
$spiritSettings = array(
	'spiritmode' => isset($seasonInfo['spiritmode']) ? (int)$seasonInfo['spiritmode'] : 0,
	'showspiritpoints' => !empty($seasonInfo['showspiritpoints']) ? 1 : 0,
	'showspiritcomments' => !empty($seasonInfo['showspiritcomments']) ? 1 : 0,
	'showspiritpointsonlyoncomplete' => isset($seasonInfo['showspiritpointsonlyoncomplete']) ? (int)!empty($seasonInfo['showspiritpointsonlyoncomplete']) : 1,
	'lockteamspiritonsubmit' => isset($seasonInfo['lockteamspiritonsubmit']) ? (int)!empty($seasonInfo['lockteamspiritonsubmit']) : 1,
);

if (!empty($_POST['save'])) {
	$seasonInfo['spiritmode'] = isset($_POST['spiritmode']) ? (int)$_POST['spiritmode'] : 0;
	$seasonInfo['showspiritpoints'] = !empty($_POST['showspiritpoints']) ? 1 : 0;
	$seasonInfo['showspiritcomments'] = !empty($_POST['showspiritcomments']) ? 1 : 0;
	$seasonInfo['showspiritpointsonlyoncomplete'] = !empty($_POST['showspiritpointsonlyoncomplete']) ? 1 : 0;
	$seasonInfo['lockteamspiritonsubmit'] = !empty($_POST['lockteamspiritonsubmit']) ? 1 : 0;

	if (SetSeason($seasonId, $seasonInfo, $seasonComment)) {
		$html .= "<p class='notice'>" . _("Spirit settings saved.") . "</p>";
		$spiritSettings = array(
			'spiritmode' => (int)$seasonInfo['spiritmode'],
			'showspiritpoints' => (int)$seasonInfo['showspiritpoints'],
			'showspiritcomments' => (int)$seasonInfo['showspiritcomments'],
			'showspiritpointsonlyoncomplete' => (int)$seasonInfo['showspiritpointsonlyoncomplete'],
			'lockteamspiritonsubmit' => (int)$seasonInfo['lockteamspiritonsubmit'],
		);
	} else {
		$html .= "<p class='warning'>" . _("Spirit settings were not saved.") . "</p>";
	}
}

$title .= ": " . utf8entities(U_($seasonInfo['name']));
$html .= "<h2>" . utf8entities(U_($seasonInfo['name'])) . ": " . _("Spirit Settings") . "</h2>\n";
$html .= "<p><a href='?view=admin/spirit&amp;season=" . urlencode($seasonId) . "'>&raquo; " . _("Back to Spirit tools") . "</a></p>";
$html .= "<p>" . _("These settings control how spirit scores are collected, shown, and locked for this event.") . "</p>";
$html .= "<form method='post' action='?view=admin/spiritsettings&amp;season=" . urlencode($seasonId) . "'>";
$html .= "<table border='0'>";

$html .= "<tr>";
$html .= "<td class='infocell'>" . _("Spirit mode") . ": </td>";
$spiritModeDisabledSelected = ((int)$spiritSettings['spiritmode'] === 0) ? " selected='selected'" : "";
$html .= "<td><select class='dropdown' name='spiritmode'>";
$html .= "<option value='0'" . $spiritModeDisabledSelected . ">" . utf8entities(SpiritModeDisabledName()) . "</option>";
foreach (SpiritCategoryModeRows() as $mode) {
	$selected = ($spiritSettings['spiritmode'] === (int)$mode['mode']) ? " selected='selected'" : "";
	$html .= "<option value='" . (int)$mode['mode'] . "'" . $selected . ">" . utf8entities(_($mode['name'])) . "</option>";
}
$html .= "</select></td></tr>";
$html .= "<tr><td></td><td><span style='color:#666; font-style:italic;'>" . _("Selects the spirit scoring model used by the event. Set this to empty to disable spirit scoring entirely.") . "</span></td></tr>";

$html .= "<tr>";
$html .= "<td class='infocell'>" . _("Spirit points visible") . ": </td>";
$html .= "<td><input class='input' type='checkbox' name='showspiritpoints' value='1'" . ($spiritSettings['showspiritpoints'] ? " checked='checked'" : "") . "/></td></tr>";
$html .= "<tr><td></td><td><span style='color:#666; font-style:italic;'>" . _("Allows non-admin users to see spirit scores for this event. Season admins can still review spirit data regardless of this flag.") . "</span></td></tr>";

$html .= "<tr>";
$html .= "<td class='infocell'>" . _("Spirit comments visible") . ": </td>";
$html .= "<td><input class='input' type='checkbox' name='showspiritcomments' value='1'" . ($spiritSettings['showspiritcomments'] ? " checked='checked'" : "") . "/></td></tr>";
$html .= "<tr><td></td><td><span style='color:#666; font-style:italic;'>" . _("Allows non-admin users to see spirit comments when spirit scores themselves are visible for the game.") . "</span></td></tr>";

$html .= "<tr>";
$html .= "<td class='infocell'>" . _("Hide spirit until complete") . ": </td>";
$html .= "<td><input class='input' type='checkbox' name='showspiritpointsonlyoncomplete' value='1'" . ($spiritSettings['showspiritpointsonlyoncomplete'] ? " checked='checked'" : "") . "/></td></tr>";
$html .= "<tr><td></td><td><span style='color:#666; font-style:italic;'>" . _("When enabled, non-admin spirit views and spirit-based averages only include games where both teams have submitted complete spirit scores.") . "</span></td></tr>";

$html .= "<tr>";
$html .= "<td class='infocell'>" . _("Lock spirit after submit") . ": </td>";
$html .= "<td><input class='input' type='checkbox' name='lockteamspiritonsubmit' value='1'" . ($spiritSettings['lockteamspiritonsubmit'] ? " checked='checked'" : "") . "/></td></tr>";
$html .= "<tr><td></td><td><span style='color:#666; font-style:italic;'>" . _("When enabled, team-side users can submit their own complete spirit score once, but cannot later edit or delete that team's spirit score or spirit comment. Season admins still bypass the lock.") . "</span></td></tr>";

$html .= "</table>";
$html .= "<p><input class='button' type='submit' name='save' value='" . _("Save Spirit Settings") . "'/></p>";
$html .= "</form>";

showPage($title, $html);
?>
