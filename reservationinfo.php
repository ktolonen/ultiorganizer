<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/configuration.functions.php';

$reservationId = intval(iget("reservation"));
$place = ReservationInfo($reservationId);
$title = _("Reservation") . ": " . utf8entities($place['name']) . " " . _("Field") . " " . utf8entities($place['fieldname']);

//common page
pageTopHeadOpen($title);
pageTopHeadClose($title, false, "onload=\"load()\" onunload=\"GUnload()\"");
leftMenu();
contentStart();
echo "<h1>" . utf8entities($place['name']) . " " . _("Field") . " " . utf8entities($place['fieldname']) . "</h1>\n";
echo "<p>" . DefTimeFormat($place['starttime']) . " - " . DefHourFormat($place['endtime']) . "</p>\n";
echo "<p>" . utf8entities($place['address']) . "</p>\n";
echo "<p>" . $place['info'] . "</p>\n";
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
      echo "'<h1>" . utf8entities($place['name']) . "</h1>'+";
      echo "'<p>" . utf8entities($place['address']) . "</p>'";
      ?> +
      "</div>";
    const infowindow = new google.maps.InfoWindow({
      content: contentString,
    });
    const marker = new google.maps.Marker({
      position: field,
      map,
      title: "<?php echo utf8entities($place['name']); ?>",
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
contentEnd();
pageEnd();
?>