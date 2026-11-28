<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/api.functions.php';
include_once $include_prefix . 'lib/scorekeeper.functions.php';

$title = _("Scorekeeping app");
$selfUrl = "?view=user/scorekeepingapp";

header("Cache-Control: no-store");
$canSetUp = CanSetUpScorekeepingApp();
if ($canSetUp && !empty($_POST['replace']) && ApiUserTokenReplace($_SESSION['uid']) !== null) {
    header("Location: " . $selfUrl);
    exit();
}
if (!empty($_POST['remove']) && ApiUserTokenRevoke($_SESSION['uid'])) {
    header("Location: " . $selfUrl);
    exit();
}
$token = ApiUserToken($_SESSION['uid']);

$html = "<h1>" . utf8entities($title) . "</h1>";
$html .= "<p><a href='https://github.com/ktolonen/ultiorganizer-app/releases'>" . utf8entities(_("Scorekeeping app releases")) . "</a></p>";
if (!$canSetUp) {
    $html .= "<p>" . utf8entities(_("No current event uses the scorekeeping app.")) . "</p>";
    if ($token !== null) {
        $html .= "<form method='post' action='" . $selfUrl . "'>";
        $html .= "<input type='submit' class='button' name='remove' value='" . utf8entities(_("Remove token")) . "'/></form>";
    }
    showPage($title, $html);
    return;
}
if ($token === null) {
    $html .= "<p>" . utf8entities(_("No tokens created.")) . "</p>";
    $html .= "<form method='post' action='" . $selfUrl . "'>";
    $html .= "<input type='submit' class='button' name='replace' value='" . utf8entities(_("Create token")) . "'/></form>";
    showPage($title, $html);
    return;
}

$setupUrl = ScorekeeperAppSetupUrl($token['token']);
$html .= "<div class='scorekeeping-qr'>" . ScorekeeperQrSvg($setupUrl, 5) . "</div>";
$html .= "<p>" . utf8entities(_("Scan this code in the scorekeeping app, then scan a scorekeeping link in the app to keep score.")) . "</p>";
$html .= "<p>" . utf8entities(_("On the phone with the app, copy this link and paste it in the app under Pair a server.")) . "</p>";
$html .= "<p><input type='text' id='setupurl' class='input' readonly='readonly' value='" . utf8entities($setupUrl) . "'/> ";
$html .= "<button type='button' class='button' onclick='copySetupUrl()'>" . utf8entities(_("Copy")) . "</button></p>";
$html .= "<script type='text/javascript'>
function copySetupUrl() {
  var field = document.getElementById('setupurl');
  field.select();
  if (navigator.clipboard) {
    navigator.clipboard.writeText(field.value);
  } else {
    document.execCommand('copy');
  }
}
</script>";
$html .= "<p>" . utf8entities(_("Anyone with this code or link can keep score with any scorekeeping link they have, until you replace or remove the token.")) . "</p>";
$lastUsed = empty($token['last_used']) ? _("Never") : DefTimeFormat($token['last_used']);
$html .= "<p>" . utf8entities(_("Last used")) . ": " . utf8entities($lastUsed) . "</p>";
$confirm = htmlspecialchars((string) json_encode(_("The current token stops working and the app must be set up again. Continue?")), ENT_QUOTES);
$html .= "<form method='post' action='" . $selfUrl . "' onsubmit='return confirm(" . $confirm . ");'>";
$html .= "<input type='submit' class='button' name='replace' value='" . utf8entities(_("Replace token")) . "'/> ";
$html .= "<input type='submit' class='button' name='remove' value='" . utf8entities(_("Remove token")) . "'/></form>";

showPage($title, $html);
