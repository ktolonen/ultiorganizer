<?php

include_once 'localization.php';
include_once '../lib/location.functions.php';

OpenConnection();

header("Content-type: text/plain; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetSearchLocations();
// Iterate through the rows, adding XML nodes for each
while ($row = @mysqli_fetch_assoc($result)){
	echo U_($row['name'])."\t".$row['address']."\t".$row['id']."\n";
}
CloseConnection();
?>
