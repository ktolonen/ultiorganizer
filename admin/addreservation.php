<?php

include_once __DIR__ . '/auth.php';
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';

$LAYOUT_ID = ADDRESERVATION;
$addmore = false;
$keepInput = false;
$html = "";
$allfields = "";
$reservationId = 0;
$season = "";

if (isset($_GET['reservation'])) {
    $reservationId = (int) $_GET['reservation'];
}
if (!empty($_GET['season'])) {
    $season = $_GET['season'];
}

//reservation parameters
$res = [
    "id" => $reservationId,
    "location" => "",
    "locationName" => "",
    "fieldname" => "",
    "reservationgroup" => "",
    "date" => "",
    "starttime" => "",
    "endtime" => "",
    "season" => $season,
];

if (isset($_POST['save']) || isset($_POST['add'])) {

    $res['id'] = isset($_POST['id']) ? $_POST['id'] : 0;
    $res['location'] = isset($_POST['location']) ? trim((string) $_POST['location']) : "";
    $res['locationName'] = isset($_POST['locationName']) ? $_POST['locationName'] : "";
    $res['fieldname'] = isset($_POST['fieldname']) ? $_POST['fieldname'] : "";
    $res['reservationgroup'] = isset($_POST['reservationgroup']) ? $_POST['reservationgroup'] : "";
    $res['date'] = isset($_POST['date']) ? trim((string) $_POST['date']) : "";
    $res['starttime'] = isset($_POST['starttime']) ? trim((string) $_POST['starttime']) : "";
    $res['endtime'] = isset($_POST['endtime']) ? trim((string) $_POST['endtime']) : "";
    $res['season'] = isset($_POST['resseason']) ? $_POST['resseason'] : $season;

    // ToInternalTimeFormat() silently falls back to 1.1.1971 00:00, so reject input it cannot parse in full.
    $isValidDateTime = static function ($date, $time) {
        $parsed = uo_strptime($date . " " . $time, DATE_FORMAT);
        return $parsed !== false && $parsed['unparsed'] === ""
            && checkdate($parsed['tm_mon'] + 1, $parsed['tm_mday'], $parsed['tm_year'] + 1900);
    };
    $inputErrors = [];
    if (!$isValidDateTime($res['date'], "00:00")) {
        $inputErrors[] = _("The date is not in a valid format.");
    } else {
        if (!$isValidDateTime($res['date'], $res['starttime'])) {
            $inputErrors[] = _("The start time is not in a valid format.");
        }
        if (!$isValidDateTime($res['date'], $res['endtime'])) {
            $inputErrors[] = _("The end time is not in a valid format.");
        }
    }

    $locationInfo = ((int) $res['location'] > 0) ? LocationInfo($res['location']) : null;
    if (!empty($inputErrors)) {
        foreach ($inputErrors as $inputError) {
            $html .= "<p class='warning'>" . utf8entities($inputError) . "</p>";
        }
        $keepInput = true;
    } elseif ((int) $res['location'] > 0 && empty($locationInfo)) {
        $html .= "<p>" . _("Select a valid location from the list or add it first.") . "</p>";
        $keepInput = true;
    } else {
        $res['starttime'] = ToInternalTimeFormat($res['date'] . " " . $res['starttime']);
        $res['endtime'] = ToInternalTimeFormat($res['date'] . " " . $res['endtime']);
        $res['date'] = ToInternalTimeFormat($res['date']);
    }

    if (!$keepInput && $res['id'] > 0) {
        SetReservation($res['id'], $res);
    } elseif (!$keepInput) {
        //check if adding more than 1 field
        $fields = [];
        $tmpfields = explode(",", $res['fieldname']);
        foreach ($tmpfields as $field) {
            $morefields = explode("-", $field);
            if (count($morefields) > 1) {
                for ($i = $morefields[0]; $i <= $morefields[1]; $i++) {
                    $fields[] = $i;
                }
            } else {
                $fields[] = $morefields[0];
            }
        }
        if (count($fields) == 0) {
            $fields[] = $res['fieldname'];
        }
        $i = 0;
        $html .= "<p>" . _("Reservations added") . ":</p>";
        $html .= "<ul>";
        $locinfo = $locationInfo;
        $allfields = $res['fieldname'];
        foreach ($fields as $field) {
            $res['fieldname'] = $field;
            $reservationId = AddReservation($res);
            $displayDate = DefWeekDateFormat($res['starttime'] ?: $res['date']);
            $html .= "<li>" . $res['reservationgroup'] . ": " . $displayDate . " ";
            $html .=  DefHourFormat($res['starttime']) . "-" . DefHourFormat($res['endtime']) . " ";
            $html .= utf8entities(ReservationPlaceText(isset($locinfo['name']) ? $locinfo['name'] : "", $field));
            $html .= "</li>";
        }
        $html .= "</ul><hr/>";
        $addmore = true;
    }
}

$title = _("Add field reservation");
//common page
pageTopHeadOpen($title);
echo "<script type='text/javascript' src='script/location-autocomplete.js'></script>";
echo "<link rel='stylesheet' type='text/css' href='script/location-autocomplete.css'/>";
echo "<script type='text/javascript' src='script/translation-autocomplete.js'></script>";

