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

$seasonfilter = array();
$seriesfilter = array();
$poolfilter = array();

$seasons = Seasons();
while($season = mysql_fetch_assoc($seasons)){
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

function gameHeight($gameInfo) {
  if(!empty($gameInfo['gametimeslot'])){
    return max(intval($gameInfo['gametimeslot'] * MIN_HEIGHT), intval(15 * MIN_HEIGHT)) -2;
  }else{
    return max(intval($gameInfo['timeslot'] * MIN_HEIGHT), intval(15 * MIN_HEIGHT)) -2;
  }
}

function pauseHeight($gameStart, $nextStart) {
  echo "<!--".EpocToMysql($gameStart)." ".EpocToMysql($nextStart)."-->\n";
  return ((($gameStart - $nextStart) / 60) * MIN_HEIGHT) - 2;
}

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
	float: left
}

ul.draglist {
	position: relative;
	width: 200px;
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
}

#user_actions {
	float: right;
}
</style>
<script type="text/javascript">
<!--
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
$scrolling = "onkeydown='KeyDown(event);' onkeyup='KeyUp(event);'";
pageTopHeadClose($title,false, $scrolling);
leftMenu($LAYOUT_ID);
contentStart();
echo "<table><tr><td style='width:300px; vertical-align: text-top'>";

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

function gameEntry($color, $height, $gameId, $gamename, $poolname, $editable=true) {
  $textColor = textColor($color);
  echo "<li class='list1' style='color:#".$textColor.";background-color:#".$color.";min-height:".$height."px' id='game".$gameId."'>";
  echo $poolname;
  if ($editable) {
    echo "<span style='align:right;float:right;'><a href='javascript:hide(\"game".$gameId."\");'>x</a></span>";
  }
  echo "<br/><b>$gamename</b>";
  echo "</li>\n";
}

