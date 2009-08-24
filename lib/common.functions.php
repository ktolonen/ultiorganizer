<?php

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
	
function WeekdayString($timestamp, $cap)
	{
	//$datetime = date_create($timestamp);
	//$weekday = date_format($datetime, 'w');
	$datetime = strtotime($timestamp);
	$weekday = date('w',$datetime);	
	switch($weekday)
		{
		case 0:
			$weekday = $cap ? _("Su") : _("su");
			break;
		case 1:
			$weekday = $cap ? _("Ma") : _("ma");
			break;
		case 2:
			$weekday = $cap ? _("Ti") : _("ti");
			break;
		case 3:
			$weekday = $cap ? _("Ke") : _("ke");
			break;
		case 4:
			$weekday = $cap ? _("To") : _("to");
			break;
		case 5:
			$weekday = $cap ? _("Pe") : _("pe");
			break;
		case 6:
			$weekday = $cap ? _("La") : _("la");
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
	$datetime = strtotime($timestamp);
	$shortdate = date('j.n.Y',$datetime);
	return $shortdate;
	}

function DefWeekDateFormat($timestamp)
	{
	return WeekdayString($timestamp,true) ." ". ShortDate($timestamp);
	}
	
function DefHourFormat($timestamp)
	{
	//$datetime = date_create($timestamp);
	//$hours = date_format($datetime, 'H:i');
	$datetime = strtotime($timestamp);
	$hours = date('H:i',$datetime);

	return $hours;
	}	

function DefBirthdayFormat($timestamp)
	{
	//$datetime = date_create($timestamp);
	//$hours = date_format($datetime, 'd.m.Y');
	$datetime = strtotime($timestamp);
	$hours = date('d.m.Y',$datetime);
	return $hours;
	}	
	
function TimeToIcal($timestamp)
	{
	//$datetime = date_create($timestamp);
	//$time = date_format($datetime, 'Ymd\THi00');
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
	$format = substr_count($timestamp, ".");
	$secs = 0;
	//mm.ss
	if($format==1)
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

function GetURLBase()
	{
	//$url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
	$url = "http://".$_SERVER['SERVER_NAME'].$_SERVER['SCRIPT_NAME'];
	$cutpos = strrpos($url, "/");
	return substr($url,0,$cutpos);
	}	
?>
