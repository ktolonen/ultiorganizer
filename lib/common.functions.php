<?php
include_once $include_prefix . 'lib/HSVClass.php';

if (!function_exists('convertToUtf8')) {
function convertToUtf8($value, $sourceEncoding = 'ISO-8859-1')
{
	if ($value === null) {
		return '';
	}
	if (!is_string($value)) {
		$value = (string)$value;
	}

	// If the value already is valid UTF-8 just return it.
	if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
		return $value;
	}

	// Try the declared source encoding first.
	if (function_exists('mb_convert_encoding')) {
		$converted = @mb_convert_encoding($value, 'UTF-8', $sourceEncoding);
		if ($converted !== false && (!function_exists('mb_check_encoding') || mb_check_encoding($converted, 'UTF-8'))) {
			return $converted;
		}
	}

	// Fallback conversions for environments without mbstring.
	if (function_exists('iconv')) {
		$converted = @iconv($sourceEncoding, 'UTF-8//TRANSLIT', $value);
		if ($converted !== false && (!function_exists('mb_check_encoding') || mb_check_encoding($converted, 'UTF-8'))) {
			return $converted;
		}
	}

	// Last resort for ISO-8859-1 input when other extensions are missing.
	if (function_exists('utf8_encode') && $sourceEncoding === 'ISO-8859-1') {
		$converted = utf8_encode($value);
		if (!function_exists('mb_check_encoding') || mb_check_encoding($converted, 'UTF-8')) {
			return $converted;
		}
	}

	// Strip out any invalid bytes to avoid failing DB inserts.
	if (function_exists('iconv')) {
		$converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
		if ($converted !== false) {
			return $converted;
		}
	}

	return $value;
}
}

if (!function_exists('normalizeTextInput')) {
	function normalizeTextInput($value, $sourceEncoding = 'ISO-8859-1')
	{
		return convertToUtf8(trim(urldecode($value)), $sourceEncoding);
	}
}

function StripFromQueryString($query_string, $needle)
{
	$safeNeedle = preg_quote($needle, '/');
	$query_string = preg_replace("/(\&|\?)*{$safeNeedle}=[a-zA-Z0-9].*?(\&;|$)/", '$2', $query_string);
	return preg_replace("/(&)+/", "&", $query_string);
}

function SafeDivide($dividend, $divisor)
{
	if (!isset($divisor) || is_null($divisor) || $divisor == 0)
		$result = 0;
	else
		$result = $dividend / $divisor;

	return $result;
}

function SecToMin($sec)
{
	$s = intval($sec);
	$str = $s % 60;

	if (strlen($str) == 1)
		$str = "0" . $str;

	$s = $s / 60;
	return (intval($s) . "." . $str);
}

function Hours($timestamp)
{
	$datetime = strtotime($timestamp);
	$hours = date('H', $datetime);

	return intval($hours);
}

function Minutes($timestamp)
{
	$datetime = strtotime($timestamp);
	$min = date('i', $datetime);

	return intval($min);
}

function WeekdayString($timestamp, $cap)
{
	//$datetime = date_create($timestamp);
	//$weekday = date_format($datetime, 'w');
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$weekday = date('w', $datetime);
	switch ($weekday) {
		case 0:
			$weekday = $cap ? _("Sun") : _("Sun");
			break;
		case 1:
			$weekday = $cap ? _("Mon") : _("Mon");
			break;
		case 2:
			$weekday = $cap ? _("Tue") : _("Tue");
			break;
		case 3:
			$weekday = $cap ? _("Wed") : _("Wed");
			break;
		case 4:
			$weekday = $cap ? _("Thu") : _("Thu");
			break;
		case 5:
			$weekday = $cap ? _("Fri") : _("Fri");
			break;
		case 6:
			$weekday = $cap ? _("Sat") : _("Sat");
			break;
		default:
			$weekday = '';
			break;
	}

	return $weekday;
}

function ShortDate($timestamp)
{
	//$datetime = date_create($timestamp);
	//$shortdate = date_format($datetime, 'j.n.Y');
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shortdate = date('j.n.Y', $datetime);
	return $shortdate;
}

function ShortEnDate($timestamp)
{
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shortdate = date('Y-m-d', $datetime);
	return $shortdate;
}

function JustDate($timestamp)
{
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shortdate = date('j.n.', $datetime);
	return $shortdate;
}

function DefWeekDateFormat($timestamp)
{
	return WeekdayString($timestamp, true) . " " . ShortDate($timestamp);
}

function DefHourFormat($timestamp)
{
	//$datetime = date_create($timestamp);
	//$hours = date_format($datetime, 'H:i');
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$hours = date('H:i', $datetime);

	return $hours;
}

function DefTimeFormat($timestamp)
{
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shorttime = date('j.n.Y H:i', $datetime);
	return $shorttime;
}

function ShortTimeFormat($timestamp)
{
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shorttime = date('j.n. H:i', $datetime);
	return $shorttime;
}

function LongTimeFormat($timestamp)
{
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shorttime = date('j.n.Y H:i:s', $datetime);
	return $shorttime;
}

function ISOTimeFormat($timestamp)
{
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$timestr = date('c', $datetime);
	return $timestr;
}

