<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/timekeeper.functions.php';

if (!isSuperAdmin()) {
    die('Insufficient rights to manage timekeeper templates.');
}

$LAYOUT_ID = TIMEKEEPERTEMPLATES;

$title = _("Timekeeper templates");
$html = "";

if (!empty($_POST['remove_x'])) {
    DeleteTimekeeperTemplate((int) $_POST['hiddenDeleteId']);
}
if (!empty($_POST['set_default'])) {
    SetDefaultTimekeeperTemplate((int) $_POST['set_default']);
}

pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<form method='post' action='?view=admin/timekeepertemplates'>";
$html .= "<h2>" . _("Timekeeper templates") . "</h2>\n";
$html .= "<table style='width:100%' border='0' cellpadding='4'>\n";
$html .= "<tr>";
$html .= "<th>" . _("Name") . "</th>";
$html .= "<th class='center'>" . _("Default") . "</th>";
$html .= "<th class='center'>" . _("Signals") . "</th>";
$html .= "<th></th><th></th>";
$html .= "</tr>\n";

foreach (TimekeeperTemplateRows() as $row) {
    $templateId = (int) $row['template_id'];
    $isDefault = (int) $row['is_default'];

    $html .= "<tr>";
    $html .= "<td><a href='?view=admin/addtimekeepertemplate&amp;template=" . $templateId . "'>" . utf8entities(U_($row['name'])) . "</a></td>";
    $html .= "<td class='center'>";
    if ($isDefault) {
        $html .= utf8entities(_("Yes"));
    } else {
        $html .= "<button class='button' type='submit' name='set_default' value='" . $templateId . "'>" . utf8entities(_("Set default")) . "</button>";
    }
    $html .= "</td>";
    $html .= "<td class='center'>" . (int) $row['signal_count'] . "</td>";
    $html .= "<td class='center'>";
    if (!$isDefault) {
        $html .= "<input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $templateId . ");\"/>";
    } else {
        $html .= "-";
    }
    $html .= "</td>";
    $html .= "</tr>\n";
}

$html .= "</table><p><input class='button' name='add' type='button' value='" . _("Add") . "' onclick=\"window.location.href='?view=admin/addtimekeepertemplate'\"/></p>";
$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>\n";

echo $html;

contentEnd();
pageEnd();
