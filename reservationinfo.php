<?php
require_once __DIR__ . '/lib/view.guard.php';
requireRoutedView('reservationinfo');

include_once 'lib/reservation.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/configuration.functions.php';

$reservationId = intval(iget("reservation"));
$place = ReservationInfo($reservationId);
$title = _("Reservation");

if (!$place) {
    pageTopHeadOpen($title);
    pageTopHeadClose($title, false);
    leftMenu();
    contentStart();
    echo "<h1>" . _("Reservation not found") . "</h1>\n";
    contentEnd();
    pageEnd();
    return;
}
$placeLabel = ReservationPlaceText($place['name'], $place['fieldname']);
$title = _("Reservation");
$headingText = _("Reservation");
if ($placeLabel !== '') {
    $title .= ": " . utf8entities($placeLabel);
    $headingText .= ": " . $placeLabel;
}
$hasCoordinates = $place['lat'] !== null && $place['lat'] !== '' && $place['lng'] !== null && $place['lng'] !== '';

//common page
pageTopHeadOpen($title);
$pageAttributes = $hasCoordinates ? "onload=\"load()\" onunload=\"GUnload()\"" : "";
pageTopHeadClose($title, false, $pageAttributes);
leftMenu();
contentStart();
echo "<h1>" . utf8entities($headingText) . "</h1>\n";
echo "<p>" . DefTimeFormat($place['starttime']) . " - " . DefHourFormat($place['endtime']) . "</p>\n";
if (!empty($place['address'])) {
    echo "<p>" . utf8entities($place['address']) . "</p>\n";
}
if (!empty($place['info'])) {
    echo "<p>" . $place['info'] . "</p>\n";
}
if ($hasCoordinates) {
    echo "<p>&nbsp;</p>";
    ?>
<div id="googleMap" style="width: 600px; height: 400px; font-family: Arial, sans-serif; font-size: 11px; border: 1px solid black"></div>
<script>
  function myMap() {

    const field = {
      lat: <?php echo $place['lat'] . ", lng: " . $place['lng']; ?>
    };

    const map = new google.maps.Map(document.getElementById("googleMap"), {
      zoom: 15,
      center: field,
    });
    const contentString =
      '<div id="content">' +
      <?php
          echo "'<h1>" . utf8entities($placeLabel) . "</h1>'+";
    echo "'<p>" . utf8entities($place['address']) . "</p>'";
    ?> +
      "</div>";
    const infowindow = new google.maps.InfoWindow({
      content: contentString,
    });
    const marker = new google.maps.Marker({
      position: field,
      map,
      title: "<?php echo utf8entities($placeLabel); ?>",
    });

    marker.addListener("click", () => {
      infowindow.open({
        anchor: marker,
        map,
        shouldFocus: false,
      });
    });

  }
</script>

<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo GetGoogleMapsAPIKey(); ?>&callback=myMap"></script>

<?php
}
contentEnd();
pageEnd();
?>
