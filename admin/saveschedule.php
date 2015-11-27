<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/timetable.functions.php';


$body = @file_get_contents('php://input');
//alternative way for IIS if above command fail
//set in php.ini: always_populate_raw_post_data = On
//$body = $HTTP_RAW_POST_DATA; 

$season = "";

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
			$season = $gameInfo['season'];
			$time = $firstStart + (60 * $gameArr[1]);
			if(!empty($gameInfo['gametimeslot'])){
				$gameEnd = $time + ($gameInfo['gametimeslot'] * 60);
			}else{
				$gameEnd = $time + ($gameInfo['timeslot'] * 60);
			}
			if ($gameEnd > $resEnd) {
				die('Game '. GameName($gameInfo) .' exceeds reserved time '. ShortTimeFormat($resInfo['endtime']));
			}
			ScheduleGame($gameArr[0], $time, $games[0]);
		} 
	} else {
		for ($i=1; $i < count($games); $i++) {
			$gameArr = explode("/", $games[$i]);
			$gameInfo = GameInfo($gameArr[0]);
			$season = $gameInfo['season'];
			UnScheduleGame($gameArr[0]);	
		}
	} 
	
}

if ($season) {
  
  $movetimes = TimetableMoveTimes($season);
  $conflicts = TimetableIntraPoolConflicts($season);
  
  foreach ($conflicts as $conflict) {
    if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
      if (strtotime($conflict['time1']) + $conflict['slot1'] * 60 + TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'], $conflict['field2']) > strtotime($conflict['time2'])) {
        $game1 = GameInfo($conflict['game1']);
      $game2 = GameInfo($conflict['game2']);
      die('Warning: Game ' . GameName($game2) . ' ('.$game2['game_id'].', pool '. $game2['pool'] . ') has a scheduling conflict with ' . GameName($game1).' ('.$game1['game_id'].', '.$game1['pool'].')');
      }
    }
  }
  
  $conflicts = TimetableInterPoolConflicts($season);

  foreach ($conflicts as $conflict) {
    if (!empty($conflict['time2']) && !empty($conflict['time1'])) {
      if (strtotime($conflict['time1']) + $conflict['slot1'] * 60 + TimetableMoveTime($movetimes, $conflict['location1'], $conflict['field1'], $conflict['location2'], $conflict['field2']) > strtotime($conflict['time2'])){
        $game1 = GameInfo($conflict['game1']);
      $game2 = GameInfo($conflict['game2']);
      die('Warning: Game ' .GameName($game2) . ' has a scheduling conflict with ' . GameName($game1).'.');
      }
    }
  }
}else {
  die ("Error, unknown season!");
}

echo _("Schedule saved and checked.");

?>