function DefBirthdayFormat($timestamp)
{
	//$datetime = date_create($timestamp);
	//$hours = date_format($datetime, 'd.m.Y');
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$hours = date('d.m.Y', $datetime);
	return $hours;
}

function TimeToIcal($timestamp)
{
	//$datetime = date_create($timestamp);
	//$time = date_format($datetime, 'Ymd\THi00');
	if (empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$time = date('Ymd\THi00', $datetime);
	return $time;
}

function DefTimestamp()
{
	return date('H:i:s', time());
}

function TimeToSec($timestamp)
{
	if (empty($timestamp))
		return 0;
	$format = substr_count($timestamp, ".");
	$secs = 0;

	//mm
	if ($format == 0) {
		$secs = intval($timestamp) * 60;
	}
	//mm.ss
	elseif ($format == 1) {
		$tmp1 = strtok($timestamp, ".");
		$tmp2 = strtok(".");
		$secs = intval($tmp1) * 60 + intval($tmp2);
	}
	//hh.mm.ss
	elseif ($format == 2) {
		$tmp1 = strtok($timestamp, ".");
		$tmp2 = strtok(".");
		$tmp3 = strtok(".");
		$secs = intval($tmp1) * 3600 + intval($tmp2) * 60 + intval($tmp3);
	}
	return $secs;
}

function ToInternalTimeFormat($timestamp)
{
	if (empty($timestamp)) {
		$timestamp = "1.1.1971 00:00"; //safer option since depending of timezone 1.1.1970 can cause error
	} else if (!uo_strptime($timestamp, DATE_FORMAT)) {
		if (!uo_strptime($timestamp . " 00:00", DATE_FORMAT)) {
			$timestamp = "1.1.1971 00:00"; //safer option since depending of timezone 1.1.1970 can cause error
		} else {
			$timestamp .= " 00:00";
		}
	}
	$datearr = uo_strptime($timestamp, DATE_FORMAT);
	return (1900 + $datearr['tm_year']) . "-" . ($datearr['tm_mon'] + 1) . "-" . $datearr['tm_mday'] . " " . $datearr['tm_hour'] . ":" . $datearr['tm_min'] . ":00";
}

function isEmptyDate($timestamp)
{
	if (empty($timestamp)) {
		return true;
	}
	$datetime = strtotime($timestamp);
	$time = date('j.n.Y H:i', $datetime);

	if (strcmp("1.1.1971 00:00", $time) == 0) {
		return true;
	}
	if (strcmp("1.1.1970 00:00", $time) == 0) {
		return true;
	}

	return false;
}

function EpocToMysql($epoc)
{
	return date('Y-m-d H:i:s', $epoc);
}


function GetScriptName()
{
	if (isset($_SERVER['SCRIPT_NAME'])) {
		return $_SERVER['SCRIPT_NAME'];
	} elseif (isset($_SERVER['PHP_SELF'])) {
		return htmlspecialchars($_SERVER['PHP_SELF']);
	} elseif (isset($_SERVER['PATH_INFO '])) {
		return $_SERVER['PATH_INFO '];
	} else {
		die("Cannot find page address");
	}
}

function GetURLBase()
{
	$url = "http://";
	$url .= GetServerName();
	$url .= GetScriptName();

	$cutpos = strrpos($url, "/");
	$url = substr($url, 0, $cutpos);
	global $include_prefix;
	if (strlen($include_prefix) > 0) {
		$updirs = explode($include_prefix, "/");
		foreach ($updirs as $dotdot) {
			$cutpos = strrpos($url, "/");
			$url = substr($url, 0, $cutpos);
		}
	}
	return $url;
}
function GetPageURL()
{
	$pageURL = 'http';
	if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {
		$pageURL .= "s";
	}
	$pageURL .= "://";
	if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] != "80") {
		$pageURL .= $_SERVER["SERVER_NAME"] . ":" . $_SERVER["SERVER_PORT"] . $_SERVER["REQUEST_URI"];
	} else {
		$pageURL .= $_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"];
	}
	return $pageURL;
}

function getSessionLocale()
{
	if (isset($_SESSION['userproperties']['locale']) && is_array($_SESSION['userproperties']['locale'])) {
		$tmparr = array_keys($_SESSION['userproperties']['locale']);
		return $tmparr[0];
	} elseif (isset($_SESSION['userproperties']['locale'])) {
		return $_SESSION['userproperties']['locale'];
	} else {
		return GetDefaultLocale();
	}
}

function GetW3CLocale()
{
	$locale = GetSessionLocale();
	$locale = str_replace("_", '-', $locale);
	return $locale;
}

function validEmail($email)
{
	$isValid = true;
	$atIndex = strrpos($email, "@");
	if (is_bool($atIndex) && !$atIndex) {
		$isValid = false;
	} else {
		$domain = substr($email, $atIndex + 1);
		$local = substr($email, 0, $atIndex);
		$localLen = strlen($local);
		$domainLen = strlen($domain);
		if ($localLen < 1 || $localLen > 64) {
			// local part length exceeded
			$isValid = false;
		} else if ($domainLen < 1 || $domainLen > 255) {
			// domain part length exceeded
			$isValid = false;
		} else if ($local[0] == '.' || $local[$localLen - 1] == '.') {
			// local part starts or ends with '.'
			$isValid = false;
		} else if (preg_match('/\\.\\./', $local)) {
			// local part has two consecutive dots
			$isValid = false;
		} else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
			// character not valid in domain part
			$isValid = false;
		} else if (preg_match('/\\.\\./', $domain)) {
			// domain part has two consecutive dots
			$isValid = false;
		} else if (!preg_match(
			'/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
			str_replace("\\\\", "", $local)
		)) {
			// character not valid in local part unless 
			// local part is quoted
			if (!preg_match(
				'/^"(\\\\"|[^"])+"$/',
				str_replace("\\\\", "", $local)
			)) {
				$isValid = false;
			}
		}
		//if ($isValid && function_exists('checkdnsrr') && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))
		//{
		// domain not found in DNS
		//   $isValid = false;
		//}
	}
	return $isValid;
}

