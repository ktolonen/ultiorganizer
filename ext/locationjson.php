<?php

include_once 'localization.php';
include_once '../lib/location.functions.php';

OpenConnection();

header("Content-type: text/plain; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetLocations();
echo json_encode($result);
CloseConnection();
?>
