<?php
include_once 'menufunctions.php';
include_once 'lib/club.functions.php';
include_once 'lib/country.functions.php';

$html = "";
if (isset($_POST['removeclub_x']) && isset($_POST['hiddenDeleteId'])) {
	$id = $_POST['hiddenDeleteId'];
	RemoveClub($id);
}elseif (isset($_POST['addclub']) && !empty($_POST['name'])){
	AddClub(0,$_POST['name']);
}elseif (isset($_POST['saveclub']) && !empty($_POST['valid'])){
	//invalidate all valid clubs
	$clubs = ClubList(true); 
	while($row = mysqli_fetch_assoc($clubs)){
		SetClubValidity($row['club_id'], false);
	}
	//revalidate
	foreach($_POST["valid"] as $clubId){
		SetClubValidity($clubId, true);
	}
}elseif (isset($_POST['removecountry_x']) && isset($_POST['hiddenDeleteId'])) {
	$id = $_POST['hiddenDeleteId'];
	RemoveCountry($id);
}elseif (isset($_POST['addcountry']) && !empty($_POST['name']) && !empty($_POST['abbreviation']) && !empty($_POST['flag'])){
	AddCountry($_POST['name'], $_POST['abbreviation'], $_POST['flag']);
}elseif (isset($_POST['savecountry']) && !empty($_POST['valid'])){
	//invalidate all valid countries
	$countries = CountryList(true); 
	foreach($countries as $row){
		SetCountryValidity($row['country_id'], false);
	}
	//revalidate
	foreach($_POST["valid"] as $countryId){
		SetCountryValidity($countryId, true);
	}
}

//common page
$title = _("Clubs and Countries");
$LAYOUT_ID = CLUBS;
pageTopHeadOpen($title);
include 'script/common.js.inc';
pageTopHeadClose($title, false);
leftMenu($LAYOUT_ID);
contentStart();

$html .= "<form method='post' action='?view=admin/clubs'>";
$html .= "<h1>"._("All Clubs")."</h1>";
$html .= "<p>"._("Add new").": ";
$html .= "<input class='input' maxlength='50' size='40' name='name'/> ";
$html .= "<input class='button' type='submit' name='addclub' value='"._("Add")."'/></p>";

$html .= "<table border='0'>\n";
$html .= "<tr><th>"._("Id")."</th> <th>"._("Name")."</th><th>"._("Teams")."</th><th>"._("Valid")."</th><th></th></tr>\n";

$i=0;
$clubs = ClubList(); 
while($row = mysqli_fetch_assoc($clubs)){

	$html .= "<tr>";
	$html .= "<td>".$row['club_id']."&#160;</td>";
	$html .=  "<td><a href='?view=user/clubprofile&amp;club=".$row['club_id']."'>".utf8entities($row['name'])."</a></td>";

	$html .= "<td class='center'>".ClubNumOfTeams($row['club_id'])."</td>";
	if(intval($row['valid'])){
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['club_id'])."' checked='checked'/></td>";
	}else{
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['club_id'])."'/></td>";
	}
		
	if(CanDeleteClub($row['club_id'])){
		$html .=  "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='removeclub' value='"._("X")."' onclick=\"setId('".$row['club_id']."');\"/></td>";
	}
	$html .= "</tr>\n";
	$i++;
}

$html .= "</table>";
$html .= "<p><input class='button' type='submit' name='save' value='"._("Save")."'/></p>";

$html .= "<h1>"._("All Countries")."</h1>";
$html .= "<p>"._("Add new")."<br/>";
$html .= _("Name") .": <input class='input' maxlength='50' size='40' name='name'/><br/>";
$html .= _("Abbreviation") .": <input class='input' maxlength='50' size='40' name='abbreviation'/><br/>";
$html .= _("Flag filename") .": <input class='input' maxlength='50' size='40' name='flag'/><br/>";
$html .= "<input class='button' type='submit' name='addcountry' value='"._("Add")."'/></p>";

$html .= "<table border='0'>\n";
$html .= "<tr><th>"._("Id")."</th> <th>"._("Name")."</th><th>"._("Abbreviation")."</th><th>"._("Teams")."</th><th>"._("Valid")."</th><th></th></tr>\n";

$i=0;
$countries = CountryList(false); 
foreach($countries as $row){

	$html .= "<tr>";
	$html .= "<td>".$row['country_id']."&#160;</td>";
	$html .=  "<td>".utf8entities($row['name'])."</td>";
	$html .=  "<td class='center'>".utf8entities($row['abbreviation'])."</td>";

	$html .= "<td class='center'>".CountryNumOfTeams($row['country_id'])."</td>";
	if(intval($row['valid'])){
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['country_id'])."' checked='checked'/></td>";
	}else{
		$html .= "<td class='center'><input class='input' type='checkbox' name='valid[]' value='".utf8entities($row['country_id'])."'/></td>";
	}

	if(CanDeleteCountry($row['country_id'])){
		$html .=  "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='removecountry' value='"._("X")."' onclick=\"setId('".$row['country_id']."');\"/></td>";
	}
	
	$html .= "</tr>\n";
	$i++;
}

$html .= "</table>";
$html .= "<p><input class='button' type='submit' name='savecountry' value='"._("Save")."'/></p>";

$html .= "<p><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>\n";

echo $html;
contentEnd();
pageEnd();
?>