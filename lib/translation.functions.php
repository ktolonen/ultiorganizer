<?php

function U_($name)
{
	$translated = translate($name, $_SESSION['dbtranslations']);
	return $translated[$name];
}

function loadDBTranslations($locale)
{
	$query = sprintf(
		"select translation_key, translation as value from uo_translation WHERE `locale`='%s'",
		DBEscapeString(str_replace(".", "_", $locale))
	);
	$result = DBQuery($query);

	$_SESSION['dbtranslations'] = array();
	while ($translation = mysqli_fetch_assoc($result)) {
		$_SESSION['dbtranslations'][strtolower($translation['translation_key'])] = $translation['value'];
	}
}

function GetTranslations()
{
	if (isset($_GET['search']))
		$search = $_GET['search'];
	elseif (isset($_GET['query']))
		$search = $_GET['query'];
	else
		$search = $_GET['q'];
	if (isset($_GET['autocomplete']) && "true" == $_GET['autocomplete']) {
		return AllTranslations($search, true);
	} else {
		return AllTranslations($search, false);
	}
}

function GetAutocompleteTranslations()
{
	if (isset($_GET['search']))
		$search = $_GET['search'];
	elseif (isset($_GET['query']))
		$search = $_GET['query'];
	else
		$search = $_GET['q'];

	return AllTranslations($search, true);
}

