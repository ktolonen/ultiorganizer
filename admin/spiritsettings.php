<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';

$seasonId = GetString("season");
$title = _("Spirit Settings");
$html = "";

if (empty($seasonId) || !hasSpiritToolsRight($seasonId)) {
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
$spiritSettings = [
    'spiritmode' => isset($seasonInfo['spiritmode']) ? (int) $seasonInfo['spiritmode'] : 0,
    'showspiritpoints' => !empty($seasonInfo['showspiritpoints']) ? 1 : 0,
    'showspiritcomments' => !empty($seasonInfo['showspiritcomments']) ? 1 : 0,
    'showspiritcommentstoteams' => !empty($seasonInfo['showspiritcommentstoteams']) ? 1 : 0,
    'showspiritpointsonlyoncomplete' => isset($seasonInfo['showspiritpointsonlyoncomplete']) ? (int) !empty($seasonInfo['showspiritpointsonlyoncomplete']) : 1,
    'lockteamspiritonsubmit' => isset($seasonInfo['lockteamspiritonsubmit']) ? (int) !empty($seasonInfo['lockteamspiritonsubmit']) : 1,
];

if (!empty($_POST['save'])) {
    $seasonInfo['spiritmode'] = isset($_POST['spiritmode']) ? (int) $_POST['spiritmode'] : 0;
    $seasonInfo['showspiritpoints'] = !empty($_POST['showspiritpoints']) ? 1 : 0;
    $seasonInfo['showspiritcomments'] = !empty($_POST['showspiritcomments']) ? 1 : 0;
    $seasonInfo['showspiritcommentstoteams'] = !empty($_POST['showspiritcommentstoteams']) ? 1 : 0;
    $seasonInfo['showspiritpointsonlyoncomplete'] = !empty($_POST['showspiritpointsonlyoncomplete']) ? 1 : 0;
    $seasonInfo['lockteamspiritonsubmit'] = !empty($_POST['lockteamspiritonsubmit']) ? 1 : 0;

    if (SetSeasonSpiritSettings($seasonId, $seasonInfo)) {
        $html .= "<p class='notice'>" . _("Spirit settings saved.") . "</p>";
        $spiritSettings = [
            'spiritmode' => (int) $seasonInfo['spiritmode'],
            'showspiritpoints' => (int) $seasonInfo['showspiritpoints'],
            'showspiritcomments' => (int) $seasonInfo['showspiritcomments'],
            'showspiritcommentstoteams' => (int) $seasonInfo['showspiritcommentstoteams'],
            'showspiritpointsonlyoncomplete' => (int) $seasonInfo['showspiritpointsonlyoncomplete'],
            'lockteamspiritonsubmit' => (int) $seasonInfo['lockteamspiritonsubmit'],
        ];
    } else {
        $html .= "<p class='warning'>" . _("Spirit settings were not saved.") . "</p>";
    }
}

$title .= ": " . utf8entities(U_($seasonInfo['name']));
$html .= "<h2>" . utf8entities(U_($seasonInfo['name'])) . ": " . _("Spirit Settings") . "</h2>\n";
$html .= "<p><a href='?view=admin/spirit&amp;season=" . urlencode($seasonId) . "'>&raquo; " . _("Back to Spirit tools") . "</a></p>";
$html .= "<p>" . _("These settings control how spirit scores are collected, shown, and locked for this event.") . "</p>";
$html .= "<form method='post' action='?view=admin/spiritsettings&amp;season=" . urlencode($seasonId) . "'>";
$spiritSelect = "<select class='dropdown' id='spiritmode' name='spiritmode'>";
$spiritSelect .= "<option value='0'" . ($spiritSettings['spiritmode'] === 0 ? " selected='selected'" : "") . ">" . utf8entities(SpiritModeDisabledName()) . "</option>";
foreach (SpiritCategoryModeRows() as $mode) {
    $selected = ($spiritSettings['spiritmode'] === (int) $mode['mode']) ? " selected='selected'" : "";
    $spiritSelect .= "<option value='" . (int) $mode['mode'] . "'" . $selected . ">" . utf8entities(_($mode['name'])) . "</option>";
}
$spiritSelect .= "</select>";

$html .= "<div class='settings-form'>\n";
$html .= "<fieldset class='settings-group'><legend>" . _("Spirit") . "</legend>\n";
$html .= SettingsRow(_("Spirit mode"), $spiritSelect, _("Selects the spirit scoring model used by the event. Set this to empty to disable spirit scoring entirely."), "spiritmode");
$html .= SettingsCheckRow('lockteamspiritonsubmit', _("Lock spirit after submit"), (bool) $spiritSettings['lockteamspiritonsubmit'], _("When enabled, team-side users can submit their own complete spirit score once, but cannot later edit or delete that team's spirit score or spirit comment. Event admins still bypass the lock."));
$html .= "</fieldset>\n";

$html .= "<fieldset class='settings-group'><legend>" . _("Visibility") . "</legend>\n";
$html .= SettingsCheckRow('showspiritpoints', _("Spirit scores visible"), (bool) $spiritSettings['showspiritpoints'], _("Allows non-admin users to see spirit scores for this event. Event admins can still review spirit data regardless of this flag."));
$html .= SettingsCheckRow('showspiritcomments', _("Spirit comments visible"), (bool) $spiritSettings['showspiritcomments'], _("Allows non-admin users to see spirit comments when spirit scores themselves are visible for the game."));
$html .= SettingsCheckRow('showspiritcommentstoteams', _("Show spirit comments to teams"), (bool) $spiritSettings['showspiritcommentstoteams'], _("Lets a team see the spirit comment it gave and received in Spiritkeeper once both teams have submitted, without making comments public."));
$html .= SettingsCheckRow('showspiritpointsonlyoncomplete', _("Hide spirit until complete"), (bool) $spiritSettings['showspiritpointsonlyoncomplete'], _("When enabled, non-admin spirit views and spirit-based averages only include games where both teams have submitted complete spirit scores."));
$html .= "</fieldset>\n";
$html .= "</div>\n";

$html .= "<p><input class='button' type='submit' name='save' value='" . _("Save Spirit Settings") . "'/></p>";
$html .= "</form>";

showPage($title, $html);
