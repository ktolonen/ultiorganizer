<?php

require_once __DIR__ . '/lib/include_only.guard.php';
denyDirectFileAccess(__FILE__);

include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/translation.functions.php';

if (!function_exists('gettext')) {
    die('The PHP gettext extension must be enabled to run Ultiorganizer.');
}

// Map locales to defined ones that are "close enough"
$localeMap = ["en" => "en_GB.utf8",
    "en-gb" => "en_GB.utf8",
    "en-au" => "en_GB.utf8",
    "en-ca" => "en_GB.utf8",
    "en-us" => "en_GB.utf8",
    "de" => "de_DE.utf8",
    "de-de" => "de_DE.utf8",
    "es" => "es_ES.utf8",
    "es-es" => "es_ES.utf8",
    "fi" => "fi_FI.utf8",
    "fi-fi" => "fi_FI.utf8"];

function setSessionLocale()
{
    global $include_prefix;
    global $locales;

    if (isset($_SESSION['userproperties']['locale'])) {
        $tmparr = array_keys($_SESSION['userproperties']['locale']);
        $oldlocale = $tmparr[0];
    } else {
        $oldlocale = "not_set";
    }

    if (iget("locale")) {
        $_SESSION['userproperties']['locale'] = [$_GET['locale'] => 0];
    }

    if (!isset($_SESSION['userproperties']['locale'])) {
        $_SESSION['userproperties']['locale'] = [PreferredLocale() => 0];
    }

    if (is_array($_SESSION['userproperties']['locale'])) {
        $tmparr = array_keys($_SESSION['userproperties']['locale']);
        $locale = $tmparr[0];
    } else {
        $locale = $_SESSION['userproperties']['locale'];
    }
    $encoding = 'UTF-8';

    $domain = 'messages';
    textdomain($domain);
    bindtextdomain($domain, $include_prefix . "locale");
    bind_textdomain_codeset($domain, $encoding);
    ActivateGettextLocale($locale, is_array($locales) ? array_keys($locales) : []);

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


function utf8entities($string)
{
    return htmlentities((string) $string, ENT_QUOTES, "UTF-8");
}

function styles()
{
    global $styles_prefix;
    global $include_prefix;
    if (!isset($styles_prefix)) {
        $styles_prefix = $include_prefix;
        //		$styles_prefix = "../";
    }
    $ret = "";
    // Load the default skin as the base, then layer the active customization on
    // top so a customization only needs to carry the rules it overrides.
    $ret .= "		<link rel=\"stylesheet\" href=\"" . $styles_prefix . "cust/default/ultiorganizer.css\" type=\"text/css\" />\n";
    if (CUSTOMIZATIONS !== 'default'
        && is_file($include_prefix . 'cust/' . CUSTOMIZATIONS . '/ultiorganizer.css')) {
        $ret .= "		<link rel=\"stylesheet\" href=\"" . $styles_prefix . "cust/" . CUSTOMIZATIONS . "/ultiorganizer.css\" type=\"text/css\" />\n";
    }
    return $ret;
}

function mobileStyles()
{
    global $styles_prefix;
    global $include_prefix;
    if (!isset($styles_prefix)) {
        $styles_prefix = $include_prefix;
    }
    $ret = "";
    // Same base + override cascade as styles().
    $ret .= "    <link rel=\"stylesheet\" href=\"" . $styles_prefix . "cust/default/ultiorganizer-mobile.css\" type=\"text/css\" />\n";
    if (CUSTOMIZATIONS !== 'default'
        && is_file($include_prefix . 'cust/' . CUSTOMIZATIONS . '/ultiorganizer-mobile.css')) {
        $ret .= "    <link rel=\"stylesheet\" href=\"" . $styles_prefix . "cust/" . CUSTOMIZATIONS . "/ultiorganizer-mobile.css\" type=\"text/css\" />\n";
    }
    return $ret;
}

function MobileLanguageSelection($queryParams = [])
{
    global $include_prefix;
    global $locales;
    global $styles_prefix;

    $localeFlagFiles = [
        'de_DE.utf8' => 'Germany.png',
        'en_GB.utf8' => 'United_Kingdom.png',
        'es_ES.utf8' => 'Spain.png',
        'fi_FI.utf8' => 'Finland.png',
    ];
    $html = "<div class='mobile-language-flags'>\n";
    if (isset($locales) && is_array($locales)) {
        foreach ($locales as $localestr => $localename) {
            $flagSrc = $styles_prefix . "locale/" . $localestr . "/flag.png";
            $flagClass = "localeselection mobile-language-flag-native";
            if (isset($localeFlagFiles[$localestr])) {
                $smallFlagFile = $localeFlagFiles[$localestr];
                if (is_file($include_prefix . "images/flags/small/" . $smallFlagFile)) {
                    $flagSrc = $styles_prefix . "images/flags/small/" . $smallFlagFile;
                    $flagClass = "localeselection mobile-language-flag-small";
                }
            }
            $localeParams = $queryParams;
            $localeParams['locale'] = $localestr;
            $html .= "<a href='?" . utf8entities(http_build_query($localeParams)) . "'>";
            $html .= "<img class='" . utf8entities($flagClass) . "' src='" . utf8entities($flagSrc)
                . "' alt='" . utf8entities($localename) . "'/>";
            $html .= "</a>\n";
        }
    }
    $html .= "</div>\n";

    return $html;
}

function MapLocale($ext_locale)
{
    global $localeMap;
    $locale = strtolower(str_replace("_", '-', $ext_locale));
    if (isset($localeMap[$locale])) {
        return $localeMap[$locale];
    } else {
        return false;
    }
}

function PreferredLocale()
{
    $langs = [];

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
