<?php
include_once 'lib/season.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/common.functions.php';

$LAYOUT_ID = SEASONS;
$seasonId = "";
$html = "";
$backurl = utf8entities($_SERVER['HTTP_REFERER']);

//season parameters
$sp = array(
	"season_id"=>"",
	"name"=>"",
	"type"=>"",
	"starttime"=>"",
	"istournament"=>0,
	"isinternational"=>0,
	"organizer"=>"",
	"category"=>"",
	"isnationalteams"=>0,
	"endtime"=>"",
	"spiritmode"=>0,
	"showspiritpoints"=>0,
	"iscurrent"=>0,
	"enrollopen"=>0,
	"enroll_deadline"=>"",
	"timezone"=>GetDefTimeZone()	
	);
	
if(!empty($_GET["season"]))
	$seasonId = $_GET["season"];
	
//process itself on submit
if(!empty($_POST['add'])){
    $backurl = utf8entities($_POST['backurl']);
	$sp['season_id'] = $_POST['season_id'];
	$sp['name'] = $_POST['seasonname'];
	$sp['type'] = $_POST['type'];
	$sp['istournament'] = !empty($_POST['istournament']);
	$sp['isinternational'] = !empty($_POST['isinternational']);
	$sp['organizer'] = $_POST['organizer'];
	$sp['category'] = $_POST['category'];
	$sp['isnationalteams'] = !empty($_POST['isnationalteams']);
	$sp['timezone'] = $_POST['timezone'];
	$sp['starttime'] = ToInternalTimeFormat($_POST['seasonstarttime']);
	$sp['endtime'] = ToInternalTimeFormat($_POST['seasonendtime']);
	$sp['enrollopen'] = !empty($_POST['enrollopen']);
	$sp['enroll_deadline'] = isset($_POST['enrollendtime']) ? ToInternalTimeFormat($_POST['enrollendtime']) : ToInternalTimeFormat($_POST['seasonstarttime']);
	$sp['iscurrent'] = !empty($_POST['iscurrent']);
	$sp['spiritmode'] = $_POST['spiritmode'];
	$sp['showspiritpoints'] = !empty($_POST['showspiritpoints']);
	$comment=$_POST['comment'];
	
	if(empty($_POST['season_id'])){
		$html .= "<p class='warning'>"._("Event id can not be empty").".</p>";
	}else if(preg_match('/[ ]/', $_POST['season_id']) || !preg_match('/[a-z0-9.]/i', $_POST['season_id'])){
		$html .= "<p class='warning'>"._("Event id may not have spaces or special characters").".</p>";
	}else if(empty($_POST['seasonname'])){
		$html .= "<p class='warning'>"._("Name can not be empty").".</p>";
	}else if(empty($_POST['type'])){
		$html .= "<p class='warning'>"._("Type can not be empty").".</p>";
	}else{
		AddSeason($sp['season_id'], $sp, $comment);
		$seasonId = $sp['season_id'];
		//add rights for season creator
		AddEditSeason($_SESSION['uid'],$sp['season_id']);
		AddUserRole($_SESSION['uid'], 'seasonadmin:'.$sp['season_id']);
		
		if($sp['istournament']){
			$_SESSION['title'] = _("New tournament addded") .":";
		}else{
			$_SESSION['title'] = _("New season added") .":";
		}
		$_SESSION["var0"] = _("Name").": ".utf8entities($sp['name']);
		$_SESSION["var1"] = _("Type").": ".utf8entities($sp['type']);
		$_SESSION["var2"] = _("Starts").": ".ShortDate($sp['starttime']);
		$_SESSION["var3"] = _("Ends").": ".ShortDate($sp['endtime']);
		$_SESSION["var4"] = _("Enrollment open").": ".(intval($sp['enrollopen'])?_("yes"):_("no"));
		$_SESSION['backurl'] = "?view=admin/seasons";
		session_write_close();
		header("location:?view=admin/seasonadmin&season=$seasonId");
	}
}else if(!empty($_POST['save'])){
    $backurl = utf8entities($_POST['backurl']);
	if(empty($_POST['seasonname'])){
		$html .= "<p class='warning'>"._("Name can not be empty").".</p>";
	}else{
		$sp['season_id'] = $seasonId;
		$sp['name'] = $_POST['seasonname'];
		$sp['type'] = $_POST['type'];
		$sp['istournament'] = !empty($_POST['istournament']);
		$sp['isinternational'] = !empty($_POST['isinternational']);
		$sp['isnationalteams'] = !empty($_POST['isnationalteams']);
		$sp['organizer'] = $_POST['organizer'];
		$sp['category'] = $_POST['category'];
		$sp['starttime'] = ToInternalTimeFormat($_POST['seasonstarttime']);
		$sp['endtime'] = ToInternalTimeFormat($_POST['seasonendtime']);
		$sp['enrollopen'] = !empty($_POST['enrollopen']);
		$sp['enroll_deadline'] = ToInternalTimeFormat($_POST['enrollendtime']);
		$sp['iscurrent'] = !empty($_POST['iscurrent']);
		$sp['spiritmode'] = $_POST['spiritmode'];
		$sp['showspiritpoints'] = !empty($_POST['showspiritpoints']);
		$sp['timezone'] = $_POST['timezone'];
		$comment=$_POST['comment'];
		SetSeason($sp['season_id'], $sp, $comment);
	}
}
	
