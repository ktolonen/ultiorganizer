<?php
include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/location.functions.php';
include_once 'lib/configuration.functions.php';

$season = 0;
$locationId = 0;

if (!empty($_GET["season"])) {
  $season = $_GET["season"];
}
if (isset($_GET['location'])) {
  $locationId = (int)$_GET['location'];
}

if (isset($_POST['save']) && isset($_POST['id'])) {
  $id = $_POST['id'];
  $name = _("Not named");
  $address = " ";
  $info = array();
  $fields = "1";
  $indoor = "0";
  $lat = "0";
  $lng = "0";
  if (isset($_POST['name'])) $name = $_POST['name'];
  if (isset($_POST['address'])) $address = $_POST['address'];
  foreach ($locales as $locale => $locname) {
    $locale = str_replace(".", "_", $locale);
    if (isset($_POST['info_' . $locale])) $info[$locale] = $_POST['info_' . $locale];
  }
  if (isset($_POST['fields'])) $fields = $_POST['fields'];
  if (isset($_POST['indoor'])) $indoor = "1";
  if (isset($_POST['lat'])) $lat = $_POST['lat'];
  if (isset($_POST['lng'])) $lng = $_POST['lng'];
  SetLocation($id, $name, $address, $info, $fields, $indoor, $lat, $lng, $season);
}

if (isset($_POST['add'])) {
  $name = _("Not named");
  $address = " ";
  $info = array();
  $fields = "1";
  $indoor = "0";
  $lat = "0";
  $lng = "0";
  if (isset($_POST['name'])) $name = $_POST['name'];
  if (isset($_POST['address'])) $address = $_POST['address'];
  foreach ($locales as $locale => $locname) {
    $locale = str_replace(".", "_", $locale);
    if (isset($_POST['info_' . $locale])) $info[$locale] = $_POST['info_' . $locale];
  }
  if (isset($_POST['fields'])) $fields = $_POST['fields'];
  if (isset($_POST['indoor'])) $indoor = "1";
  if (isset($_POST['lat'])) $lat = $_POST['lat'];
  if (isset($_POST['lng'])) $lng = $_POST['lng'];
  $newId = AddLocation($name, $address, $info, $fields, $indoor, $lat, $lng, $season);
  header('location: ?view=admin/addlocations&location=' . $newId . "&amp;season=" . $season);
  exit();
}

if (isset($_POST['delete']) && isset($_POST['id'])) {
  $id = $_POST['id'];
  RemoveLocation($id);
  header('location: ?view=admin/locations');
  exit();
}

//common page
$title = _("Add game location");
$LAYOUT_ID = ADDLOCATION;
pageTopHeadOpen($title);

include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "datasource", "autocomplete"));
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

if ($locationId > 0) {
  $locationInfo = LocationInfo($locationId);
  $loc['id'] = $locationInfo['id'];
  $loc['name'] = $locationInfo['name'];
  $loc['address'] = $locationInfo['address'];
  $loc['fields'] = $locationInfo['fields'];
  $loc['indoor'] = $locationInfo['indoor'];
  $loc['info'] = $locationInfo['info'];
  $loc['lat'] = $locationInfo['lat'];
  $loc['lng'] = $locationInfo['lng'];
} else {
  $loc['id'] = -1;
  $loc['name'] = "";
  $loc['address'] = "";
  $loc['fields'] = 1;
  $loc['indoor'] = "";
  $loc['info'] = "";
  $loc['lat'] = 0;
  $loc['lng'] = 0;
}
$html = "<div id='editPlace'>";
$html .= "  <form method='post' action='?view=admin/addlocations&season=" . $season . "&location=" . $locationId . "'>";
$html .= "<input type='hidden' name='id' id='id' value='" . (int)($loc['id']) . "'/>";
$html .= "   <table>";
$html .= "      <tbody>";
$html .= "        <tr>";
$html .= "          <th>" . _("Name") . ":</th>";
$html .= "          <td>";
$html .= TranslatedField("name", $loc['name']);
$html .= "          </td>";
$html .= "        </tr>";
$html .= "        <tr>";
$html .= "          <th>" . _("Address") . "</th>";
$html .= "          <td><input type='text' name='address' id='address' size='30' value='" . utf8entities($loc['address']) . "'/>&nbsp;<a href='javascript:geocode();'>" .  _("Fetch coordinates") . "</a></td>";
$html .= "        </tr>";

