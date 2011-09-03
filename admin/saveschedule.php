<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';

$body = @file_get_contents('php://input');
//alternative way for IIS if above command fail
//set in php.ini: always_populate_raw_post_data = On
//$body = $HTTP_RAW_POST_DATA; 

$places = explode("|", $body);
foreach ($places as $placeGameStr) {
	$games = explode(":", $placeGameStr);
	if (intval($games[0]) != 0) {
		
		ClearReservation($games[0]);
		$resInfo = ReservationInfo($games[0]);
		$firstStart = strtotime($resInfo['starttime']);
		$resEnd = strtotime($resInfo['endtime']);
		for ($i=1; $i < count($games); $i++) {
			$gameArr = explode("/", $games[$i]);
			$gameInfo = GameInfo($gameArr[0]);
			$time = $firstStart + (60 * $gameArr[1]);
			if(!empty($gameInfo['gametimeslot'])){
				$gameEnd = $time + ($gameInfo['gametimeslot'] * 60);
			}else{
				$gameEnd = $time + ($gameInfo['timeslot'] * 60);
			}
			if ($gameEnd > $resEnd) {
				die('Game exceeds reserved time');
			}
			ScheduleGame($gameArr[0], $time, $games[0]);
		} 
	} else {
		for ($i=1; $i < count($games); $i++) {
			$gameArr = explode("/", $games[$i]);
			$gameInfo = GameInfo($gameArr[0]);
			UnScheduleGame($gameArr[0]);	
		}
	} 
	
}
echo _("Schedule saved");

?>