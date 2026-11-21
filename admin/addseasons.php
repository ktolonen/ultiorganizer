<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/configuration.functions.php';

$LAYOUT_ID = SEASONS;
$seasonId = "";
$html = "";
$backurl = utf8entities(empty($_SERVER['HTTP_REFERER']) ? "" : $_SERVER['HTTP_REFERER']);

//season parameters
$sp = [
    "season_id" => "",
    "name" => "",
    "type" => "",
    "starttime" => "",
    "istournament" => 0,
    "isinternational" => 0,
    "organizer" => "",
    "category" => "",
    "isnationalteams" => 0,
    "endtime" => "",
    "spiritmode" => 0,
    "showspiritpoints" => 0,
    "showspiritcomments" => 0,
    "showspiritpointsonlyoncomplete" => 1,
    "lockteamspiritonsubmit" => 1,
    "use_season_points" => 0,
    "hide_time_on_scoresheet" => 0,
    "hometeammode" => 0,
    "event_readonly" => 0,
    "maintenance_mode" => 0,
    "public_event" => 0,
    "api_public" => 0,
    "showgamecomments" => 0,
    "anonymous_scorekeeping" => 0,
    "scorekeeping_app" => 0,
    "require_accreditation" => 0,
    "iscurrent" => 0,
    "enrollopen" => 0,
    "enroll_deadline" => "",
    "timezone" => GetDefTimeZone(),
];

if (!empty($_GET["season"])) {
    $seasonId = $_GET["season"];
}

// Shared by add and save: start is required, an empty end falls back to the start.
$checkDates = function (array &$sp): string {
    if (isEmptyDate($sp['starttime'])) {
        return "<p class='warning'>" . _("Enter a valid start date.") . "</p>";
    }
    if (isEmptyDate($sp['endtime'])) {
        $sp['endtime'] = $sp['starttime'];
    }
    if (strtotime($sp['endtime']) < strtotime($sp['starttime'])) {
        return "<p class='warning'>" . _("The end date cannot be before the start date.") . "</p>";
    }
    return "";
};

