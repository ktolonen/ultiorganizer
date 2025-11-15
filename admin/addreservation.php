<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/location.functions.php';

$LAYOUT_ID = ADDRESERVATION;
$addmore = false;
$html = "";
$allfields = "";
$reservationId = 0;
$season = "";

if (isset($_GET['reservation'])) {
  $reservationId = $_GET['reservation'];
}
if (!empty($_GET['season'])) {
  $season = $_GET['season'];
}

//reservation parameters
$res = array(
  "id" => $reservationId,
  "location" => "",
  "fieldname" => "",
  "reservationgroup" => "",
  "date" => "",
  "starttime" => "",
  "endtime" => "",
  "season" => $season,
  "timeslots" => ""
);

if (isset($_POST['save']) || isset($_POST['add'])) {

  $res['id'] = isset($_POST['id']) ? $_POST['id'] : 0;
  $res['location'] = isset($_POST['location']) ? $_POST['location'] : 0;
  $res['fieldname'] = isset($_POST['fieldname']) ? $_POST['fieldname'] : "";
  $res['reservationgroup'] = isset($_POST['reservationgroup']) ? $_POST['reservationgroup'] : "";
  $res['date'] = isset($_POST['date']) ? $_POST['date'] : "1.1.19710";
  $res['starttime'] = isset($_POST['starttime']) ? ToInternalTimeFormat($res['date'] . " " . $_POST['starttime']) : ToInternalTimeFormat("1.1.1971 00:00");
  $res['endtime'] = isset($_POST['endtime']) ? ToInternalTimeFormat($res['date'] . " " . $_POST['endtime']) : ToInternalTimeFormat("1.1.1971 00:00");
  $res['date'] = ToInternalTimeFormat($res['date']);
  $res['timeslots'] = isset($_POST['timeslots']) ? $_POST['timeslots'] : "";
  $res['season'] = isset($_POST['resseason']) ? $_POST['resseason'] : $season;

  if ($res['id'] > 0) {
    SetReservation($res['id'], $res);
  } else {
    //check if adding more than 1 field
    $fields = array();
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
    $locinfo = LocationInfo($res['location']);
    $allfields = $res['fieldname'];
    foreach ($fields as $field) {
      $res['fieldname'] = $field;
      $reservationId = AddReservation($res);
      $displayDate = DefWeekDateFormat($res['starttime'] ?: $res['date']);
      $html .= "<li>" . $res['reservationgroup'] . ": " . $displayDate . " ";
      if (!empty($res['timeslots'])) {
        $html .= $res['timeslots'] . " ";
      } else {
        $html .=  DefHourFormat($res['starttime']) . "-" . DefHourFormat($res['endtime']) . " ";
      }
      $locationName = isset($locinfo['name']) ? $locinfo['name'] : _("Unknown location");
      $html .=  $locationName . " " . _("field") . " " . $field;
      $html .= "</li>";
    }
    $html .= "</ul><hr/>";
    $addmore = true;
  }
}

$title = _("Add field reservation");
//common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete", "calendar"));

?>
<link rel="stylesheet" type="text/css" href="script/yui/calendar/calendar.css" />

<script type="text/javascript">
  YAHOO.namespace("calendar");

  YAHOO.calendar.init = function() {

    YAHOO.calendar.cal1 = new YAHOO.widget.Calendar("cal1", "calContainer1");
    YAHOO.calendar.cal1.cfg.setProperty("START_WEEKDAY", "1");
    YAHOO.calendar.cal1.render();

    function handleCal1Button(e) {
      var containerDiv = YAHOO.util.Dom.get("calContainer1");

      if (containerDiv.style.display == "none") {
        updateCal("date", YAHOO.calendar.cal1);
        YAHOO.calendar.cal1.show();
      } else {
        YAHOO.calendar.cal1.hide();
      }
    }

    // Listener to show the Calendar when the button is clicked
    YAHOO.util.Event.addListener("showcal1", "click", handleCal1Button);
    YAHOO.calendar.cal1.hide();

    function handleSelect1(type, args, obj) {
      var dates = args[0];
      var date = dates[0];
      var year = date[0],
        month = date[1],
        day = date[2];

      var txtDate1 = document.getElementById("date");
      txtDate1.value = day + "." + month + "." + year;
    }

    function updateCal(input, obj) {
      var txtDate1 = document.getElementById(input);
      if (txtDate1.value != "") {
        var date = txtDate1.value.split(".");
        obj.select(date[1] + "/" + date[0] + "/" + date[2]);
        obj.cfg.setProperty("pagedate", date[1] + "/" + date[2]);
        obj.render();
      }
    }
    YAHOO.calendar.cal1.selectEvent.subscribe(handleSelect1, YAHOO.calendar.cal1, true);
  }
  YAHOO.util.Event.onDOMReady(YAHOO.calendar.init);
</script>

