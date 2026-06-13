<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/timekeeper.functions.php';

if (!isSuperAdmin()) {
    die('Insufficient rights to manage Timekeeper templates.');
}

$LAYOUT_ID = ADDTIMEKEEPERTEMPLATE;

$title = _("Edit");
$html = "";
$templateId = isset($_GET["template"]) ? (int) $_GET["template"] : 0;
$actionDefinitions = TimekeeperActionSignalGroups();
$templateName = "";
$signalValues = [];

function TimekeeperTemplateSignalsFromPost()
{
    $signals = [];
    if (empty($_POST['signal_row']) || !is_array($_POST['signal_row'])) {
        return $signals;
    }

    foreach ($_POST['signal_row'] as $rowId) {
        $suffix = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $rowId);
        $actionKey = isset($_POST['signal_action_' . $suffix]) ? $_POST['signal_action_' . $suffix] : "";
        $signalText = isset($_POST['signal_text_' . $suffix]) ? $_POST['signal_text_' . $suffix] : "";
        $signalTime = isset($_POST['signal_time_' . $suffix]) ? (int) $_POST['signal_time_' . $suffix] : 0;
        if ($actionKey === "" || trim($signalText) === "") {
            continue;
        }
        $signals[] = [
            'action_key' => $actionKey,
            'signal_time' => $signalTime,
            'signal_text' => $signalText,
        ];
    }
    return $signals;
}

function TimekeeperTemplateParamsFromPost()
{
    $params = [];
    $params['name'] = empty($_POST['name']) ? "no name" : $_POST['name'];
    foreach (TimekeeperTemplateCapFields() as $fieldId => $field) {
        $params[$fieldId] = isset($_POST['cap_' . $fieldId]) ? (int) $_POST['cap_' . $fieldId] : (int) $field['default'];
    }
    return $params;
}

if (!empty($_POST['save']) || !empty($_POST['add'])) {
    $params = TimekeeperTemplateParamsFromPost();
    $signals = TimekeeperTemplateSignalsFromPost();
    if (!empty($_POST['add'])) {
        $templateId = AddTimekeeperTemplate($params, $signals);
    } else {
        SetTimekeeperTemplate($templateId, $params, $signals);
    }
}

$capValues = TimekeeperTemplateCapDefaults();
if ($templateId) {
    $info = TimekeeperTemplateInfo($templateId);
    if (!$info) {
        die('Timekeeper template not found.');
    }
    $templateName = $info['name'];
    $capValues = TimekeeperTemplateCapsFromRow($info);
    $signalValues = TimekeeperTemplateSignalRows($templateId);
} else {
    $defaultId = TimekeeperDefaultTemplateId();
    if ($defaultId) {
        $info = TimekeeperTemplateInfo($defaultId);
        if ($info) {
            $signalValues = TimekeeperTemplateSignalRows($defaultId);
        }
    }
}

pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'lib/yui.functions.php';
$html .= yuiLoad(["utilities", "datasource", "autocomplete"]);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

if ($templateId) {
    $html .= "<h2>" . _("Edit Timekeeper template") . "</h2>\n";
    $html .= "<form method='post' action='?view=admin/addtimekeepertemplate&amp;template=$templateId'>";
} else {
    $html .= "<h2>" . _("Add Timekeeper template") . "</h2>\n";
    $html .= "<form method='post' action='?view=admin/addtimekeepertemplate'>";
}

$html .= "<table cellpadding='2'>\n";
$html .= "<tr><td class='infocell'>" . _("Name") . ":</td><td>";
$html .= TranslatedField("name", $templateName, "150", "30");
$html .= "</td><td></td></tr>\n";
foreach (TimekeeperTemplateCapFields() as $capFieldId => $capField) {
    $html .= "<tr><td class='infocell'>" . utf8entities($capField['label']) . ":</td><td>";
    $html .= "<input class='input' size='5' name='cap_" . utf8entities($capFieldId)
        . "' value='" . (int) $capValues[$capFieldId] . "'/> " . utf8entities($capField['unit']);
    $html .= "</td><td></td></tr>\n";
}
$html .= "</table>\n";

$html .= "<h3>" . _("Signals") . "</h3>\n";
$html .= "<p>" . utf8entities(_("Each signal has a time (seconds from the start of the action) and an instruction to call out. The timer counts down from the highest signal time. Clear the instruction to remove a signal; fill a blank row to add one.")) . "</p>\n";
$html .= "<p>" . utf8entities(_("After saving, translate signal instructions under Administration > Translations.")) . "</p>\n";
$rowNumber = 0;
$translationFields = ["name"];
$blankRowsPerAction = 1;
foreach ($actionDefinitions as $actionKey => $actionDefinition) {
    $html .= "<h4>" . utf8entities($actionDefinition['label']) . "</h4>\n";
    $html .= "<table cellpadding='2'>\n";
    $html .= "<tr><th class='left'>" . _("Time") . "</th><th class='left'>" . _("Instruction") . "</th></tr>\n";
    $rowsForAction = [];
    foreach ($signalValues as $signal) {
        if ($signal['action_key'] === $actionKey) {
            $rowsForAction[] = $signal;
        }
    }
    for ($blank = 0; $blank < $blankRowsPerAction; $blank++) {
        $rowsForAction[] = ['signal_id' => 0, 'action_key' => $actionKey, 'signal_time' => '', 'signal_text' => ''];
    }

    foreach ($rowsForAction as $signal) {
        $rowId = 'r' . $rowNumber;
        $rowNumber++;
        $translationField = 'signal_text_' . $rowId;
        $translationFields[] = $translationField;
        $html .= "<tr>";
        $html .= "<td>";
        $html .= "<input type='hidden' name='signal_row[]' value='" . utf8entities($rowId) . "'/>";
        $html .= "<input type='hidden' name='signal_action_" . utf8entities($rowId) . "' value='" . utf8entities($actionKey) . "'/>";
        $html .= "<input class='input' size='5' name='signal_time_" . utf8entities($rowId) . "' value='" . utf8entities((string) $signal['signal_time']) . "'/> s";
        $html .= "</td><td>";
        $html .= TranslatedField($translationField, $signal['signal_text'], "220", "50");
        $html .= "</td>";
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
}

if ($templateId) {
    $html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
} else {
    $html .= "<p><input class='button' name='add' type='submit' value='" . _("Add") . "'/>";
}
$html .= "<input class='button' type='button' name='back' value='" . _("Back") . "' onclick=\"window.location.href='?view=admin/timekeepertemplates'\"/></p>";
$html .= "</form>\n";
foreach ($translationFields as $translationField) {
    $html .= TranslationScript($translationField);
}

echo $html;

contentEnd();
pageEnd();