//process itself on submit
if (!empty($_POST['add'])) {
    $backurl = utf8entities($_POST['backurl']);
    $sp['season_id'] = $_POST['season_id'];
    $sp['name'] = $_POST['seasonname'];
    $sp['type'] = $_POST['type'];
    $sp['isinternational'] = !empty($_POST['isinternational']);
    $sp['organizer'] = $_POST['organizer'];
    $sp['category'] = $_POST['category'];
    $sp['isnationalteams'] = !empty($_POST['isnationalteams']);
    $sp['timezone'] = $_POST['timezone'];
    $sp['starttime'] = ToInternalTimeFormat($_POST['seasonstarttime']);
    $sp['endtime'] = ToInternalTimeFormat($_POST['seasonendtime']);
    $sp['enrollopen'] = !empty($_POST['enrollopen']);
    $sp['enroll_deadline'] = isset($_POST['enrollendtime']) ? ToInternalTimeFormat($_POST['enrollendtime']) : ToInternalTimeFormat($_POST['seasonstarttime']);
    $sp['iscurrent'] = !empty($_POST['iscurrent']);
    $sp['api_public'] = !empty($_POST['api_public']);
    $sp['spiritmode'] = $_POST['spiritmode'];
    $sp['use_season_points'] = !empty($_POST['use_season_points']);
    $sp['hide_time_on_scoresheet'] = !empty($_POST['hide_time_on_scoresheet']);
    $sp['hometeammode'] = isset($_POST['hometeammode']) ? (int) $_POST['hometeammode'] : 0;
    $sp['event_readonly'] = !empty($_POST['event_readonly']);
    $sp['maintenance_mode'] = !empty($_POST['maintenance_mode']);
    $sp['public_event'] = !empty($_POST['public_event']);
    $sp['showgamecomments'] = !empty($_POST['showgamecomments']);
    $sp['anonymous_scorekeeping'] = !empty($_POST['anonymous_scorekeeping']);
    $sp['scorekeeping_app'] = max(SCOREKEEPING_APP_OFF, min(SCOREKEEPING_APP_USERS, (int) ($_POST['scorekeeping_app'] ?? 0)));
    $sp['require_accreditation'] = !empty($_POST['require_accreditation']);
    $comment = $_POST['comment'];

    if (empty($_POST['season_id'])) {
        $html .= "<p class='warning'>" . _("Event ID cannot be empty") . ".</p>";
    } elseif (!preg_match('/^[\p{L}\p{N}._-]+$/u', $_POST['season_id'])) {
        $html .= "<p class='warning'>" . _("Event ID may not have spaces or special characters") . ".</p>";
    } elseif (mb_strlen($_POST['season_id']) > 10) {
        $html .= "<p class='warning'>" . sprintf(_("Event ID can be at most %d characters"), 10) . ".</p>";
    } elseif (SeasonExists($sp['season_id'])) {
        $html .= "<p class='warning'>" . _("Event ID already exists") . ".</p>";
    } elseif (empty($_POST['seasonname'])) {
        $html .= "<p class='warning'>" . _("Name cannot be empty") . ".</p>";
    } elseif (empty($_POST['type'])) {
        $html .= "<p class='warning'>" . _("Type cannot be empty") . ".</p>";
    } elseif (($dateError = $checkDates($sp)) !== "") {
        $html .= $dateError;
    } else {
        if (AddSeason($sp['season_id'], $sp, $comment)) {
            $seasonId = $sp['season_id'];
            //add rights for season creator
            AddEditSeason($_SESSION['uid'], $sp['season_id']);
            AddUserRole($_SESSION['uid'], 'seasonadmin:' . $sp['season_id']);

            $_SESSION['title'] = _("New event added") . ":";
            /* FIXME Does anybody need this? I don't get it ... */
            $_SESSION["var0"] = _("Name") . ": " . utf8entities($sp['name']);
            $_SESSION["var1"] = _("Type") . ": " . utf8entities($sp['type']);
            $_SESSION["var2"] = _("Starts") . ": " . ShortDate($sp['starttime']);
            $_SESSION["var3"] = _("Ends") . ": " . ShortDate($sp['endtime']);
            $_SESSION["var4"] = _("Enrollment open") . ": " . (intval($sp['enrollopen']) ? _("yes") : _("no"));
            $_SESSION['backurl'] = "?view=admin/seasons";
            session_write_close();
            header("location:?view=admin/seasonadmin&season=$seasonId");
        } else {
            $html .= "<p class='warning'>" . _("Event ID already exists") . ".</p>";
        }
    }
} elseif (!empty($_POST['save'])) {
    $backurl = utf8entities($_POST['backurl']);
    if (empty($_POST['seasonname'])) {
        $html .= "<p class='warning'>" . _("Name cannot be empty") . ".</p>";
    } else {
        $existing = SeasonInfo($seasonId);
        $sp['season_id'] = $seasonId;
        $sp['name'] = $_POST['seasonname'];
        $sp['type'] = $_POST['type'];
        $sp['istournament'] = (int) $existing['istournament'];
        $sp['isinternational'] = !empty($_POST['isinternational']);
        $sp['isnationalteams'] = !empty($_POST['isnationalteams']);
        $sp['organizer'] = $_POST['organizer'];
        $sp['category'] = $_POST['category'];
        $sp['starttime'] = ToInternalTimeFormat($_POST['seasonstarttime']);
        $sp['endtime'] = ToInternalTimeFormat($_POST['seasonendtime']);
        $sp['enrollopen'] = !empty($_POST['enrollopen']);
        $sp['enroll_deadline'] = ToInternalTimeFormat($_POST['enrollendtime']);
        $sp['iscurrent'] = !empty($_POST['iscurrent']);
        $sp['api_public'] = !empty($_POST['api_public']);
        $sp['spiritmode'] = $_POST['spiritmode'];
        $sp['showspiritpoints'] = isset($existing['showspiritpoints']) ? (int) $existing['showspiritpoints'] : 0;
        $sp['showspiritcomments'] = isset($existing['showspiritcomments']) ? (int) $existing['showspiritcomments'] : 0;
        $sp['showspiritpointsonlyoncomplete'] = isset($existing['showspiritpointsonlyoncomplete']) ? (int) $existing['showspiritpointsonlyoncomplete'] : 1;
        $sp['lockteamspiritonsubmit'] = isset($existing['lockteamspiritonsubmit']) ? (int) $existing['lockteamspiritonsubmit'] : 1;
        $sp['use_season_points'] = !empty($_POST['use_season_points']);
        $sp['hide_time_on_scoresheet'] = !empty($_POST['hide_time_on_scoresheet']);
        $sp['hometeammode'] = isset($_POST['hometeammode']) ? (int) $_POST['hometeammode'] : 0;
        $sp['event_readonly'] = !empty($_POST['event_readonly']);
        $sp['maintenance_mode'] = !empty($_POST['maintenance_mode']);
        $sp['public_event'] = !empty($_POST['public_event']);
        $sp['showgamecomments'] = !empty($_POST['showgamecomments']);
        $sp['anonymous_scorekeeping'] = !empty($_POST['anonymous_scorekeeping']);
        $sp['scorekeeping_app'] = max(SCOREKEEPING_APP_OFF, min(SCOREKEEPING_APP_USERS, (int) ($_POST['scorekeeping_app'] ?? 0)));
        $sp['require_accreditation'] = !empty($_POST['require_accreditation']);
        $sp['timezone'] = $_POST['timezone'];
        $comment = $_POST['comment'];
        $dateError = $checkDates($sp);
        if ($dateError !== "") {
            $html .= $dateError;
        } else {
            SetSeason($sp['season_id'], $sp, $comment);
        }
    }
}

