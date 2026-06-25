<?php

include_once __DIR__ . '/auth.php';

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';

define('EVENT_DATA_IMPORT_PENDING_SESSION_KEY', 'eventdataimport_pending');
define('EVENT_DATA_IMPORT_PENDING_TTL', 3600);

function EventDataImportReplaceWarning()
{
    return _("This operation replaces event-owned data in the selected event with the JSON snapshot. Event-owned rows missing from the snapshot will be deleted. Event-level admin rights and matched existing player profiles are left unchanged, but admin rights delegated to specific divisions, teams, games, or reservations must be re-granted after the import.");
}

function EventDataImportClearPending()
{
    if (!empty($_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY]['path']) && is_file($_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY]['path'])) {
        @unlink($_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY]['path']);
    }
    unset($_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY]);
}

function EventDataImportPending()
{
    if (empty($_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY]) || !is_array($_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY])) {
        return null;
    }

    $pending = $_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY];
    if (
        empty($pending['path']) ||
        empty($pending['token']) ||
        empty($pending['source_season_id']) ||
        empty($pending['created_at']) ||
        !is_file($pending['path']) ||
        time() - (int) $pending['created_at'] > EVENT_DATA_IMPORT_PENDING_TTL
    ) {
        EventDataImportClearPending();
        return null;
    }

    return $pending;
}

function EventDataImportStorePending($uploadedFile, $info)
{
    EventDataImportClearPending();

    $path = tempnam(sys_get_temp_dir(), 'uo-event-import-');
    if ($path === false) {
        throw new EventSnapshotException(_("Could not keep the import file for confirmation. Select the file again to import it."));
    }

    try {
        $token = bin2hex(random_bytes(16));
    } catch (Exception $e) {
        @unlink($path);
        throw new EventSnapshotException(_("Could not keep the import file for confirmation. Select the file again to import it."));
    }

    if (!move_uploaded_file($uploadedFile, $path)) {
        @unlink($path);
        throw new EventSnapshotException(_("Could not keep the import file for confirmation. Select the file again to import it."));
    }

    $pending = [
        'path' => $path,
        'token' => $token,
        'source_season_id' => (string) $info['season_id'],
        'source_name' => (string) $info['name'],
        'created_at' => time(),
    ];
    $_SESSION[EVENT_DATA_IMPORT_PENDING_SESSION_KEY] = $pending;

    return $pending;
}

function EventDataImportPendingMatchesPost($pending)
{
    return !empty($pending) &&
        !empty($_POST['pendingtoken']) &&
        hash_equals((string) $pending['token'], (string) $_POST['pendingtoken']);
}

function EventDataImportRunPending($pending, $mode)
{
    if (!EventDataImportPendingMatchesPost($pending)) {
        throw new EventSnapshotException(_("The uploaded import file is no longer available. Select the file again to import it."));
    }

    set_time_limit(300);
    ini_set("memory_limit", "512M");
    $targetSeasonId = $mode === "replace" ? $pending['source_season_id'] : "";

    try {
        return EventSnapshotImportJson($pending['path'], $targetSeasonId, $mode);
    } finally {
        EventDataImportClearPending();
    }
}

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
$pendingImport = empty($seasonId) ? EventDataImportPending() : null;
if (!empty($seasonId)) {
    EventDataImportClearPending();
}

// post_max_size and upload_max_filesize are PHP_INI_PERDIR directives and
// cannot be raised here; they must be configured on the server (see
// docs/deployment.md). When an upload exceeds them, PHP discards the body,
// leaving $_POST and $_FILES empty, so detect that case and report it.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $error = _("The uploaded file is too large for the server. Please contact the system administrator to raise the allowed upload size.");
} elseif (isset($_POST['cancelpending'])) {
    EventDataImportClearPending();
    $pendingImport = null;
} elseif ((isset($_POST['addpending']) || isset($_POST['replacepending'])) && isSuperAdmin()) {
    try {
        $mode = isset($_POST['replacepending']) ? "replace" : "new";
        $result = EventDataImportRunPending($pendingImport, $mode);
        $seasonId = $result['season_id'];
        $warnings = $result['warnings'];
        $imported = true;
    } catch (EventSnapshotException $e) {
        $error = $e->getMessage();
    }
    $pendingImport = EventDataImportPending();
} elseif (isset($_POST['add']) && isSuperAdmin()) {
    if (is_uploaded_file($_FILES['restorefile']['tmp_name'])) {

        try {
            set_time_limit(300);
            ini_set("memory_limit", "512M");
            $info = EventSnapshotImportInfo($_FILES['restorefile']['tmp_name']);
            if (!empty($info['exists'])) {
                $pendingImport = EventDataImportStorePending($_FILES['restorefile']['tmp_name'], $info);
            } else {
                $result = EventSnapshotImportJson($_FILES['restorefile']['tmp_name'], $seasonId, "new");
                $seasonId = $result['season_id'];
                $warnings = $result['warnings'];
                unlink($_FILES['restorefile']['tmp_name']);
                $imported = true;
            }
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
if (!empty($pendingImport) && empty($seasonId) && !$imported) {
    $html .= "<form method='post' action='?view=admin/eventdataimport'>\n";
    $html .= "<input type='hidden' name='pendingtoken' value='" . utf8entities($pendingImport['token']) . "'/>";
    $html .= "<p class='warning'>" . sprintf(
        _("The import file contains event %1\$s (%2\$s), which already exists."),
        utf8entities($pendingImport['source_name']),
        utf8entities($pendingImport['source_season_id']),
    ) . "</p>";
    $html .= "<p>" . _("Choose whether to update the existing event or import the snapshot as a new event.") . "</p>";
    $html .= "<p>" . EventDataImportReplaceWarning() . "</p>";
    $html .= "<p><input class='button' type='submit' name='replacepending' value='" . _("Update") . "'/>";
    $html .= "<input class='button' type='submit' name='addpending' value='" . _("Import as new event") . "'/>";
    $html .= "<input class='button' type='submit' name='cancelpending' value='" . _("Cancel") . "'/></p>";
    $html .= "</form>";
} else {
    $html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataimport&amp;season=" . $seasonUrl . "'>\n";

    $html .= "<p><span class='profileheader'>" . _("Select file to import") . ": </span></p>\n";

    $html .= "<p>" . _("Only JSON event snapshots are supported. Legacy XML exports cannot be imported.") . "</p>";
    $html .= "<p><input class='input' type='file' size='80' name='restorefile' accept='application/json,.json'/>";
    $html .= "<input type='hidden' name='MAX_FILE_SIZE' value='100000000'/></p>";

    if (empty($seasonId)) {
        $html .= "<p><input class='button' type='submit' name='add' value='" . _("Import") . "'/>";
        $html .= "<input class='button' type='button' name='return'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasons'\"/></p>";
    } else {
        $html .= "<p>" . EventDataImportReplaceWarning() . "</p>";
        $html .= "<p><input class='button' type='submit' name='replace' value='" . _("Update") . "'/>";
        $html .= "<input class='button' type='button' name='return'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasonadmin&amp;season=" . $seasonUrl . "'\"/></p>";
    }

    $html .= "</form>";
}

showPage($title, $html);
