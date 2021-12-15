<?php
$h = fopen('paikat.csv', 'r');
$locations = array();
while ($line = fgets($h)) {
	$props = explode(";", $line);
	if (!isset($locations[$props[1]])) {
		echo "	runQuery(\"insert into pelik_location (id, name, fields, indoor, address, lat, lng) values (";
		echo $props[1] . ", " . $props[3] . ", " . $props[2] . ", " . $props[4] . ", " . $props[6] . ", " . $props[7] . ", " . trim($props[8]) . ")\");\n";
		$locations[$props[1]] = $props[3];
	}
	echo "	runQuery(\"insert into pelik_reservation (id, location, fieldname, reservationgroup, starttime, endtime) ";
	echo "select paikka_id, " . $props[1] . ", " . trim($props[5]) . ", turnaus, aikaalku, aikaloppu from pelik_paikka where paikka like " . $props[0] . "\");\n";
}
