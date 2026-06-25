<?php

include_once __DIR__ . '/auth.php';

include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/data.functions.php';

$html = "";
$title = _("Event data export");
$seasonId = isset($_GET['season']) ? $_GET['season'] : "";

if (!empty($_POST['season'])) {
    $seasonId = $_POST['season'];

    try {
        $safeFilename = preg_replace('/[^A-Za-z0-9_.-]/', '_', $seasonId);
        $filename = ($safeFilename === '' ? 'event' : $safeFilename) . ".json";
        $data = EventSnapshotExportJson($seasonId);

        header("Pragma: public");
        header("Expires: -1");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: public");
        header("Content-Type: application/json; charset=UTF-8");
        header("Content-Description: File Transfer");
        header("Content-Disposition: attachment; filename=\"$filename\";");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: " . strlen($data));
        echo $data;
        exit;
    } catch (EventSnapshotException $e) {
        $html .= "<p class='warning'>" . utf8entities($e->getMessage()) . "</p>";
    }
}


//season selection
$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/eventdataexport'>\n";

$html .= "<p>" . _("Select event") . ": <select class='dropdown' name='season'>\n";

$seasons = Seasons();

foreach ($seasons as $row) {
    $selected = $row['season_id'] === $seasonId ? " selected='selected'" : "";
    $html .= "<option class='dropdown'" . $selected . " value='" . utf8entities($row['season_id']) . "'>" . utf8entities($row['name']) . "</option>";
}

$html .= "</select></p>\n";
$html .= "<p><input class='button' type='submit' name='select' value='" . _("Select") . "'/></p>";

$html .= "</form>";

showPage($title, $html);
