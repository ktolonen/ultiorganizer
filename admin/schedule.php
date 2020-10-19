<?php
include_once 'lib/reservation.functions.php';
include_once 'lib/game.functions.php';
include_once 'lib/team.functions.php';
include_once 'lib/common.functions.php';
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';

$LAYOUT_ID = SCHEDULE;
$title = _("Scheduling");

if (isset($_GET['reservations'])) {
  $reservations = explode(",", $_GET['reservations']);
} else {
  $reservations = array_flip($_SESSION['userproperties']['userrole']['resadmin']);
}
$reservationData = ReservationInfoArray($reservations);
$numOfreservations = count($reservations);

define("MIN_HEIGHT", 1.0);
$MAX_COLUMNS = 4;

$maxtimeslot = 30;
$seriesId = 0;
$poolId = 0;
$seasonId = "";

if(!empty($_GET["series"])) {
  $seriesId = intval($_GET["series"]);
}

if(!empty($_GET["pool"])) {
  $poolId = intval($_GET["pool"]);
}

if(!empty($_GET["season"])) {
  $seasonId = $_GET["season"];
}

$backurl = "?view=admin/reservations";
if (!empty($seasonId))
  $backurl .= "&season=$seasonId";
if (!empty($seriesId))
  $backurl .= "&series=$seriesId";
if (!empty($pool))
  $backurl .= "&pool=$poolId";


$seasonfilter = array();
$seriesfilter = array();
$poolfilter = array();

$seasons = Seasons();
while($season = mysqli_fetch_assoc($seasons)){
  $seasonfilter[] = array('id'=>$season['season_id'],'name'=>U_(SeasonName($season['season_id'])));
}

$series = SeasonSeries($seasonId);
foreach($series as $ser){
  $seriesfilter[] = array('id'=>$ser['series_id'],'name'=>U_($ser['name']));
}

$pools = SeriesPools($seriesId);
foreach($pools as $tmppool){
  $poolfilter[] = array('id'=>$tmppool['pool_id'],'name'=>U_($tmppool['name']));
}

//common page
pageTopHeadOpen($title);

include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities"));

?>

<style type="text/css">
body {
	margin: 0;
	padding: 0;
}
</style>


<style type="text/css">
div.workarea {
	padding: 0px;
	float: left;
   min-width:150px;
   /* min-width:150px; */ 
}

td.scheduling_column {
   vertical-align:top;
   /* padding:5px; */
   font-size:8px;
}

ul.draglist {
	position: relative;
	background: #f7f7f7;
	border: 1px solid gray;
	list-style: none;
	margin: 0;
	padding: 0;
}

ul.draglist li {
	margin: 0px;
	cursor: move;
	zoom: 1;
	text-align: center;
	vertical-align: center;
}

li.list1 {
	background-color: #aaaaaa;
	border: 1px solid #7EA6B2;
 width:150px;
}

th.scheduling { width:150px };

td.timecolumn {
   vertical-align:top;
}

ul.timelist {
 position: relative;
 list-style: none;
 margin: 0;
 padding: 0;
}

ul.timelist li {
 border: 1px solid #7EA6B2;
}


#user_actions {
	float: right;
}
</style>
<script type="text/javascript">

var with_ctrl_key=false;
var with_shift_key = false;

