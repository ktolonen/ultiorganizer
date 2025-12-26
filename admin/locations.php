<?php
include_once __DIR__ . '/auth.php';
include_once 'menufunctions.php';
include_once 'lib/location.functions.php';
include_once 'lib/configuration.functions.php';

$season = 0;
if (!empty($_GET["season"])) {
  $season = $_GET["season"];
}

//process itself on submit
if (!empty($_POST['remove_x']) && !empty($_POST['hiddenDeleteId'])) {
  $id = $_POST['hiddenDeleteId'];
  RemoveLocation($id);
}

//common page
$title = _("Game locations");
$LAYOUT_ID = LOCATIONS;
pageTopHeadOpen($title);
?>
<script type="text/javascript">
  function setId(id) {
    var input = document.getElementById("hiddenDeleteId");

    var answer = confirm('<?php echo _("Are you sure you want to delete the location?"); ?>');
    if (answer) {
      input.value = id;
    } else {
      input.value = "";
    }
  }
</script>
<?php
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();
$html = "";
$html .= "<div id='googleMap' style='width:600px; height: 400px; font-family:Arial,";
$html .= "sans-serif; font-size:11px; border:1px solid black'>";
$html .= "</div>";
$html .= "<form method='post' action='?view=admin/locations'>";

$html .=  "<table style='white-space: nowrap;width:90%' border='0' cellpadding='4px'>\n";
$html .=  "<tr>
	<th>" . _("Name") . "</th>
	<th>" . _("Address") . "</th>
  <th>" . _("Coordinates") . "</th>
	<th>" . _("Operations") . "</th>
  <th></th>
	</tr>\n";
$locations = GetLocations();
foreach ($locations as $place) {
  $html .= "<tr>";
  $html .= "<td>" . utf8entities($place['name']) . "</td>";
  $html .= "<td>" . utf8entities($place['address']) . "</td>";
  $html .= "<td>[" . round($place['lat'], 2) . ", " . round($place['lng'], 2) . "]</td>";
  $html .= "<td><a href='?view=admin/addlocations&location=" . $place['id'] . "'>" . _("edit") . "</a></td>";
  if (CanDeleteLocation($place['id'])) {
    $html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId('" . $place['id'] . "');\"/></td>";
  } else {
    $html .= "<td />";
  }
  $html .= "</tr>\n";
}
$html .=  "</table>";
$html .= "<p><input type='button' onclick=\"window.location.href='?view=admin/addlocations'\" value='" . _('Add') . "'/></p>";
$html .=  "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .=  "</form>";
echo $html;
contentEnd();
?>
<script>
  let map;
  let service;
  let infowindow;
  let geocoder;

  function initMap() {
    const helsinki = new google.maps.LatLng(60.192059, 24.945831);

    infowindow = new google.maps.InfoWindow();
    map = new google.maps.Map(document.getElementById("googleMap"), {
      center: helsinki,
      zoom: 8,
    });
    geocoder = new google.maps.Geocoder();
    $.getJSON('ext/locationjson.php', function(data) {
      $.each(data, function(i, value) {

        var myLatlng = new google.maps.LatLng(value.lat, value.lng);
        var marker = new google.maps.Marker({
          position: myLatlng,
          map: map,
          title: value.name
        });

        const contentString =
          '<div id="content">' +
          '<h1>' + value.name + '</h1>' +
          '<p>' + value.address + '</p>' +
          '<a href=?view=admin/addlocations&season=<?php echo $season ?>&location=' + value.id + '><?php echo _("edit") ?></a>' +
          "</div>";

        const infowindow = new google.maps.InfoWindow({
          content: contentString,
        });

        marker.addListener("click", () => {
          infowindow.open({
            anchor: marker,
            map,
            shouldFocus: true,
          });
        });

      });
    });

  }

  function seachByNameOrAddress() {
    var search = document.getElementById('search').value;

    const request = {
      query: search,
      fields: ["name", "geometry"],
    };

    service = new google.maps.places.PlacesService(map);
    service.findPlaceFromQuery(request, (results, status) => {
      if (status === google.maps.places.PlacesServiceStatus.OK && results) {
        for (let i = 0; i < results.length; i++) {
          createMarker(results[i]);
        }

        map.setCenter(results[0].geometry.location);
      }
    });

    var searchUrl = 'ext/locationxml.php?search=' + search;
    searchLocations(searchUrl);
  }

  function createMarker(place) {
    if (!place.geometry || !place.geometry.location) return;

    const marker = new google.maps.Marker({
      map,
      position: place.geometry.location,
    });

    google.maps.event.addListener(marker, "click", () => {
      infowindow.setContent(place.name || "");
      infowindow.open(map);
    });
  }

  function geocode() {
    var address = "address:'" + document.getElementById('search').value + "'";
    geocoder
      .geocode({
        address
      })
      .then((result) => {
        const {
          results
        } = result;

        map.setCenter(results[0].geometry.location);
        marker = new google.maps.Marker({
          position: results[0].geometry.location,
          map,
          title: document.getElementById('search').value,
        });


        return results;
      })
      .catch((e) => {
        alert("Geocode was not successful for the following reason: " + e);
      });
  }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GetGoogleMapsAPIKey(); ?>&amp;libraries=places&amp;callback=initMap"></script>

<?php
pageEnd();
?>