function is_odd($number)
{
	return $number & 1; // 0 = even, 1 = odd
}

function textColor($bgcolor)
{
	$hsv = new HSVClass();
	$hsv->setRGBString($bgcolor);
	$hsv->changeHue(180);
	$hsvArr = $hsv->getHSV();
	$hsv->setHSV($hsvArr['h'], 1 - $hsvArr['s'], 1 - $hsvArr['v']);
	return $hsv->getRGBString();
}

function RGBtoRGBa($rgbstring, $alpha)
{
	$r = $rgbstring[0] . $rgbstring[1];
	$g = $rgbstring[2] . $rgbstring[3];
	$b = $rgbstring[4] . $rgbstring[5];

	$r = hexdec($r);
	$g = hexdec($g);
	$b = hexdec($b);
	return "rgba($r,$g,$b,$alpha)";
}

function getChkNum($sNum)
{
	$multipliers = array(7, 3, 1);
	$sNumLen = strlen($sNum);
	$sNum = str_split($sNum);
	$chkSum = 0;
	for ($i = $sNumLen - 1; $i >= 0; --$i) {
		$chkSum += $sNum[$i] * $multipliers[($sNumLen - 1 - $i) % 3];
	}
	return (10 - $chkSum % 10) % 10;
}

function checkChkNum($sNum)
{
	$chk = substr($sNum, -1);
	$chkStr = substr($sNum, 0, -1);
	return $chk == getChkNum($chkStr);
}

if (!function_exists('str_split')) {
	function str_split($string, $split_length = 1)
	{
		$array = explode("\r\n", chunk_split($string, $split_length));
		array_pop($array);
		return $array;
	}
}

/*
 * This work of Lionel SAURON (http://sauron.lionel.free.fr:80) is licensed under the
 * Creative Commons Attribution-Noncommercial-Share Alike 2.0 France License.
 *
 * To view a copy of this license, visit http://creativecommons.org/licenses/by-nc-sa/2.0/fr/
 * or send a letter to Creative Commons, 171 Second Street, Suite 300, San Francisco, California, 94105, USA.
 */

/**
 * Portable wrapper for parsing a time/date generated with strftime().
 * Falls back to a PHP implementation to avoid calling the deprecated
 * native strptime() on PHP 8.2+.
 */
function uo_strptime($sDate, $sFormat)
{
	if (function_exists('strptime') && PHP_VERSION_ID < 80200) {
		return strptime($sDate, $sFormat);
	}
	return uo_strptime_fallback($sDate, $sFormat);
}

/**
 * Limited strptime() implementation that supports %S, %M, %H, %d, %m, %Y.
 *
 * @author Lionel SAURON
 */
function uo_strptime_fallback($sDate, $sFormat)
{
	$aResult = array(
		'tm_sec'   => 0,
		'tm_min'   => 0,
		'tm_hour'  => 0,
		'tm_mday'  => 1,
		'tm_mon'   => 0,
		'tm_year'  => 0,
		'tm_wday'  => 0,
		'tm_yday'  => 0,
		'unparsed' => $sDate,
	);

	while ($sFormat != "") {
		$nIdxFound = strpos($sFormat, '%');
		if ($nIdxFound === false) {
			$aResult['unparsed'] = ($sFormat == $sDate) ? "" : $sDate;
			break;
		}

		$sFormatBefore = substr($sFormat, 0, $nIdxFound);
		$sDateBefore   = substr($sDate,   0, $nIdxFound);

		if ($sFormatBefore != $sDateBefore) break;

		$sFormat = substr($sFormat, $nIdxFound);
		$sDate   = substr($sDate,   $nIdxFound);

		$aResult['unparsed'] = $sDate;

		$sFormatCurrent = substr($sFormat, 0, 2);
		$sFormatAfter   = substr($sFormat, 2);

		$nValue = -1;
		$sDateAfter = "";

		switch ($sFormatCurrent) {
			case '%S':
				sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
				if (($nValue < 0) || ($nValue > 59)) return false;
				$aResult['tm_sec']  = $nValue;
				break;
			case '%M':
				sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
				if (($nValue < 0) || ($nValue > 59)) return false;
				$aResult['tm_min']  = $nValue;
				break;
			case '%H':
				sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
				if (($nValue < 0) || ($nValue > 23)) return false;
				$aResult['tm_hour']  = $nValue;
				break;
			case '%d':
				sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
				if (($nValue < 1) || ($nValue > 31)) return false;
				$aResult['tm_mday']  = $nValue;
				break;
			case '%m':
				sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
				if (($nValue < 1) || ($nValue > 12)) return false;
				$aResult['tm_mon']  = ($nValue - 1);
				break;
			case '%Y':
				sscanf($sDate, "%4d%[^\\n]", $nValue, $sDateAfter);
				if ($nValue < 1900) return false;
				$aResult['tm_year']  = ($nValue - 1900);
				break;
			default:
				break 2;
		}

		$sFormat = $sFormatAfter;
		$sDate   = $sDateAfter;

		$aResult['unparsed'] = $sDate;
	}

	$nParsedDateTimestamp = mktime(
		$aResult['tm_hour'],
		$aResult['tm_min'],
		$aResult['tm_sec'],
		$aResult['tm_mon'] + 1,
		$aResult['tm_mday'],
		$aResult['tm_year'] + 1900
	);

	if (($nParsedDateTimestamp === false)
		|| ($nParsedDateTimestamp === -1)
	) return false;

	$aResult['tm_wday'] = (int) date('w', $nParsedDateTimestamp);
	$aResult['tm_yday'] = (int) date('z', $nParsedDateTimestamp);

	return $aResult;
}