function KeyDown(event){
   var KeyID = event.keyCode;
	
   switch(KeyID){

      case 16:
      with_shift_key = true;
      break; 

      case 17:
 	  with_ctrl_key = true;
      break;

      case 37:
      //Arrow Left
	  if(with_ctrl_key){
		window.scrollBy(2*-window.innerWidth+50,0);
	  }else if(with_shift_key){
		window.scrollBy(-50,0);
	  }else{
		window.scrollBy(-window.innerWidth+50,0);
	  }

      break;

      case 38:
	  //Arrow Up
	  if(with_ctrl_key){
		window.scrollBy(0,2*-window.innerHeight+50);
	  }else if(with_shift_key){
		window.scrollBy(0,-50);
	  }else{
		window.scrollBy(0,-window.innerHeight+50);
	  }
      break;

      case 39:
	  //Arrow Right
	  if(with_ctrl_key){
		window.scrollBy(2*window.innerWidth-50,0);
	  }else if(with_shift_key){
		window.scrollBy(50,0);
	  }else{
		window.scrollBy(window.innerWidth-50,0);
	  }
      break;

      case 40:
	  //Arrow Down
	  if(with_ctrl_key){
		window.scrollBy(0,2*window.innerHeight-50);
	  }else if(with_shift_key){
		window.scrollBy(0,50);
	  }else{
		window.scrollBy(0,window.innerHeight-50);
	  }
      break;
   }
}

function KeyUp(event){
   var KeyID = event.keyCode;
	
   switch(KeyID){

      case 16:
      with_shift_key = false;
      break; 

      case 17:
 	  with_ctrl_key = false;
      break;
	}
}
//-->

</script>

<?php

function gameDuration($gameInfo) {
  return empty($gameInfo['gametimeslot'])?$gameInfo['timeslot']:$gameInfo['gametimeslot'];
}

function gameHeight($duration) {
  return max(intval($duration * MIN_HEIGHT), intval(15 * MIN_HEIGHT)) -2;
}

function pauseHeight($duration) {
  // echo "<!--".EpocToMysql($gameStart)." ".EpocToMysql($nextStart)."-->\n";
  return ($duration * MIN_HEIGHT) - 2;
}

$scrolling = "onkeydown='KeyDown(event);' onkeyup='KeyUp(event);'";
pageTopHeadClose($title,false, $scrolling);
pageMainStart();
contentStart();

echo "<a href='" . utf8entities($backurl) . "'>" . _("Return") . "</a>";

echo "<table><tr><td class='scheduling_column'>";

//$teams = UnscheduledTeams();
//$unscheduledTeams = array_flip(UnscheduledTeams());
if($poolId){
  $gameData = UnscheduledPoolGameInfo($poolId);
}elseif($seriesId){
  $gameData = UnscheduledSeriesGameInfo($seriesId);
}elseif(!empty($seasonId)){
  $gameData = UnscheduledSeasonGameInfo($seasonId);
}else{
  $gameData = array();
}

function jsSecure($string) {
  return str_replace(array('"', "\n"), array('\"', ''), $string);
}

function pauseEntry($height, $duration, $gameId) {
  $html = "<li class='list1' id='pause" . $gameId . "' style='min-height:" . $height . "px'>";
  $html .= "<input type='hidden' id='ptime" . $gameId . "' name='ptimes[]' value='" . $duration . "'/>";
  $html .= sprintf(_("Pause: %s&thinsp;min."), $duration);
  $html .= "<span style='align:right;float:right'><a href='javascript:hide(\"pause" . $gameId . "\");'>x</a></span></li>\n";
  return $html;
}

function gameEntry($gameInfo, $height, $duration, $poolname, $editable = true) {
  $color = $gameInfo['color'];
  $textColor = textColor($color);
  $gameId = $gameInfo['game_id'];
  $gamename = utf8entities(getGameName($gameInfo, true));
  $tooltip = utf8entities(getGameName($gameInfo));
  if ($tooltip == $gamename)
    $tooltip = "";
  else
    $tooltip = " title='" . $tooltip . "'";
  $html = "<li class='list1' style='color:#" . $textColor . ";background-color:#" . $color . ";min-height:" . $height .
       "px' id='game" . $gameId . "'" . $tooltip . ">";
  $html .= "<input type='hidden' id='gtime" . $gameId . "' name='gtimes[]' value='" . $duration . "'/>";
  $html .= $poolname;
  if ($editable) {
    $html .= "<span style='align:right;float:right;'><a href='javascript:hide(\"game" . $gameId . "\");'>x</a></span>";
  } else {
    $html .= "<span style='align:right;float:right;'>#</span>";
  }
  $html .= "<br/>".(empty($gamename)?"":"<b>$gamename</b> "). sprintf(_("%d&nbsp;min."), $duration);
  $html .= "</li>\n";
  return $html;
}

