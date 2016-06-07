<?php 

function CountryName($countryId){
	$query = sprintf("SELECT name FROM uo_country WHERE country_id=%d",
		(int)$countryId);
	return _(DBQueryToValue($query));
}

function CountryId($countryname){
	$query = sprintf("SELECT country_id FROM uo_country WHERE name='%s'",
		mysql_real_escape_string($countryname));
	return DBQueryToValue($query);
}


function CountryInfo($countryId){
	$query = sprintf("SELECT * FROM uo_country
		WHERE country_id = %d",
		(int)$countryId);
	$ret = DBQueryToRow($query);
	$ret['name'] = _($ret['name']);	
	return $ret;
}

function CountryList($onlyvalid=true, $onlyplayed=false){
	$query = "SELECT c.* FROM uo_country c";
	
	if($onlyplayed){
		$query .= " LEFT JOIN uo_team team ON(team.country = c.country_id)";
	}
	
	if($onlyvalid || $onlyplayed){
		$query .= " WHERE ";
	}
	
	if($onlyvalid){
		$query .= "c.valid=1";
	}
	
	if($onlyvalid && $onlyplayed){
		$query .= " AND ";
	}
	
	if($onlyplayed){
		$query .= "team.team_id IS NOT NULL";
	}
	
	$query .= " GROUP BY c.country_id ORDER BY c.name";
	
	return  DBQueryToArray($query);
}

function CountryDropList($id,$name){
	$html = "";
	$query = sprintf("SELECT country_id, name FROM uo_country WHERE valid=1 ORDER BY name");
	$result =  DBQuery($query);
	$html .= "<select class='dropdown' id='$id' name='$name'>\n";
	while($row = mysql_fetch_assoc($result)){
		$html .= "<option value='".utf8entities($row['name'])."'>".utf8entities(_($row['name']))."</option>\n";
	}
	$html .= "</select>\n";
	return $html;
}

function CountryDropListWithValues($id, $name, $selectedId, $width=""){
	$html = "";
	$style = "";
	if(!empty($width)){
	  $style = "style='width:$width'";
	}
	$query = sprintf("SELECT country_id, name FROM uo_country WHERE valid=1 ORDER BY name");
	$result =  DBQuery($query);
	$html .= "<select class='dropdown' $style id='$id' name='$name'>\n";
	$html .= "<option value='-1'></option>\n";
	
	while($row = mysql_fetch_assoc($result)){
		if($row['country_id']==$selectedId){
			$html .= "<option selected='selected' value='".utf8entities($row['country_id'])."'>".utf8entities(_($row['name']))."</option>\n";
		}else{
		$html .= "<option value='".utf8entities($row['country_id'])."'>".utf8entities(_($row['name']))."</option>\n";
		}
	}
	$html .= "</select>\n";
	return $html;
}

function CountryNumOfTeams($countryId){
	$query = sprintf("SELECT count(team.team_id) FROM uo_country c
		LEFT JOIN uo_team team ON(team.country = c.country_id)
		WHERE c.country_id=%d",
		(int)$countryId);
	return DBQueryToValue($query);
}

function CountryTeams($countryId, $season=""){
	if(empty($season)){
		$query = sprintf("SELECT ser.season, team.team_id, team.name, ser.type AS seriesname, ser.series_id, team.club 
			FROM uo_country c
			LEFT JOIN uo_team team ON(team.country = c.country_id)
			LEFT JOIN uo_series ser ON(team.series = ser.series_id)
			LEFT JOIN uo_team_stats stats ON(team.team_id = stats.team_id)
			WHERE team.country=%d AND stats.team_id IS NOT NULL ORDER BY ser.season DESC, ser.ordering, team.name",
			(int)$countryId);
	}else{
		$query = sprintf("SELECT team.team_id, team.name, ser.type AS seriesname, ser.series_id, team.club
			FROM uo_country c
			LEFT JOIN uo_team team ON(team.country = c.country_id)
			LEFT JOIN uo_series ser ON(team.series = ser.series_id)
			WHERE team.country=%d AND ser.season='%s' ORDER BY ser.ordering, team.name",
			(int)$countryId,
			mysql_real_escape_string($season));
	}
	return DBQueryToArray($query);
}
	
