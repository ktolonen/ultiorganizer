<?php
require_once $include_prefix.'lib/gettext/gettext.inc';
require_once $include_prefix.'lib/translation.functions.php';

// Map locales to defined ones that are "close enough"
$localeMap = array("en" => "en_GB.utf8",
					"en-gb" => "en_GB.utf8", 
					"en-au" => "en_GB.utf8",
					"en-ca" => "en_GB.utf8",
					"en-us" => "en_GB.utf8",
					"fi" => "fi_FI.utf8",
					"fi-fi" => "fi_FI.utf8");

function setSessionLocale() {
	global $include_prefix;
	
	if (isset($_SESSION['userproperties']['locale'])) {
		$tmparr = array_keys($_SESSION['userproperties']['locale']);
		$oldlocale = $tmparr[0];
	} else {
		$oldlocale = "not_set";
	}
	
	if (isset($_GET['locale'])) {
		$_SESSION['userproperties']['locale'] = array($_GET['locale'] => 0); 		
	}
	
	if (!isset($_SESSION['userproperties']['locale'])) {
		$_SESSION['userproperties']['locale'] = array(PreferredLocale() => 0);
	}
	
	if(is_array($_SESSION['userproperties']['locale'])){
		$tmparr = array_keys($_SESSION['userproperties']['locale']);
		$locale = $tmparr[0];
	}else{
		$locale = $_SESSION['userproperties']['locale'];
	}
	$encoding = 'UTF-8';
	
	putenv("LC_MESSAGES=$locale");
	$domain = 'messages';
	T_textdomain($domain);
	T_bindtextdomain($domain, $include_prefix."locale");
	T_bind_textdomain_codeset($domain, $encoding);
	T_setlocale(LC_MESSAGES, $locale);
	
	if (!headers_sent()) {
		header("Content-type: text/html; charset=$encoding");
	}
	if ($oldlocale != $locale) {
		loadDBTranslations($locale);
		if (isset($_SESSION['uid']) && $_SESSION['uid'] != "anonymous") {
			SetUserLocale($_SESSION['uid'], $locale);
		}
	}
}


function utf8entities($string) {
	return htmlentities($string, ENT_QUOTES, "UTF-8");
}

function styles() {
	global $styles_prefix;
	global $include_prefix;
	if (!isset($styles_prefix)) {
		$styles_prefix = $include_prefix;
	}
	$ret = "";
	if (is_file($include_prefix.'cust/'.CUSTOMIZATIONS.'/layout.css')) {
		$ret .= "		<link rel=\"stylesheet\" href=\"".$styles_prefix."cust/".CUSTOMIZATIONS."/layout.css\" type=\"text/css\" />\n";
	} else {
		$ret .= "		<link rel=\"stylesheet\" href=\"".$styles_prefix."cust/default/layout.css\" type=\"text/css\" />\n";
	}
	if (is_file($include_prefix.'cust/'.CUSTOMIZATIONS.'/font.css')) {
		$ret .= "		<link rel=\"stylesheet\" href=\"".$styles_prefix."cust/".CUSTOMIZATIONS."/font.css\" type=\"text/css\" />\n";
	} else {
		$ret .= "		<link rel=\"stylesheet\" href=\"".$styles_prefix."cust/default/font.css\" type=\"text/css\" />\n";
	}
	if (is_file($include_prefix.'cust/'.CUSTOMIZATIONS.'/default.css')) {
		$ret .= "		<link rel=\"stylesheet\" href=\"".$styles_prefix."cust/".CUSTOMIZATIONS."/default.css\" type=\"text/css\" />\n";
	} else {
		$ret .= "		<link rel=\"stylesheet\" href=\"".$styles_prefix."cust/default/default.css\" type=\"text/css\" />\n";
	}
	return $ret;
}

function MapLocale($ext_locale) {
	global $localeMap;
	$locale = strtolower(str_replace("_",'-',$ext_locale));
	if (isset($localeMap[$locale])) {
		return $localeMap[$locale];
	} else {
		return false;
	}
}

function PreferredLocale() {
	$langs = array();

	//temporarly disabled, seems not working properly on ffda server with english windows and
	//user selected Finnish.
	/*
	if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
		// break up string into pieces (languages and q factors)
		preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);

		if (count($lang_parse[1])) {
			// create a list like "en" => 0.8
			$langs = array_combine($lang_parse[1], $lang_parse[4]);
			 
			// set default to 1 for any without q factor
			foreach ($langs as $lang => $val) {
				if ($val === '') $langs[$lang] = 1;
			}

			// sort list based on value
			arsort($langs, SORT_NUMERIC);
		}
	}
	foreach ($langs as $lang => $val) {
		if (MapLocale($lang)) {
			return MapLocale($lang);
		}
	}
	*/
	return GetDefaultLocale();
}

?>