echo "<table><tr><td>\n";
echo "<h3>"._("Unscheduled")."</h3>\n";
echo "<form action='' method='get' name='filtersel'>";
echo "<p><select class='dropdown' style='width:100%' name='eventfilter' onchange='OnEventSelect(this);'>\n";
echo "<option class='dropdown' value=''>"._("Select event")."</option>";
foreach($seasonfilter as $season){
  if($seasonId==$season['id']){
    echo "<option class='dropdown' selected='selected' value='". $season['id'] . "'>". utf8entities($season['name']) ."</option>";
  }else{
    echo "<option class='dropdown' value='". $season['id'] . "'>". utf8entities($season['name']) ."</option>";
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
    echo "<option class='dropdown' selected='selected' value='". $series['id'] . "'>". utf8entities($series['name']) ."</option>";
  }else{
    echo "<option class='dropdown' value='". $series['id'] . "'>". utf8entities($series['name']) ."</option>";
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
    echo "<option class='dropdown' selected='selected' value='". $pool['id'] . "'>". utf8entities($pool['name']) ."</option>";
  }else{
    echo "<option class='dropdown' value='". $pool['id'] . "'>". utf8entities($pool['name']) ."</option>";
  }
}
echo "</select></p>\n";
echo "</form>";
echo "<div class='workarea' >\n";
echo "<ul id='unscheduled' class='draglist' style='min-height:600px'>\n";
foreach ($gameData as $gameId => $gameInfo) {
  if (hasEditGamesRight($gameInfo['series'])) {
    $height = gameHeight($gameInfo);
    $poolname = U_($gameInfo['seriesname']).", ". U_($gameInfo['poolname']);
    if(!empty($gameInfo['gametimeslot'])){
      $maxtimeslot = max($maxtimeslot, $gameInfo['gametimeslot']);
    }else{
      $maxtimeslot = max($maxtimeslot, $gameInfo['timeslot']);
    }
    if($gameInfo['hometeam'] && $gameInfo['visitorteam']){
      gameEntry($gameInfo['color'], $height, $gameId, $gameInfo['hometeamname']." - ".$gameInfo['visitorteamname'],$poolname);
    }else{
      gameEntry($gameInfo['color'], $height, $gameId, U_($gameInfo['phometeamname'])." - ".U_($gameInfo['pvisitorteamname']),$poolname);
    }
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
echo "<input type='text' id='pauseLen' value='$maxtimeslot' size='3'/>". _("minutes");
echo "</td><td style='vertical-align:top'>";
$reservedPauses = array();

if(count($reservationData)>1){
  foreach ($reservationData as $day => $dayArray) {
    echo "<table><tr>\n";

    foreach ($dayArray as $reservationId => $reservationArray) {
      if (!isset($firstStart)) {
        $firstStart = strtotime($reservationArray['starttime']);
        echo "<td>".JustDate($reservationArray['starttime'])."</td>\n";
      }
      echo "<td style='vertical-align:top;padding:5px'>\n";
      $offset = intval((strtotime($reservationArray['starttime']) - $firstStart) / 60) + 60;
      $startTime = strtotime($reservationArray['starttime']);
      $endTime =  strtotime($reservationArray['endtime']);
      $duration = ($endTime - $startTime) / 60;

      echo "<div style='vertical-align:bottom;min-height:".intval($offset * MIN_HEIGHT)."px'>";
      echo "<h3>".$reservationArray['name']." "._("Field ")." ".$reservationArray['fieldname']." ".date("H:i", $startTime)." -&gt;</h3></div>\n";
      echo "<div class='workarea' >\n";
      echo "<ul id='res".$reservationId."' class='draglist' style='min-height:".($duration * MIN_HEIGHT)."px'>\n";
      $nextStart = $startTime;
      foreach ($reservationArray['games'] as $gameId => $gameInfo) {
        $gameStart = strtotime($gameInfo['time']);
        if ($gameStart > $nextStart) {
          $height = pauseHeight($gameStart, $nextStart);
          echo "<li class='list1' id='pause".$gameId."' style='min-height:".$height."px'>"._("Pause")."<span style='align:right;float:right'><a href='javascript:hide(\"pause".$gameId."\");'>x</a></span></li>\n";
          $reservedPauses[] = "pause".$gameId;
        }
        if(!empty($gameInfo['gametimeslot'])){
          $nextStart = $gameStart + (max($gameInfo['gametimeslot'], 60) * 60);
        }else{
          $nextStart = $gameStart + (max($gameInfo['timeslot'], 60) * 60);
        }
        $height = gameHeight($gameInfo);
        if($gameInfo['hometeam'] && $gameInfo['visitorteam']){
          $gametitle = $gameInfo['hometeamname']." - ".$gameInfo['visitorteamname'];
        }else{
          $gametitle = $gameInfo['phometeamname']." - ".$gameInfo['pvisitorteamname'];
        }
        $pooltitle = U_($gameInfo['seriesname']).", ". U_($gameInfo['poolname']);
        if (hasEditGamesRight($gameInfo['series'])) {
          gameEntry($gameInfo['color'],$height,$gameId,$gametitle,$pooltitle);
        } else {
          gameEntry($gameInfo['color'],$height,"unmanaged".$gameId,$gametitle,$pooltitle, false);
        }
      }
      echo "</ul>\n";
      echo "</div>\n</td>\n";
    }
    echo "<td>".JustDate($reservationArray['starttime'])."</td>\n";
    echo "</tr>\n</table>\n";
    unset($firstStart);
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
      $offset = intval((strtotime($reservationArray['starttime']) - $firstStart) / 60) + 60;
      $startTime = strtotime($reservationArray['starttime']);
      $endTime =  strtotime($reservationArray['endtime']);
      $duration = ($endTime - $startTime) / 60;

      echo "<div style='vertical-align:bottom;min-height:".intval($offset * MIN_HEIGHT)."px'>";
      echo "<h3>".$reservationArray['name']." "._("Field ")." ".$reservationArray['fieldname']." ".date("H:i", $startTime)." -&gt;</h3></div>\n";
      echo "<div class='workarea' >\n";
      echo "<ul id='res".$reservationId."' class='draglist' style='min-height:".($duration * MIN_HEIGHT)."px'>\n";
      $nextStart = $startTime;
      foreach ($reservationArray['games'] as $gameId => $gameInfo) {
        $gameStart = strtotime($gameInfo['time']);
        if ($gameStart > $nextStart) {
          $height = pauseHeight($gameStart, $nextStart);
          echo "<li class='list1' id='pause".$gameId."' style='min-height:".$height."px'>"._("Pause")."<span style='align:right;float:right'><a href='javascript:hide(\"pause".$gameId."\");'>x</a></span></li>\n";
          $reservedPauses[] = "pause".$gameId;
        }
        if(!empty($gameInfo['gametimeslot'])){
          $nextStart = $gameStart + (max($gameInfo['gametimeslot'], 60) * 60);
        }else{
          $nextStart = $gameStart + (max($gameInfo['timeslot'], 60) * 60);
        }
        $height = gameHeight($gameInfo);
        if($gameInfo['hometeam'] && $gameInfo['visitorteam']){
          $gametitle = $gameInfo['hometeamname']." - ".$gameInfo['visitorteamname'];
        }else{
          $gametitle = $gameInfo['phometeamname']." - ".$gameInfo['pvisitorteamname'];
        }
        $pooltitle = U_($gameInfo['seriesname']).", ". U_($gameInfo['poolname']);
        if (hasEditGamesRight($gameInfo['series'])) {
          gameEntry($gameInfo['color'],$height,$gameId,$gametitle,$pooltitle);
        } else {
          gameEntry($gameInfo['color'],$height,"unmanaged".$gameId,$gametitle,$pooltitle, false);
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
?>
<table>
	<tr>
		<td id="user_actions" style='float: left; padding: 20px'><input
			type="button" id="showButton" value="<?php echo _("Save"); ?>" /></td>
		<td class='center'>
			<div style='width: 100px; height: 100px' id="responseStatus"></div>
		</td>
	</tr>
</table>
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

//ajax based solution would be better, but at now this feels simpler because also dropdown fields need to be updated according user selection.
function OnEventSelect(dropdown)
{
    var myindex  = dropdown.selectedIndex;
    var SelValue = dropdown.options[myindex].value;
	
	var url = location.href;
	
	var param = "season";
	var re = new RegExp("([?|&])" + param + "=.*?(&|$)","i");
    if (url.match(re)){
        url=url.replace(re,'$1' + param + "=" + SelValue + '$2');
    }else{
		url = url + '&' + param + "=" + SelValue;
	}
	
	var param = "series";
	var re = new RegExp("([?|&])" + param + "=.*?(&|$)","i");
    if (url.match(re)){
        url=url.replace(re,'$1' + param + "=" + 0 + '$2');
    }
		
	var param = "pool";
	var re = new RegExp("([?|&])" + param + "=.*?(&|$)","i");
    if (url.match(re)){
        url=url.replace(re,'$1' + param + "=" + 0 + '$2');
    }
	redirecturl=url;
	redirectWithConfirm();
}

function OnSeriesSelect(dropdown)
{
    var myindex  = dropdown.selectedIndex;
    var SelValue = dropdown.options[myindex].value;
	var url = location.href;

	var param = "series";
	var re = new RegExp("([?|&])" + param + "=.*?(&|$)","i");
    if (url.match(re)){
        url=url.replace(re,'$1' + param + "=" + SelValue + '$2');
    }else{
		url = url + '&' + param + "=" + SelValue;
	}
		
	var param = "pool";
	var re = new RegExp("([?|&])" + param + "=.*?(&|$)","i");
    if (url.match(re)){
        url=url.replace(re,'$1' + param + "=" + 0 + '$2');
    }
	
	redirecturl=url;
	redirectWithConfirm();
}

function OnPoolSelect(dropdown)
{
    var myindex  = dropdown.selectedIndex;
    var SelValue = dropdown.options[myindex].value;
	var url = location.href;
	
	var param = "pool";
	var re = new RegExp("([?|&])" + param + "=.*?(&|$)","i");
    if (url.match(re)){
        url=url.replace(re,'$1' + param + "=" + SelValue + '$2');
    }else{
		url = url + '&' + param + "=" + SelValue;
	}
	redirecturl=url;
	redirectWithConfirm();
}

function redirectWithConfirm(){
	if(modified){
		var answer = confirm('<?php echo _("Save changes?");?>');
		if (answer){
			YAHOO.example.ScheduleApp.requestString();
		}else{
			location.href=redirecturl;
		}
	}else{
		location.href=redirecturl;
	}
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
    	var pauseElem = document.createElement("li");
	pauseElem.innerHTML = "<?php echo _("Pause"); ?><span style=\"align:right;float:right\"><a href='javascript:hide(\"pause" + pauseIndex+ "\");'>x</a></span>";
	pauseElem.setAttribute("class", "list1");
	pauseElem.setAttribute("id", "pause" + pauseIndex);
	Dom.setStyle(pauseElem, "min-height", ((Dom.get("pauseLen").value * minHeight)-2) + "px");
	unscheduled.appendChild(pauseElem);
	new YAHOO.example.DDList("pause" + pauseIndex);
	pauseIndex++;
    },	
    
    requestString: function() {
        var parseList = function(ul, id) {
            var items = ul.getElementsByTagName("li");
            var out = id;
			var offset = 0;
            for (i=0;i<items.length;i=i+1) {

                var height =  Dom.getStyle(items[i], "min-height"); // items[i].firstChild.data; //
                height = parseInt(height.substring(0, height.length -2)) + 2;
				var nextId = items[i].id.substring(4);
				if (!isNaN(nextId)) {
                	out += ":" + nextId + "/" + offset;
				}
                offset += (height/minHeight);
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
	Dom.setStyle(responseDiv,"height", "20px");
	Dom.setStyle(responseDiv,"width", "20px");
	Dom.setStyle(responseDiv,"class", "inprogress");
	responseDiv.innerHTML = '&nbsp;';
	var transaction = YAHOO.util.Connect.asyncRequest('POST', 'index.php?view=admin/saveschedule', callback, request);         
    },
};

var callback = {
	success: function(o) {
		var responseDiv = Dom.get("responseStatus");
		Dom.setStyle(responseDiv,"background-image","");
		Dom.setStyle(responseDiv,"color", "#00aa00");
		responseDiv.innerHTML = o.responseText;
		modified=0;
		if(redirecturl){
			location.href=redirecturl;
		}
	},

	failure: function(o) {
		var responseDiv = Dom.get("responseStatus");
		Dom.setStyle(responseDiv,"background-image","");
		Dom.setStyle(responseDiv,"color", "#aa0000");
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
		modified=1;
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