$title = _("Edit event");
if (strlen($sp['name']) > 0) {
    $title .= ": " . $sp['name'];
}

if ($seasonId) {
    $info = SeasonInfo($seasonId);
    $sp['season_id'] = $info['season_id'];
    $sp['name'] = $info['name'];
    $sp['type'] = $info['type'];
    $sp['starttime'] = $info['starttime'];
    $sp['endtime'] = $info['endtime'];
    $sp['iscurrent'] = $info['iscurrent'];
    $sp['api_public'] = isset($info['api_public']) ? $info['api_public'] : 0;
    $sp['enrollopen']  = $info['enrollopen'];
    $sp['enroll_deadline'] = $info['enroll_deadline'];
    $sp['istournament'] = $info['istournament'];
    $sp['isinternational'] = $info['isinternational'];
    $sp['organizer'] = $info['organizer'];
    $sp['category'] = $info['category'];
    $sp['isnationalteams'] = $info['isnationalteams'];
    $sp['spiritmode'] = isset($info['spiritmode']) ? $info['spiritmode'] : 0;
    $sp['showspiritpoints'] = $info['showspiritpoints'];
    $sp['showspiritcomments'] = isset($info['showspiritcomments']) ? $info['showspiritcomments'] : 0;
    $sp['showspiritpointsonlyoncomplete'] = isset($info['showspiritpointsonlyoncomplete']) ? $info['showspiritpointsonlyoncomplete'] : 1;
    $sp['lockteamspiritonsubmit'] = isset($info['lockteamspiritonsubmit']) ? $info['lockteamspiritonsubmit'] : 1;
    $sp['use_season_points'] = isset($info['use_season_points']) ? $info['use_season_points'] : 0;
    $sp['hide_time_on_scoresheet'] = isset($info['hide_time_on_scoresheet']) ? $info['hide_time_on_scoresheet'] : 0;
    $sp['hometeammode'] = isset($info['hometeammode']) ? $info['hometeammode'] : 0;
    $sp['event_readonly'] = isset($info['event_readonly']) ? $info['event_readonly'] : 0;
    $sp['maintenance_mode'] = isset($info['maintenance_mode']) ? $info['maintenance_mode'] : 0;
    $sp['public_event'] = isset($info['public_event']) ? $info['public_event'] : 0;
    $sp['showgamecomments'] = isset($info['showgamecomments']) ? $info['showgamecomments'] : 0;
    $sp['anonymous_scorekeeping'] = isset($info['anonymous_scorekeeping']) ? $info['anonymous_scorekeeping'] : 0;
    $sp['scorekeeping_app'] = isset($info['scorekeeping_app']) ? (int) $info['scorekeeping_app'] : 0;
    $sp['require_accreditation'] = isset($info['require_accreditation']) ? $info['require_accreditation'] : 0;
    $sp['timezone'] = $info['timezone'];
    $comment = CommentRaw(1, $info['season_id']);
} else {
    $comment = "";
}

