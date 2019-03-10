<?php

include_once 'localization.php';

header("Content-type: text/plain; charset=UTF-8");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: -1");
$result = GetAutocompleteTranslations();
foreach ($result as $lang => $translations) {
	foreach ((array)$translations as $key => $translation) {
		echo $lang."\t".$key."\t".$translation."\n";
	}
}
CloseConnection();
?>