$title = _("Edit event");
if (strlen($sp['name']) > 0) {
	$title .= ": ".$sp['name'];
}

if($seasonId){
	$info = SeasonInfo($seasonId);
	$sp['season_id'] = $info['season_id'];
	$sp['name'] = $info['name'];
	$sp['type'] = $info['type'];
	$sp['starttime'] = $info['starttime'];
	$sp['endtime'] = $info['endtime'];
	$sp['iscurrent'] = $info['iscurrent'];
	$sp['enrollopen']  = $info['enrollopen'];
	$sp['enroll_deadline'] = $info['enroll_deadline'];
	$sp['istournament'] = $info['istournament'];
	$sp['isinternational'] = $info['isinternational'];
	$sp['organizer'] = $info['organizer'];
	$sp['category'] = $info['category'];
	$sp['isnationalteams'] = $info['isnationalteams'];
	$sp['spiritmode'] = $info['spiritmode'];
	$sp['showspiritpoints'] = $info['showspiritpoints'];
	$sp['timezone'] = $info['timezone'];
	$comment = CommentRaw(1, $info['season_id']);
} else {
	$comment = "";
}

//common page
pageTopHeadOpen($title);
include_once 'lib/yui.functions.php';
echo yuiLoad(array("utilities", "calendar", "datasource", "autocomplete"));
?>
<link rel="stylesheet" type="text/css" href="script/yui/calendar/calendar.css" />

<script type="text/javascript">
<!--

YAHOO.namespace("calendar");