function getHName($gameInfo) {
  return empty($gameInfo['hometeamshortname'])?$gameInfo['hometeamname']:$gameInfo['hometeamshortname'];
}

function getVName($gameInfo) {
  return empty($gameInfo['visitorteamshortname'])?$gameInfo['visitorteamname']:$gameInfo['visitorteamshortname'];
}

function getGameName($gameInfo, $short = false) {
  if ($gameInfo['hometeam'] && $gameInfo['visitorteam']) {
    if ($short) {
      $gametitle = substr(getHName($gameInfo), 0, 12) . " - " . substr(getVName($gameInfo), 0, 12);
    } else {
      $gametitle = $gameInfo['hometeamname'] . " - " . $gameInfo['visitorteamname'];
    }
  } else {
    $gametitle = $gameInfo['phometeamname'] . " - " . $gameInfo['pvisitorteamname'];
  }
  return $gametitle;
}

function gamePoolName($gameInfo) {
  return U_($gameInfo['seriesname']).", ". U_($gameInfo['poolname']);
}

function tableStart($dayArray, $skip, $max) {
  echo "<table><tr>\n";
  $index = 0;
  foreach ($dayArray as $reservationId => $reservationArray) {
    if (++$index <= $skip)
      continue;
    if ($index > $skip + $max)
      break;
    $startTime = strtotime($reservationArray['starttime']);
    echo "<th class='scheduling'>" . $reservationArray['name'] . " " . _("Field ") . " " . $reservationArray['fieldname'] .
         " " . date("H:i", $startTime) . "</th>\n";
  }
  echo "<th>" . JustDate($reservationArray['starttime']) . "</th></tr><tr>\n";
  return $startTime;
}

function tableEnd($firstStart, $lastEnd) {
  echo "<td class='timecolumn'>";
  if (isset($firstStart)) {
    echo "<ul class='timelist'>\n";
    for($t=$firstStart;$t<$lastEnd;$t+=60*60) {
      echo "<li style='min-height:".(min(60, ($lastEnd-$t)/60)*MIN_HEIGHT-2)."px'>".date("H:i", $t)."</li>\n";
    }
    echo "</ul>";
  }
  echo "</td>\n";
  echo "</tr>\n</table>\n";
}

echo "<table><tr><td class='scheduling_column'>\n";
echo "<h3>"._("Unscheduled")."</h3>\n";
echo "<form action='' method='get'>";
echo "<p><select class='dropdown' style='width:100%' name='eventfilter' onchange='OnEventSelect(this);'>\n";
echo "<option class='dropdown' value=''>"._("Select event")."</option>";
foreach($seasonfilter as $season){
  if($seasonId==$season['id']){
    echo "<option class='dropdown' selected='selected' value='".utf8entities($season['id'])."'>". utf8entities($season['name']) ."</option>";
  }else{
    echo "<option class='dropdown' value='".utf8entities($season['id'])."'>". utf8entities($season['name']) ."</option>";
  }
}
echo "</select><br/>\n";
$disabled = "";
if(empty($seasonId)){
  $disabled = "disabled='disabled'";
}
echo "<select class='dropdown' $disabled style='width:100%' name='seriesfilter' onchange='OnSeriesSelect(this);'>\n";
echo "<option class='dropdown' value='0'>"._("All divisions")."</option>";
foreach($seriesfilter as $series){
  if($seriesId==$series['id']){
    echo "<option class='dropdown' selected='selected' value='".utf8entities($series['id'])."'>". utf8entities($series['name']) ."</option>";
  }else{
    echo "<option class='dropdown' value='".utf8entities($series['id'])."'>". utf8entities($series['name']) ."</option>";
  }
}
echo "</select><br/>\n";
$disabled = "";
if(!$seriesId){
  $disabled = "disabled='disabled'";
}
echo "<select class='dropdown' $disabled style='width:100%' name='poolfilter' onchange='OnPoolSelect(this);'>\n";
echo "<option class='dropdown' value='0'>"._("All pools")."</option>";
foreach($poolfilter as $pool){
  if($poolId==$pool['id']){
    echo "<option class='dropdown' selected='selected' value='".utf8entities($pool['id'])."'>". utf8entities($pool['name']) ."</option>";
  }else{
    echo "<option class='dropdown' value='".utf8entities($pool['id'])."'>". utf8entities($pool['name']) ."</option>";
  }
}
echo "</select></p>\n";
echo "</form>";

