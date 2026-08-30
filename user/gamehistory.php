<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/gamehistory.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';

if (empty($_GET["game"])) {
    showPage(_("History"), "<p class='warning'>" . _("Game not found") . ".</p>");
    return;
}

$gameId = intval($_GET["game"]);

if (!hasEditGameEventsRight($gameId)) {
    die('Insufficient rights to edit game');
}

$game_result = GameInfo($gameId);
$seasoninfo = SeasonInfo($game_result['season']);

$LAYOUT_ID = GAMEHISTORY;
$title = _("History");
$html = "";
$feedback = "";

if (!empty($_POST['restore']) && !empty($_POST['history_id'])) {
    $historyId = intval($_POST['history_id']);
    $restoreEntry = GameHistoryEntry($historyId);
    if ($restoreEntry === null || (int) $restoreEntry['game'] !== $gameId) {
        $feedback .= "<p class='warning'>" . _("Restore failed") . ".</p>";
    } else {
        $outcome = GameHistoryRestore($historyId);
        if ($outcome['restored']) {
            $feedback .= "<p>" . _("Restored") . ".</p>";
            foreach ($outcome['warnings'] as $warning) {
                $feedback .= "<p class='warning'>" . utf8entities($warning) . "</p>";
            }
        } else {
            $feedback .= "<p class='warning'>" . _("Restore failed") . ".</p>";
        }
    }
}

$viewEntry = null;
if (!empty($_GET['entry'])) {
    $entryCandidate = GameHistoryEntry(intval($_GET['entry']));
    if ($entryCandidate !== null && (int) $entryCandidate['game'] === $gameId) {
        $viewEntry = $entryCandidate;
    }
}

pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

$menutabs[_("Result")] = "?view=user/addresult&game=$gameId";
$menutabs[_("Players")] = "?view=user/addplayerlists&game=$gameId";
$menutabs[_("Scoresheet")] = "?view=user/addscoresheet&game=$gameId";
$menutabs[_("History")] = "?view=user/gamehistory&game=$gameId";
if (!empty($seasoninfo['spiritmode'])) {
    $spiritUrl = SpiritEntryUrl($gameId);
    if (!empty($spiritUrl)) {
        $menutabs[_("Spirit score")] = $spiritUrl;
    }
}
if (ShowDefenseStats()) {
    $menutabs[_("Defence sheet")] = "?view=user/adddefensesheet&game=$gameId";
}
pageMenu($menutabs);

$html .= $feedback;

$count = GameHistoryCount($gameId);
$rows = GameHistoryList($gameId, 200);

if ($count === 0) {
    $html .= "<p>" . _("No changes recorded") . ".</p>";
} else {
    $html .= "<table class='data'>\n<tr>";
    $html .= "<th>" . _("Time") . "</th>";
    $html .= "<th>" . _("User") . "</th>";
    $html .= "<th>" . _("Source") . "</th>";
    $html .= "<th>" . _("Description") . "</th>";
    $html .= "<th></th>";
    $html .= "</tr>\n";

    $confirmText = htmlspecialchars(addslashes(_("This overwrites the current scoresheet with this saved version.")), ENT_QUOTES);

    foreach ($rows as $row) {
        $html .= "<tr>";
        $html .= "<td>" . utf8entities(DefTimeFormat($row['time'])) . "</td>";
        $html .= "<td>" . utf8entities($row['user_id']) . "</td>";
        $html .= "<td>" . utf8entities($row['source']) . "</td>";
        $html .= "<td>" . utf8entities(GameHistoryFormatDetail($row)) . "</td>";
        $html .= "<td>";
        if (!empty($row['has_snapshot'])) {
            $html .= "<a href='?view=user/gamehistory&amp;game=$gameId&amp;entry="
                . intval($row['history_id']) . "'>" . _("Show") . "</a> ";
            $html .= "<form method='post' style='display:inline'>";
            $html .= "<input type='hidden' name='history_id' value='" . intval($row['history_id']) . "'/>";
            $html .= "<input type='submit' name='restore' value='" . _("Restore this version")
                . "' onclick='return confirm(\"" . $confirmText . "\");'/>";
            $html .= "</form>";
        }
        $html .= "</td></tr>\n";
    }
    $html .= "</table>\n";

    if ($count > count($rows)) {
        $html .= "<p>" . sprintf(_("Showing the most recent %d of %d changes."), count($rows), $count) . "</p>";
    }
}

if ($viewEntry !== null && is_array($viewEntry['snapshot'])) {
    $html .= "<h2>" . _("Saved state") . "</h2>\n";
    $html .= "<table class='data'>\n<tr><th>" . _("Point") . "</th><th>"
        . _("Score") . "</th><th>" . _("Assist") . "</th><th>" . _("Scorer") . "</th></tr>\n";
    foreach ($viewEntry['snapshot']['goals'] ?? [] as $goal) {
        $html .= "<tr>";
        $html .= "<td>" . intval($goal['num']) . "</td>";
        $html .= "<td>" . intval($goal['homescore']) . "-" . intval($goal['visitorscore']) . "</td>";
        $html .= "<td>" . utf8entities($goal['assist_name'] ?? "") . "</td>";
        $html .= "<td>" . utf8entities($goal['scorer_name'] ?? "") . "</td>";
        $html .= "</tr>\n";
    }
    $html .= "</table>\n";
}

echo $html;

//common end
contentEnd();
pageEnd();