//common page
pageTopHeadOpen($title);
echo "<script type='text/javascript' src='script/translation-autocomplete.js'></script>";
include 'script/rescalendar.inc';

pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

if (empty($seasonId)) {
    $html .= "<h2>" . _("Create new event") . "</h2>\n";
    $html .= "<form method='post' action='?view=admin/addseasons'>";
    $disabled = "";
} else {
    $html .= "<form method='post' action='?view=admin/addseasons&amp;season=$seasonId'>";
    $disabled = "disabled='disabled'";
}

$dateField = function (string $id, ?string $value, string $calId, string $fallback = ""): string {
    $fb = $fallback !== "" ? " data-calendar-fallback='" . $fallback . "'" : "";
    return "<input class='input' size='12' maxlength='10' id='" . $id . "' data-calendar-button='" . $calId . "'" . $fb . " name='" . $id . "' value='" . (isEmptyDate($value) ? "" : ShortDate($value)) . "'/>&nbsp;"
        . "<button type='button' class='button' id='" . $calId . "'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button>";
};

$html .= "<div class='settings-form'>\n";

// Basics
$html .= "<fieldset class='settings-group'><legend>" . _("Event") . "</legend>\n";
$html .= SettingsRow(_("Event ID"), "<input class='input' id='season_id' name='season_id' maxlength='10' $disabled value='" . utf8entities($sp['season_id']) . "'/>", _("Used in page addresses. Cannot be changed later."), "season_id");
$html .= SettingsRow(_("Name"), TranslatedField("seasonname", $sp['name'], "340"));
$typeSelect = "<select class='dropdown' id='type' name='type'>\n";
foreach (SeasonTypes() as $type) {
    $sel = ($sp['type'] == $type) ? " selected='selected'" : "";
    $typeSelect .= "<option class='dropdown'$sel value='" . utf8entities($type) . "'>" . U_($type) . "</option>\n";
}
$typeSelect .= "</select>";
$html .= SettingsRow(_("Type"), $typeSelect, "", "type");
$html .= SettingsRow(_("Organizer"), "<input class='input' size='50' maxlength='50' id='organizer' name='organizer' value='" . utf8entities($sp['organizer']) . "'/>", "", "organizer");
$html .= SettingsRow(_("Category"), "<input class='input' size='50' maxlength='50' id='category' name='category' value='" . utf8entities($sp['category']) . "'/>", "", "category");
$html .= SettingsCheckRow('isinternational', _("International"), !empty($sp['isinternational']), _("Shows each team's country and flag. Teams choose their country when they enroll."));
$html .= SettingsCheckRow('isnationalteams', _("For national teams"), !empty($sp['isnationalteams']), _("Teams represent countries instead of clubs. Club fields are hidden."));
$html .= SettingsRow(
    _("Comment"),
    "<textarea class='input' rows='6' id='comment' name='comment'>" . htmlentities($comment) . "</textarea>",
    utf8entities(_("Shown on the event schedule page. You can use <b>, <em>, and <br /> tags.")),
    "comment",
);
$html .= "</fieldset>\n";