$zeroGames = array();

echo "<div class='workarea' >\n";
echo "<ul class='draglist' id='unscheduled'  style='min-height:600px'>\n";
foreach ($gameData as $gameId => $gameInfo) {
  if (hasEditGamesRight($gameInfo['series'])) {
    $duration = gameDuration($gameInfo);
    if ($duration <= 0) {
      $zeroGames[] = count($zeroGames) == 0?$gameInfo:$gameId;
    }
    $height = gameHeight($duration);
    $poolname = gamePoolName($gameInfo);
    $maxtimeslot = max($maxtimeslot, $duration);
    if ($duration > 0)
      echo gameEntry($gameInfo, $height, $duration, $poolname);
    else
      echo gameEntry($gameInfo, $height, $duration, $poolname, false);
  }
}
if(count($gameData)==0){
  echo "<li></li>";
}
echo "</ul>\n";
echo "</div>\n</td>\n";
echo "</tr>\n</table>\n";
echo "<p>&nbsp;</p>";
echo "<input type='button' id='pauseButton' value='"._("Add pause")."'/>";
echo "<div style='white-space:nowrap'><input type='text' id='pauseLen' value='$maxtimeslot' size='3'/>&thinsp;". _("minutes")."</div>\n";
echo "</td><td style='vertical-align:top'>\n";
$reservedPauses = array();

$MINTOP = 30;