include 'script/rescalendar.inc';


$setFocus = "OnLoad=\"document.getElementById('date').focus();\"";
pageTopHeadClose($title, false, $setFocus);
leftMenu($LAYOUT_ID);
contentStart();
if ($reservationId > 0 && !$keepInput) {
    $reservationInfo = ReservationInfo($reservationId);
    $res['id'] = $reservationId;
    $res['location'] = $reservationInfo['location'];
    $res['fieldname'] = $reservationInfo['fieldname'];
    $res['reservationgroup'] = $reservationInfo['reservationgroup'];
    $res['date'] = ShortDate($reservationInfo['date']);
    $res['starttime'] = DefHourFormat($reservationInfo['starttime']);
    $res['endtime'] = DefHourFormat($reservationInfo['endtime']);
    $res['season'] = $reservationInfo['season'];
    $res['locationName'] = isset($reservationInfo['name']) ? $reservationInfo['name'] : "";
    if (!empty($allfields)) {
        $res['fieldname'] = $allfields;
    }
}

echo $html;

$html = "<form method='post' action='?view=admin/addreservation&amp;season=" . $season . "&amp;reservation=" . $res['id'] . "'>\n";
$html .= "<table>\n";

$html .= "<tr><td>" . _("Date") . " (" . _("dd.mm.yyyy") . "):</td><td>";
$html .= "<input type='text' class='input' name='date' id='date' data-calendar-button='showcal1' value='" . utf8entities($res['date']) . "'/>&nbsp;\n";
$html .= "<button type='button' class='button' id='showcal1'>
		<img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button></td></tr>\n";

$html .= "<tr><td>" . _("Start time") . " (" . _("hh:mm") . "):</td><td>";
$html .= "<input type='text' class='input' name='starttime' value='" . utf8entities($res['starttime']) . "'/>\n";
$html .= "</td></tr>\n";

$html .= "<tr><td>" . _("End time") . " (" . _("hh:mm") . "):</td><td>";
$html .= "<input type='text' class='input' name='endtime' value='" . utf8entities($res['endtime']) . "'/>\n";
$html .= "</td></tr>\n";

$html .= "<tr><td>" . _("Grouping name") . ":</td>";
$html .= "<td>" . TranslatedField("reservationgroup", $res['reservationgroup']) . "</td></tr>\n";
$html .= "<tr><td>" . _("Fields") . ":</td><td>";

$html .= TranslatedField("fieldname", $res['fieldname']);

if (!$addmore) {
    $html .= _("Enter separate field numbers (1,2,3) or multiple fields (1-30)");
}
$html .= "</td></tr>\n";

$html .= "<tr><td>&nbsp;</td><td><div id='locationAutocomplete'>";
$html .= "<input class='input' id='locationName' size='30' type='text' style='width:200px' name='locationName' value='";
$locationInputValue = $res['locationName'];
if (empty($locationInputValue) && $res['location'] > 0) {
    $location_info = LocationInfo($res['location']);
    $locationInputValue = isset($location_info['name']) ? $location_info['name'] : "";
}
$html .= utf8entities($locationInputValue);
$html .= "' aria-label='" . utf8entities(_("Location")) . "'/></div>\n";
$html .= "</td></tr>\n";
$html .= "<tr><td>" . _("Location") . ":</td><td>";
$html .= "</td></tr>\n";
$html .= "<tr><td></td><td>&nbsp;</td></tr>\n";
if (isSuperAdmin()) {
    $html .= "<tr><td>" . _("Event") . ":</td><td>";
    $html .= "<select class='dropdown' name='resseason'>\n";
    $html .= "<option class='dropdown' value=''></option>";
    $seasons = Seasons();

    foreach ($seasons as $row) {
        if ($res['season'] == $row['season_id'] || $season == $row['season_id']) {
            $html .= "<option class='dropdown' selected='selected' value='" . utf8entities($row['season_id']) . "'>" . utf8entities($row['name']) . "</option>";
        } else {
            $html .= "<option class='dropdown' value='" . utf8entities($row['season_id']) . "'>" . utf8entities($row['name']) . "</option>";
        }
    }
    $html .= "</select></p>\n";
    $html .= "</td></tr>\n";
}

$html .= "<tr><td>";

$html .= "<input type='hidden'  name='location' id='location' value='" . utf8entities($res['location']) . "'/>";

if (!$addmore) {
    $html .= "<input type='hidden' name='id' value='" . utf8entities($res['id']) . "'/>";
    $html .= "<input type='submit' class='button' name='save' value='" . _("Save") . "'/>";
} else {
    $html .= "<input type='submit' class='button' name='add' value='" . _("Add") . "'/>";
}
$html .= "<input class='button' type='button' name='back'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/reservations&amp;season=" . $season . "'\"/>";
$html .= "</td><td>&nbsp;</td></tr>\n";
$html .= "</table>\n";
$html .= "</form>";
echo $html;
echo "<script type='text/javascript'>attachLocationSuggestions();</script>";
echo TranslationScript("reservationgroup");
echo TranslationScript("fieldname");
contentEnd();
pageEnd();
