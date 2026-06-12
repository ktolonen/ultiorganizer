<?php

$include_prefix = "../";

//Open database connection
include_once '../lib/database.php';
OpenConnection();

include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/session.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/timekeeper.functions.php';
include_once $include_prefix . 'localization.php';

//Public tool: a session is used only to remember the chosen language.
startSecureSession();
if (!isset($_SESSION['uid'])) {
    $_SESSION['uid'] = "anonymous";
}

setSessionLocale();

$styles_prefix = '../';
$pageTitle = _("Timekeeper");
$favicon = $styles_prefix . "cust/" . CUSTOMIZATIONS . "/favicon.png";
if (!is_file($include_prefix . "cust/" . CUSTOMIZATIONS . "/favicon.png")) {
    $favicon = $styles_prefix . "cust/default/favicon.png";
}

$sessionLocale = getSessionLocale();
$lang = explode('_', $sessionLocale);
$lang = !empty($lang[0]) ? $lang[0] : 'en';

$timekeeperActions = TimekeeperActionDefinitions();
$timekeeperCapFields = TimekeeperTemplateCapFields();
$timekeeperCapDefaults = TimekeeperTemplateCapDefaults();
$timekeeperClientTemplates = TimekeeperTemplatesForClient();

$timekeeperLocaleFlagFiles = [
    'de_DE.utf8' => 'Germany.png',
    'en_GB.utf8' => 'United_Kingdom.png',
    'es_ES.utf8' => 'Spain.png',
    'fi_FI.utf8' => 'Finland.png',
];

// Translated strings consumed by script/timekeeper.js.
$timekeeperI18n = [
    'sc_halfstart' => _("Start of game"),
    'sc_betweenpoints' => _("Start of point"),
    'sc_timeout' => _("Timeout"),
    'sc_timeoutbeforepull' => _("Timeout before pull"),
    'sc_halftime' => _("Halftime"),
    'sc_dispute' => _("Call or discussion"),
    'sc_discretrieval' => _("Disc retrieval"),
    // Fallback only: a "Timeout before pull" template with no signal still needs
    // a label for the end-of-timeout signal. Signal text otherwise comes from
    // the template rows.
    'sig_end_timeout' => _("Timeout over"),
    'ui_pause' => _("Pause"),
    'ui_resume' => _("Resume"),
    'ui_mark' => _("Mark"),
    'ui_start_clock' => _("Start"),
    'ui_resume_clock' => _("Resume"),
    'ui_sound_on' => _("Sound on"),
    'ui_sound_off' => _("Sound off"),
    'ui_template' => _("Template"),
    'ui_dismiss' => _("Dismiss"),
    'ui_seconds' => _("seconds"),
    'cap_half_time' => _("Halftime cap reached"),
    'cap_time' => _("Time cap reached"),
    'cap_half_time_mark' => _("Halftime cap"),
    'cap_time_mark' => _("Time cap"),
];

echo "<!DOCTYPE html>\n";
echo "<html lang='" . utf8entities($lang) . "'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'/>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1, viewport-fit=cover'/>\n";
echo "<link rel='icon' type='image/png' href='" . utf8entities($favicon) . "'/>\n";
echo "<title>" . utf8entities($pageTitle) . "</title>\n";
echo mobileStyles();
echo "</head>\n";
echo "<body>\n";
echo "<div data-role='page'>\n";

echo "<div data-role='header'>\n";
echo "<h1>" . utf8entities($pageTitle) . "</h1>\n";
echo "</div>\n";

echo "<div data-role='content'>\n";