if(count($reservationData)>0){
  foreach ($reservationData as $day => $dayArray) {
    $lastEnd = 0;
    $columnCount = $lastBreak = 0;
    tableStart($dayArray, 0, $MAX_COLUMNS);
    
    foreach ($dayArray as $reservationId => $reservationArray) {
      if (!isset($firstStart)) {
        $firstStart = strtotime($reservationArray['starttime']);
      }
      echo "<td class='scheduling_column'>\n";
      $offset = intval((strtotime($reservationArray['starttime']) - $firstStart) / 60) + $MINTOP;
      $lastEnd = max($lastEnd, strtotime($reservationArray['endtime']));
      $startTime = strtotime($reservationArray['starttime']);
      $endTime =  strtotime($reservationArray['endtime']);
      $duration = ($endTime - $startTime) / 60;

      echo "<div class='workarea' >\n";
      echo "<ul id='res".$reservationId."' class='draglist' style='min-height:".($duration * MIN_HEIGHT)."px'>\n";
      $nextStart = $startTime;
      foreach ($reservationArray['games'] as $gameId => $gameInfo) {
        $gameStart = strtotime($gameInfo['time']);
        $duration = ($gameStart - $nextStart) / 60;
        $height = pauseHeight($duration);
        if ($nextStart<$gameStart) {
          echo pauseEntry($height, $duration, $gameId);
          $reservedPauses[] = "pause".$gameId;
        }
        $duration = gameDuration($gameInfo);
        if ($duration <= 0) {
          $zeroGames[] = count($zeroGames) == 0?$gameInfo:$gameId;
        }
        $nextStart = $gameStart + ($duration * 60);
        $height = gameHeight($duration);
        $gametitle = getGameName($gameInfo);
        $pooltitle = gamePoolName($gameInfo); 
        if ($duration > 0 && hasEditGamesRight($gameInfo['series'])) {
          echo gameEntry($gameInfo, $height, $duration, $pooltitle);
        } else {
          echo gameEntry($gameInfo, $height, $duration, $pooltitle, false);
        }
      }
      echo "</ul>\n";
      echo "</div>\n</td>\n";
      
      ++$columnCount;
      if (count($dayArray) > $MAX_COLUMNS && ($columnCount-$lastBreak >= $MAX_COLUMNS)) {
        tableEnd($firstStart, $lastEnd);
        unset($firstStart);
        $lastEnd=0;
        if ($columnCount < count($dayArray))
          tableStart($dayArray, $columnCount, $MAX_COLUMNS);
        $lastBreak = $columnCount;
      }
    }
    if (isset($firstStart)) {
      tableEnd($firstStart, $lastEnd);
    }    
    unset($firstStart);
    $lastEnd=0;
  }
}else{
  foreach ($reservationData as $day => $dayArray) {
    $rowcount=2;

    foreach ($dayArray as $reservationId => $reservationArray) {

      if(($rowcount%2)==0){
        echo "<table><tr>\n";
      }
      $rowcount++;
      if (!isset($firstStart)) {
        $firstStart = strtotime($reservationArray['starttime']);
      }
      echo "<td style='vertical-align:top;padding:5px'>\n";
      $offset = intval((strtotime($reservationArray['starttime']) - $firstStart) / 60) + $MINTOP;
      $startTime = strtotime($reservationArray['starttime']);
      $endTime =  strtotime($reservationArray['endtime']);
      $duration = ($endTime - $startTime) / 60;

      echo "<div style='vertical-align:bottom;min-height:".intval($offset * MIN_HEIGHT)."px'>";
      echo "<h3>".$reservationArray['name']." "._("Field ")." ".$reservationArray['fieldname']." ".date("H:i", $startTime)."</h3></div>\n";
      echo "<div class='workarea' >\n";
      echo "<ul id='res".$reservationId."' class='draglist' style='min-height:".($duration * MIN_HEIGHT)."px'>\n";
      $nextStart = $startTime;
      foreach ($reservationArray['games'] as $gameId => $gameInfo) {
        $gameStart = strtotime($gameInfo['time']);
        if ($gameStart > $nextStart) {
          $duration = ($gameStart - $nextStart) / 60;
          $height = pauseHeight($duration);
          echo pauseEntry($height, $duration, $gameId);
          $reservedPauses[] = "pause".$gameId;
        }
        $duration = gameDuration($gameInfo);
        if ($duration <= 0) {
          $zeroGames[] = count($zeroGames) == 0?$gameInfo:$gameId;
        }
        $nextStart = $gameStart + (max($duration, 60) * 60);
        $height = gameHeight($duration);
        $gametitle = getGameName($gameInfo);
        $pooltitle = gamePoolName($gameInfo);
        if ($duration > 0 && hasEditGamesRight($gameInfo['series'])) {
          echo gameEntry($gameInfo, $height, $duration, $pooltitle);
        } else {
          echo gameEntry($gameInfo, $height, $duration, $pooltitle, false);
        }
      }
      echo "</ul>\n";
      echo "</div>\n</td>\n";
      if(($rowcount%2)==0){
        echo "</tr>\n</table>\n";
      }

    }
  }

}
echo "<table><tr>";
echo "<td id='user_actions' style='float: left; padding: 20px'>";
echo "<input type='button' id='showButton' value='" . _("Save") . "' /></td>";
echo "<td class='center'><div id='responseStatus'></div>";
if (!empty($zeroGames)) {
  echo "<p>". sprintf(_("Warning: Games with duration 0 found. They can not be scheduled. Edit the game duration or the time slot length of pool %s ..."),
      gamePoolName($zeroGames[0])) ."</p>";
}
echo "</td></tr></table>\n";
echo "<a href='" . utf8entities($backurl) . "'>" . _("Return") . "</a>";
?>
<script type="text/javascript">
//<![CDATA[

