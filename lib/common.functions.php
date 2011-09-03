<?php
include_once $include_prefix.'lib/HSVClass.php';


function StripFromQueryString($query_string, $needle) {
   $query_string = preg_replace("/(\&|\?)*$needle=[a-zA-Z0-9].*?(\&;|$)/", '$2',   $query_string);
   return preg_replace("/(&)+/","&",$query_string);
}


function getSessionLocale() {
	if(isset($_SESSION['userproperties']['locale']) && is_array($_SESSION['userproperties']['locale'])){
		$tmparr = array_keys($_SESSION['userproperties']['locale']);
		return $tmparr[0];
	} elseif (isset($_SESSION['userproperties']['locale'])) {
		return $_SESSION['userproperties']['locale'];
	} else {
		return DEFAULT_LOCALE;
	}
}

function SafeDivide($dividend,$divisor)
	{
	if (!isset($divisor) || is_null($divisor) || $divisor==0)
		$result = 0;
	else			
		$result = $dividend/$divisor;
	
	return $result;
	}
	
function SecToMin($sec)
	{
	$s = intval($sec);
	$str = $s % 60;
	
	if (strlen($str) == 1)
		$str = "0" . $str;
	
	$s = $s/60;
	return (intval($s).".". $str);
	}

function Hours($timestamp)
	{
	$datetime = strtotime($timestamp);
	$hours = date('H',$datetime);

	return intval($hours);
	}	

function Minutes($timestamp)
	{
	$datetime = strtotime($timestamp);
	$min = date('i',$datetime);

	return intval($min);
	}	
	
