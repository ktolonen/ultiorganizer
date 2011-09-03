<?php 

function EventCategories(){
	return array("security","user","enrolment","club","team","player","season","series","pool","game","media");
	}
	
function LogEvent($event){
		if(empty($event['id1']))
			$event['id1']="";
			
		if(empty($event['id2']))
			$event['id2']="";

		if(empty($event['source']))
			$event['source']="";
			
		if(empty($event['description']))
			$event['description']="";
		
		if(strlen($event['description'])>50)
			$event['description']=substr($event['description'],0,50);
		
		if(strlen($event['id1'])>20)
			$event['id1']=substr($event['id1'],0,20);
		
		if(strlen($event['id2'])>20)
			$event['id2']=substr($event['id2'],0,20);
			
		if(empty($event['user_id'])){
			if(!empty($_SESSION['uid']))
				$event['user_id'] = $_SESSION['uid'];
			else
				$event['user_id'] = "unknown";
		}
		
		$event['ip'] = "";		
		if(!empty($_SERVER['REMOTE_ADDR']))
			$event['ip'] = $_SERVER['REMOTE_ADDR'];
		
		$query = sprintf("INSERT INTO uo_event_log (user_id, ip, category, type, source,
			id1, id2, description)
				VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')",
		mysql_real_escape_string($event['user_id']),
		mysql_real_escape_string($event['ip']),
		mysql_real_escape_string($event['category']),
		mysql_real_escape_string($event['type']),
		mysql_real_escape_string($event['source']),
		mysql_real_escape_string($event['id1']),
		mysql_real_escape_string($event['id2']),
		mysql_real_escape_string($event['description']));
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return mysql_insert_id();
	}	

function EventList($categoryfilter, $userfilter){
	if(isSuperAdmin()){
		if(count($categoryfilter)==0){
			return false;
		}
		$query = "SELECT * FROM uo_event_log WHERE ";
		
		$i=0;	
		foreach($categoryfilter as $cat){
			if($i==0){$query .= "(";}
			if($i>0){$query .= " OR ";}
		
			$query .= sprintf("category='%s'", mysql_real_escape_string($cat));
			$i++;
			if($i==count($categoryfilter)){	$query .= ")";}
		}
		
		if(!empty($userfilter)){
			$query .= sprintf("AND user_id='%s'", mysql_real_escape_string($userfilter));
		}
		$query .= " ORDER BY time DESC";
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		return $result;
	}
}

function ClearEventList($ids){
	if(isSuperAdmin()){
		$query = sprintf("DELETE FROM uo_event_log WHERE event_id IN (%s)", mysql_real_escape_string($ids));
				
		$result = mysql_query($query);
		if (!$result) { die('Invalid query: ' . mysql_error()); }
		return $result;
	}
}
	
function Log1($category, $type, $id1="", $id2="", $description="", $source=""){
	$event['category'] = $category;
	$event['type'] = $type;
	$event['id1'] = $id1;
	$event['id2'] = $id2;
	$event['description'] = $description;
	$event['source'] = $source;	
	return LogEvent($event);
	}	

function Log2($category, $type, $description="", $source=""){
	$event['category'] = $category;
	$event['type'] = $type;
	$event['description'] = $description;
	$event['source'] = $source;	
	return LogEvent($event);
	}
	
function LogPlayerProfileUpdate($playerId, $source=""){
	$event['category'] = "player";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $playerId;
	$event['description'] = "profile updated";
	return LogEvent($event);
	}
	
function LogTeamProfileUpdate($teamId, $source=""){
	$event['category'] = "team";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $teamId;
	$event['description'] = "profile updated";
	return LogEvent($event);
	}
	
function LogUserAuthentication($userId, $result, $source=""){
	$event['user_id'] = $userId;
	$event['category'] = "security";
	$event['type'] = "authenticate";
	$event['source'] = $source;
	$event['description'] = $result;
	return LogEvent($event);
	}

function LogGameResult($gameId, $result, $source=""){
	$event['category'] = "game";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $result;
	return LogEvent($event);
	}	

function LogGameUpdate($gameId, $details, $source=""){
	$event['category'] = "game";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $gameId;
	$event['description'] = $details;
	return LogEvent($event);
	}
	
function GetLastGameUpdateEntry($gameId, $source) {
	$query = sprintf("SELECT * FROM uo_event_log WHERE id1=%d AND source='%s' ORDER BY TIME DESC",
		(int)$gameId, mysql_real_escape_string($source));	
	$result = mysql_query($query);
	if (!$result) { die('Invalid query: ' . mysql_error()); }
	return mysql_fetch_assoc($result);
}

function LogPoolUpdate($poolId, $details, $source=""){
	$event['category'] = "pool";
	$event['type'] = "change";
	$event['source'] = $source;
	$event['id1'] = $poolId;
	$event['description'] = $details;
	return LogEvent($event);
	}

	/**
	 * Log page load into database for usage statistics.
	 * 
	 * @param string $page - loaded page
	 */
function LogPageLoad($page){

  $query=sprintf("SELECT loads FROM uo_pageload_counter WHERE page='%s'",
		mysql_real_escape_string($page));
  $loads = DBQueryToValue($query);
  
  if($loads<0){
    $query=sprintf("INSERT INTO uo_pageload_counter (page, loads) VALUES ('%s',%d)",
        mysql_real_escape_string($page),1);
    DBQuery($query);
    
  }else{
    $loads++;
    $query=sprintf("UPDATE uo_pageload_counter SET loads=%d WHERE page='%s'",
	  $loads,
      mysql_real_escape_string($page));
    DBQuery($query);
  }
}

/**
 * Log visitors visit into database for usage statistics.
 * 
 * @param string $ip - ip address
 */
function LogVisitor($ip){

  $query=sprintf("SELECT visits FROM uo_visitor_counter WHERE ip='%s'",
		mysql_real_escape_string($ip));
  $visits = DBQueryToValue($query);
 
  if($visits<0){
    $query=sprintf("INSERT INTO uo_visitor_counter (ip, visits) VALUES ('%s',%d)",
        mysql_real_escape_string($ip),1);
    DBQuery($query);
  }else{
    $visits++;
    $query=sprintf("UPDATE uo_visitor_counter SET visits=%d WHERE ip='%s'",
	  $visits,
      mysql_real_escape_string($ip));
    DBQuery($query);
  }
}

/**
 * Get visitor count.
 */
function LogGetVisitorCount(){
  $query=sprintf("SELECT SUM(visits) AS visits, COUNT(ip) AS visitors FROM uo_visitor_counter");
  return DBQueryToRow($query);
}

/**
 * Get page loads.
 */
function LogGetPageLoads(){
  $query=sprintf("SELECT page, loads FROM uo_pageload_counter ORDER BY loads DESC");
  return DBQueryToArray($query);
}

?>