if (!function_exists("stripos")) {
	function stripos($str, $needle, $offset = 0)
	{
		return strpos(strtolower($str), strtolower($needle), $offset);
	}/* endfunction stripos */
}/* endfunction exists stripos */

function recur_mkdirs($path, $mode = 0775)
{
	$dirs = explode('/', $path);
	$pos = strrpos($path, ".");
	if ($pos === false) { // note: three equal signs
		// not found, means path ends in a dir not file
		$subamount = 0;
	} else {
		$subamount = 1;
	}

	for ($c = 0; $c < count($dirs) - $subamount; $c++) {
		$thispath = "";
		for ($cc = 0; $cc <= $c; $cc++) {
			$thispath .= $dirs[$cc] . '/';
		}
		if (!file_exists($thispath)) {
			//print "$thispath<br>";
			mkdir($thispath, $mode);
		}
	}
}

function SafeUrl($url)
{
	if ((strtolower(substr($url, 0, 7)) != "http://") && (strtolower(substr($url, 0, 8)) != "https://")) {
		$url = "http://" . $url;
	}
	return $url;
}

function colorstring2rgb($color)
{
	if ($color[0] == '#')
		$color = substr($color, 1);

	if (strlen($color) == 6)
		list($r, $g, $b) = array(
			$color[0] . $color[1],
			$color[2] . $color[3],
			$color[4] . $color[5]
		);
	elseif (strlen($color) == 3)
		list($r, $g, $b) = array($color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2]);
	else
		return false;

	$r = hexdec($r);
	$g = hexdec($g);
	$b = hexdec($b);

	return array('r' => $r, 'g' => $g, 'b' => $b);
}

# Define parse_ini_string if it doesn't exist.
# Does accept lines starting with ; as comments
# Does not accept comments after values
if (!function_exists('parse_ini_string')) {

	function parse_ini_string($string)
	{
		$array = array();

		$lines = explode("\n", $string);

		foreach ($lines as $line) {
			$statement = preg_match(
				"/^(?!;)(?P<key>[\w+\.\-]+?)\s*=\s*(?P<value>.+?)\s*$/",
				$line,
				$match
			);

			if ($statement) {
				$key    = $match['key'];
				$value    = $match['value'];

				# Remove quote
				if (preg_match("/^\".*\"$/", $value) || preg_match("/^'.*'$/", $value)) {
					$value = mb_substr($value, 1, mb_strlen($value) - 2);
				}

				$array[$key] = $value;
			}
		}
		return $array;
	}
}
if (!function_exists('array_combine')) {
	function array_combine($arr1, $arr2)
	{
		$out = array();

		$arr1 = array_values($arr1);
		$arr2 = array_values($arr2);

		foreach ($arr1 as $key1 => $value1) {
			$out[(string)$value1] = $arr2[$key1];
		}

		return $out;
	}
}

function ResultsetToCsv($result, $separator)
{
	$csv_terminated = "\n";
	$csv_separator = $separator;
	$csv_enclosed = '"';
	$csv_escaped = "\\";

	$fields_cnt = mysqli_num_fields($result);

	$schema_insert = '';

	for ($i = 0; $i < $fields_cnt; $i++) {
		$l = $csv_enclosed . str_replace(
			$csv_enclosed,
			$csv_escaped . $csv_enclosed,
			stripslashes(mysqli_fetch_field_direct($result, $i)->name)
		) . $csv_enclosed;
		$schema_insert .= $l;
		$schema_insert .= $csv_separator;
	} // end for

	$out = trim(substr($schema_insert, 0, -1));
	$out .= $csv_terminated;

	// Format the data
	while ($row = mysqli_fetch_array($result)) {
		$schema_insert = '';
		for ($j = 0; $j < $fields_cnt; $j++) {
			if ($row[$j] == '0' || $row[$j] != '') {

				if ($csv_enclosed == '') {
					$schema_insert .= $row[$j];
				} else {
					$schema_insert .= $csv_enclosed .
						str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
				}
			} else {
				$schema_insert .= '';
			}

			if ($j < $fields_cnt - 1) {
				$schema_insert .= $csv_separator;
			}
		} // end for

		$out .= $schema_insert;
		$out .= $csv_terminated;
	} // end while
	return $out;
}

