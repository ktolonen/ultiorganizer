<?php
include_once $include_prefix.'lib/team.functions.php';
include_once $include_prefix.'lib/common.functions.php';
include_once $include_prefix.'lib/season.functions.php';
include_once $include_prefix.'lib/player.functions.php';
include_once $include_prefix.'lib/pool.functions.php';
include_once $include_prefix.'lib/reservation.functions.php';
include_once $include_prefix.'lib/url.functions.php';

$LAYOUT_ID = ADDMEDIALINK;
$max_file_size = 5 * 1024 * 1024; //5 MB
$max_new_links = 3;
$html = "";
$title = _("Add links");
$userinfo = UserInfo($_SESSION['uid']);
$teamId = 0;
$palyerId = 0;
$clubId = 0;
$gameId = 0;
$countryId = 0;
$seriesId = 0;
$poolId = 0;
$owner = "";
$owner_id = 0;

if(!empty($_GET["team"])){
	$owner_id = intval($_GET["team"]);
	$owner = "team";
}

if(!empty($_GET["player"])){
	$owner_id = intval($_GET["player"]);
	$owner = "player";
}
if(!empty($_GET["club"])){
	$owner_id = intval($_GET["club"]);
	$owner = "club";
}
if(!empty($_GET["game"])){
	$owner_id = intval($_GET["game"]);
	$owner = "game";
}
if(!empty($_GET["country"])){
	$owner_id = intval($_GET["country"]);
	$owner = "country";
}
if(!empty($_GET["series"])){
	$owner_id = intval($_GET["series"]);
	$owner = "series";
}
if(!empty($_GET["pool"])){
	$owner_id = intval($_GET["pool"]);
	$owner = "pool";
}

if(isset($_SERVER['HTTP_REFERER']))
	$backurl = utf8entities($_SERVER['HTTP_REFERER']);


$pageurl = "?view=user/addmedialink&amp;$owner=$owner_id";

if(isset($_POST['save'])){
	$backurl = utf8entities($_POST['backurl']);
		
	for($i=0;$i<$max_new_links;$i++){
		
		if(!empty($_POST["url$i"])){
			$url = array(
				"owner"=>$owner,
				"owner_id"=>$owner_id,
				"type"=>$_POST["urltype$i"],
				"url"=>$_POST["url$i"],
				"ismedialink"=>1,
				"name"=>"",
				"mediaowner"=>"",
				"publisher_id"=>$userinfo['id']
			);
			
			if(!empty($_POST["urlname$i"])){
				$url['name'] = $_POST["urlname$i"];
			}
			if(!empty($_POST["mediaowner$i"])){
				$url['mediaowner'] = $_POST["mediaowner$i"];
			}
			$url_id = AddMediaUrl($url);
			$_SESSION["var$i"] = utf8entities($_POST["url$i"]);
			
			if($owner == "game" && !empty($_POST["time$i"])){
				$time = TimeToSec($_POST["time$i"]);
				AddGameMediaEvent($owner_id, $time, $url_id);
			}
		}
		
	$_SESSION['title'] = _("Added media links") .":";
	$_SESSION['backurl'] = $backurl;
	session_write_close();
	header("location:?view=admin/success");
	}
}elseif(isset($_POST['removeurl_x'])){
	$backurl = utf8entities($_POST['backurl']);
	$id = $_POST['hiddenDeleteId'];
	RemoveMediaUrl($id);
}

	
//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'script/common.js.inc';
?>
<script type="text/javascript">
<!--
function validTime(field) 
	{
	field.value=field.value.replace(/[^0-9]/g, '.')
	}
//-->
</script>
<?php
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//content
$html .= "<form method='post' enctype='multipart/form-data' action='$pageurl'>\n";
	

$urls = GetMediaUrlList($owner, $owner_id);

