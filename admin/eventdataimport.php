<?php

include_once __DIR__ . '/auth.php';

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';

$html = "";
$title = _("Event data import");
$seasonId = "";
$imported = false;
$error = "";
$warnings = [];

//check access rights before user can upload data into server
if (!empty($_GET['season'])) {
    $seasonId = $_GET["season"];
    if (!isSeasonAdmin($seasonId)) {
        die(_("Insufficient rights to import data"));
    }
} else {
    if (!isSuperAdmin()) {
        die(_("Insufficient rights to import data"));
    }
}

// post_max_size and upload_max_filesize are PHP_INI_PERDIR directives and
// cannot be raised here; they must be configured on the server (see
// docs/deployment.md). When an upload exceeds them, PHP discards the body,
// leaving $_POST and $_FILES empty, so detect that case and report it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = _("The uploaded file is too large for the server. Please contact the system administrator to raise the allowed upload size.");
} elseif (isset($_POST['add']) && isSuperAdmin()) {
    if (is_uploaded_file($_FILES['restorefile']['tmp_name'])) {

        try {
            set_time_limit(300);
            ini_set("memory_limit", "512M");
            $result = EventSnapshotImportJson($_FILES['restorefile']['tmp_name'], $seasonId, "new");
            $seasonId = $result['season_id'];
            $warnings = $result['warnings'];
            unlink($_FILES['restorefile']['tmp_name']);
            $imported = true;
        } catch (EventSnapshotException $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = _("Select file to import");
    }
} elseif (isset($_POST['replace'])) {
    if (is_uploaded_file($_FILES['restorefile']['tmp_name'])) {

        try {
            set_time_limit(300);
            ini_set("memory_limit", "512M");
            $result = EventSnapshotImportJson($_FILES['restorefile']['tmp_name'], $seasonId, "replace");
            $seasonId = $result['season_id'];
            $warnings = $result['warnings'];
            unlink($_FILES['restorefile']['tmp_name']);
            $imported = true;
        } catch (EventSnapshotException $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = _("Select file to import");
    }
}

//common page
if ($imported) {
    $html .= "<p>" . _("Data imported!") . "</p>";
    if (!empty($warnings)) {
        $html .= "<p class='warning'>" . _("Import completed with warnings:") . "</p><ul>";
        foreach ($warnings as $warning) {
            $html .= "<li>" . utf8entities($warning) . "</li>";
        }
        $html .= "</ul>";
    }
    unset($_POST['restore']);
    unset($_POST['replace']);
}
if (!empty($error)) {
    $html .= "<p class='warning'>" . utf8entities($error) . "</p>";
}

$seasonUrl = utf8entities(urlencode($seasonId));
$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataimport&amp;season=" . $seasonUrl . "'>\n";

$html .= "<p><span class='profileheader'>" . _("Select file to import") . ": </span></p>\n";

$html .= "<p>" . _("Only JSON event snapshots are supported. Legacy XML exports cannot be imported.") . "</p>";
$html .= "<p><input class='input' type='file' size='80' name='restorefile' accept='application/json,.json'/>";
$html .= "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'/></p>";

if (empty($seasonId)) {
    $html .= "<p><input class='button' type='submit' name='add' value='" . _("Import") . "'/>";
    $html .= "<input class='button' type='button' name='return'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasons'\"/></p>";
} else {
    $html .= "<p>" . _("This operation replaces event-owned data in the selected event with the JSON snapshot. Event-owned rows missing from the snapshot will be deleted, but user rights and matched existing player profiles are left unchanged.") . "</p>";
    $html .= "<p><input class='button' type='submit' name='replace' value='" . _("Update") . "'/>";
    $html .= "<input class='button' type='button' name='return'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasonadmin&amp;season=" . $seasonUrl . "'\"/></p>";
}

$html .= "</form>";

showPage($title, $html);
