<?php

$include_prefix = "../";

//Open database connection
include_once '../lib/database.php';
OpenConnection();

include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/session.functions.php';
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'localization.php';

//Public tool: a session is used only to remember the chosen language.
startSecureSession();
if (!isset($_SESSION['uid'])) {
    $_SESSION['uid'] = "anonymous";
}

setSessionLocale();

$styles_prefix = '../';
$pageTitle = _("Timekeeper");

$sessionLocale = getSessionLocale();
$lang = explode('_', $sessionLocale);
$lang = !empty($lang[0]) ? $lang[0] : 'en';

// Configuration groups. Each field has a WFDF default (seconds) and is fully
// editable by the user; only the offsets are configurable, labels are fixed.
$timekeeperGroups = [
    'betweenpoints' => [
        'label' => _("Start of point"),
        'fields' => [
            'bp_off' => ['label' => _("Offence warning"), 'default' => 45],
            'bp_def' => ['label' => _("Defence warning"), 'default' => 60],
            'bp_play' => ['label' => _("Play"), 'default' => 75],
        ],
    ],
    // Timeout. After the pull (WFDF A5.6), timed from the call: 45 (offence
    // 30 s warning) / 60 (offence 15 s warning) / 75 (defence 15 s warning) /
    // 90 (play). Before the pull (A5.5), pressed while Start of point runs:
    // "Added time before pull" is added to the point-start timeline.
    'timeout' => [
        'label' => _("Timeout"),
        'fields' => [
            'to_off1' => ['label' => _("First offence warning"), 'default' => 45],
            'to_off2' => ['label' => _("Second offence warning"), 'default' => 60],
            'to_def' => ['label' => _("Defence warning"), 'default' => 75],
            'to_play' => ['label' => _("Play"), 'default' => 90],
            'to_add' => ['label' => _("Added time before pull"), 'default' => 75],
        ],
    ],
    'halftime' => [
        'label' => _("Halftime"),
        'fields' => [
            'ht_len' => ['label' => _("Halftime duration"), 'default' => 60],
            'ht_warn' => ['label' => _("Warning before end"), 'default' => 30],
        ],
    ],
    'halfstart' => [
        'label' => _("Start of game"),
        'fields' => [
            'hs_lead1' => ['label' => _("First warning before start"), 'default' => 60],
            'hs_lead2' => ['label' => _("Second warning before start"), 'default' => 0],
        ],
    ],
    'dispute' => [
        'label' => _("Call or discussion"),
        'fields' => [
            'dp_first' => ['label' => _("First signal"), 'default' => 45],
            'dp_restart' => ['label' => _("Play must restart"), 'default' => 60],
            'dp_repeat' => ['label' => _("Repeat interval"), 'default' => 15],
        ],
    ],
];

// Flatten the defaults for the client.
$timekeeperDefaults = [];
foreach ($timekeeperGroups as $group) {
    foreach ($group['fields'] as $fieldId => $field) {
        $timekeeperDefaults[$fieldId] = $field['default'];
    }
}

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
    'sc_halftime' => _("Halftime"),
    'sc_dispute' => _("Call or discussion"),
    'sig_off_warn' => _("Offence warning"),
    'sig_def_warn' => _("Defence warning"),
    'sig_play' => _("Play"),
    'sig_end_timeout' => _("Timeout over"),
    'sig_half_warn' => _("Halftime ending"),
    'sig_half_end' => _("Halftime over"),
    'sig_start_warn' => _("Approaching start"),
    'sig_start_go' => _("Start of play"),
    'sig_dispute' => _("Resolve call or discussion"),
    'sig_restart' => _("Play must restart"),
    'ui_pause' => _("Pause"),
    'ui_resume' => _("Resume"),
    'ui_mark' => _("Mark"),
    'ui_start_clock' => _("Start"),
    'ui_resume_clock' => _("Resume"),
    'ui_sound_on' => _("Sound on"),
    'ui_sound_off' => _("Sound off"),
];

echo "<!DOCTYPE html>\n";
echo "<html lang='" . utf8entities($lang) . "'>\n";
echo "<head>\n";
echo "<meta charset='UTF-8'/>\n";
echo "<meta name='viewport' content='width=device-width, initial-scale=1, viewport-fit=cover'/>\n";
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
echo "<p class='mobile-meta'>" . utf8entities(_("Values are in seconds. Defaults follow the WFDF rules.")) . "</p>\n";
echo "<form id='tk-config-form' onsubmit='return false;'>\n";
foreach ($timekeeperGroups as $group) {
    echo "<fieldset class='tk-config-group'>\n";
    echo "<legend>" . utf8entities($group['label']) . "</legend>\n";
    foreach ($group['fields'] as $fieldId => $field) {
        echo "<div class='tk-config-row'>\n";
        echo "<label for='cfg_" . utf8entities($fieldId) . "'>" . utf8entities($field['label']) . "</label>\n";
        echo "<input type='number' inputmode='numeric' min='0' step='1' id='cfg_" . utf8entities($fieldId)
            . "' data-field='" . utf8entities($fieldId) . "' value='" . (int) $field['default'] . "'/>\n";
        echo "<span class='tk-unit'>s</span>\n";
        echo "</div>\n";
    }
    echo "</fieldset>\n";
}
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
$scenarioOrder = ['betweenpoints', 'timeout', 'halfstart', 'halftime', 'dispute'];
foreach ($scenarioOrder as $scenarioId) {
    echo "<button type='button' class='tk-action' data-scenario='" . utf8entities($scenarioId)
        . "' data-role='button'>" . utf8entities($timekeeperGroups[$scenarioId]['label']) . "</button>\n";
}
echo "</div>\n";
echo "</div>\n";

echo "<div class='card'>\n";
echo "<h2>" . utf8entities(_("Game clock")) . "</h2>\n";
echo "<div class='tk-matchclock-body'>\n";
echo "<div class='tk-matchclock-main'>\n";
echo "<div class='tk-matchclock' id='tk-matchclock-time'>0:00</div>\n";
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
echo "var TIMEKEEPER_DEFAULTS = " . json_encode($timekeeperDefaults) . ";\n";
echo "var TIMEKEEPER_I18N = " . json_encode($timekeeperI18n) . ";\n";
echo "</script>\n";
echo "<script src='" . $styles_prefix . "script/timekeeper.js'></script>\n";

echo "</body>\n";
echo "</html>\n";

CloseConnection();
