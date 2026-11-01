<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/api.functions.php';
include_once $include_prefix . 'lib/scorekeeper.functions.php';

$title = _("Scorekeeping app");
$selfUrl = "?view=user/scorekeepingapp";

header("Cache-Control: no-store");
if (!empty($_POST['replace']) && ApiUserTokenReplace($_SESSION['uid']) !== null) {
    header("Location: " . $selfUrl);
    exit();
}
if (!empty($_POST['remove']) && ApiUserTokenRevoke($_SESSION['uid'])) {
    header("Location: " . $selfUrl);
    exit();
}
$token = ApiUserToken($_SESSION['uid']);

$html = "<h1>" . utf8entities($title) . "</h1>";
if ($token === null) {
    $html .= "<p>" . utf8entities(_("No tokens created.")) . "</p>";
    $html .= "<form method='post' action='" . $selfUrl . "'>";
    $html .= "<input type='submit' class='button' name='replace' value='" . utf8entities(_("Create token")) . "'/></form>";
    showPage($title, $html);
    return;
}

$html .= "<div class='scorekeeping-qr'>" . ScorekeeperQrSvg(ScorekeeperAppSetupUrl($token['token']), 5) . "</div>";
$html .= "<p>" . utf8entities(_("Scan this code in the scorekeeping app, then scan a scorekeeping link in the app to keep score.")) . "</p>";
$html .= "<p>" . utf8entities(_("Anyone with this code can keep score with any scorekeeping link they have, until you replace or remove the token.")) . "</p>";
$lastUsed = empty($token['last_used']) ? _("Never") : DefTimeFormat($token['last_used']);
$html .= "<p>" . utf8entities(_("Last used")) . ": " . utf8entities($lastUsed) . "</p>";
$confirm = htmlspecialchars((string) json_encode(_("The current token stops working and the app must be set up again. Continue?")), ENT_QUOTES);
$html .= "<form method='post' action='" . $selfUrl . "' onsubmit='return confirm(" . $confirm . ");'>";
$html .= "<input type='submit' class='button' name='replace' value='" . utf8entities(_("Replace token")) . "'/> ";
$html .= "<input type='submit' class='button' name='remove' value='" . utf8entities(_("Remove token")) . "'/></form>";

showPage($title, $html);