var Dom = YAHOO.util.Dom;
var redirecturl="";
var modified=0;

function hide(id) {
	var elem = Dom.get(id);
	var list = Dom.getAncestorByTagName(elem, "ul");
	list.removeChild(elem);
}

function setModified(newValue) {
 modified=newValue;
 if (modified)
    window.onbeforeunload = function() {
        return "";
    }
 else
    window.onbeforeunload = null;
}

function paramExp(param) {
  return new RegExp("([?|&])" + param + "=([^&]*)?(&|$)","i")
}

function getParam(url, param) {
    var found = 0;
	var re = paramExp(param);
    if (url.match(re)){
        found = url.match(re)[2];
    }
    return found;
}

function changeParam(url, param, value) {
  var re = paramExp(param);
  if (url.match(re)){
    url=url.replace(re,'$1' + param + "=" + value + '$3');
  }else{
    url = url + '&' + param + "=" + value;
  }
  return url;
}

function getUrl(url, season, series, pool) {
  if (season!=null) {
    url = changeParam(url, "season", season);
  }
  if (series!=null) {
    url = changeParam(url, "series", series);
  }
  if (pool != null) {
    url = changeParam(url, "pool", pool);
  }
  return url;
}

//ajax based solution would be better, but at now this feels simpler because also dropdown fields need to be updated according user selection.
function OnEventSelect(dropdown)
{
  var myindex  = dropdown.selectedIndex;
  var SelValue = dropdown.options[myindex].value;
  var event = getParam(location.href, "season");

  redirecturl=getUrl(location.href, SelValue, 0, 0);
  if (!redirectWithConfirm()){
    dropdown.value = series;
  }
}
  
function OnSeriesSelect(dropdown)
{
  var myindex  = dropdown.selectedIndex;
  var SelValue = dropdown.options[myindex].value;
  var series = getParam(location.href, "series");

  redirecturl = getUrl(location.href, null, SelValue, 0);
  if (!redirectWithConfirm()){
    dropdown.value = series;
  }
}

function OnPoolSelect(dropdown)
{
  var myindex  = dropdown.selectedIndex;
  var SelValue = dropdown.options[myindex].value;
  var pool = getParam(location.href, "pool");

  redirecturl = getUrl(location.href, null, null, SelValue);
  if (!redirectWithConfirm()){
    dropdown.value = pool;
  }
}

function redirectWithConfirm(){
  if(modified){
    var answer = confirm('<?php echo _("Save changes?");?>');
    if (answer){
      YAHOO.example.ScheduleApp.requestString();
      setModified(false);
    }else{
      answer = confirm('<?php echo _("Do you want to leave anyway? If you select Yes, you will lose all your changes.");?>');
      if (answer) {
        setModified(false);
        location.href=redirecturl;
      } else
        return false;
	  }
  }else{
    location.href=redirecturl;
  }
  return true;
}