YAHOO.calendar.init = function() {

	YAHOO.calendar.cal1 = new YAHOO.widget.Calendar("cal1","calContainer1");
	YAHOO.calendar.cal2 = new YAHOO.widget.Calendar("cal2","calContainer2");
	YAHOO.calendar.cal3 = new YAHOO.widget.Calendar("cal3","calContainer3");
	YAHOO.calendar.cal1.cfg.setProperty("START_WEEKDAY", "1"); 
	YAHOO.calendar.cal2.cfg.setProperty("START_WEEKDAY", "1"); 
	YAHOO.calendar.cal3.cfg.setProperty("START_WEEKDAY", "1"); 
	YAHOO.calendar.cal1.render();
	YAHOO.calendar.cal2.render();
	YAHOO.calendar.cal3.render();

	function handleCal1Button(e) {
		var containerDiv = YAHOO.util.Dom.get("calContainer1"); 
		
		if(containerDiv.style.display == "none"){
			updateCal("seasonstarttime",YAHOO.calendar.cal1);
			YAHOO.calendar.cal1.show();
		}else{
			YAHOO.calendar.cal1.hide();
		}
	}
	
	function handleCal2Button(e) {
		var containerDiv = YAHOO.util.Dom.get("calContainer2"); 
		
		if(containerDiv.style.display == "none"){
			var txtDate1 = document.getElementById("seasonendtime");
			if (txtDate1.value != "") {
				updateCal("seasonendtime",YAHOO.calendar.cal2);
			}else{
				updateCal("seasonstarttime",YAHOO.calendar.cal2);
			}
			YAHOO.calendar.cal2.show();
		}else{
			YAHOO.calendar.cal2.hide();
		}
	}
	function handleCal3Button(e) {
		var containerDiv = YAHOO.util.Dom.get("calContainer3"); 
		
		if(containerDiv.style.display == "none"){
			var txtDate1 = document.getElementById("enrollendtime");
			if (txtDate1.value != "") {
				updateCal("enrollendtime",YAHOO.calendar.cal3);
			}else{
				updateCal("seasonstarttime",YAHOO.calendar.cal3);
			}
			YAHOO.calendar.cal3.show();
		}else{
			YAHOO.calendar.cal3.hide();
		}
	}
	// Listener to show the Calendar when the button is clicked
	YAHOO.util.Event.addListener("showcal1", "click", handleCal1Button);
	YAHOO.util.Event.addListener("showcal2", "click", handleCal2Button);
	YAHOO.util.Event.addListener("showcal3", "click", handleCal3Button);
	YAHOO.calendar.cal1.hide();
	YAHOO.calendar.cal2.hide();
	YAHOO.calendar.cal3.hide();
	
	function handleSelect1(type,args,obj) {
			var dates = args[0]; 
			var date = dates[0];
			var year = date[0], month = date[1], day = date[2];
			
			var txtDate1 = document.getElementById("seasonstarttime");
			txtDate1.value = day + "." + month + "." + year;
		}

	function handleSelect2(type,args,obj) {
			var dates = args[0]; 
			var date = dates[0];
			var year = date[0], month = date[1], day = date[2];
			
			var txtDate1 = document.getElementById("seasonendtime");
			txtDate1.value = day + "." + month + "." + year;
		}

	function handleSelect3(type,args,obj) {
		var dates = args[0]; 
		var date = dates[0];
		var year = date[0], month = date[1], day = date[2];
		
		var txtDate1 = document.getElementById("enrollendtime");
		txtDate1.value = day + "." + month + "." + year;
	}

	function updateCal(input,obj) {
            var txtDate1 = document.getElementById(input);
            if (txtDate1.value != "") {
				var date = txtDate1.value.split(".");
				obj.select(date[1] + "/" + date[0] + "/" + date[2]);
				obj.cfg.setProperty("pagedate", date[1] + "/" + date[2]);
				obj.render();
            }
        }

	YAHOO.calendar.cal1.selectEvent.subscribe(handleSelect1, YAHOO.calendar.cal1, true);
	YAHOO.calendar.cal2.selectEvent.subscribe(handleSelect2, YAHOO.calendar.cal2, true);
	YAHOO.calendar.cal3.selectEvent.subscribe(handleSelect3, YAHOO.calendar.cal3, true);
}
YAHOO.util.Event.onDOMReady(YAHOO.calendar.init);
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

if(empty($seasonId)){
	$html .= "<h2>"._("Add new season/tournament")."</h2>\n";
	$html .= "<form method='post' action='?view=admin/addseasons'>";
	$disabled="";
}else{
	$html .= "<h2>"._("Edit season/tournament")."</h2>\n";
	$html .= "<form method='post' action='?view=admin/addseasons&amp;season=$seasonId'>";
	$disabled="disabled='disabled'";
}

$html .= "<table border='0'>";
$html .= "<tr><td class='infocell'>"._("Event id").": </td><td><input class='input' name='season_id' $disabled value='".utf8entities($sp['season_id'])."'/></td></tr>";
$html .= "<tr rowspan='2'><td class='infocell'>"._("Name").": </td>
			<td>".TranslatedField("seasonname", $sp['name'])."</td>
		</tr>\n";
$html .= "<tr><td class='infocell'>"._("Type").": </td><td><select class='dropdown' name='type'>\n";

$types = SeasonTypes();

foreach($types as $type){
	if($sp['type']==$type)
		$html .= "<option class='dropdown' selected='selected' value='$type'>".U_($type)."</option>\n";
	else
		$html .= "<option class='dropdown' value='$type'>".U_($type)."</option>\n";
}

$html .= "</select></td></tr>\n";