// Schedule
$tzSelect = "<select class='dropdown' id='timezone' name='timezone'>\n<option value=''></option>\n";
foreach (GetTimeZoneArray() as $tz) {
    $sel = ($sp['timezone'] == $tz) ? " selected='selected'" : "";
    $tzSelect .= "<option$sel value='" . utf8entities($tz) . "'>" . utf8entities($tz) . "</option>\n";
}
$tzSelect .= "</select>";
$html .= "<fieldset class='settings-group'><legend>" . _("Schedule") . "</legend>\n";
$html .= SettingsRow(_("Timezone"), $tzSelect, "", "timezone");
$html .= SettingsRow(_("Starts") . " (" . _("dd.mm.yyyy") . ")", $dateField('seasonstarttime', $sp['starttime'], 'showcal1'), "", "seasonstarttime");
$html .= SettingsRow(_("Ends") . " (" . _("dd.mm.yyyy") . ")", $dateField('seasonendtime', $sp['endtime'], 'showcal2', 'seasonstarttime'), "", "seasonendtime");
$html .= "</fieldset>\n";

// Enrollment
$html .= "<fieldset class='settings-group'><legend>" . _("Enrollment") . "</legend>\n";
$html .= SettingsCheckRow('enrollopen', _("Open for enrollment"), !empty($sp['enrollopen']), _("Logged-in users can enroll teams. An event admin accepts or rejects each enrollment."));
$html .= SettingsRow(_("Enrolling ends"), $dateField('enrollendtime', $sp['enroll_deadline'], 'showcal3', 'seasonstarttime'), _("Shown to teams. Enrollment does not close automatically."), "enrollendtime");
$html .= "</fieldset>\n";

// Visibility and access
$html .= "<fieldset class='settings-group'><legend>" . _("Visibility") . "</legend>\n";
$html .= SettingsCheckRow('iscurrent', _("Shown in main menu"), !empty($sp['iscurrent']), _("Lists the event in the main menu. Does not affect who can view it."));
$html .= SettingsCheckRow('public_event', _("Public event"), !empty($sp['public_event']), _("Anyone can view the event's pages without logging in. When off, only users with a role in this event and system administrators can see them."));
$html .= SettingsCheckRow('api_public', _("Visible in public API and external links"), !empty($sp['api_public']), _("Other websites and apps can read the event's data through the API, widgets, feeds, and CSV exports. Requires Public event."));
$html .= SettingsCheckRow('showgamecomments', _("Show game notes on public pages"), !empty($sp['showgamecomments']), _("Everyone can read game notes. Event admins and spirit directors always see them."));
$html .= SettingsCheckRow('maintenance_mode', _("Soft maintenance mode"), !empty($sp['maintenance_mode']), _("Public pages show a maintenance notice. Admins can still use all pages."));
$html .= SettingsCheckRow('event_readonly', _("Read-only event"), !empty($sp['event_readonly']), _("Locks the event, for example after it ends. Only system administrators can make changes."));
$html .= "</fieldset>\n";