// --- Language screen -----------------------------------------------------
// Reusable "change language" pattern: a footer button (#tk-nav-language) reveals
// this flag list; each flag reloads the page with ?locale=... and the chosen
// locale is inherited from the session afterwards. This block plus the footer
// button are self-contained so the same control can be copied into the
// Scorekeeper and Spiritkeeper apps (use the app's own path prefix for the flag
// image src). Hidden by default; the timer view is the first screen.
echo "<div id='tk-screen-language' class='tk-screen tk-hidden'>\n";
echo "<div class='card'><h2>" . utf8entities(_("Select language")) . "</h2>\n";
echo "<div class='tk-language-flags'>\n";
if (isset($locales) && is_array($locales)) {
    foreach ($locales as $localestr => $localename) {
        $flagSrc = $styles_prefix . "locale/" . $localestr . "/flag.png";
        $flagClass = "localeselection tk-language-flag-native";
        if (isset($timekeeperLocaleFlagFiles[$localestr])) {
            $smallFlagFile = $timekeeperLocaleFlagFiles[$localestr];
            if (is_file($include_prefix . "images/flags/small/" . $smallFlagFile)) {
                $flagSrc = $styles_prefix . "images/flags/small/" . $smallFlagFile;
                $flagClass = "localeselection tk-language-flag-small";
            }
        }
        echo "<a href='?locale=" . urlencode($localestr) . "'>";
        echo "<img class='" . utf8entities($flagClass) . "' src='" . utf8entities($flagSrc)
            . "' alt='" . utf8entities($localename) . "'/>";
        echo "</a>\n";
    }
}
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

// --- Configuration screen ------------------------------------------------
echo "<div id='tk-screen-config' class='tk-screen tk-hidden'>\n";
echo "<div class='card'>\n";
echo "<h2>" . utf8entities(_("Time limits")) . "</h2>\n";
echo "<p class='mobile-meta'>" . utf8entities(_("Values are in seconds unless another unit is shown. Defaults follow the WFDF rules.")) . "</p>\n";
echo "<div class='tk-config-row'>\n";
echo "<label for='tk-template-select'>" . utf8entities(_("Template")) . "</label>\n";
echo "<select id='tk-template-select'>\n";
foreach ($timekeeperClientTemplates['templates'] as $template) {
    echo "<option value='" . (int) $template['id'] . "'>" . utf8entities($template['name']) . "</option>\n";
}
echo "</select>\n";
echo "<span class='tk-unit'></span>\n";
echo "</div>\n";
echo "<form id='tk-config-form' onsubmit='return false;'>\n";
echo "<fieldset class='tk-config-group'>\n";
echo "<legend>" . utf8entities(_("Game clock caps")) . "</legend>\n";
foreach ($timekeeperCapFields as $fieldId => $field) {
    echo "<div class='tk-config-row'>\n";
    echo "<label for='cfg_" . utf8entities($fieldId) . "'>" . utf8entities($field['label']) . "</label>\n";
    echo "<input type='number' inputmode='numeric' min='0' step='1' id='cfg_" . utf8entities($fieldId)
        . "' data-field='" . utf8entities($fieldId) . "' value='" . (int) $field['default'] . "'/>\n";
    echo "<span class='tk-unit'>" . utf8entities($field['unit']) . "</span>\n";
    echo "</div>\n";
}
echo "</fieldset>\n";
echo "<div id='tk-signal-config'></div>\n";
echo "</form>\n";
echo "<div class='form-actions'>\n";
echo "<button type='button' id='tk-config-reset' class='button-secondary' data-role='button'>" . utf8entities(_("Reset")) . "</button>\n";
echo "<button type='button' id='tk-config-done' data-role='button'>" . utf8entities(_("Done")) . "</button>\n";
echo "</div>\n";
echo "</div>\n";
echo "</div>\n";

// --- Timer screen (default first view) -----------------------------------
echo "<div id='tk-screen-timer' class='tk-screen'>\n";

echo "<div id='tk-display' class='card tk-display tk-state-ready'>\n";
echo "<div class='tk-display-main'>\n";
echo "<div class='tk-display-scenario' id='tk-display-scenario'>&nbsp;</div>\n";
echo "<div class='tk-display-time' id='tk-display-time'>0:00</div>\n";
echo "<div class='tk-display-signal' id='tk-display-signal'>&nbsp;</div>\n";
echo "<div class='tk-display-actions'>\n";
echo "<button type='button' id='tk-timer-pause' data-role='button' disabled>" . utf8entities(_("Pause")) . "</button>\n";
echo "<button type='button' id='tk-timer-stop' class='button-secondary' data-role='button' disabled>" . utf8entities(_("Stop")) . "</button>\n";
echo "</div>\n";
echo "</div>\n"; // /main
// Side panel: the running scenario's signal limits, in light grey.
echo "<div class='tk-side' id='tk-action-limits'></div>\n";
echo "</div>\n";

