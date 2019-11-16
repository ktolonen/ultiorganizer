<?php
include_once 'menufunctions.php';
include_once 'lib/sms.functions.php';
include_once 'lib/series.functions.php';
include_once 'lib/player.functions.php';
include_once 'lib/pool.functions.php';

$LAYOUT_ID = EVENTVIEWER;
$title = _("SMS handling");
//$html = "";
//
//$userfilter="";
//$categoryfilter= array();//EventCategories();
//$resolve = false;
//
//if(isset($_POST['update'])){
//	$userfilter=$_POST["userid"];
//	if(!empty($_POST["category"])){
//		$categoryfilter=$_POST["category"];
//	}
//	if(!empty($_POST["resolve"])){
//		$resolve=true;
//	}
//}elseif(isset($_POST['delete']) && !empty($_POST["event_ids"])){
//	$ids=$_POST["event_ids"];
//	ClearEventList($ids);
//}

//common page
pageTopHeadOpen($title);
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

if(!empty($_POST["sms_1"])) {
	debugvar($_POST);
	$smscounter=0;
	foreach($_POST as $key=>$sms) {
		if (substr($key,0,4)=="sms_") {
			$smscounter++;
			$smstext=explode(";",$sms);
			$smsarray[$smscounter]['msg']=$smstext[0];
			$smsarray[$smscounter]['to1']=$smstext[1];
			$smsarray[$smscounter]['to2']=$smstext[2];			
			$smsarray[$smscounter]['to3']=$smstext[3];			
			$smsarray[$smscounter]['to4']=$smstext[4];			
			$smsarray[$smscounter]['to5']=$smstext[5];			
		}
		
		// send SMS and store in DB
		SendSMS($smsarray[$smscounter]);
		
	}
	
}


	$sms = GetAllSMS();

	if(mysqli_num_rows($sms)){
		echo "<table border='0' width='500'><tr>
			<th>"._("Msg")."</th>
			<th>"._("To1")."</th>
			<th>"._("To2")."</th>
			<th>"._("To3")."</th>
			<th>"._("Created")."</th>
			<th>"._("Sent")."</th>
			<th>"._("Delivered")."</th>
			<th>"._("click_id")."</th></tr>";
	}
		
	$smscount=0;
	while($row = mysqli_fetch_assoc($sms))	{
		
		echo "<tr>";
		echo "<td>".utf8entities($row['msg'])."</td>";
		echo "<td>".utf8entities($row['to1'])."</td>";
		echo "<td>".utf8entities($row['to2'])."</td>";
		echo "<td>".utf8entities($row['to3'])."</td>";
		echo "<td>".utf8entities($row['created'])."</td>";
		echo "<td>".utf8entities($row['sent'])."</td>";
		echo "<td>".utf8entities($row['delivered'])."</td>";
		echo "</tr>";
		
	}

	echo "</table>";

contentEnd();
pageEnd();
?>