(function() {

var Event = YAHOO.util.Event;
var DDM = YAHOO.util.DragDropMgr;
var pauseIndex = 1;
var minHeight = <?php echo MIN_HEIGHT; ?>;


YAHOO.example.ScheduleApp = {
    init: function() {
<?php 
echo "		new YAHOO.util.DDTarget(\"unscheduled\");\n";
foreach ($reservationData as $day => $dayArray) {
	foreach ($dayArray as $reservationId => $reservationArray) {
		echo "		new YAHOO.util.DDTarget(\"res".$reservationId."\");\n";
		foreach ($reservationArray['games'] as $gameId => $gameInfo) {
			if (hasEditGamesRight($gameInfo['series'])) {
				echo "		new YAHOO.example.DDList(\"game".$gameId."\");\n";
			}
		}
	}
}
foreach ($gameData as $gameId => $gameInfo) {
	if (hasEditGamesRight($gameInfo['series'])) {
		echo "		new YAHOO.example.DDList(\"game".$gameId."\");\n";
	}
}
foreach ($reservedPauses as $pauseId) {
	echo "		new YAHOO.example.DDList(\"".$pauseId."\");\n";
}

?>
        Event.on("showButton", "click", this.requestString);
        Event.on("pauseButton", "click", this.addPause);
    },
    
	addPause: function() {
    	var unscheduled = Dom.get("unscheduled");
    	var pauseElement = document.createElement("div");
        var duration = Dom.get("pauseLen").value;
        var height = (duration * minHeight)-2;
        var html = "<?php echo jsSecure(pauseEntry('%h%', '%d%', '%i%')); ?>";
        html = html.replace(/%h%/g, height);
        html = html.replace(/%d%/g, duration);
        html = html.replace(/%i%/g, pauseIndex);
        pauseElement.innerHTML = html;
        pauseElement = pauseElement.firstChild;
        
	    unscheduled.appendChild(pauseElement);
	    new YAHOO.example.DDList("pause" + pauseIndex);
	    pauseIndex++;
    },	
    
    requestString: function() {
        var parseList = function(ul, id) {
            var items = ul.getElementsByTagName("li");
            var out = id;
			var offset = 0;
            for (i=0;i<items.length;i=i+1) {
                var duration =  parseInt(items[i].firstChild.value);
				var nextId = items[i].id.substring(4);
				if (!isNaN(nextId)) {
                	out += ":" + nextId + "/" + offset;
				}
                offset += duration;
            }
            return out;
        };
<?php 
		echo "	var unscheduled=Dom.get(\"unscheduled\");\n";
        foreach ($reservationData as $day => $dayArray) {
        	foreach ($dayArray as $reservationId => $reservationArray) {
      			echo "	var res".$reservationId."=Dom.get(\"res".$reservationId."\");\n";
        	}
        }
        echo "	var request = parseList(unscheduled, \"0\") + \"\\n\"";
        foreach ($reservationData as $day => $dayArray) {
        	foreach ($dayArray as $reservationId => $reservationArray) {
            	echo " + \"|\" + parseList(res".$reservationId.", \"".$reservationId."\")";
        	}
        }
        echo ";\n";
?>
	var responseDiv = Dom.get("responseStatus");
	Dom.setStyle(responseDiv,"background-image","url('images/indicator.gif')");
	Dom.setStyle(responseDiv,"background-repeat","no-repeat");
	Dom.setStyle(responseDiv,"background-position", "top right");
	Dom.setStyle(responseDiv,"class", "inprogress");
	responseDiv.innerHTML = '&nbsp;';
	var transaction = YAHOO.util.Connect.asyncRequest('POST', 'index.php?view=admin/saveschedule', callback, request);         
    },
};

var callback = {
	success: function(o) {
		var responseDiv = Dom.get("responseStatus");
		YAHOO.util.Dom.removeClass(responseDiv,"attention");
		YAHOO.util.Dom.addClass(responseDiv,"highlight");
		Dom.setStyle(responseDiv,"background-image","");
		responseDiv.innerHTML = o.responseText;
		setModified(false);
		if(redirecturl){
			location.href=redirecturl;
		}
	},

	failure: function(o) {
		var responseDiv = Dom.get("responseStatus");
		YAHOO.util.Dom.removeClass(responseDiv,"highlight");
		YAHOO.util.Dom.addClass(responseDiv,"attention");
		Dom.setStyle(responseDiv,"background-image","");
		responseDiv.innerHTML = o.responseText;
	}
}



YAHOO.example.DDList = function(id, sGroup, config) {

    YAHOO.example.DDList.superclass.constructor.call(this, id, sGroup, config);

    this.logger = this.logger || YAHOO;
    var el = this.getDragEl();
    Dom.setStyle(el, "opacity", 0.57); // The proxy is slightly transparent

    this.goingUp = false;
    this.lastY = 0;
};

YAHOO.extend(YAHOO.example.DDList, YAHOO.util.DDProxy, {

    startDrag: function(x, y) {
        this.logger.log(this.id + " startDrag");

        // make the proxy look like the source element
        var dragEl = this.getDragEl();
        var clickEl = this.getEl();
        Dom.setStyle(clickEl, "visibility", "hidden");

        dragEl.innerHTML = clickEl.innerHTML;

        Dom.setStyle(dragEl, "color", Dom.getStyle(clickEl, "color"));
        Dom.setStyle(dragEl, "backgroundColor", Dom.getStyle(clickEl, "backgroundColor"));
        Dom.setStyle(dragEl, "font-size", Dom.getStyle(clickEl, "font-size"));
        Dom.setStyle(dragEl, "font-family", Dom.getStyle(clickEl, "font-family"));
        Dom.setStyle(dragEl, "border", "2px solid gray");
        Dom.setStyle(dragEl, "text-align", "center");
    },

    

    endDrag: function(e) {

        var srcEl = this.getEl();
        var proxy = this.getDragEl();
        setModified(true);
        // Show the proxy element and animate it to the src element's location
        Dom.setStyle(proxy, "visibility", "");
        var a = new YAHOO.util.Motion( 
            proxy, { 
                points: { 
                    to: Dom.getXY(srcEl)
                }
            }, 
            0.2, 
            YAHOO.util.Easing.easeOut 
        )
        var proxyid = proxy.id;
        var thisid = this.id;

        // Hide the proxy and show the source element when finished with the animation
        a.onComplete.subscribe(function() {
                Dom.setStyle(proxyid, "visibility", "hidden");
                Dom.setStyle(thisid, "visibility", "");
            });
        a.animate();
    },

    onDragDrop: function(e, id) {

        // If there is one drop interaction, the li was dropped either on the list,
        // or it was dropped on the current location of the source element.
        if (DDM.interactionInfo.drop.length === 1) {

            // The position of the cursor at the time of the drop (YAHOO.util.Point)
            var pt = DDM.interactionInfo.point; 

            // The region occupied by the source element at the time of the drop
            var region = DDM.interactionInfo.sourceRegion; 

            // Check to see if we are over the source element's location.  We will
            // append to the bottom of the list once we are sure it was a drop in
            // the negative space (the area of the list without any list items)
            if (!region.intersect(pt)) {
                var destEl = Dom.get(id);
                var destDD = DDM.getDDById(id);
                destEl.appendChild(this.getEl());
                destDD.isEmpty = false;
                DDM.refreshCache();
            }

        }
    },

    onDrag: function(e) {

        // Keep track of the direction of the drag for use during onDragOver
        var y = Event.getPageY(e);

        if (y < this.lastY) {
            this.goingUp = true;
        } else if (y > this.lastY) {
            this.goingUp = false;
        }

        this.lastY = y;
    },

    onDragOver: function(e, id) {
    
        var srcEl = this.getEl();
        var destEl = Dom.get(id);

        // We are only concerned with list items, we ignore the dragover
        // notifications for the list.
        if (destEl.nodeName.toLowerCase() == "li") {
            var orig_p = srcEl.parentNode;
            var p = destEl.parentNode;

            if (this.goingUp) {
                p.insertBefore(srcEl, destEl); // insert above
            } else {
                p.insertBefore(srcEl, destEl.nextSibling); // insert below
            }

            DDM.refreshCache();
        }
    }
});

Event.onDOMReady(YAHOO.example.ScheduleApp.init, YAHOO.example.ScheduleApp, true);

})();

//]]>
</script>


<?php 
 echo "</td></tr>\n</table>\n";
contentEnd();
pageEnd();
?>
