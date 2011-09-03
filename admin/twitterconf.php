<?php
include_once 'lib/season.functions.php';
include_once 'lib/configuration.functions.php';
require_once('lib/twitteroauth/twitteroauth.php');

$LAYOUT_ID = TWITTERCONFIGURATION;
$title = _("Twitter configuration");
$html = "";
$season = $_GET["Season"];
if(!isset($_SESSION['TwitterConsumerKey'])){
	$twitterconf = GetTwitterConf();
	$_SESSION['TwitterConsumerKey'] = $twitterconf['TwitterConsumerKey'];
	$_SESSION['TwitterConsumerSecret'] = $twitterconf['TwitterConsumerSecret'];
	$_SESSION['TwitterOAuthCallback'] = $twitterconf['TwitterOAuthCallback'];
}

if(!empty($_POST['register']) && isSuperAdmin()){
	$_SESSION['season'] = $season;
	$_SESSION['purpose'] = $_POST['purpose'];
	$_SESSION['id'] = $_POST['id'];

	header("location:?view=admin/twitterconnect");
}else if(!empty($_POST['unregister']) && isSuperAdmin()){
	$key = GetTwitterKeyById($_POST['id']);
	$twitter = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $key['keystring'], $key['secrets']);
	$twitter->post('account/end_session');
	DeleteTwitterKey($_POST['id']);
	$url = GetUrl(substr($key['purpose'],0,6), $key['id'], "result_twitter");
	RemoveUrl($url['url_id']);	
}
//common page
pageTop($title);
?>
<script type="text/javascript">
<!--
function setId(id1,id2) {
	var input1 = document.getElementById("id");
	input1.value = id1;	
	var input2 = document.getElementById("purpose");
	input2.value = id2;
}
//-->
</script>
<?php
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<h2>". _("Twitter accounts to publish game results") ."</h2>";
$html .= "<p>". _("Note if you are signed in Twitter with same browser then this account is automatically used for following twitter requests. If you want assign division to different account, please sign out from Twitter after establishing one twitter.") ."</p>";
$html .= "<form method='post' action='?view=admin/twitterconf&amp;Season=$season'>";
$html .= "<table border='0' cellpadding='4px'>\n";
$html .= "<tr>";
$html .= "<th>"._("Source")."</th>";
$html .= "<th>"._("Purpose")."</th>";
$html .= "<th>"._("Screen name")."</th>";
$html .= "<th>"._("Calls remain")."</th>";
$html .= "<th>"._("Account")."</th>";
$html .= "</tr>\n";

//season
$html .= "<tr>";
$html .= "<td>".U_(SeasonName($season))."</td>";
$html .= "<td>"._("All results")."</td>";
$purpose = "season results";
$key = GetTwitterKey($season, $purpose);
if($key){
	$twitter = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $key['keystring'], $key['secrets']);
	$content = $twitter->get('account/verify_credentials');
	$html .= "<td>".$content->screen_name."</td>";
	
	$url = array(
		"url_id"=>0,
		"owner"=>"season",
		"owner_id"=>$season,
		"type"=>"result_twitter",
		"url"=>"http://www.twitter.com/".$content->screen_name,
		"ismedialink"=>0,
		"name"=>"All results",
		"mediaowner"=>"",
		"publisher_id"=>"",
		"ordering"=>""
	);
	
	$savedurl = GetUrl($url['owner'],$url['owner_id'],$url['type']);
	if($savedurl){
		$url['url_id']=$savedurl['url_id'];
		SetUrl($url);
	}else{
		AddUrl($url);
	}	
		
	$content = $twitter->get('account/rate_limit_status');
	$html .= "<td>".$content->remaining_hits."</td>";
	$html .= "<td><input class='button' name='unregister' type='submit' onclick=\"setId('".$key['key_id']."','".$purpose."');\" value='"._("Unregister")."'/></td>";
	$twitter->post('account/end_session');
}else{
	$html .= "<td>-</td>";
	$html .= "<td>-</td>";
	$html .= "<td><input class='button' name='register' type='submit' onclick=\"setId('".$season."','".$purpose."');\" value='"._("Register")."'/></td>";	
}
$html .= "</tr>\n";

$purpose = "series results";
$series = SeasonSeries($season);
foreach($series as $row){
	$html .= "<tr>";
	$html .= "<td>".U_($row['name'])."</td>";
	$html .= "<td>"._("Division results")."</td>";
	$key = GetTwitterKey($row['series_id'], $purpose);
	if($key){
		$twitter = new TwitterOAuth($_SESSION['TwitterConsumerKey'], $_SESSION['TwitterConsumerSecret'], $key['keystring'], $key['secrets']);
		$content = $twitter->get('account/verify_credentials');
		$html .= "<td>".$content->screen_name."</td>";
		$url = array(
		"url_id"=>0,
		"owner"=>"series",
		"owner_id"=>$row['series_id'],
		"type"=>"result_twitter",
		"url"=>"http://www.twitter.com/".$content->screen_name,
		"ismedialink"=>0,
		"name"=>$row['name']." results",
		"mediaowner"=>"",
		"publisher_id"=>"",
		"ordering"=>""
		);
		
		$savedurl = GetUrl($url['owner'],$url['owner_id'],$url['type']);
		if($savedurl){
			$url['url_id']=$savedurl['url_id'];
			SetUrl($url);
		}else{
			AddUrl($url);
		}
			$content = $twitter->get('account/rate_limit_status');
		$html .= "<td>".$content->remaining_hits."</td>";
		$html .= "<td><input class='button' name='unregister' type='submit' onclick=\"setId('".$key['key_id']."','".$purpose."');\" value='"._("Unregister")."'/></td>";
		$twitter->post('account/end_session');
	}else{
		$html .= "<td>-</td>";
		$html .= "<td>-</td>";
		$html .= "<td><input class='button' name='register' type='submit' onclick=\"setId('".$row['series_id']."','".$purpose."');\" value='"._("Register")."'/></td>";	
	}
	$html .= "</tr>\n";
}
$html .= "</table>\n";
$html .=  "<div>";
$html .=  "<input type='hidden' id='id' name='id'/>";
$html .=  "<input type='hidden' id='purpose' name='purpose'/>";
$html .=  "</div>";
$html .= "</form>";
echo $html;
contentEnd();
pageEnd();

?>