foreach ($locales as $locale => $name) {
  $locale = str_replace(".", "_", $locale);
  $html .=  "<tr><th>" . _("Info") . " (" . $name . ")";
  $html .= ":</th><td><textarea name='info_" . $locale . "' id='info_" . $locale . "' rows='3' cols='18'>";
  if ($loc['id'] > 0) {
    $html .= utf8entities(LocationInfoText($loc['id'], $locale));
  }
  $html .= "</textarea></td></tr>";
}

$html .= "<tr>";
$html .= "<th>" . _("Fields") . ":</th>";
$html .= "<td><input type='text' name='fields' id='fields' value='" . utf8entities($loc['fields']) . "'/></td>";
$html .= "</tr>";
$html .= "<tr>";
$html .= "<th>" . _("Indoor pitch") . ":</th>";
if (intval($loc['indoor'])) {
  $html .= "<td><input type='checkbox' name='indoor' id='indoor' checked='checked'/></td>";
} else {
  $html .= "<td><input type='checkbox' name='indoor' id='indoor'/></td>";
}

$html .= "</tr>";
$html .= "<tr>";
$html .= "<th>" . _("Longitude") . ":</th>";
$html .= "<td><input type='text' name='lng' id='lng' value='" . utf8entities($loc['lng']) . "' /></td>";
$html .= "</tr>";
$html .= "<tr>";
$html .= "<th>" . _("Latitude") . ":</th>";
$html .= "<td><input type='text' name='lat' id='lat' value='" . utf8entities($loc['lat']) . "' /></td>";
$html .= "</tr>";
$html .= "</tbody>";
$html .= "</table>";
$html .= "<div id='googleMap' style='width: 300px; height: 120px; font-family: Arial, sans-serif; font-size: 11px; border: 1px solid black'></div>";
$html .= "<p>";
if ($locationId > 0) {
  $html .= "<input type='submit' id='save' name='save' value='" . _("Save") . "' />";
} else {
  $html .= "<input type='submit' id='add' name='add' value='" . _("Add") . "' />";
}

$html .= "<input class='button' type='button' name='back'  value='" . _("Return") . "' onclick=\"window.location.href='?view=admin/locations&amp;season=" . $season . "'\"/>";
$html .= "</p>";
$html .= "</form>";
$html .= "</div>";

echo $html;
contentEnd();
?>
<script>
  let map;
  let geocoder;
  let marker;

  function myMap() {

    const field = {
      lat: <?php echo $loc['lat'] . ", lng: " . $loc['lng']; ?>
    };

    map = new google.maps.Map(document.getElementById("googleMap"), {
      zoom: 15,
      center: field,
    });
    geocoder = new google.maps.Geocoder();
    const contentString =
      '<div id="content">' +
      <?php
      echo "'<p>" . utf8entities($loc['name']) . "</p>'+";
      echo "'<p>" . utf8entities($loc['address']) . "</p>'";
      ?> +
      "</div>";
    const infowindow = new google.maps.InfoWindow({
      content: contentString,
    });
    marker = new google.maps.Marker({
      position: field,
      map,
      title: "<?php echo utf8entities($loc['name']); ?>",
    });

    marker.addListener("click", () => {
      infowindow.open({
        anchor: marker,
        map,
        shouldFocus: false,
      });
    });
  }

  function geocode() {
    var address = "address:'" + document.getElementById('address').value + "'";
    geocoder
      .geocode({
        address
      })
      .then((result) => {
        const {
          results
        } = result;

        document.getElementById('lat').value = results[0].geometry.location.lat();
        document.getElementById('lng').value = results[0].geometry.location.lng();

        map.setCenter(results[0].geometry.location);
        marker.setPosition(results[0].geometry.location);
        marker.setMap(map);

        return results;
      })
      .catch((e) => {
        alert("Geocode was not successful for the following reason: " + e);
      });
  }
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GetGoogleMapsAPIKey(); ?>&callback=myMap"></script>
<?php
pageEnd();
?>