// Games and scorekeeping
$homeSelect = "<select class='dropdown' id='hometeammode' name='hometeammode'>\n";
$homeSelect .= "<option value='0'" . ((int) $sp['hometeammode'] === 0 ? " selected='selected'" : "") . ">" . _("Balance home team equally") . "</option>\n";
$homeSelect .= "<option value='1'" . ((int) $sp['hometeammode'] === 1 ? " selected='selected'" : "") . ">" . _("Higher rank is home team") . "</option>\n";
$homeSelect .= "</select>";
$html .= "<fieldset class='settings-group'><legend>" . _("Games") . "</legend>\n";
$html .= SettingsRow(_("Home team assignment"), $homeSelect, _("Applies to games created from pools. Existing games are not changed."), "hometeammode");
$appSelect = "<select class='dropdown' id='scorekeeping_app' name='scorekeeping_app'>\n";
foreach ([SCOREKEEPING_APP_OFF => _("Not in use"), SCOREKEEPING_APP_ADMINS => _("Event admins"), SCOREKEEPING_APP_USERS => _("All logged-in users")] as $mode => $label) {
    $appSelect .= "<option value='" . $mode . "'" . ((int) $sp['scorekeeping_app'] === $mode ? " selected='selected'" : "") . ">" . utf8entities($label) . "</option>\n";
}
$appSelect .= "</select>";
$appLabel = _("Scorekeeping app");
if (empty($sp['event_readonly']) && !IsSelfRegistrationDisabled() && (int) $sp['scorekeeping_app'] === SCOREKEEPING_APP_USERS) {
    $warning = utf8entities(_("Anyone can register and change results using the scorekeeping app."));
    $appLabel .= " <button type='button' class='warning warning-icon' popovertarget='scorekeeping-app-warning' aria-label='" . $warning . "'>&#9888;</button>";
    $appSelect .= "<span id='scorekeeping-app-warning' class='warning-tip' popover>" . $warning . "</span>";
}
$html .= SettingsRow($appLabel, $appSelect, _("Who can set up the scorekeeping app and keep score with it in this event."), "scorekeeping_app");
$linkWarning = "";
if (empty($sp['event_readonly']) && !empty($sp['anonymous_scorekeeping'])) {
    $warning = utf8entities(_("Anyone with a scorekeeping link can change results without logging in."));
    $linkWarning = " <button type='button' class='warning warning-icon' popovertarget='scorekeeping-links-warning' aria-label='" . $warning . "'>&#9888;</button>";
    $linkWarning .= "<span id='scorekeeping-links-warning' class='warning-tip' popover>" . $warning . "</span>";
}
$html .= SettingsCheckRow('anonymous_scorekeeping', _("Scorekeeping links work without logging in") . $linkWarning, !empty($sp['anonymous_scorekeeping']), _("Anyone with a game or field scorekeeping link can record scores. Share the links only with your scorekeepers."));
$html .= SettingsCheckRow('hide_time_on_scoresheet', _("Hide time on scoresheet"), !empty($sp['hide_time_on_scoresheet']), _("Goals are recorded and shown without times."));
$html .= SettingsCheckRow('require_accreditation', _("Require accreditation"), !empty($sp['require_accreditation']), _("Only accredited players can be added to game rosters."));
$html .= SettingsCheckRow('use_season_points', _("Season points"), !empty($sp['use_season_points']), _("Admins enter points per team and round. Totals form a separate ranking on the Points tab."));
$html .= "</fieldset>\n";

// Spirit
$spiritSelect = "<select class='dropdown' id='spiritmode' name='spiritmode'>\n";
$spiritSelect .= "<option value='0'" . ((int) $sp['spiritmode'] === 0 ? " selected='selected'" : "") . ">" . utf8entities(SpiritModeDisabledName()) . "</option>\n";
foreach (SpiritCategoryModeRows() as $mode) {
    $sel = ($sp['spiritmode'] == $mode['mode']) ? " selected='selected'" : "";
    $spiritSelect .= "<option$sel value='" . utf8entities($mode['mode']) . "'>" . utf8entities(_($mode['name'])) . "</option>\n";
}
$spiritSelect .= "</select>";
$spiritHint = "";
if (!empty($seasonId) && (int) $sp['spiritmode'] > 0) {
    $spiritHint = "<a href='?view=admin/spiritsettings&amp;season=" . urlencode($seasonId) . "'>" . _("Open Spirit Settings") . "</a>";
}
$html .= "<fieldset class='settings-group'><legend>" . _("Spirit") . "</legend>\n";
$html .= SettingsRow(_("Spirit mode"), $spiritSelect, $spiritHint, "spiritmode");
$html .= "</fieldset>\n";

$html .= "</div>\n";
if (empty($seasonId)) {
    $html .= "<p><input class='button' type='submit' name='add' value='" . _("Add") . "' />";
} else {
    $html .= "<p><input class='button' type='submit' name='save' value='" . _("Save") . "' />";
}
$html .= "<input type='hidden' name='backurl' value='$backurl'/>";
$html .= "<input class='button' type='button' value='" . _("Return") . "' data-href='$backurl' onclick='navigateBack(this)' /></p>";
$html .= "</form>\n";

echo $html;
echo TranslationScript("seasonname");

contentEnd();
pageEnd();
