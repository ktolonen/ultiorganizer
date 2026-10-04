<?php

include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/season.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/scorekeeper.functions.php';

$LAYOUT_ID = SEASONADMIN;
$season = (string) iget("season");
$title = utf8entities(SeasonName($season)) . ": " . _("Scorekeeping links");
$selfUrl = "?view=admin/scorekeepinglinks&amp;season=" . urlencode($season);

if (!isSeasonAdmin($season)) {
    showPage($title, "<p>" . _("Insufficient user rights") . "</p>");
    return;
}

header("Cache-Control: no-store");
if (!empty($_POST['revoke'])) {
    $scope = (string) ($_POST['scope'] ?? '');
    $scopeId = (int) ($_POST['id'] ?? 0);
    $scopeSeason = $scope === 'game' ? GameSeason($scopeId) : ReservationSeason($scopeId);
    if ($scopeSeason === $season) {
        ScorekeeperRevokeToken($scope, $scopeId);
    }
    header("Location: " . html_entity_decode($selfUrl));
    exit();
}

if ((int) iget("print") === 1) {
    $day = (string) iget("day");
    $group = (string) iget("group");
    $sheets = "";
    foreach (SeasonReservations($season, $group === "" ? "all" : $group) as $reservation) {
        if ($day !== "" && substr((string) $reservation['starttime'], 0, 10) !== $day) {
            continue;
        }
        if (ReservationGames((int) $reservation['id'], $season) !== []) {
            $sheets .= ScorekeeperLinkSheetHtml('reservation', (int) $reservation['id']);
        }
    }
    showPrintablePage($title, $sheets);
    return;
}

$reservations = SeasonReservations($season);
$tokens = SeasonScorekeeperTokens($season);
$canIssue = !isEventReadonly($season) || canBypassEventReadonly($season);
$confirm = htmlspecialchars((string) json_encode(_("The current link stops working and everyone who opened it loses access. Continue?")), ENT_QUOTES);

// Every action is a button; navigation goes through a GET form.
$getButton = function ($params, $label, $newTab = false) {
    $html = "<form method='get' action='index.php' style='display:inline;'" . ($newTab ? " target='_blank'" : "") . ">";
    foreach ($params as $name => $value) {
        $html .= "<input type='hidden' name='" . $name . "' value='" . utf8entities((string) $value) . "'/>";
    }
    return $html . "<input type='submit' class='button' value='" . utf8entities($label) . "'/></form>";
};
$linkRow = function ($scope, $id, $label) use ($tokens, $canIssue, $confirm, $selfUrl, $getButton) {
    $token = $tokens[$scope . ":" . $id] ?? null;
    $users = [];
    foreach ($token['users'] ?? [] as $user) {
        $users[] = utf8entities($user['name'] ?: $user['userid']);
    }
    $html = "<tr class='admintablerow'><td>" . $label . "</td>";
    $html .= "<td>" . ($token ? ShortDate($token['created']) . " " . DefHourFormat($token['created']) : "") . "</td>";
    $html .= "<td>" . implode(", ", $users) . "</td><td class='right'>";
    if ($canIssue) {
        $html .= $getButton(['view' => 'user/scorekeepinglink', $scope => $id], _("Show"));
        if ($token) {
            $html .= "<form method='post' action='" . $selfUrl . "' style='display:inline;' onsubmit='return confirm(" . $confirm . ");'>";
            $html .= "<input type='hidden' name='scope' value='" . $scope . "'/><input type='hidden' name='id' value='" . $id . "'/>";
            $html .= " <input type='submit' class='button' name='revoke' value='" . utf8entities(_("Revoke")) . "'/></form>";
        }
    }
    return $html . "</td></tr>\n";
};
$gameLabel = function ($game) {
    $home = $game['hometeam'] ? $game['hometeamname'] : U_($game['phometeamname']);
    $away = $game['visitorteam'] ? $game['visitorteamname'] : U_($game['pvisitorteamname']);
    return DefHourFormat($game['time']) . " " . utf8entities($home) . " - " . utf8entities($away);
};
// One table per field; the shared column widths keep the tables aligned.
$tableHead = function ($heading) {
    return "<table class='admintable'>\n<colgroup><col style='width:45%'/><col style='width:15%'/><col style='width:25%'/><col style='width:15%'/></colgroup>\n"
        . "<tr><th>" . $heading . "</th><th>" . _("Created") . "</th><th>" . _("Users") . "</th><th></th></tr>\n";
};

$html = "<h2>" . $title . "</h2>\n";
if (!$canIssue) {
    $html .= "<p>" . utf8entities(_("The event is read-only.")) . "</p>\n";
}

$day = null;
foreach ($reservations as $reservation) {
    $resId = (int) $reservation['id'];
    $resDay = substr((string) $reservation['starttime'], 0, 10);
    if ($resDay !== $day) {
        $day = $resDay;
        $html .= "<h3>" . DefWeekDateFormat($reservation['starttime']) . "</h3>\n";
        if ($canIssue) {
            $html .= "<p>" . $getButton(['view' => 'admin/scorekeepinglinks', 'season' => $season, 'print' => 1, 'day' => $day], _("Print field sheets"), true) . "</p>\n";
        }
    }
    $heading = utf8entities(ReservationPlaceText(U_($reservation['name']), U_($reservation['fieldname'])))
        . " " . DefHourFormat($reservation['starttime']) . "-" . DefHourFormat($reservation['endtime']);
    $html .= $tableHead($heading);
    $html .= $linkRow('reservation', $resId, "<b>" . _("All games") . "</b>");
    foreach (ReservationGames($resId, $season) as $game) {
        $html .= $linkRow('game', (int) $game['game_id'], $gameLabel($game));
    }
    $html .= "</table>\n";
}

$unscheduled = array_filter(SeasonAllGames($season), fn($game) => empty($game['reservation']));
if ($unscheduled !== []) {
    $html .= "<h3>" . _("Unscheduled") . "</h3>\n" . $tableHead(_("Game"));
    foreach ($unscheduled as $game) {
        $target = ScorekeeperLinkTarget('game', (int) $game['game_id']);
        $html .= $linkRow('game', (int) $game['game_id'], utf8entities($target === null ? "" : $target['subject']));
    }
    $html .= "</table>\n";
}

pageTopHeadOpen($title);
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();
echo $html;
contentEnd();
pageEnd();
