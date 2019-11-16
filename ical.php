<?php
include_once 'lib/common.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/location.functions.php';
include_once 'lib/timetable.functions.php';

header("Content-Type: text/Calendar; charset=utf-8");

$order = 'tournaments';
$timefilter = 'coming';
$id=0;
$gamefilter="season";

if(iget("series")) {
  $id = iget("series");
  $gamefilter="series";
} elseif(iget("pool")) {
  $id = iget("pool");
  $gamefilter="pool";
} elseif(iget("pools")) {
  $id = iget("pools");
  $gamefilter="poolgroup";
} elseif(iget("team")) {
  $id = iget("team");
  $gamefilter="team";
} elseif(iget("season")) {
  $id = iget("season");
  $gamefilter="season";
} else {
  $id = CurrentSeason();
  $gamefilter="season";
}


if(iget("order")) {
  $order  = iget("order");
}

if(iget("time")) {
  $timefilter  = iget("time");
}

$games = TimetableGames($id, $gamefilter, $timefilter, $order);

echo "BEGIN:VCALENDAR\n";
echo "VERSION:2.0\n";
echo "PRODID: "._("Ultiorganizer")."\n\n";

while($game = mysqli_fetch_assoc($games))
{
  $location = LocationInfo($game['place_id']);
  echo "\nBEGIN:VEVENT";
  echo "\nSUMMARY:". TeamName($game['hometeam']) ."-". TeamName($game['visitorteam']);
  echo "\nDESCRIPTION:". U_($game['seriesname']) .": ". U_($game['poolname']);
  echo "\nLOCATION: ". $game['placename'] ." ". $game['fieldname'];
  if(!empty($game['timezone'])){
    echo "\nDTSTART;TZID=".$game['timezone'].":". TimeToIcal($game['time']);
  }else{
    echo "\nDTSTART:". TimeToIcal($game['time']);
  }
  echo "\nDURATION: P".intval($game['timeslot'])."M";
  echo "\nGEO:".$location['lat'].";".$location['lng'];
  echo "\nEND:VEVENT\n";
}
echo "\nEND:VCALENDAR\n";

?>