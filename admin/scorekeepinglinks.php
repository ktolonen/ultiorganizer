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

$reservations = SeasonReservations($season);

if ((int) iget("print") === 1) {
    $day = (string) iget("day");
    $sheets = "";
    foreach ($reservations as $reservation) {
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

$tokens = SeasonScorekeeperTokens($season);
$canIssue = !isEventReadonly($season) || canBypassEventReadonly($season);
$confirm = htmlspecialchars((string) json_encode(_("The current link stops working and everyone who opened it loses access. Continue?")), ENT_QUOTES);

$linkRow = function ($scope, $id, $label) use ($tokens, $canIssue, $confirm, $selfUrl) {
    $token = $tokens[$scope . ":" . $id] ?? null;
    $users = [];
    foreach ($token['users'] ?? [] as $user) {
        $users[] = utf8entities($user['name'] ?: $user['userid']);
    }
    $html = "<tr class='admintablerow'><td>" . $label . "</td>";
    $html .= "<td>" . ($token ? ShortDate($token['created']) . " " . DefHourFormat($token['created']) : "") . "</td>";
    $html .= "<td>" . implode(", ", $users) . "</td><td class='right'>";
    if ($canIssue) {
        $html .= "<a href='?view=user/scorekeepinglink&amp;" . $scope . "=" . $id . "'>" . _("Show") . "</a>";
        if ($token) {
            $html .= "<form method='post' action='" . $selfUrl . "' style='display:inline;' onsubmit='return confirm(" . $confirm . ");'>";
            $html .= "<input type='hidden' name='scope' value='" . $scope . "'/><input type='hidden' name='id' value='" . $id . "'/>";
            $html .= " <input type='submit' class='button' name='revoke' value='" . utf8entities(_("Revoke")) . "'/></form>";
        }
    }
    return $html . "</td></tr>\n";
};
$gameLabel = function ($game) {
    $home = $game['hometeam'] ? $game['hometeamname'] : $game['phometeamname'];
    $away = $game['visitorteam'] ? $game['visitorteamname'] : $game['pvisitorteamname'];
    return "&nbsp;&nbsp;" . DefHourFormat($game['time']) . " " . utf8entities($home) . " - " . utf8entities($away);
};
$tableHead = "<table class='admintable'>\n<tr><th></th><th>" . _("Created") . "</th><th>" . _("Users") . "</th><th></th></tr>\n";

$html = "<h2>" . $title . "</h2>\n";
if (!$canIssue) {
    $html .= "<p>" . utf8entities(_("The event is read-only.")) . "</p>\n";
}

$day = null;
foreach ($reservations as $reservation) {
    $resId = (int) $reservation['id'];
    $resDay = substr((string) $reservation['starttime'], 0, 10);
    if ($resDay !== $day) {
        $html .= $day === null ? "" : "</table>\n";
        $day = $resDay;
        $html .= "<h3>" . DefWeekDateFormat($reservation['starttime']);
        if ($canIssue) {
            $html .= " <a href='" . $selfUrl . "&amp;print=1&amp;day=" . $day . "' target='_blank' rel='noopener'>" . _("Print field sheets") . "</a>";
        }
        $html .= "</h3>\n" . $tableHead;
    }
    $label = "<b>" . utf8entities(ReservationPlaceText(U_($reservation['name']), U_($reservation['fieldname']))) . "</b> "
        . DefHourFormat($reservation['starttime']) . "-" . DefHourFormat($reservation['endtime']);
    $html .= $linkRow('reservation', $resId, $label);
    foreach (ReservationGames($resId, $season) as $game) {
        $html .= $linkRow('game', (int) $game['game_id'], $gameLabel($game));
    }
}
$html .= $day === null ? "" : "</table>\n";

$unscheduled = array_filter(SeasonAllGames($season), fn($game) => empty($game['reservation']));
if ($unscheduled !== []) {
    $html .= "<h3>" . _("Unscheduled") . "</h3>\n" . $tableHead;
    foreach ($unscheduled as $game) {
        $html .= $linkRow('game', (int) $game['game_id'], utf8entities(GameName(GameInfo((int) $game['game_id']))));
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
