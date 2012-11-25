<?php
include_once $include_prefix.'lib/configuration.functions.php';
include_once $include_prefix.'lib/facebook.functions.php';
include_once $include_prefix.'lib/url.functions.php';

$LAYOUT_ID = ADDSEASONLINKS;
$title = _("Event links");
$html = "";
$seasonId = $_GET["season"];

if(!empty($_POST['save'])){

	$settings = array();

	$setting = array();
	$setting['name']="FacebookUpdatePage";
	$setting['value']=$_POST['FacebookUpdatePage'];
	$settings[] = $setting;
	
	SetServerConf($settings);
	
	for($i=0;!empty($_POST["urlid$i"]);$i++){
		$url = array(
				"url_id"=>$_POST["urlid$i"],
				"owner"=>"ultiorganizer",
				"owner_id"=>$seasonId,
				"type"=>$_POST["urltype$i"],
				"ordering"=>$_POST["urlorder$i"],
				"url"=>$_POST["url$i"],
				"ismedialink"=>0,
				"name"=>$_POST["urlname$i"],
				"mediaowner"=>"",
				"publisher_id"=>""
			);

		if($_POST["urltype$i"]=="menumail"){
			SetMail($url);
		}else{
			SetUrl($url);
		}
	}
	if(!empty($_POST["newurl"])){
		$url = array(
				"owner"=>"ultiorganizer",
				"owner_id"=>$seasonId,
				"type"=>$_POST["newurltype"],
				"ordering"=>$_POST["newurlorder"],
				"url"=>$_POST["newurl"],
				"ismedialink"=>0,
				"name"=>$_POST["newurlname"],
				"mediaowner"=>"",
				"publisher_id"=>""
			);
		if($_POST["newurltype"]=="menumail"){
			AddMail($url);
		}else{
			AddUrl($url);
		}
	}
	$serverConf = GetSimpleServerConf();
	
}elseif(!empty($_POST['remove_x'])) {
	$id = $_POST['hiddenDeleteId'];
	RemoveUrl($id);
}

$settings = GetServerConf();
//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();
$htmltmp1 = "";
$htmltmp2 = "";

foreach($settings as $setting){
	
	if($setting['name']=="FacebookUpdatePage"){
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>"._("Facebook Update Page").":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='FacebookUpdatePage' value='".$setting['value']."'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	
}			

$html .= "<form method='post' action='?view=admin/addseasonlinks&amp;season=".$seasonId."' id='Form'>";

$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= "<tr><th>"._("Type")."</th><th>"._("Order")."</th><th>"._("Name")."</th><th>"._("Url")."</th><th></th></tr>\n";
$urls = GetUrlListByTypeArray(array("menulink","menumail"),$seasonId);
$i=0;
foreach($urls as $url){
	$html .= "<tr>";
	$html .= "<td>".$url['type']."<input type='hidden' name='urltype".$i."' value='".$url['type']."'/></td>";
	$html .= "<td><input class='input' size='3' maxlength='2' name='urlorder".$i."' value='".$url['ordering']."'/></td>";
	$html .= "<td><input class='input' size='30' maxlength='150' name='urlname".$i."' value='".$url['name']."'/></td>";
	$html .= "<td><input class='input' size='40' maxlength='500' name='url".$i."' value='".$url['url']."'/></td>";
	$html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' name='remove' alt='"._("X")."' onclick=\"setId(".$url['url_id'].");\"/></td>";
	$html .= "<td><input type='hidden' name='urlid".$i."' value='".$url['url_id']."'/></td>";
	$html .= "</tr>\n";
	$i++;
}
$html .= "<tr><td><select class='dropdown' name='newurltype'>\n";
$html .= "<option value='menulink'>"._("Menu link")."</option>\n";
$html .= "<option value='menumail'>"._("Menu mail")."</option>\n";
$html .= "</select></td>";
$html .= "<td><input class='input' size='3' maxlength='2' name='newurlorder' value=''/></td>";
$html .= "<td><input class='input' size='30' maxlength='150' name='newurlname' value=''/></td>";
$html .= "<td><input class='input' size='40' maxlength='500' name='newurl' value=''/></td>";
$html .= "</tr>\n";
$html .= "</table>\n";


$html .= "<h1>". _("3rd party API settings") ."</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= $htmltmp1;
$html .= "</table>\n";


$html .= "<input type='hidden' name='save' value='hiddensave'/>\n";
$html .= "<p><input class='button' name='savebutton' type='submit' value='"._("Save")."'/></p>";
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>";
echo $html;
contentEnd();

	echo "</body></html>";
?>