function RemoveCountry($countryId) {
	if(isSuperAdmin()) {
		Log2("country","delete",CountryName($countryId));
		$query = sprintf("DELETE FROM uo_country WHERE country_id=%d",
			(int)$countryId);
		return DBQuery($query);
	} else { die('Insufficient rights to remove country'); }
}

function AddCountry($name, $abbreviation, $flag) {
	if(isSuperAdmin()) {
		$query = sprintf("INSERT INTO uo_country (name, abbreviation, flagfile) 
			VALUES ('%s', '%s', '%s')",
			mysql_real_escape_string($name),
			mysql_real_escape_string($abbreviation),
			mysql_real_escape_string($flag));
			
		$result = DBQuery($query);
		$countryId = mysql_insert_id();
		Log1("country","add",$countryId);
		return $countryId;
	} else { die('Insufficient rights to add country'); }	
}

function CanDeleteCountry($countryId) {
	$query = sprintf("SELECT count(*) FROM uo_team WHERE country='%s'",
		mysql_real_escape_string($countryId));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	if (!$row = mysql_fetch_row($result)) return false;
	return ($row[0] == 0);
}

function SetCountryValidity($countryId, $valid){
	if (isSuperAdmin()){	
		$query = sprintf("UPDATE uo_country SET valid=%d WHERE country_id=%d",
				(int)$valid,
				(int)$countryId);
		return DBQuery($query);
	} else { die('Insufficient rights to set country validity'); }	
}

function CountryPools($seasonId, $countryId){
	
	$query = sprintf("SELECT pool.pool_id, pool.name, pool.continuingpool, pool.series, ser.series_id, ser.name FROM uo_pool pool
		LEFT JOIN uo_series ser ON(ser.series_id=pool.series)
		LEFT JOIN uo_team_pool tp ON(tp.pool=pool.pool_id)
		LEFT JOIN uo_team team ON(tp.team=team.team_id)
		WHERE pool.visible=1 AND ser.season='%s' AND team.country=%d
		ORDER BY ser.ordering ASC, pool.ordering ASC, pool.pool_id ASC",
			mysql_real_escape_string($seasonId),
			(int)$countryId);
	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	
	return $result;
	}
	