<?php
$setFocus = "OnLoad=\"document.getElementById('date').focus();\"";
pageTopHeadClose($title, false, $setFocus);
leftMenu($LAYOUT_ID);
contentStart();
if ($reservationId > 0) {
  $reservationInfo = ReservationInfo($reservationId);
  $res['id'] = $reservationId;
  $res['location'] = $reservationInfo['location'];
  $res['fieldname'] = $reservationInfo['fieldname'];
  $res['reservationgroup'] = $reservationInfo['reservationgroup'];
  $res['date'] = ShortDate($reservationInfo['date']);
  $res['starttime'] = DefHourFormat($reservationInfo['starttime']);
  $res['endtime'] = DefHourFormat($reservationInfo['endtime']);
  $res['season'] = $reservationInfo['season'];
  $res['timeslots'] = $reservationInfo['timeslots'];
  if (!empty($allfields)) {
    $res['fieldname'] = $allfields;
  }
}

echo $html;

$html = "<form method='post' action='?view=admin/addreservation&amp;season=" . $season . "&amp;reservation=" . $res['id'] . "'>\n";
$html .= "<table>\n";

$html .= "<tr><td>" . _("Date") . " (" . _("dd.mm.yyyy") . "):</td><td>";
$html .= "<input type='text' class='input' name='date' id='date' value='" . utf8entities($res['date']) . "'/>&nbsp;\n";
$html .= "<button type='button' class='button' id='showcal1'>
		<img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button></td></tr>\n";
$html .= "<tr><td></td><td><div id='calContainer1'></div></td></tr>\n";

$html .= "<tr><td>" . _("Start time") . " (" . _("hh:mm") . "):</td><td>";
$html .= "<input type='text' class='input' name='starttime' value='" . utf8entities($res['starttime']) . "'/>\n";
$html .= "</td></tr>\n";

$html .= "<tr><td>" . _("End time") . " (" . _("hh:mm") . "):</td><td>";
$html .= "<input type='text' class='input' name='endtime' value='" . utf8entities($res['endtime']) . "'/>\n";
$html .= "</td></tr>\n";

/* Not yet supported
$html .= "<tr><td>"._("Timeslots")." ("._("hh:mm,hh:mm")."):</td><td>";
$html .= "<input type='text' class='input' size='32' maxlength='100' name='timeslots' value='".utf8entities($res['timeslots'])."'/>\n";
$html .= "</td></tr>\n";
*/

$html .= "<tr><td>" . _("Grouping name") . ":</td>";
$html .= "<td>" . TranslatedField("reservationgroup", $res['reservationgroup']) . "</td></tr>\n";
$html .= "<tr><td>" . _("Fields") . ":</td><td>";

$html .= TranslatedField("fieldname", $res['fieldname']);

if (!$addmore) {
  $html .= _("Enter separate field numbers (1,2,3) or multiple fields (1-30)");
}
$html .= "</td></tr>\n";

$html .= "<tr><td>&nbsp;</td><td><div id='locationAutocomplete' class='yui-skin-sam'>";
$html .= "<input class='input' id='locationName' size='30' type='text' style='width:200px' name='locationName' value='";
if ($res['location'] > 0) {
  $location_info = LocationInfo($res['location']);
  $html .= utf8entities($location_info['name']);
}
$html .= "'/><div style='width:400px' id='locationContainer'></div></div>\n";
$html .= "</td></tr>\n";
$html .= "<tr><td>" . _("Location") . ":</td><td>";
$html .= "</td></tr>\n";
$html .= "<tr><td></td><td>&nbsp;</td></tr>\n";
if (isSuperAdmin()) {
  $html .= "<tr><td>" . _("Season") . ":</td><td>";
  $html .= "<select class='dropdown' name='resseason'>\n";
  $html .= "<option class='dropdown' value=''></option>";
  $seasons = Seasons();

  while ($row = mysqli_fetch_assoc($seasons)) {
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
?>
<script type="text/javascript">
  //<![CDATA[
  var locationSelectHandler = function(sType, aArgs) {
    var oData = aArgs[2];
    document.getElementById("location").value = oData[2];
  };

  FetchLocation = function() {
    var locationSource = new YAHOO.util.XHRDataSource("ext/locationtxt.php");
    locationSource.responseSchema = {
      recordDelim: "\n",
      fieldDelim: "\t"
    };
    locationSource.responseType = YAHOO.util.XHRDataSource.TYPE_TEXT;
    locationSource.maxCacheEntries = 60;

    // First AutoComplete
    var locationAutoComp = new YAHOO.widget.AutoComplete("locationName", "locationContainer", locationSource);
    locationAutoComp.formatResult = function(oResultData, sQuery, sResultMatch) {

      // some other piece of data defined by schema 
      var moreData1 = oResultData[1];

      var aMarkup = ["<div class='myCustomResult'>",
        "<span style='font-weight:bold'>",
        sResultMatch,
        "</span>",
        " / ",
        moreData1,
        "</div>"
      ];
      return (aMarkup.join(""));
    };
    locationAutoComp.itemSelectEvent.subscribe(locationSelectHandler);
    return {
      oDS: locationSource,
      oAC: locationAutoComp
    }
  }();
  //]]>
</script>
<?php
echo TranslationScript("reservationgroup");
echo TranslationScript("fieldname");
contentEnd();
pageEnd();
?>