function ArrayToCsv($result, $separator)
{
	$csv_terminated = "\n";
	$csv_separator = $separator;
	$csv_enclosed = '"';
	$csv_escaped = "\\";

	if (count($result) == 0) {
		return "";
	}
	$fields_cnt = count($result[0]);

	$schema_insert = '';
	$keys = array_keys($result[0]);

	foreach ($keys as $fieldname) {
		$l = $csv_enclosed . str_replace(
			$csv_enclosed,
			$csv_escaped . $csv_enclosed,
			stripslashes($fieldname)
		) . $csv_enclosed;
		$schema_insert .= $l;
		$schema_insert .= $csv_separator;
		//echo $fieldname;
	} // end for

	$out = trim(substr($schema_insert, 0, -1));
	$out .= $csv_terminated;

	// Format the data
	foreach ($result as $row) {
		$schema_insert = '';
		$j = 0;
		foreach ($row as $value) {
			if ($value == '0' || $value != '') {

				if ($csv_enclosed == '') {
					$schema_insert .= $value;
				} else {
					$schema_insert .= $csv_enclosed .
						str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $value) . $csv_enclosed;
				}
			} else {
				$schema_insert .= '';
			}

			if ($j < $fields_cnt - 1) {
				$schema_insert .= $csv_separator;
			}
			$j++;
		} // end for

		$out .= $schema_insert;
		$out .= $csv_terminated;
	} // end while
	return $out;
}

function CreateOrdering($tables, $orderby)
{
	if (!isset($orderby)) {
		return "";
	}
	if (!is_array($orderby)) {
		$check = array($orderby => "ASC");
	} elseif (is_array(current($orderby))) {
		$check = current($orderby);
	} else {
		$check = $orderby;
	}

	if (is_array($tables)) {
		foreach ($tables as $table => $alias) {
			$fields[$alias] = GetTableColumns($table);
		}
	} else {
		$fields[$tables] = GetTableColumns($tables);
	}
	$ret = "";
	foreach ($orderby as $field => $direction) {
		if (is_numeric($field)) {
			$field = $direction;
			$direction = "ASC";
		} else {
			$direction = strtoupper($direction);
		}
		if ($direction != "DESC" && $direction != "ASC") {
			die("Invalid ordering direction '" . $direction . "'");
		}
		$tableAndField = explode(".", $field, 2);
		if (count($tableAndField) != 2) {
			die("Table alias missing from field '" . $field . "'");
		}
		$table = $tableAndField[0];
		$field = $tableAndField[1];
		if (!isset($fields[$table])) {
			die("Unknown table alias '" . $table . "'. Available aliases are: " . print_r(array_keys($fields), true));
		}
		$tableFields = $fields[$table];

		if (!isset($tableFields[$field])) {
			die("Invalid field '" . $field . "'");
		}
		if (isset($table)) {
			$field = $table . "." . $field;
		}
		$ret .= ", " . $field . " " . $direction;
	}
	if (strlen($ret) > 0) {
		$ret = "ORDER BY" . substr($ret, 1);
	}
	return $ret;
}

function CreateFilter($tables, $filter)
{
	if (!isset($filter) || !is_array($filter) || (count($filter) == 0)) {
		return "";
	}
	$fields = array();
	if (is_array($tables)) {
		foreach ($tables as $table => $alias) {
			$fields[$alias] = GetTableColumns($table);
		}
	} else {
		$fields[$tables] = GetTableColumns($tables);
	}
	if (isset($filter["field"])) {
		$ret = _handleCriteria($filter, $fields);
	} elseif (isset($filter["join"])) {
		$ret = _handleJoin($filter, $fields);
	} elseif (is_array(current($filter))) {
		$ret = _handleArray(current($filter), $fields);
	}
	if (strlen(trim(preg_replace('/[\(\)]/', '', $ret))) > 0) {
		$ret = "WHERE " . $ret;
	} else {
		$ret = "";
	}
	return $ret;
}

function _handleArray($filter, $fields)
{
	if (isset($filter["join"])) {
		return _handleJoin($filter, $fields);
	} elseif (isset($filter["field"])) {
		return _handleCriteria($filter, $fields);
	} elseif (is_array(current($filter))) {
		return _handleArray(current($filter), $fields);
	}
}

function _handleJoin($filter, $fields)
{
	if (!(isset($filter['join']) && isset($filter['criteria']) && is_array($filter['criteria']))) {
		die("Invalid join " . print_r($filter, true));
	}
	$operator = strtoupper($filter['join']);
	if ($operator != "AND" && $operator != "OR") {
		die("Invalid join operator '" . $operator . "'");
	}
	$criteria = array();
	foreach ($filter['criteria'] as $next) {
		if (isset($next["join"])) {
			$criteria[] = _handleJoin($next, $fields);
		} elseif (isset($next["field"])) {
			$criteria[] = _handleCriteria($next, $fields);
		} elseif (is_array(current($next))) {
			$criteria[] = _handleArray(current($next), $fields);
		}
	}
	return "(" . implode(") " . $operator . " (", $criteria) . ")";
}

