<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/game.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/scorekeeper.functions.php';

$title = _("Scorekeeping link");
$gameId = (int) iget("game");
$reservationId = (int) iget("reservation");
$scope = $gameId > 0 ? 'game' : 'reservation';
$scopeId = $gameId > 0 ? $gameId : $reservationId;
$print = (int) iget("print") === 1;
$selfUrl = "?view=user/scorekeepinglink&amp;" . $scope . "=" . $scopeId;

$html = "";
header("Cache-Control: no-store");
if (!empty($_POST['rotate']) && ScorekeeperRotateToken($scope, $scopeId) !== null) {
    header("Location: " . html_entity_decode($selfUrl));
    exit();
}
$token = ScorekeeperToken($scope, $scopeId);
if ($token === null) {
    $season = $scope === 'game' ? GameSeason($scopeId) : ReservationSeason($scopeId);
    $reason = !empty($season) && isEventReadonly($season) && !canBypassEventReadonly($season)
        ? _("The event is read-only.") : _("Insufficient rights.");
    showPage($title, "<h1>" . utf8entities($title) . "</h1><p>" . utf8entities($reason) . "</p>");
    return;
}
$url = ScorekeeperTokenUrl($token);

if ($print) {
    showPrintablePage($title, ScorekeeperLinkSheetHtml($scope, $scopeId));
    return;
}

$target = ScorekeeperLinkTarget($scope, $scopeId);
$seasonInfo = SeasonInfo($target['season']);
$anonymous = !empty($seasonInfo['anonymous_scorekeeping']);
if ($scope === 'game') {
    $who = $anonymous
        ? _("Anyone with this link can keep score for this game without logging in.")
        : _("Anyone with this link can keep score for this game after logging in.");
} else {
    $who = $anonymous
        ? _("Anyone with this link can keep score for these games without logging in.")
        : _("Anyone with this link can keep score for these games after logging in.");
}

$html .= "<h1>" . utf8entities($title) . "</h1>";
$html .= "<p><b>" . utf8entities($target['subject']) . "</b></p>";
$html .= ScorekeeperLinkGamesHtml($target['games']);
$html .= "<div class='scorekeeping-qr'>" . ScorekeeperQrSvg($url, 5) . "</div>";
$html .= "<p><input type='text' id='scorekeepingurl' class='input' readonly='readonly' value='" . utf8entities($url) . "'/> ";
$html .= "<button type='button' class='button' onclick='copyScorekeepingUrl()'>" . utf8entities(_("Copy")) . "</button> ";
$html .= "<button type='button' class='button' id='scorekeepingshare' style='display:none;' onclick='shareScorekeepingUrl()'>" . utf8entities(_("Share")) . "</button></p>";
$html .= "<p><a href='" . $selfUrl . "&amp;print=1' target='_blank' rel='noopener'>" . utf8entities(_("Printable version")) . "</a></p>";
$html .= "<p>" . utf8entities($who);
if ($scope === 'reservation') {
    $html .= " " . utf8entities(_("The link works only on the day of the field reservation."));
} elseif (!empty($target['games'][0]['time'])) {
    $html .= " " . utf8entities(_("The link works only on the day of the game."));
}
$html .= "</p>";
$confirm = htmlspecialchars((string) json_encode(_("The current link stops working and everyone who opened it loses access. Continue?")), ENT_QUOTES);
$html .= "<form method='post' action='" . $selfUrl . "' onsubmit='return confirm(" . $confirm . ");'>";
$html .= "<input type='submit' class='button' name='rotate' value='" . utf8entities(_("Replace link")) . "'/></form>";
$html .= "<script type='text/javascript'>
function copyScorekeepingUrl() {
  var field = document.getElementById('scorekeepingurl');
  field.select();
  if (navigator.clipboard) {
    navigator.clipboard.writeText(field.value);
  } else {
    document.execCommand('copy');
  }
}
function shareScorekeepingUrl() {
  navigator.share({ url: document.getElementById('scorekeepingurl').value }).catch(function () {});
}
if (navigator.share) {
  document.getElementById('scorekeepingshare').style.display = '';
}
</script>";

showPage($title, $html);