if(count($urls)){
	$html .= "<table width='100%'>";

	foreach($urls as $url){
		$html .= "<tr style='border-bottom-style:solid;border-bottom-width:1px;'>";
		$html .= "<td><img width='16' height='16' src='images/linkicons/".$url['type'].".png' alt='".$url['type']."'/></td>";
		$html .= "<td>";
		if(!empty($url['name'])){
			$html .="<a href='". $url['url']."'>". utf8entities($url['name'])."</a> (".utf8entities($url['url']).")";
		}else{
			$html .="<a href='". $url['url']."'>". utf8entities($url['url'])."</a>";
		}
		$html .= "</td>";
		$html .= "<td>". $url['mediaowner']."</td>";
		$html .= "<td>". $url['publisher']."</td>";
		
		if($url['publisher_id']==$userinfo['id']){
			$html .= "<td class='right'><input class='deletebutton' type='image' src='images/remove.png' name='removeurl' value='X' alt='X' onclick='setId(".$url['url_id'].");'/></td>";
		}
		$html .= "</tr>";
	}

	$html .= "</table>";
}
if($owner == "game"){
	$events = GameMediaEvents($owner_id);
	
	//remove if url deleted
	foreach($events as $event){
		if(empty($event['url'])){
			RemoveGameMediaEvent($owner_id, $event['info']);
		}
	}
	
	$events = GameMediaEvents($owner_id);
	
	if(count($events)){
		$html .= "<table>";
		$html .= "<tr>";
		$html .= "<th>"._("Time")."</th>";
		$html .= "<th colspan='2'>"._("URL")."</th>";
		$html .= "</tr>";
		
		foreach($events as $event){
			$html .= "<tr>";
			$html .= "<td>".SecToMin($event['time'])."</td>";
			$html .= "<td><img width='16' height='16' src='images/linkicons/".$event['type'].".png' alt='".$event['type']."'/></td>";
			$html .= "<td>".$event['url']."</td>";
			$html .= "</tr>";
		}
		$html .= "</table>";
	}
}

$html .= "<table>";
$html .= "<tr>";
if($owner == "game"){
	$html .= "<th>"._("Time")." ("._("optional").")</th>";
}
$html .= "<th>"._("Type")."</th>";
$html .= "<th>"._("URL")."</th>";
$html .= "<th>"._("Name")." ("._("optional").")</th>";
$html .= "<th>"._("Media owner")." ("._("optional").")</th>";
$html .= "<th>"._("Publisher")."</th>";
$html .= "</tr>";

$urltypes = GetMediaUrlTypes();
for($i=0;$i<$max_new_links;$i++){
	$html .= "<tr>";
	if($owner == "game"){
		$html .= "<td><input class='input' onkeyup=\"validTime(this);\" name='time$i' maxlength='8' size='8'/></td>";
	}
	$html .= "<td><select class='dropdown' name='urltype$i'>\n";
	foreach($urltypes as $type){
		$html .= "<option value='".$type['type']."'>". $type['name'] ."</option>\n";
	}
	$html .= "</select></td>";
	$html .= "<td><input class='input' maxlength='500' size='30' name='url$i' value=''/></td>";
	$html .= "<td><input class='input' maxlength='500' size='15' name='urlname$i' value=''/></td>";
	$html .= "<td><input class='input' maxlength='100' size='15' name='mediaowner$i' value=''/></td>";
	$html .= "<td style='white-space: nowrap'>". $userinfo['name']."</td>";
	$html .= "</tr>";
}
$html .= "</table>";
$html .=  "<p>
	  <input class='button' type='submit' name='save' value='"._("Save")."' />
	  <input class='button' type='button' name='takaisin'  value='"._("Return")."' onclick=\"window.location.href='$backurl'\"/>
	  <input type='hidden' name='backurl' value='$backurl'/>
	  <input type='hidden' name='MAX_FILE_SIZE' value='$max_file_size'/>
	  </p>\n";
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>";

echo $html;

//common end
contentEnd();
pageEnd();
?>