function _handleCriteria($filter, $fields)
{
	if (!(isset($filter['field']) && isset($filter['operator']) && isset($filter['value']))) {
		die("Invalid criteria " . print_r($filter, true));
	}
	$field = $filter['field'];
	if (is_array($field) && isset($field['function'])) {
		$fieldAndType = _handleFunction($field, $fields);
		$field = current($fieldAndType);
		$type = next($fieldAndType);
	} elseif (is_array($field) && isset($field['constanttype'])) {
		$fieldAndType = _handleConstant($field);
		$field = current($fieldAndType);
		$type = next($fieldAndType);
	} else {
		$fieldAndType = _handleFieldName($field, $fields);
		$field = current($fieldAndType);
		$type = next($fieldAndType);
	}

	$operator = strtoupper($filter['operator']);
	if (
		$operator != "=" && $operator != ">" && $operator != ">=" && $operator != "<" && $operator != "<=" && $operator != "!="
		&& $operator != "LIKE" && $operator != "IN" && $operator != "SUBSELECT"
	) {
		die("Invalid comparison operator '" . $operator . "'");
	}
	if ($operator == "SUBSELECT") {
		$start = $field . " IN ";
	} else {
		$start = $field . " " . $operator . " ";
	}
	$query = $start . _handleLiteral($operator, $type, $filter['value']);
	return $query;
}

function _handleConstant($field)
{
	if (!(isset($field['constanttype']) && isset($field['value']))) {
		die("Invalid constant " . print_r($field, true));
	}
	$type = $field['constanttype'];
	$value = _handleLiteral("=", $type, $field['value']);
	return array($value, $type);
}

function _handleLiteral($operator, $type, $value)
{
	if (is_array($value) && isset($value['variable'])) {
		$value = _handleVariable($value);
	}
	if ($operator == "IN") {
		if ($type == "int") {
			return "(" . DBEscapeString($value) . ")";
		} else {
			// split a string at unescaped comma
			// where backslash is the escape character
			$splitter = "/\\,((?:[^\\\\,]|\\\\.)*)/";
			preg_match_all($splitter, "," . $value, $aPieces, PREG_PATTERN_ORDER);
			$aPieces = $aPieces[1];

			// $aPieces now contains the exploded string
			// and unescaping can be safely done on each piece
			foreach ($aPieces as $idx => $piece) {
				$aPieces[$idx] = DBEscapeString(preg_replace("/\\\\(.)/s", "$1", $piece));
			}
			return "('" . implode("', '", $aPieces) . "')";
		}
	} else if ($operator == "SUBSELECT") {
		if (!(is_array($value) && isset($value['table']) &&
			isset($value['field']) && isset($value['join']) &&
			isset($value['criteria']))) {
			die("Invalid SUBSELECT '" . print_r($value, true) . "'");
		}
		$table = $value['table'];
		$columns = GetTableColumns("uo_" . $table);
		if (count($columns) < 1) {
			die("Invalid SUBSELECT table '" . $table . "'");
		}
		$field = $value['field'];
		$fields = array($table => $columns);
		if (is_array($field)) {
			$fieldAndType = _handleFunction($field, $fields);
			$field = current($fieldAndType);
			$type = next($fieldAndType);
		} else {
			$fieldAndType = _handleFieldName($field, $fields);
			$field = current($fieldAndType);
			$type = next($fieldAndType);
		}
		$join = _handleJoin($value, $fields);
		return "(SELECT " . $field . " FROM uo_" . $table . " " . $table . " WHERE " . $join . ")";
	} else if ($type == "int") {
		return intval($value);
	} else {
		return "'" . DBEscapeString($value) . "'";
	}
}

function _handleFieldName($field, $fields)
{
	$tableAndField = explode(".", $field, 2);
	if (count($tableAndField) != 2) {
		die("Table alias missing from field '" . $field . "'");
	}
	$table = $tableAndField[0];
	$field = $tableAndField[1];
	if (!isset($fields[$table])) {
		die("Unknown table alias '" . $table . "'. Available aliases are: " . print_r(array_keys($fields), true));
	}
	$tableFields = $fields[$table];

	if (!isset($tableFields[$field])) {
		die("Invalid field '" . $field . "'");
	}
	$type = $tableFields[$field];
	if (isset($table)) {
		$field = $table . "." . $field;
	}
	return array($field, $type);
}

function _handleFunction($filter, $fields)
{
	if (!(isset($filter['function']) && isset($filter['args'])
		&& is_array($filter['args']) && isset($filter['returntype']))) {
		die("Invalid function " . print_r($filter, true));
	}
	$func = strtoupper($filter['function']);
	if (!preg_match('/^([0-9A-Z_])*$/', $func)) {
		die("Invalid function name '" . $func . "'");
	}
	$args = $filter['args'];
	$finalArgs = array();
	foreach ($args as $nextarg) {
		if (!is_array($nextarg)) {
			die("Invalid function argument " . $nextarg);
		}
		if (isset($nextarg['field'])) {
			$fieldAndType = _handleFieldName($nextarg['field'], $fields);
			$finalArgs[] = current($fieldAndType);
		} elseif (isset($nextarg['value']) && isset($nextarg['type'])) {
			$finalArgs[] = _handleLiteral("=", $nextarg['type'], $nextarg['value']);
		} else {
			die("Invalid function argument " . $nextarg);
		}
	}
	$ret = $func . "(" . implode(', ', $finalArgs) . ")";
	return array($ret, $filter['returntype']);
}

