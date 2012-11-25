<?php
include_once 'lib/pool.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/standings.functions.php';

$body = @file_get_contents('php://input');
//alternative way for IIS if above command fail
//set in php.ini: always_populate_raw_post_data = On
//$body = $HTTP_RAW_POST_DATA; 

$poolIds = array();
$tmppools = explode("|", $body);
foreach ($tmppools as $PoolStr) {
	$pool = explode("/", $PoolStr);
	if($pool[0]!=0){
	  $poolIds[] = $pool[0];
	}
}

$pools = explode("|", $body);
foreach ($pools as $PoolStr) {
	$pools = explode("/", $PoolStr);
	
	if ($pools[0] != "0") {
		for ($i=1; $i < count($pools); $i++) {
		    
			$teamArr = explode("/", $pools[$i]);
			foreach ($poolIds as $PoolId) {
				PoolDeleteTeam($PoolId,$teamArr[0]);
			}
			PoolAddTeam($pools[0],$teamArr[0],$i);
		}
	} else {
		for ($i=1; $i < count($pools); $i++) {
			$teamArr = explode("/", $pools[$i]);
			foreach ($poolIds as $PoolId) {
				PoolDeleteTeam($PoolId,$teamArr[0]);
			}
		}
	} 
}
foreach ($poolIds as $PoolId) {
	ResolvePoolStandings($PoolId);
}
echo _("Teams saved");

?>