echo "<div class='card'>\n";
echo "<h2>" . utf8entities(_("Actions")) . "</h2>\n";
echo "<div class='tk-actions'>\n";
// Start of point is the most-used action, so it leads.
$scenarioOrder = ['betweenpoints', 'timeout', 'halfstart', 'halftime', 'dispute', 'discretrieval'];
foreach ($scenarioOrder as $scenarioId) {
    echo "<button type='button' class='tk-action' data-scenario='" . utf8entities($scenarioId)
        . "' data-role='button'>" . utf8entities($timekeeperActions[$scenarioId]['label']) . "</button>\n";
}
echo "</div>\n";
echo "</div>\n";

echo "<div class='card'>\n";
echo "<h2>" . utf8entities(_("Game clock")) . "</h2>\n";
echo "<div class='tk-matchclock-body tk-cap-none' id='tk-matchclock-body'>\n";
echo "<div class='tk-matchclock-main'>\n";
echo "<div class='tk-matchclock' id='tk-matchclock-time'>0:00</div>\n";
echo "<div class='tk-cap-alert tk-hidden' id='tk-cap-alert'><span id='tk-cap-text'></span> ";
echo "<button type='button' id='tk-cap-dismiss' class='button-secondary' data-role='button'>" . utf8entities(_("Dismiss")) . "</button></div>\n";
echo "<div class='tk-matchclock-actions'>\n";
// Primary button becomes "Mark" once running, snapshotting the current time.
echo "<button type='button' id='tk-clock-primary' data-role='button'>" . utf8entities(_("Start")) . "</button>\n";
echo "<button type='button' id='tk-clock-pause' class='button-secondary' data-role='button' disabled>" . utf8entities(_("Pause")) . "</button>\n";
echo "<button type='button' id='tk-clock-reset' class='button-secondary' data-role='button'>" . utf8entities(_("Reset")) . "</button>\n";
echo "</div>\n";
echo "</div>\n"; // /main
// Side panel: marked snapshot times, in light grey.
echo "<div class='tk-side' id='tk-clock-marks'></div>\n";
echo "</div>\n"; // /body
echo "</div>\n";

echo "</div>\n"; // /timer screen

echo "</div>\n"; // /content

// --- Footer --------------------------------------------------------------
echo "<div data-role='footer' class='ui-bar' data-position='fixed'>\n";
echo "<a class='footer-compact' href='" . BASEURL . "/' data-role='button' rel='external' data-icon='home'>" . utf8entities(_("Ultiorganizer")) . "</a>";
echo "<button type='button' id='tk-nav-language' class='footer-compact' data-role='button'>" . utf8entities(_("Change language")) . "</button>";
echo "<button type='button' id='tk-nav-config' class='footer-compact' data-role='button'>" . utf8entities(_("Time limits")) . "</button>";
echo "<button type='button' id='tk-sound-toggle' class='footer-compact' data-role='button'>" . utf8entities(_("Sound on")) . "</button>";
echo "</div>\n";

echo "</div>\n"; // /page

echo "<script>\n";
echo "var TIMEKEEPER_CAP_DEFAULTS = " . json_encode($timekeeperCapDefaults) . ";\n";
echo "var TIMEKEEPER_DEFAULT_TEMPLATE_ID = " . json_encode($timekeeperClientTemplates['defaultTemplateId']) . ";\n";
echo "var TIMEKEEPER_TEMPLATES = " . json_encode($timekeeperClientTemplates['templates']) . ";\n";
echo "var TIMEKEEPER_I18N = " . json_encode($timekeeperI18n) . ";\n";
echo "</script>\n";
echo "<script src='" . $styles_prefix . "script/timekeeper.js'></script>\n";

echo "</body>\n";
echo "</html>\n";

CloseConnection();