function _handleVariable($value)
{
	if (!(isset($value['variable']))) {
		//die("Invalid variable ".print_r($value, true));
		return "";
	}

	$varname = $value['variable'];
	if (!(isset($GLOBALS[$varname]) || isset($_SESSION[$varname]))) {
		//die("Variable unavailable '".$varname."'");
		return "";
	}
	if (isset($_SESSION[$varname])) {
		$varvalue = $_SESSION[$varname];
	} else {
		$varvalue = $GLOBALS[$varname];
	}

	if (is_array($varvalue)) {
		$i = 1;
		while (isset($value['key' . $i])) {
			if (isset($varvalue[$value['key' . $i]])) {
				$varvalue = $varvalue[$value['key' . $i]];
			} else {
				//die("Key ".$value['key'.$i]." not set for variable ".$varname);
				return "";
			}
			$i++;
		}
	}

	if (is_array($varvalue)) {
		if (isset($value['implode-keys'])) {
			$keys = array_keys($varvalue);
			$varvalue = implode($value['implode-keys'], $keys);
		}
		if (isset($value['implode-values'])) {
			$varvalue = implode($value['implode-values'], $varvalue);
		}
	}

	while (is_array($varvalue)) {
		$varvalue = current($varvalue);
	}

	return $varvalue;
}


function GetTableColumns($table)
{
	global $include_prefix;
	$ret = array();
	$result = DBQuery(sprintf(
		"SELECT * FROM %s WHERE 1=0",
		DBEscapeString($table)
	));
	$fields = mysqli_num_fields($result);
	for ($i = 0; $i < $fields; $i++) {
		$name  = strtolower(mysqli_fetch_field_direct($result, $i)->name);
		$ret[$name] = mysqli_fetch_field_direct($result, $i)->name;
	}
	return $ret;
}

/**
 * make a recursive copy of an array
 *
 * @param array $aSource
 * @return array    copy of source array
 */

function array_copy($aSource)
{
	// check if input is really an array
	if (!is_array($aSource)) {
		die("Input is not an Array");
	}

	// initialize return array
	$aRetAr = array();

	// get array keys
	$aKeys = array_keys($aSource);
	// get array values
	$aVals = array_values($aSource);

	// loop through array and assign keys+values to new return array
	for ($x = 0; $x < count($aKeys); $x++) {
		// clone if object
		if (is_object($aVals[$x])) {
			$aRetAr[$aKeys[$x]] = clone ($aVals[$x]);
			// recursively add array
		} elseif (is_array($aVals[$x])) {
			$aRetAr[$aKeys[$x]] = array_copy($aVals[$x]);
			// assign just a plain scalar value
		} else {
			$aRetAr[$aKeys[$x]] = $aVals[$x];
		}
	}

	return $aRetAr;
}

function startsWith($haystack, $needle)
{
	// search backwards starting from haystack length characters from the end
	return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
}


/**
 * returns the English ordinal of an integer
 *
 * @param  integer   a number
 * @return string    the ordinal number in the current locale
 */

function ordinal($number)
{
	if (class_exists("NumberFormatter")) {
		$nf = new NumberFormatter(GetSessionLocale(), NumberFormatter::ORDINAL);
		return $nf->format($number);
	}
	if (startsWith(GetSessionLocale(), 'en')) {
		// check if we can handle the size of the number
		$ordinal = $number;
		switch ($number % 10) {
			case 1:
				$ordinal .= "st";
				break;
			case 2:
				$ordinal .= "nd";
				break;
			case 3:
				$ordinal .= "rd";
				break;
			default:
				$ordinal .= "th";
				break;
		}
		return $ordinal;
	} else {
		return $number . ".";
	}
}


if (!function_exists('str_getcsv')) {
	function str_getcsv($input, $delimiter = ',', $enclosure = '"', $escape = null, $eol = null)
	{
		$temp = fopen("php://memory", "rw");
		fwrite($temp, $input);
		fseek($temp, 0);
		$r = fgetcsv($temp, 4096, $delimiter, $enclosure);
		fclose($temp);
		return $r;
	}
}
function strEndsWith($whole, $end)
{
	return (strpos($whole, $end, strlen($whole) - strlen($end)) !== false);
}

/**
 * 
 * Returns string from $_GET by ignoring case. 
 * @param string $string
 */
function iget($string)
{

	if (!empty($_GET[$string])) {
		return urldecode($_GET[$string]);
	}

	$string = strtolower($string);

	if (!empty($_GET[$string])) {
		return urldecode($_GET[$string]);
	}

	$string = ucfirst($string);

	if (!empty($_GET[$string])) {
		return urldecode($_GET[$string]);
	}

	$string = strtoupper($string);

	if (!empty($_GET[$string])) {
		return urldecode($_GET[$string]);
	}

	return "";
}

/**
 * Safely resolve a view script name to an includeable path inside a base directory.
 *
 * @param string $view Raw view name from request
 * @param string $baseDir Directory that contains the view files
 * @param string $default Default view name to fall back to
 * @param array $deny List of disallowed view names (without .php)
 * @return string Full path to the resolved view file
 */