$html .= "<tr><td class='infocell'>"._("Tournament").": </td><td><input class='input' type='checkbox' name='istournament' ";
if ($sp['istournament']) {
	$html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("International").": </td><td><input class='input' type='checkbox' name='isinternational' ";
if ($sp['isinternational']) {
	$html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("For national teams").": </td><td><input class='input' type='checkbox' name='isnationalteams' ";
if ($sp['isnationalteams']) {
	$html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("Spirit points given").": </td><td>";
$spiritmodes = SpiritModes();
$html .= "<select class='dropdown' id='spiritmode' name='spiritmode'>\n";
$html .= "<option value='0'></option>\n";
foreach($spiritmodes as $mode) {
  $selected =  ($sp['spiritmode']==$mode['mode'])?" selected='selected'":"";
  $html .= "<option $selected value='". utf8entities($mode['mode']) . "'>".utf8entities(_($mode['name'])) . "</option>\n";
}
$html .= "</select>\n";
$html .= "</td></tr>\n";

$html .= "<tr><td class='infocell'>"._("Spirit points visible").": </td><td><input class='input' type='checkbox' name='showspiritpoints' ";
if ($sp['showspiritpoints']) {
	$html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "<tr><td class='infocell'>"._("Organizer").": </td><td><input class='input' size='50' maxlength='50' name='organizer' value='".utf8entities($sp['organizer'])."'/></td></tr>";
$html .= "<tr><td class='infocell'>"._("Category").": </td><td><input class='input' size='50' maxlength='50' name='category' value='".utf8entities($sp['category'])."'/></td></tr>";

$html .= "<tr><td class='infocell' style='vertical-align:top'>".htmlentities(_("Comment (you can use <p>, </i>, and <br> tags)")).":</td>
		<td><textarea class='input' rows='10' cols='70' name='comment'>".htmlentities($comment)."</textarea></td></tr>";

$html .= "<tr><td class='infocell'>"._("Timezone").": </td><td>";
$dateTimeZone = GetTimeZoneArray();
$html .= "<select class='dropdown' id='timezone' name='timezone'>\n";
$html .= "<option value=''></option>\n";
foreach($dateTimeZone as $tz){
	if($sp['timezone']==$tz){
		$html .= "<option selected='selected' value='$tz'>".utf8entities($tz)."</option>\n";
	}else{
		$html .= "<option value='$tz'>".utf8entities($tz)."</option>\n";
	}
}
$html .= "</select>\n";
//$dateTime = new DateTime("now", new DateTimeZone($sp['timezone']));
//$html .= DefTimeFormat($dateTime->format("Y-m-d H:i:s"));
$html .= "</td></tr>";

$html .= "<tr><td class='infocell'>"._("Starts")." ("._("dd.mm.yyyy")."): </td><td><input class='input' size='12' maxlength='10' id='seasonstarttime' name='seasonstarttime' value='".ShortDate($sp['starttime'])."'/>&nbsp;&nbsp;";
$html .= "<button type='button' class='button' id='showcal1'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button></td></tr>";
$html .= "<tr><td></td><td><div id='calContainer1'></div></td></tr>";
$html .= "<tr><td class='infocell'>"._("Ends")." ("._("dd.mm.yyyy")."): </td><td><input class='input' size='12' maxlength='10' id='seasonendtime' name='seasonendtime' value='".ShortDate($sp['endtime'])."'/>&nbsp;&nbsp;";
$html .= "<button type='button' class='button' id='showcal2'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button></td></tr>";
$html .= "<tr><td></td><td><div id='calContainer2'></div></td></tr>";
$html .= "<tr><td class='infocell'>"._("Open for enrollment").": </td><td><input class='input' type='checkbox' name='enrollopen' ";
if ($sp['enrollopen']) {
	$html .= "checked='checked'";
}
$html .= "/></td></tr>";
$html .= "<tr><td class='infocell'>"._("Enrolling ends")."<br/>("._("only informational")."): </td>";
$html .= "<td><input class='input' size='12' maxlength='10' id='enrollendtime' name='enrollendtime'  value='".ShortDate($sp['enroll_deadline'])."'/>&nbsp;&nbsp;";
$html .= "<button type='button' class='button' id='showcal3'><img width='12px' height='10px' src='images/calendar.gif' alt='cal'/></button></td></tr>";
$html .= "<tr><td></td><td><div id='calContainer3'></div></td></tr>";

$html .= "<tr><td class='infocell'>"._("Shown in main menu").": </td><td><input class='input' type='checkbox' name='iscurrent' ";
if ($sp['iscurrent']) {
	$html .= "checked='checked'";
}
$html .= "/></td></tr>";

$html .= "</table>\n";
if(empty($seasonId)){
	$html .= "<p><input class='button' type='submit' name='add' value='"._("Add")."' />";
}else{
	$html .= "<p><input class='button' type='submit' name='save' value='"._("Save")."' />";	
}
$html .= "<input type='hidden' name='backurl' value='$backurl'/>";
$html .= "<input class='button' type='button' value='"._("Return")."' onclick=\"window.location.href='$backurl'\" /></p>";
$html .= "</form>\n";

echo $html;
echo TranslationScript("seasonname");

contentEnd();
pageEnd();
?>