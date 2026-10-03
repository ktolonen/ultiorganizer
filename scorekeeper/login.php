<?php

$html = "";
$errors = "";
if (isset($_POST['login'])) {
    if (!isset($_SESSION['uid']) || $_SESSION['uid'] == "anonymous") {
        $errors .= "<p class='warning'>" . _("Check the username and password.") . "</p>\n";
    } else {
        header("location:?view=respgames");
    }
} elseif (isset($_SESSION['uid']) && $_SESSION['uid'] != "anonymous") {
    header("location:?view=respgames");
}
$html .= "<div data-role='header'>\n";
$html .= "<h1>" . _("Log in") . "</h1>\n";
$html .= "</div><!-- /header -->\n\n";
$html .= "<div data-role='content'>\n";
$html .= $errors;
$html .= ScorekeeperTakeNoticeHtml();
if (!empty($_SESSION['scorekeeper_pending_token'])) {
    $pendingDescription = ScorekeeperTokenDescription((int) $_SESSION['scorekeeper_pending_token']);
    if ($pendingDescription !== "") {
        $html .= "<p>" . utf8entities(sprintf(_("Log in to keep score for %s."), $pendingDescription)) . "</p>\n";
    }
}
$html .= "<form action='?view=login' method='post' data-ajax='false'>\n";
$html .= "<label for='myusername'>" . _("Username") . ":</label>";
$html .= "<input type='text' id='myusername' name='myusername' size='15'/> ";
$html .= "<label for='mypassword'>" . _("Password") . ":</label>";
$html .= "<input type='password' id='mypassword' name='mypassword' size='15'/> ";
$html .= "<div class='form-actions'>";
$html .= "<input type='submit' name='login' value='" . _("Log in") . "'/>";
$html .= "</div>";
$html .= "</form>";
$html .= "<div class='card mobile-language-selection'>";
$html .= "<h2>" . utf8entities(_("Select language")) . "</h2>";
$html .= MobileLanguageSelection(['view' => 'login']);
$html .= "</div>";
$html .= "</div><!-- /content -->\n\n";

echo $html;