function resolveViewPath($view, $baseDir, $default = 'frontpage', $deny = array())
{
	// Default when empty
	if (!$view) {
		$view = $default;
	}

	// Basic format check (allow subdirectories, block traversal) and deny-list
	if (strpos($view, '..') !== false || !preg_match('/^[a-z0-9_\\/\\-]+$/i', $view) || in_array($view, $deny, true)) {
		http_response_code(400);
		$view = $default;
	}

	$baseDirReal = rtrim(realpath($baseDir), DIRECTORY_SEPARATOR);
	$target = $baseDirReal . '/' . $view . '.php';

	// Ensure file exists and is within base dir
	$targetReal = realpath($target);
	if ($targetReal === false || strpos($targetReal, $baseDirReal) !== 0 || !is_file($targetReal)) {
		http_response_code(404);
		$view = $default;
		$targetReal = $baseDirReal . '/' . $view . '.php';
	}

	return $targetReal;
}

/**
 *  HTML escapes a string but leaves some tags intact 
 */
function someHTML($string)
{
	$string = utf8entities($string);
	$string = str_replace("&lt;b&gt;", "<b>", $string);
	$string = str_replace("&lt;/b&gt;", "</b>", $string);
	$string = str_replace("&lt;i&gt;", "<i>", $string);
	$string = str_replace("&lt;/i&gt;", "</i>", $string);
	$string = str_replace("&lt;em&gt;", "<em>", $string);
	$string = str_replace("&lt;/em&gt;", "</em>", $string);
	$string = str_replace("&lt;br&gt;", "<br />", $string);
	$string = str_replace("&lt;br /&gt;", "<br />", $string);
	$string = str_replace("&lt;br/&gt;", "<br />", $string);
	return $string;
}

/**
 * Returns the raw form of a comment field.
 * 
 * @param int $type The type of entity. 1: season, 2: series, 3: pool.
 * @param string $id The id of the season, series, or pool.
 * @return string the comment or an empty string if no comment exists.
 */
function CommentRaw($type, $id)
{
	$query = sprintf(
		"SELECT comment FROM uo_comment
		WHERE type='%d' AND id='%s'",
		(int) $type,
		DBEscapeString($id)
	);
	$comment = DBQueryToValue($query);
	if ($comment != -1)
		return $comment;
	else
		return "";
}

/**
 * Returns a comment field, with most of the html-tags and entities encoded.
 * 
 * @param int $type The type of entity. 1: season, 2: series, 3: pool.
 * @param string $id The id of the season, series, or pool.
 * @return string the comment or an empty string if no comment exists.
 */
function CommentHTML($type, $id)
{
	$comment = CommentRaw($type, $id);
	if ($comment != -1)
		return "<div class='comment'>" . someHTML($comment) . "</div>\n";
	else
		return "";
}

/**
 * Sets or deletes a comment.
 *
 * @param int $type The type of entity. 1: season, 2: series, 3: pool.
 * @param string $id The id of the season, series, or pool.
 * @param string $comment the new value or an empty string or null if the comment should be deleted.
 * @return true if the query was successfull, false otherwise 
 */
function SetComment($type, $id, $comment)
{
	if (empty($comment))
		$query = sprintf(
			"DELETE FROM uo_comment WHERE type='%d' AND id='%s'",
			(int) $type,
			DBEscapeString($id)
		);
	else {
		$query = sprintf(
			"INSERT INTO uo_comment
  				(type, id, comment) 
  				VALUES	(%d,'%s','%s') ON DUPLICATE KEY UPDATE comment='%s'",
			(int) $type,
			DBEscapeString($id),
			DBEscapeString($comment),
			DBEscapeString($comment)
		);
	}
	return DBQuery($query);
}

/**
 * Has the same interface as usort, but provides a stable sort, i.e., if two members compare equal in cmp_function, their relative order is not changed with respect to the original order.
 * 
 * @param array $array
 *          The input array.
 * @param mixed $cmp_function
 *          The comparison function must return an integer less than, equal to, or greater than zero if the first argument is considered to be respectively less than, equal to, or greater than the second: int callback ( mixed $a, mixed $b )
 */
function mergesort(&$array, $cmp_function = 'strcmp')
{
	// Arrays of size < 2 require no action.
	if (count($array) < 2) return;
	// Split the array in half
		$halfway = (int) floor(count($array) / 2);
	$array1 = array_slice($array, 0, $halfway);
	$array2 = array_slice($array, $halfway);
	// Recurse to sort the two halves
	mergesort($array1, $cmp_function);
	mergesort($array2, $cmp_function);
	// If all of $array1 is <= all of $array2, just append them.
	if (call_user_func($cmp_function, end($array1), $array2[0]) < 1) {
		$array = array_merge($array1, $array2);
		return;
	}
	// Merge the two sorted arrays into a single sorted array
	$array = array();
	$ptr1 = $ptr2 = 0;
	while ($ptr1 < count($array1) && $ptr2 < count($array2)) {
		if (call_user_func($cmp_function, $array1[$ptr1], $array2[$ptr2]) < 1) {
			$array[] = $array1[$ptr1++];
		} else {
			$array[] = $array2[$ptr2++];
		}
	}
	// Merge the remainder
	while ($ptr1 < count($array1)) $array[] = $array1[$ptr1++];
	while ($ptr2 < count($array2)) $array[] = $array2[$ptr2++];
	return;
}