function WeekdayString($timestamp, $cap)
	{
	//$datetime = date_create($timestamp);
	//$weekday = date_format($datetime, 'w');
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$weekday = date('w',$datetime);	
	switch($weekday)
		{
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
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shortdate = date('j.n.Y',$datetime);
	return $shortdate;
	}

function ShortEnDate($timestamp)
	{
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shortdate = date('Y-m-d',$datetime);
	return $shortdate;
	}
	
function JustDate($timestamp)
	{
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shortdate = date('j.n.',$datetime);
	return $shortdate;
	}
	
function DefWeekDateFormat($timestamp)
	{
	return WeekdayString($timestamp,true) ." ". ShortDate($timestamp);
	}

function GetDefTimeZone()
	{
	//in future timezone can be added to user properties
	return DEFAULT_TIMEZONE;
	}
	
function DefHourFormat($timestamp)
	{
	//$datetime = date_create($timestamp);
	//$hours = date_format($datetime, 'H:i');
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$hours = date('H:i',$datetime);

	return $hours;
	}	

function DefTimeFormat($timestamp) {
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shorttime = date('j.n.Y H:i',$datetime);
	return $shorttime;
}

function ShortTimeFormat($timestamp)
	{
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shorttime = date('j.n. H:i',$datetime);
	return $shorttime;
	}

function LongTimeFormat($timestamp)
	{
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$shorttime = date('j.n.Y H:i:s',$datetime);
	return $shorttime;
	}

function ISOTimeFormat($timestamp) {
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$timestr = date('c',$datetime);
	return $timestr;
}

function DefBirthdayFormat($timestamp)
	{
	//$datetime = date_create($timestamp);
	//$hours = date_format($datetime, 'd.m.Y');
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$hours = date('d.m.Y',$datetime);
	return $hours;
	}	
	
function TimeToIcal($timestamp)
	{
	//$datetime = date_create($timestamp);
	//$time = date_format($datetime, 'Ymd\THi00');
	if(empty($timestamp))
		return "";
	$datetime = strtotime($timestamp);
	$time = date('Ymd\THi00',$datetime);
	return $time;
	}

function DefTimestamp()
	{
	return date( 'H:i:s', time());
	}
	
function TimeToSec($timestamp)
	{
	if(empty($timestamp))
		return 0;
	$format = substr_count($timestamp, ".");
	$secs = 0;

	//mm
	if($format==0)
		{
		$secs = intval($timestamp)*60;
		}
	//mm.ss
	elseif($format==1)
		{
		$tmp1 = strtok($timestamp, ".");
		$tmp2 = strtok(".");
		$secs = intval($tmp1)*60+intval($tmp2);
		}
	//hh.mm.ss
	elseif($format==2)
		{
		$tmp1 = strtok($timestamp, ".");
		$tmp2 = strtok(".");
		$tmp3 = strtok(".");
		$secs = intval($tmp1)*3600+intval($tmp2)*60+intval($tmp3);
		}
	return $secs;
	}
	
function ToInternalTimeFormat($timestamp) {
	if(empty($timestamp)) {
		$timestamp="1.1.1971 00:00"; //safer option since depending of timezone 1.1.1970 can cause error
	} else if (!strptime($timestamp, DATE_FORMAT)) {
		if (!strptime($timestamp." 00:00", DATE_FORMAT)) {
			$timestamp="1.1.1971 00:00"; //safer option since depending of timezone 1.1.1970 can cause error
		} else {
			$timestamp .= " 00:00";
		}
	}
	$datearr = strptime($timestamp, DATE_FORMAT);
	return (1900 + $datearr['tm_year'])."-".($datearr['tm_mon'] + 1)."-".$datearr['tm_mday']." ".$datearr['tm_hour'].":".$datearr['tm_min'].":00";
}

function isEmptyDate($timestamp){
	if(empty($timestamp)){
	 return true;
	}
	$datetime = strtotime($timestamp);
	$time = date('j.n.Y H:i',$datetime);
	
	if(strcmp("1.1.1971 00:00",$time)==0){
		return true;
	}
	if(strcmp("1.1.1970 00:00",$time)==0){
		return true;
	}
	
	return false;
}

function EpocToMysql($epoc) {
	return date('Y-m-d H:i:s',$epoc);
}


function GetScriptName() {
	if(isset($_SERVER['SCRIPT_NAME'])) {
		return $_SERVER['SCRIPT_NAME'];
	}elseif(isset($_SERVER['PHP_SELF'])) {
		return $_SERVER['PHP_SELF'];
	}elseif(isset($_SERVER['PATH_INFO '])) {
		return $_SERVER['PATH_INFO '];
	}else{
		die("Cannot find page address");
	}
}

function GetURLBase()
	{
	$url = "http://";
	$url .= GetServerName();
	$url .= GetScriptName();
	
	$cutpos = strrpos($url, "/");
	$url = substr($url,0,$cutpos);
	global $include_prefix;
	if (strlen($include_prefix) > 0) {
		$updirs = explode($include_prefix, "/");
		foreach ($updirs as $dotdot) {
			$cutpos = strrpos($url, "/");
			$url = substr($url,0,$cutpos);
		}
	}
	return $url;
}

function GetLocale() {
	$locale = DEFAULT_LOCALE;
	if (isset($_GET['locale'])) {
		$locale = $_GET['locale'];
	} else if(isset($_SESSION['userproperties']['locale'])){
		$tmparr = array_keys($_SESSION['userproperties']['locale']);
		$locale = $tmparr[0];
	}
	return $locale;
}
	
function GetW3CLocale() {
	$locale = GetLocale();
	$locale=str_replace("_",'-',$locale);
	return $locale;
}	

function validEmail($email) {
   $isValid = true;
   $atIndex = strrpos($email, "@");
   if (is_bool($atIndex) && !$atIndex)
   {
      $isValid = false;
   }
   else
   {
      $domain = substr($email, $atIndex+1);
      $local = substr($email, 0, $atIndex);
      $localLen = strlen($local);
      $domainLen = strlen($domain);
      if ($localLen < 1 || $localLen > 64)
      {
         // local part length exceeded
         $isValid = false;
      }
      else if ($domainLen < 1 || $domainLen > 255)
      {
         // domain part length exceeded
         $isValid = false;
      }
      else if ($local[0] == '.' || $local[$localLen-1] == '.')
      {
         // local part starts or ends with '.'
         $isValid = false;
      }
      else if (preg_match('/\\.\\./', $local))
      {
         // local part has two consecutive dots
         $isValid = false;
      }
      else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))
      {
         // character not valid in domain part
         $isValid = false;
      }
      else if (preg_match('/\\.\\./', $domain))
      {
         // domain part has two consecutive dots
         $isValid = false;
      }
      else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/',
                 str_replace("\\\\","",$local)))
      {
         // character not valid in local part unless 
         // local part is quoted
         if (!preg_match('/^"(\\\\"|[^"])+"$/',
             str_replace("\\\\","",$local)))
         {
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

function is_odd($number) {
   return $number & 1; // 0 = even, 1 = odd
}

function textColor($bgcolor) {
	$hsv = new HSVClass();
	$hsv->setRGBString($bgcolor);
	$hsv->changeHue(180);
	$hsvArr = $hsv->getHSV();
	$hsv->setHSV($hsvArr['h'], 1-$hsvArr['s'],1-$hsvArr['v']);
	return $hsv->getRGBString();
}

function RGBtoRGBa($rgbstring, $alpha) {
	$r = $rgbstring[0].$rgbstring[1];
    $g = $rgbstring[2].$rgbstring[3];
    $b = $rgbstring[4].$rgbstring[5];
        
   $r = hexdec($r);
   $g = hexdec($g);
   $b = hexdec($b);
   return "rgba($r,$g,$b,$alpha)";
}

function getChkNum($sNum) {
  $multipliers = array(7,3,1);
  $sNumLen = strlen($sNum);
  $sNum = str_split($sNum);
  $chkSum = 0;
  for ($i = $sNumLen - 1; $i >= 0; --$i) {
    $chkSum += $sNum[$i] * $multipliers[($sNumLen - 1 - $i) % 3];
  }
  return (10 - $chkSum % 10) % 10;
}

function checkChkNum($sNum) {
	$chk = substr($sNum, -1);
	$chkStr = substr($sNum, 0, -1);
	return $chk == getChkNum($chkStr);
}

if(!function_exists('str_split')) {
  function str_split($string, $split_length = 1) {
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
 * Parse a time/date generated with strftime().
 *
 * This function is the same as the original one defined by PHP (Linux/Unix only),
 *  but now you can use it on Windows too.
 *  Limitation : Only this format can be parsed %S, %M, %H, %d, %m, %Y
 * 
 * @author Lionel SAURON
 * @version 1.0
 * @public
 * 
 * @param $sDate(string)    The string to parse (e.g. returned from strftime()).
 * @param $sFormat(string)  The format used in date  (e.g. the same as used in strftime()).
 * @return (array)          Returns an array with the <code>$sDate</code> parsed, or <code>false</code> on error.
 */
if(function_exists("strptime") == false)
{
    function strptime($sDate, $sFormat)
    {
        $aResult = array
        (
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
        
        while($sFormat != "")
        {
            // ===== Search a %x element, Check the static string before the %x =====
            $nIdxFound = strpos($sFormat, '%');
            if($nIdxFound === false)
            {
                
                // There is no more format. Check the last static string.
                $aResult['unparsed'] = ($sFormat == $sDate) ? "" : $sDate;
                break;
            }
            
            $sFormatBefore = substr($sFormat, 0, $nIdxFound);
            $sDateBefore   = substr($sDate,   0, $nIdxFound);
            
            if($sFormatBefore != $sDateBefore) break;
            
            // ===== Read the value of the %x found =====
            $sFormat = substr($sFormat, $nIdxFound);
            $sDate   = substr($sDate,   $nIdxFound);
            
            $aResult['unparsed'] = $sDate;
            
            $sFormatCurrent = substr($sFormat, 0, 2);
            $sFormatAfter   = substr($sFormat, 2);
            
            $nValue = -1;
            $sDateAfter = "";
            
            switch($sFormatCurrent)
            {
                case '%S': // Seconds after the minute (0-59)
                    
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
                    
                    if(($nValue < 0) || ($nValue > 59)) return false;
                    
                    $aResult['tm_sec']  = $nValue;
                    break;
                
                // ----------
                case '%M': // Minutes after the hour (0-59)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
                    
                    if(($nValue < 0) || ($nValue > 59)) return false;
                
                    $aResult['tm_min']  = $nValue;
                    break;
                
                // ----------
                case '%H': // Hour since midnight (0-23)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
                    
                    if(($nValue < 0) || ($nValue > 23)) return false;
                
                    $aResult['tm_hour']  = $nValue;
                    break;
                
                // ----------
                case '%d': // Day of the month (1-31)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
                    
                    if(($nValue < 1) || ($nValue > 31)) return false;
                
                    $aResult['tm_mday']  = $nValue;
                    break;
                
                // ----------
                case '%m': // Months since January (0-11)
                    sscanf($sDate, "%2d%[^\\n]", $nValue, $sDateAfter);
                    
                    if(($nValue < 1) || ($nValue > 12)) return false;
                
                    $aResult['tm_mon']  = ($nValue - 1);
                    break;
                
                // ----------
                case '%Y': // Years since 1900
                    sscanf($sDate, "%4d%[^\\n]", $nValue, $sDateAfter);
                    
                    if($nValue < 1900) return false;
                
                    $aResult['tm_year']  = ($nValue - 1900);
                    break;
                
                // ----------
                default:
                    break 2; // Break Switch and while
                
            } // END of case format
            
            // ===== Next please =====
            $sFormat = $sFormatAfter;
            $sDate   = $sDateAfter;
            
            $aResult['unparsed'] = $sDate;
            
        } // END of while($sFormat != "")
        
        // ===== Create the other value of the result array =====
        $nParsedDateTimestamp = mktime($aResult['tm_hour'], $aResult['tm_min'], $aResult['tm_sec'],
                                $aResult['tm_mon'] + 1, $aResult['tm_mday'], $aResult['tm_year'] + 1900);
        
        // Before PHP 5.1 return -1 when error
        if(($nParsedDateTimestamp === false)
        ||($nParsedDateTimestamp === -1)) return false;
        
        $aResult['tm_wday'] = (int) strftime("%w", $nParsedDateTimestamp); // Days since Sunday (0-6)
        $aResult['tm_yday'] = (strftime("%j", $nParsedDateTimestamp) - 1); // Days since January 1 (0-365)

        return $aResult;
    } // END of function
    
} // END of if(function_exists("strptime") == false) 

if(!function_exists("stripos")){
    function stripos(  $str, $needle, $offset = 0  ){
        return strpos(  strtolower( $str ), strtolower( $needle ), $offset  );
    }/* endfunction stripos */
}/* endfunction exists stripos */

function recur_mkdirs($path, $mode = 0775){
    $dirs = explode('/',$path);
    $pos = strrpos($path, ".");
    if ($pos === false) { // note: three equal signs
       // not found, means path ends in a dir not file
        $subamount=0;
    }
    else {
        $subamount=1;
    }
   
    for ($c=0;$c < count($dirs) - $subamount; $c++) {
        $thispath="";
        for ($cc=0; $cc <= $c; $cc++) {
            $thispath.=$dirs[$cc].'/';
        }
        if (!file_exists($thispath)) {
            //print "$thispath<br>";
            mkdir($thispath,$mode);
        }
    }
}

function SafeUrl($url){
	if((strtolower(substr($url,0,7))!= "http://") && (strtolower(substr($url,0,8))!= "https://")) {
		$url = "http://".$url;
	}
	return $url;

}	

function colorstring2rgb($color)
{
    if ($color[0] == '#')
        $color = substr($color, 1);

    if (strlen($color) == 6)
        list($r, $g, $b) = array($color[0].$color[1],
                                 $color[2].$color[3],
                                 $color[4].$color[5]);
    elseif (strlen($color) == 3)
        list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
    else
        return false;

    $r = hexdec($r); $g = hexdec($g); $b = hexdec($b);

    return array('r'=>$r, 'g'=>$g, 'b'=>$b);
}

# Define parse_ini_string if it doesn't exist.
# Does accept lines starting with ; as comments
# Does not accept comments after values
if( !function_exists('parse_ini_string') ){
   
    function parse_ini_string( $string ) {
        $array = Array();

        $lines = explode("\n", $string );
       
        foreach( $lines as $line ) {
            $statement = preg_match(
"/^(?!;)(?P<key>[\w+\.\-]+?)\s*=\s*(?P<value>.+?)\s*$/", $line, $match );

            if( $statement ) {
                $key    = $match[ 'key' ];
                $value    = $match[ 'value' ];
               
                # Remove quote
                if( preg_match( "/^\".*\"$/", $value ) || preg_match( "/^'.*'$/", $value ) ) {
                    $value = mb_substr( $value, 1, mb_strlen( $value ) - 2 );
                }
               
                $array[ $key ] = $value;
            }
        }
        return $array;
    }
}
if( !function_exists('array_combine') ){
function array_combine($arr1, $arr2) {
    $out = array();
   
    $arr1 = array_values($arr1);
    $arr2 = array_values($arr2);
   
    foreach($arr1 as $key1 => $value1) {
        $out[(string)$value1] = $arr2[$key1];
    }
   
    return $out;
}	
}
        
function ResultsetToCsv($result, $separator){
    $csv_terminated = "\n";
    $csv_separator = $separator;
    $csv_enclosed = '"';
    $csv_escaped = "\\";
	
    $fields_cnt = mysql_num_fields($result);
 
     $schema_insert = '';
 
    for ($i = 0; $i < $fields_cnt; $i++){
        $l = $csv_enclosed . str_replace($csv_enclosed, $csv_escaped . $csv_enclosed,
            stripslashes(mysql_field_name($result, $i))) . $csv_enclosed;
        $schema_insert .= $l;
        $schema_insert .= $csv_separator;
    } // end for
 
    $out = trim(substr($schema_insert, 0, -1));
    $out .= $csv_terminated;
 
    // Format the data
    while ($row = mysql_fetch_array($result)){
        $schema_insert = '';
        for ($j = 0; $j < $fields_cnt; $j++){
            if ($row[$j] == '0' || $row[$j] != ''){
 
                if ($csv_enclosed == ''){
                    $schema_insert .= $row[$j];
                } else{
                    $schema_insert .= $csv_enclosed . 
					str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $row[$j]) . $csv_enclosed;
                }
            } else{
                $schema_insert .= '';
            }
 
            if ($j < $fields_cnt - 1){
                $schema_insert .= $csv_separator;
            }
        } // end for
 
        $out .= $schema_insert;
        $out .= $csv_terminated;
    } // end while
	return $out;
}

function ArrayToCsv($result, $separator){
    $csv_terminated = "\n";
    $csv_separator = $separator;
    $csv_enclosed = '"';
    $csv_escaped = "\\";
	
	if(count($result)==0){
		return "";
	}
    $fields_cnt = count($result[0]);
	
    $schema_insert = '';
	$keys = array_keys($result[0]);
	
    foreach($keys as $fieldname){
        $l = $csv_enclosed . str_replace($csv_enclosed, $csv_escaped . $csv_enclosed,
            stripslashes($fieldname)) . $csv_enclosed;
        $schema_insert .= $l;
        $schema_insert .= $csv_separator;
		//echo $fieldname;
    } // end for
	
    $out = trim(substr($schema_insert, 0, -1));
    $out .= $csv_terminated;
 
    // Format the data
    foreach($result as $row){
        $schema_insert = '';
		$j=0;
        foreach($row as $value){
            if ($value == '0' || $value != ''){
 
                if ($csv_enclosed == ''){
                    $schema_insert .= $value;
                } else{
                    $schema_insert .= $csv_enclosed . 
					str_replace($csv_enclosed, $csv_escaped . $csv_enclosed, $value) . $csv_enclosed;
                }
            } else{
                $schema_insert .= '';
            }
 
            if ($j < $fields_cnt - 1){
                $schema_insert .= $csv_separator;
            }
			$j++;
        } // end for
 
        $out .= $schema_insert;
        $out .= $csv_terminated;
    } // end while
	return $out;
}

function CreateOrdering($tables, $orderby) {
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
			die("Invalid ordering direction '".$direction."'");
		}
		$tableAndField = explode(".", $field, 2);
		if (count($tableAndField) != 2) {
			die("Table alias missing from field '".$field."'");
		}
		$table = $tableAndField[0];
		$field = $tableAndField[1];
		if (!isset($fields[$table])) {
				die("Unknown table alias '".$table."'. Available aliases are: ".print_r(array_keys($fields), true));
		}
		$tableFields = $fields[$table];

		if (!isset($tableFields[$field])) {
			die("Invalid field '".$field."'");
		}
		if (isset($table)) {
			$field = $table.".".$field;
		}
		$ret .= ", ".$field." ".$direction;
	}
	if (strlen($ret) > 0) {
		$ret = "ORDER BY".substr($ret, 1);
	} 
	return $ret;
}
	
function CreateFilter($tables, $filter) {
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
		$ret = "WHERE ".$ret;
	} else {
		$ret = "";
	}
	return $ret;
	
}

function _handleArray($filter, $fields) {
	if (isset($filter["join"])) {
		return _handleJoin($filter, $fields);
	} elseif (isset($filter["field"])) {
		return _handleCriteria($filter, $fields);
	} elseif (is_array(current($filter))) {
		return _handleArray(current($filter), $fields);
	}
}

function _handleJoin($filter, $fields) {
	if (!(isset($filter['join']) && isset($filter['criteria']) && is_array($filter['criteria']))) {
		die("Invalid join ".print_r($filter, true));
	}
	$operator = strtoupper($filter['join']);
	if ($operator != "AND" && $operator != "OR") {
		die("Invalid join operator '".$operator."'");
	}
	$criteria = array();
	foreach($filter['criteria'] as $next) {
		if (isset($next["join"])) {
			$criteria[] = _handleJoin($next, $fields);
		} elseif (isset($next["field"])) {
			$criteria[] = _handleCriteria($next, $fields);
		} elseif (is_array(current($next))) {
			$criteria[] = _handleArray(current($next), $fields);
		}
	}
	return "(".implode(") ".$operator." (", $criteria).")";
}

function _handleCriteria($filter, $fields) {
	if (!(isset($filter['field']) && isset($filter['operator']) && isset($filter['value']))) {
		die("Invalid criteria ".print_r($filter, true));
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
	if ($operator != "=" && $operator != ">" && $operator != ">=" && $operator != "<" && $operator != "<=" && $operator != "!="
		&& $operator != "LIKE" && $operator != "IN" && $operator != "SUBSELECT") {
		die("Invalid comparison operator '".$operator."'");		
	}
	if ($operator == "SUBSELECT") {
		$start = $field." IN ";
	} else {
		$start = $field." ".$operator." ";	
	}
	$query = $start._handleLiteral($operator, $type, $filter['value']);
	return $query;
}

function _handleConstant($field) {
	if (!(isset($field['constanttype']) && isset($field['value']))) {
		die("Invalid constant ".print_r($field, true));
	}
	$type = $field['constanttype'];
	$value = _handleLiteral("=", $type, $field['value']);
	return array($value, $type);
}

function _handleLiteral($operator, $type, $value) {
	if (is_array($value) && isset($value['variable'])) {
		$value = _handleVariable($value);
	}
	if ($operator == "IN") {
		if ($type == "int") {
			return "(".mysql_real_escape_string($value).")";
		} else {
			// split a string at unescaped comma
			// where backslash is the escape character
			$splitter = "/\\,((?:[^\\\\,]|\\\\.)*)/";
			preg_match_all($splitter, ",".$value, $aPieces, PREG_PATTERN_ORDER);
			$aPieces = $aPieces[1];

			// $aPieces now contains the exploded string
			// and unescaping can be safely done on each piece
			foreach ($aPieces as $idx=>$piece) {
				$aPieces[$idx] = mysql_real_escape_string(preg_replace("/\\\\(.)/s", "$1", $piece));
			}
			return "('".implode("', '",$aPieces)."')";
		}	
	} else if ($operator == "SUBSELECT") {
		if (!(is_array($value) && isset($value['table']) &&
			isset($value['field']) && isset($value['join']) &&
			isset($value['criteria']))) {
			die("Invalid SUBSELECT '".print_r($value, true)."'");
		}
		$table = $value['table'];
		$columns = GetTableColumns("uo_".$table);
		if (count($columns) < 1) {
			die("Invalid SUBSELECT table '".$table."'");
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
		return "(SELECT ".$field." FROM uo_".$table." ".$table." WHERE ".$join.")";
	} else if ($type == "int") {
		 return intval($value);
	} else {
		 return "'".mysql_real_escape_string($value)."'";
	}
}

function _handleFieldName($field, $fields) {
	$tableAndField = explode(".", $field, 2);
	if (count($tableAndField) != 2) {
		die("Table alias missing from field '".$field."'");
	}
	$table = $tableAndField[0];
	$field = $tableAndField[1];
	if (!isset($fields[$table])) {
			die("Unknown table alias '".$table."'. Available aliases are: ".print_r(array_keys($fields), true));
	}
	$tableFields = $fields[$table];
	
	if (!isset($tableFields[$field])) {
		die("Invalid field '".$field."'");
	}
	$type = $tableFields[$field];
	if (isset($table)) {
		$field = $table.".".$field;
	}
	return array($field, $type);
}

function _handleFunction($filter, $fields) {
	if (!(isset($filter['function']) && isset($filter['args']) 
		&& is_array($filter['args']) && isset($filter['returntype']))) {
		die("Invalid function ".print_r($filter, true));
	}
	$func = strtoupper($filter['function']);
	if (!preg_match('/^([0-9A-Z_])*$/', $func)) {
		die("Invalid function name '".$func."'");
	}
	$args = $filter['args'];
	$finalArgs = array();
	foreach ($args as $nextarg) {
		if (!is_array($nextarg)) {
			die("Invalid function argument ".$nextarg);
		}
		if (isset($nextarg['field'])) {
			$fieldAndType = _handleFieldName($nextarg['field'], $fields); 
			$finalArgs[] = current($fieldAndType);
		} elseif (isset($nextarg['value']) && isset($nextarg['type'])) {
			$finalArgs[] = _handleLiteral("=", $nextarg['type'], $nextarg['value']);			
		} else {
			die("Invalid function argument ".$nextarg);
		}
	}
	$ret = $func."(".implode(', ', $finalArgs).")";
	return array($ret, $filter['returntype']);
}

function _handleVariable($value) {
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
		$i=1;
		while (isset($value['key'.$i])) {
			if (isset($varvalue[$value['key'.$i]])) {
				$varvalue = $varvalue[$value['key'.$i]];
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
			$varvalue = implode($value['implode-keys'],$keys);
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


function GetTableColumns($table) {
	global $include_prefix;
	if (is_file($include_prefix."lib/table-definition-cache/tables_".DB_VERSION.".php")) {
		include $include_prefix."lib/table-definition-cache/tables_".DB_VERSION.".php";
		global $tables;
		if (isset($tables) && isset($tables[$table])) {
			return $tables[$table];
		}
	}
	$ret = array();
	$result = DBQuery(sprintf("SELECT * FROM %s WHERE 1=0", 
		mysql_real_escape_string($table)));
	$fields = mysql_num_fields($result);
	for ($i=0; $i < $fields; $i++) {
	    $name  = strtolower(mysql_field_name($result, $i));
		$ret[$name] = mysql_field_type($result, $i);
	}
	return $ret;
}

/**
 * make a recursive copy of an array
 *
 * @param array $aSource
 * @return array    copy of source array
 */

function array_copy ($aSource) {
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
	for ($x=0;$x<count($aKeys);$x++) {
		// clone if object
		if (is_object($aVals[$x])) {
			$aRetAr[$aKeys[$x]]=clone($aVals[$x]);
			// recursively add array
		} elseif (is_array($aVals[$x])) {
			$aRetAr[$aKeys[$x]]=array_copy ($aVals[$x]);
			// assign just a plain scalar value
		} else {
			$aRetAr[$aKeys[$x]]=$aVals[$x];
		}
	}
	 
	return $aRetAr;
}

/**
 * returns the English ordinal of an integer
 *
 * @param  integer   a number
 * @return string    the ordinal number in English 
 */

function ordinal($number) {
	  //check if we can handle the size of the number
	  If ($number < 1 || $number > 99){
	      die("The number ".$number." is out of range for this sub-function to convert to an ordinal");
	  }
	  $ordinal=$number;
	  switch($number) {
	  	case 1:
	  	case 21:
	  	case 31:
	  	case 41:
	  	case 51: 
	  	case 61:
	  	case 71: 
	  	case 81: 
	  	case 91:
	    	$ordinal .= "st"; break;
	  	case 2:
	  	case 22:
	  	case 32: 
	  	case 42: 
	  	case 52:
	  	case 62: 
	  	case 72: 
	  	case 82: 
	  	case 92:
	    	$ordinal .= "nd"; break;
	  	case 3:
	  	case 23: 
	  	case 33: 
	  	case 43: 
	  	case 53: 
	  	case 63: 
	  	case 73: 
	  	case 83: 
	  	case 93:
	     	$ordinal .= "rd"; break;
	  	default:
	     	$ordinal .= "th"; break;
	  }
	  return $ordinal;
}


if (version_compare(PHP_VERSION, '5.0.0', '<')) {
 eval('function clone($object) {return $object;}');
}

if (!function_exists('str_getcsv')) {
function str_getcsv($input, $delimiter=',', $enclosure='"', $escape=null, $eol=null) {
  $temp=fopen("php://memory", "rw");
  fwrite($temp, $input);
  fseek($temp, 0);
  $r=fgetcsv($temp, 4096, $delimiter, $enclosure);
  fclose($temp);
  return $r;
}
}
function strEndsWith($whole, $end){
    return (strpos($whole, $end, strlen($whole) - strlen($end)) !== false);
}
?>