function AllTranslations($search, $autocomplete = false)
{
	global $locales;
	$splitted = preg_split(WORD_DELIMITER, $search, -1, PREG_SPLIT_NO_EMPTY);
	$translation_arrays = array();
	$results = array();
	foreach ($locales as $loc => $name) {
		$loc = str_replace(".", "_", $loc);
		$results[$loc] = "";
		$query = sprintf("SELECT translation_key, translation FROM uo_translation
          WHERE locale='%s' AND (", DBEscapeString($loc));

		$first = true;
		foreach ($splitted as $nextkey) {
			if ($first) {
				$first = false;
			} else {
				$query .= " OR ";
			}
			$query .= " translation_key like '" . DBEscapeString($nextkey) . "%'";
		}
		$query .= ")";
		$answer = DBQuery($query);
		$translations = array();
		while ($row = mysqli_fetch_assoc($answer)) {
			$translations[$row['translation_key']] = $row['translation'];
		}
		$translation_arrays[$loc] = $translations;
	}

	foreach ($translation_arrays as $lang => $translation_array) {
		if ($autocomplete) {
			$results[$lang] = autocompleteTranslate($search, $translation_array);
		} else {
			$results[$lang] = translate($search, $translation_array);
		}
	}
	$found = false;
	foreach ($results as $lang => $translations) {
		$flipped = array_flip($translations);
		if (isset($flipped[$search])) {
			$found = true;
		}
	}
	if (!$found) {
		$results[_("None")] = array($search => $search);
	}
	return $results;
}

function Translations()
{
	if (hasTranslationRight()) {
		$query = "SELECT * FROM uo_translation ORDER BY translation_key ASC";
		$result = DBQuery($query);
		return $result;
	} else {
		die('Insufficient rights to get translations');
	}
}

function SetTranslation($key, $translations)
{
	if (hasTranslationRight()) {
		foreach ($translations as $locale => $value) {
			$locale = str_replace(".", "_", $locale);
			if (empty($value)) {
				$query = sprintf(
					"DELETE FROM uo_translation 
            WHERE translation_key = '%s' AND locale='%s'",
					DBEscapeString($key),
					DBEscapeString($locale)
				);
				DBQuery($query);
			} else {
				$query = sprintf(
					"UPDATE uo_translation SET translation='%s' WHERE locale='%s' AND translation_key='%s'",
					DBEscapeString($value),
					DBEscapeString($locale),
					DBEscapeString($key)
				);
				$result = DBQuery($query);
			}
		}
	} else {
		die('Insufficient rights to change translation');
	}
}

function AddTranslation($key, $translations)
{
	if (hasTranslationRight()) {
		foreach ($translations as $locale => $value) {
			$locale = str_replace(".", "_", $locale);
			if (empty($value)) {
				$query = sprintf(
					"DELETE FROM uo_translation 
            WHERE translation_key = '%s' AND locale='%s'",
					DBEscapeString($key),
					DBEscapeString($locale)
				);
				DBQuery($query);
			} else {
				$query = sprintf(
					"INSERT INTO uo_translation (translation_key, locale, translation)
	      VALUES ('%s', '%s', '%s')",
					DBEscapeString($key),
					DBEscapeString($locale),
					DBEscapeString($value)
				);
				$result = DBQuery($query);
			}
		}
	} else {
		die('Insufficient rights to add translation');
	}
}

function RemoveTranslation($key)
{
	if (hasTranslationRight()) {
		$query = sprintf(
			"DELETE FROM uo_translation WHERE translation_key='%s'",
			DBEscapeString($key)
		);
		$result = DBQuery($query);
	} else {
		die('Insufficient rights to remove translation');
	}
}

function translate($name, $translation_array)
{
	$retArr = array();
	$ret = "";

	$key = strtolower((string)$name);
	if ($name !== '' && isset($translation_array[$key])) {
		$retArr[$name] = $translation_array[$key];
		return $retArr;
	} else {
		$ret = "";
		$splitSource = (string)$name;
		$splitted = preg_split(WORD_DELIMITER, $splitSource, -1, PREG_SPLIT_NO_EMPTY + PREG_SPLIT_DELIM_CAPTURE);
		foreach ($splitted as $part) {
			if (preg_match(WORD_DELIMITER, $part)) {
				$ret .= $part;
			} else {
				if (is_numeric($part)) {
					$ret .= $part;
				} else {
					$partKey = strtolower((string)$part);
					if ($part !== '' && isset($translation_array[$partKey])) {
						$ret .= $translation_array[$partKey];
					} else {
						$ret .= $part;
					}
				}
			}
		}
		$retArr[$name] = $ret;
		return $retArr;
	}
}

function autocompleteTranslate($name, $translation_array)
{
	$ret = array();
	foreach ($translation_array as $key => $translation) {
		if (strpos($key, strtolower($name)) === 0) {
			if (strlen($key) > strlen($name)) {
				$retName = $name . substr($key, strlen($name) - strlen($key));
			} else {
				$retName = $name;
			}
			$ret[$retName] = $translation;
		}
	}

	$splitted = preg_split(WORD_DELIMITER, $name, -1, PREG_SPLIT_NO_EMPTY);
	if (count($splitted) > 1) {
		$splitted = preg_split(WORD_DELIMITER, $name, -1, PREG_SPLIT_NO_EMPTY + PREG_SPLIT_DELIM_CAPTURE);

		$final = "";
		$finalkey = "";
		$lastpart = "";
		$lastkey = "";
		$current = "";
		$currentkey = "";
		$pending_delims = "";
		foreach ($splitted as $part) {
			if (preg_match(WORD_DELIMITER, $part)) {
				$pending_delims .= $part;
			} else {
				$current = $lastpart;
				$lastpart = $part;
				if (isset($translation_array[strtolower($current)])) {
					$final .= $translation_array[strtolower($current)];
				} else {
					$final .= $current;
				}
				$finalkey .= $current;
				$final .= $pending_delims;
				$finalkey .= $pending_delims;
				$pending_delims = "";
			}
		}
		$matchFound = false;
		foreach ($translation_array as $key => $translation) {
			if (strpos($key, strtolower($lastpart)) === 0) {
				$matchFound = true;
				if (strlen($key) > strlen($lastpart)) {
					$retName = $finalkey . $lastpart . substr($key, strlen($lastpart) - strlen($key)) . $pending_delims;
				} else {
					$retName = $finalkey . $lastpart . $pending_delims;
				}
				$retVal = $final . $translation . $pending_delims;
				if (strcasecmp($retVal, $name) != 0) {
					$ret[$retName] = $retVal;
				}
			}
		}
		if (!$matchFound) {
			$retName = $finalkey . $lastpart . $pending_delims;
			$retVal = $final . $lastpart . $pending_delims;
			if (strcasecmp($retVal, $name) != 0) {
				$ret[$retName] = $retVal;
			}
		}
	}
	return $ret;
}

function TranslatedField($fieldName, $value, $width = "200", $size = "30")
{
	return "
	<div style='width:" . $width . "px; height:20px' id='" . $fieldName . "Autocomplete' class='yui-skin-sam'>
		<input class='input' size='" . $size . "' maxlength='" . $size . "' style='width:" . $width . "px' id='" . $fieldName .
		"' name='" . $fieldName . "' value='" . utf8entities($value) . "'/>
		<div style='width:" . $width . "px' id='" . $fieldName . "Container'>
		</div>
	</div>\n";
}

function TranslationScript($fieldName)
{
	return "<script type=\"text/javascript\">
//<![CDATA[
var " . $fieldName . "SelectHandler = function(sType, aArgs) {
	var oData = aArgs[2];
	document.getElementById(\"" . $fieldName . "\").value = oData[1];
};

" . $fieldName . "Fetch = function(){        
	var translationSource = new YAHOO.util.XHRDataSource(\"ext/autocompletetranslationtxt.php\");
    translationSource.responseSchema = {
         recordDelim: \"\\n\",
         fieldDelim: \"\\t\"
    };
    translationSource.responseType = YAHOO.util.XHRDataSource.TYPE_TEXT;
    translationSource.maxCacheEntries = 60;

    // First AutoComplete
    var translationAutoComp = new YAHOO.widget.AutoComplete(\"" . $fieldName . "\",\"" . $fieldName . "Container\", translationSource);
    translationAutoComp.formatResult = function(oResultData, sQuery, sResultMatch) { 

    	// some other piece of data defined by schema 
		var translated = oResultData[2];  
		var completed = oResultData[1];
		var fill = completed.substring(sQuery.length);
		var aMarkup = [\"<div class='myCustomResult'>\", 
		sQuery, 
		\"<span style='font-weight:bold'>\", 
		fill, 
		\" &raquo; </span>\",
		sResultMatch,
		\": \", 
		translated, 
		\"</div>\"]; 
		return (aMarkup.join(\"\"));
	}; 
	translationAutoComp.itemSelectEvent.subscribe(" . $fieldName . "SelectHandler);
    return {
        oDS: translationSource,
        oAC: translationAutoComp
    }
}();
//]]>
</script>";
}
