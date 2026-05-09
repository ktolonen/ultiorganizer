<?php

include_once __DIR__ . '/localization.php';
include_once __DIR__ . '/../lib/location.functions.php';

OpenConnection();

header("Content-type: text/plain; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetSearchLocationsArray();
// Iterate through the rows, adding XML nodes for each
foreach ($result as $row) {
	echo U_($row['name']) . "\t" . $row['address'] . "\t" . $row['id'] . "\n";
}
CloseConnection();
