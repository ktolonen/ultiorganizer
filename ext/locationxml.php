<?php

include_once 'localization.php';
include_once '../lib/location.functions.php';

OpenConnection();
header("Content-type: text/xml");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetSearchLocations();

// for php 5 onwards
if (version_compare(PHP_VERSION, '5.0.0', '>')) {
	$dom = new DOMDocument("1.0");
	$node = $dom->createElement("markers");
	$parnode = $dom->appendChild($node);

	// Iterate through the rows, adding XML nodes for each
	$savedID = null;
	while ($row = @mysqli_fetch_assoc($result)){
	  if ($row['id'] !== $savedID) {
	  $node = $dom->createElement("marker");
	  $newnode = $parnode->appendChild($node);
	  $newnode->setAttribute("id", $row['id']);
	  $newnode->setAttribute("name", U_($row['name']));
	  $newnode->setAttribute("address", $row['address']);
	  $newnode->setAttribute("lat", $row['lat']);
	  $newnode->setAttribute("lng", $row['lng']);
	  $newnode->setAttribute("info", $row['info']);
	  $newnode->setAttribute("indoor", $row['indoor']);
	  $newnode->setAttribute("fields", $row['fields']);
	  }
      $newnode->setAttribute("info_" . $row['locale'], $row['locale_info']);
      $savedID = $row['id'];
	}

	echo $dom->saveXML();

//PHP4 with domxml and iconv extensions
}elseif(extension_loaded("domxml") && extension_loaded("iconv")) {
	$dom = domxml_new_doc("1.0");
	$node = $dom->create_element("markers");
	$parnode = $dom->append_child($node);

	// Iterate through the rows, adding XML nodes for each
	while ($row = @mysqli_fetch_assoc($result)){
	$node = $dom->create_element("marker");
	$newnode = $parnode->append_child($node);
	  $newnode->set_attribute("id", $row['id']);
	  $newnode->set_attribute("name", U_($row['name']));
	  $newnode->set_attribute("address", $row['address']);
	  $newnode->set_attribute("lat", $row['lat']);
	  $newnode->set_attribute("lng", $row['lng']);
	  $newnode->set_attribute("info", $row['info']);
	  foreach ($locales as $locale => $locname) {
	  	$locale = str_replace(".", "_", $locale);
	  	$newnode->set_attribute("info_".$locale, $row['info_'.$locale]);
	  }
	  $newnode->set_attribute("indoor", $row['indoor']);
	  $newnode->set_attribute("fields", $row['fields']);
	  
	}
	echo $dom->dump_mem(true);

//hardcoded xml for those who can't install extensions
}else{
	echo "<?xml version=\"1.0\"?>\n";
	echo "<markers>\n";

	// Iterate through the rows, adding XML nodes for each
	while ($row = @mysqli_fetch_assoc($result)){
		echo "<marker";
		echo " id=\"". $row['id']."\"";
		echo " name=\"". U_($row['name'])."\"";
		echo " address=\"". $row['address']."\"";
		echo " lat=\"". $row['lat']."\"";
		echo " lng=\"". $row['lng']."\"";
		echo " info=\"". $row['info']."\"";
	  	foreach ($locales as $locale => $locname) {
	  		$locale = str_replace(".", "_", $locale);
			echo " info_".$locale."=\"". $row['info_'.$locale]."\"";
	  	}
		echo " indoor=\"". $row['indoor']."\"";
		echo " fields=\"". $row['fields']."\"";
		echo "/>\n";
	}
	echo "</markers>\n";
}
CloseConnection();
?>