function GetTimeZoneArray(){
	
	//Hard coded list is easiest way and supported also with PHP 4. 
	$tz = array(  
        'Africa/Abidjan',  
        'Africa/Accra',  
        'Africa/Addis_Ababa',  
        'Africa/Algiers',  
        'Africa/Asmara',  
        'Africa/Bamako',  
        'Africa/Bangui',  
        'Africa/Banjul',  
        'Africa/Bissau',  
        'Africa/Blantyre',  
        'Africa/Brazzaville',  
        'Africa/Bujumbura',  
        'Africa/Cairo',  
        'Africa/Casablanca',  
        'Africa/Ceuta',  
        'Africa/Conakry',  
        'Africa/Dakar',  
        'Africa/Dar_es_Salaam',  
        'Africa/Djibouti',  
        'Africa/Douala',  
        'Africa/El_Aaiun',  
        'Africa/Freetown',  
        'Africa/Gaborone',  
        'Africa/Harare',  
        'Africa/Johannesburg',  
        'Africa/Kampala',  
        'Africa/Khartoum',  
        'Africa/Kigali',  
        'Africa/Kinshasa',  
        'Africa/Lagos',  
        'Africa/Libreville',  
        'Africa/Lome',  
        'Africa/Luanda',  
        'Africa/Lubumbashi',  
        'Africa/Lusaka',  
        'Africa/Malabo',  
        'Africa/Maputo',  
        'Africa/Maseru',  
        'Africa/Mbabane',  
        'Africa/Mogadishu',  
        'Africa/Monrovia',  
        'Africa/Nairobi',  
        'Africa/Ndjamena',  
        'Africa/Niamey',  
        'Africa/Nouakchott',  
        'Africa/Ouagadougou',  
        'Africa/Porto-Novo',  
        'Africa/Sao_Tome',  
        'Africa/Tripoli',  
        'Africa/Tunis',  
        'Africa/Windhoek',  
        'America/Adak',  
        'America/Anchorage',  
        'America/Anguilla',  
        'America/Antigua',  
        'America/Araguaina',  
        'America/Argentina/Buenos_Aires',  
        'America/Argentina/Catamarca',  
        'America/Argentina/Cordoba',  
        'America/Argentina/Jujuy',  
        'America/Argentina/La_Rioja',  
        'America/Argentina/Mendoza',  
        'America/Argentina/Rio_Gallegos',  
        'America/Argentina/Salta',  
        'America/Argentina/San_Juan',  
        'America/Argentina/San_Luis',  
        'America/Argentina/Tucuman',  
        'America/Argentina/Ushuaia',  
        'America/Aruba',  
        'America/Asuncion',  
        'America/Atikokan',  
        'America/Bahia',  
        'America/Barbados',  
        'America/Belem',  
        'America/Belize',  
        'America/Blanc-Sablon',  
        'America/Boa_Vista',  
        'America/Bogota',  
        'America/Boise',  
        'America/Cambridge_Bay',  
        'America/Campo_Grande',  
        'America/Cancun',  
        'America/Caracas',  
        'America/Cayenne',  
        'America/Cayman',  
        'America/Chicago',  
        'America/Chihuahua',  
        'America/Costa_Rica',  
        'America/Cuiaba',  
        'America/Curacao',  
        'America/Danmarkshavn',  
        'America/Dawson',  
        'America/Dawson_Creek',  
        'America/Denver',  
        'America/Detroit',  
        'America/Dominica',  
        'America/Edmonton',  
        'America/Eirunepe',  
        'America/El_Salvador',  
        'America/Fortaleza',  
        'America/Glace_Bay',  
        'America/Godthab',  
        'America/Goose_Bay',  
        'America/Grand_Turk',  
        'America/Grenada',  
        'America/Guadeloupe',  
        'America/Guatemala',  
        'America/Guayaquil',  
        'America/Guyana',  
        'America/Halifax',  
        'America/Havana',  
        'America/Hermosillo',  
        'America/Indiana/Indianapolis',  
        'America/Indiana/Knox',  
        'America/Indiana/Marengo',  
        'America/Indiana/Petersburg',  
        'America/Indiana/Tell_City',  
        'America/Indiana/Vevay',  
        'America/Indiana/Vincennes',  
        'America/Indiana/Winamac',  
        'America/Inuvik',  
        'America/Iqaluit',  
        'America/Jamaica',  
        'America/Juneau',  
        'America/Kentucky/Louisville',  
        'America/Kentucky/Monticello',  
        'America/La_Paz',  
        'America/Lima',  
        'America/Los_Angeles',  
        'America/Maceio',  
        'America/Managua',  
        'America/Manaus',  
        'America/Marigot',  
        'America/Martinique',  
        'America/Mazatlan',  
        'America/Menominee',  
        'America/Merida',  
        'America/Mexico_City',  
        'America/Miquelon',  
        'America/Moncton',  
        'America/Monterrey',  
        'America/Montevideo',  
        'America/Montreal',  
        'America/Montserrat',  
        'America/Nassau',  
        'America/New_York',  
        'America/Nipigon',  
        'America/Nome',  
        'America/Noronha',  
        'America/North_Dakota/Center',  
        'America/North_Dakota/New_Salem',  
        'America/Panama',  
        'America/Pangnirtung',  
        'America/Paramaribo',  
        'America/Phoenix',  
        'America/Port-au-Prince',  
        'America/Port_of_Spain',  
        'America/Porto_Velho',  
        'America/Puerto_Rico',  
        'America/Rainy_River',  
        'America/Rankin_Inlet',  
        'America/Recife',  
        'America/Regina',  
        'America/Resolute',  
        'America/Rio_Branco',  
        'America/Santarem',  
        'America/Santiago',  
        'America/Santo_Domingo',  
        'America/Sao_Paulo',  
        'America/Scoresbysund',  
        'America/Shiprock',  
        'America/St_Barthelemy',  
        'America/St_Johns',  
        'America/St_Kitts',  
        'America/St_Lucia',  
        'America/St_Thomas',  
        'America/St_Vincent',  
        'America/Swift_Current',  
        'America/Tegucigalpa',  
        'America/Thule',  
        'America/Thunder_Bay',  
        'America/Tijuana',  
        'America/Toronto',  
        'America/Tortola',  
        'America/Vancouver',  
        'America/Whitehorse',  
        'America/Winnipeg',  
        'America/Yakutat',  
        'America/Yellowknife',  
        'Antarctica/Casey',  
        'Antarctica/Davis',  
        'Antarctica/DumontDUrville',  
        'Antarctica/Mawson',  
        'Antarctica/McMurdo',  
        'Antarctica/Palmer',  
        'Antarctica/Rothera',  
        'Antarctica/South_Pole',  
        'Antarctica/Syowa',  
        'Antarctica/Vostok',  
        'Arctic/Longyearbyen',  
        'Asia/Aden',  
        'Asia/Almaty',  
        'Asia/Amman',  
        'Asia/Anadyr',  
        'Asia/Aqtau',  
        'Asia/Aqtobe',  
        'Asia/Ashgabat',  
        'Asia/Baghdad',  
        'Asia/Bahrain',  
        'Asia/Baku',  
        'Asia/Bangkok',  
        'Asia/Beirut',  
        'Asia/Bishkek',  
        'Asia/Brunei',  
        'Asia/Choibalsan',  
        'Asia/Chongqing',  
        'Asia/Colombo',  
        'Asia/Damascus',  
        'Asia/Dhaka',  
        'Asia/Dili',  
        'Asia/Dubai',  
        'Asia/Dushanbe',  
        'Asia/Gaza',  
        'Asia/Harbin',  
        'Asia/Ho_Chi_Minh',  
        'Asia/Hong_Kong',  
        'Asia/Hovd',  
        'Asia/Irkutsk',  
        'Asia/Jakarta',  
        'Asia/Jayapura',  
        'Asia/Jerusalem',  
        'Asia/Kabul',  
        'Asia/Kamchatka',  
        'Asia/Karachi',  
        'Asia/Kashgar',  
        'Asia/Kathmandu',  
        'Asia/Kolkata',  
        'Asia/Krasnoyarsk',  
        'Asia/Kuala_Lumpur',  
        'Asia/Kuching',  
        'Asia/Kuwait',  
        'Asia/Macau',  
        'Asia/Magadan',  
        'Asia/Makassar',  
        'Asia/Manila',  
        'Asia/Muscat',  
        'Asia/Nicosia',  
        'Asia/Novosibirsk',  
        'Asia/Omsk',  
        'Asia/Oral',  
        'Asia/Phnom_Penh',  
        'Asia/Pontianak',  
        'Asia/Pyongyang',  
        'Asia/Qatar',  
        'Asia/Qyzylorda',  
        'Asia/Rangoon',  
        'Asia/Riyadh',  
        'Asia/Sakhalin',  
        'Asia/Samarkand',  
        'Asia/Seoul',  
        'Asia/Shanghai',  
        'Asia/Singapore',  
        'Asia/Taipei',  
        'Asia/Tashkent',  
        'Asia/Tbilisi',  
        'Asia/Tehran',  
        'Asia/Thimphu',  
        'Asia/Tokyo',  
        'Asia/Ulaanbaatar',  
        'Asia/Urumqi',  
        'Asia/Vientiane',  
        'Asia/Vladivostok',  
        'Asia/Yakutsk',  
        'Asia/Yekaterinburg',  
        'Asia/Yerevan',  
        'Atlantic/Azores',  
        'Atlantic/Bermuda',  
        'Atlantic/Canary',  
        'Atlantic/Cape_Verde',  
        'Atlantic/Faroe',  
        'Atlantic/Madeira',  
        'Atlantic/Reykjavik',  
        'Atlantic/South_Georgia',  
        'Atlantic/St_Helena',  
        'Atlantic/Stanley',  
        'Australia/Adelaide',  
        'Australia/Brisbane',  
        'Australia/Broken_Hill',  
        'Australia/Currie',  
        'Australia/Darwin',  
        'Australia/Eucla',  
        'Australia/Hobart',  
        'Australia/Lindeman',  
        'Australia/Lord_Howe',  
        'Australia/Melbourne',  
        'Australia/Perth',  
        'Australia/Sydney',  
        'Europe/Amsterdam',  
        'Europe/Andorra',  
        'Europe/Athens',  
        'Europe/Belgrade',  
        'Europe/Berlin',  
        'Europe/Bratislava',  
        'Europe/Brussels',  
        'Europe/Bucharest',  
        'Europe/Budapest',  
        'Europe/Chisinau',  
        'Europe/Copenhagen',  
        'Europe/Dublin',  
        'Europe/Gibraltar',  
        'Europe/Guernsey',  
        'Europe/Helsinki',  
        'Europe/Isle_of_Man',  
        'Europe/Istanbul',  
        'Europe/Jersey',  
        'Europe/Kaliningrad',  
        'Europe/Kiev',  
        'Europe/Lisbon',  
        'Europe/Ljubljana',  
        'Europe/London',  
        'Europe/Luxembourg',  
        'Europe/Madrid',  
        'Europe/Malta',  
        'Europe/Mariehamn',  
        'Europe/Minsk',  
        'Europe/Monaco',  
        'Europe/Moscow',  
        'Europe/Oslo',  
        'Europe/Paris',  
        'Europe/Podgorica',  
        'Europe/Prague',  
        'Europe/Riga',  
        'Europe/Rome',  
        'Europe/Samara',  
        'Europe/San_Marino',  
        'Europe/Sarajevo',  
        'Europe/Simferopol',  
        'Europe/Skopje',  
        'Europe/Sofia',  
        'Europe/Stockholm',  
        'Europe/Tallinn',  
        'Europe/Tirane',  
        'Europe/Uzhgorod',  
        'Europe/Vaduz',  
        'Europe/Vatican',  
        'Europe/Vienna',  
        'Europe/Vilnius',  
        'Europe/Volgograd',  
        'Europe/Warsaw',  
        'Europe/Zagreb',  
        'Europe/Zaporozhye',  
        'Europe/Zurich',  
        'Indian/Antananarivo',  
        'Indian/Chagos',  
        'Indian/Christmas',  
        'Indian/Cocos',  
        'Indian/Comoro',  
        'Indian/Kerguelen',  
        'Indian/Mahe',  
        'Indian/Maldives',  
        'Indian/Mauritius',  
        'Indian/Mayotte',  
        'Indian/Reunion',  
        'Pacific/Apia',  
        'Pacific/Auckland',  
        'Pacific/Chatham',  
        'Pacific/Easter',  
        'Pacific/Efate',  
        'Pacific/Enderbury',  
        'Pacific/Fakaofo',  
        'Pacific/Fiji',  
        'Pacific/Funafuti',  
        'Pacific/Galapagos',  
        'Pacific/Gambier',  
        'Pacific/Guadalcanal',  
        'Pacific/Guam',  
        'Pacific/Honolulu',  
        'Pacific/Johnston',  
        'Pacific/Kiritimati',  
        'Pacific/Kosrae',  
        'Pacific/Kwajalein',  
        'Pacific/Majuro',  
        'Pacific/Marquesas',  
        'Pacific/Midway',  
        'Pacific/Nauru',  
        'Pacific/Niue',  
        'Pacific/Norfolk',  
        'Pacific/Noumea',  
        'Pacific/Pago_Pago',  
        'Pacific/Palau',  
        'Pacific/Pitcairn',  
        'Pacific/Ponape',  
        'Pacific/Port_Moresby',  
        'Pacific/Rarotonga',  
        'Pacific/Saipan',  
        'Pacific/Tahiti',  
        'Pacific/Tarawa',  
        'Pacific/Tongatapu',  
        'Pacific/Truk',  
        'Pacific/Wake',  
        'Pacific/Wallis',  
        'UTC'); 
	return $tz;
} 	
	
?>
