<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/image.functions.php';

$seasonId = iget("season");
$info = SeasonInfo($seasonId);
$title = _("Banner");
$html = "";

if (!$info) {
    showPage($title, "<h1>" . _("Event not found") . "</h1>");
    return;
}

$title = _("Banner") . ": " . utf8entities(U_($info['name']));

if (!isSeasonAdmin($seasonId)) {
    showPage($title, "<p>" . _("Insufficient user rights") . "</p>");
    return;
}

if (!empty($_POST['upload'])) {
    $html .= UploadSeasonBanner($seasonId, isset($_FILES['bannerfile']) ? $_FILES['bannerfile'] : []);
    $info = SeasonInfo($seasonId);
}

if (!empty($_POST['remove'])) {
    RemoveSeasonBanner($seasonId);
    $info = SeasonInfo($seasonId);
}

$seasonUrl = utf8entities(urlencode($seasonId));

$html .= "<h2>" . utf8entities(U_($info['name'])) . "</h2>\n";
$html .= "<p>" . _("The banner is shown at the top of the event's public pages. A wide image works best, for example 1600 x 400 pixels.") . "</p>\n";

$banner = SeasonBannerHTML($seasonId);
if ($banner === "") {
    $html .= "<p>" . _("No banner set.") . "</p>\n";
} else {
    $html .= "<p>" . $banner . "</p>\n";
}

$html .= "<form method='post' enctype='multipart/form-data' action='?view=admin/seasonbanner&amp;season=" . $seasonUrl . "'>\n";
$html .= "<p><input class='input' type='file' size='60' name='bannerfile' accept='image/*'/>";
$html .= "<input type='hidden' name='MAX_FILE_SIZE' value='5242880'/></p>\n";
$html .= "<p><input class='button' type='submit' name='upload' value='" . _("Upload") . "'/>";
if ($banner !== "") {
    $html .= "<input class='button' type='submit' name='remove' value='" . _("Delete image") . "'/>";
}
$html .= "<input class='button' type='button' name='return' value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/seasonadmin&amp;season=" . $seasonUrl . "'\"/></p>\n";
$html .= "</form>\n";

showPage($title, $html);
