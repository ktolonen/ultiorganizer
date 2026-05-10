<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

interface SchedulePdf
{
    public function PrintSchedule($scope, $id, $games);

    public function PrintOnePageSchedule($scope, $id, $games, $colors = false);
}

interface ScoreSheetPdf
{
    public function PrintScoreSheet(
        $seasonname,
        $gameId,
        $hometeamname,
        $visitorteamname,
        $poolname,
        $time,
        $placename,
        $homeplayers = [],
        $visitorplayers = [],
    );

    public function PrintPlayerList($homeplayers, $visitorplayers);

    public function PrintRoster($teamname, $seriesname, $poolname, $players);
}
