<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/pool.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';
include_once 'lib/statistical.functions.php';

$body = @file_get_contents('php://input');

$series = explode("|", $body);
foreach ($series as $seriesStr) {
    $teams = explode(":", $seriesStr);
    $teamIds = [];
    foreach ($teams as $teamId) {
        if (!empty($teamId)) {
            $teamIds[] = (int) $teamId;
        }
    }
    if (count($teamIds) && !SaveFinalStandingsOrderByTeamIds($teamIds)) {
        http_response_code(400);
        die(_("Failed to save standings"));
    }
}

echo _("Standings saved");
