<?php
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/player.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
$LAYOUT_ID = PLAYERPROFILE;
$max_file_size = 5 * 1024 * 1024; //5 MB
$max_new_links = 3;
$html = "";
$playerId = 0;

if (isset($_GET["player"])) {
	$playerId = intval($_GET["player"]);
} elseif (isset($_GET["profile"])) {
	$playerId = PlayerLatestId(intval($_GET["profile"]));
}

$player = PlayerInfo($playerId);

if (empty($player['profile_id'])) {
	CreatePlayerProfile($playerId);
	$player = PlayerInfo($playerId);
}

//$accId = 0;
if (!hasEditPlayerProfileRight($playerId)) {
	die('Insufficient rights to edit player profile');
}


if (isset($_SERVER['HTTP_REFERER']))
	$backurl = utf8entities($_SERVER['HTTP_REFERER']);
else
	$backurl = "?view=user/teamplayers&team=" . $player['team'];

$title = _("Player information") . ": " . utf8entities($player['firstname'] . " " . $player['lastname']);

//player profile
$pp = array(
	"profile_id" => $player['profile_id'],
	"accreditation_id" => "",
	"num" => $player['num'],
	"firstname" => $player['firstname'],
	"lastname" => $player['lastname'],
	"nickname" => "",
	"gender" => "",
	"email" => "",
	"national_id" => "",
	"info" => "",
	"birthdate" => "",
	"birthplace" => "",
	"nationality" => "",
	"throwing_hand" => "",
	"height" => "",
	"weight" => "",
	"position" => "",
	"story" => "",
	"achievements" => "",
	"profile_image" => "",
	"public" => ""
);

if (isset($_POST['save'])) {
	$backurl = utf8entities($_POST['backurl']);

	if (isset($_POST['accreditationId'])) {
		$pp['accreditation_id'] = $_POST['accreditationId'];
	} else {
		$pp['accreditation_id'] = "";
	}
	$pp['nickname'] = $_POST['nickname'];
	$pp['firstname'] = $_POST['firstname'];
	$pp['lastname'] = $_POST['lastname'];
	$pp['num'] = $_POST['num'];
	$pp['email'] = $_POST['email'];
	$pp['gender'] = $_POST['gender'];
	$pp['info'] = $_POST['info'];
	$pp['national_id'] = $_POST['national_id'];

	$pp['birthdate'] = ToInternalTimeFormat($_POST['birthdate']);
	$pp['birthplace'] = $_POST['birthplace'];
	$pp['nationality'] = $_POST['nationality'];
	$pp['throwing_hand'] = $_POST['throwing_hand'];
	$pp['height'] = $_POST['height'];
	$pp['weight'] = $_POST['weight'];
	$pp['position'] = $_POST['position'];
	$pp['story'] = $_POST['story'];
	$pp['achievements'] = $_POST['achievements'];
	$pp['public'] = "";
	if (!empty($_POST["public"])) {
		foreach ($_POST["public"] as $column) {
			if (strlen($pp['public']) > 0) {
				$pp['public'] .= "|";
			}
			$pp['public'] .= $column;
		}
	}

	SetPlayerProfile($player['team'], $playerId, $pp);

	for ($i = 0; $i < $max_new_links; $i++) {

		if (!empty($_POST["url$i"])) {
			$name = "";
			if (!empty($_POST["urlname$i"])) {
				$name = $_POST["urlname$i"];
			}
			AddPlayerProfileUrl($playerId, $_POST["urltype$i"], $_POST["url$i"], $name);
		}
	}

	if (is_uploaded_file($_FILES['picture']['tmp_name'])) {
		$html .= UploadPlayerImage($playerId);
	}
} elseif (isset($_POST['remove'])) {
	RemovePlayerProfileImage($playerId);
} elseif (isset($_POST['removeurl_x'])) {
	$id = $_POST['hiddenDeleteId'];
	RemovePlayerProfileUrl($playerId, $id);
}

$player = PlayerInfo($playerId);
$profile = PlayerProfile($player['profile_id']);

if ($profile) {
	$pp['profile_id'] = $player['profile_id'];
	$pp['accreditation_id'] = $profile['accreditation_id'];
	$pp['firstname'] = !empty($profile['firstname']) ? $profile['firstname'] : $player['firstname'];
	$pp['lastname'] = !empty($profile['lastname']) ? $profile['lastname'] : $player['lastname'];
	$pp['nickname'] = $profile['nickname'];
	$pp['num'] = $player['num'];
	$pp['email'] = $player['email'];
	$pp['gender'] = $profile['gender'];
	$pp['info'] = $profile['info'];
	$pp['national_id'] = $profile['national_id'];
	$pp['birthdate'] = $profile['birthdate'];
	if (isEmptyDate($pp['birthdate']))
		$pp['birthdate'] = "";
	$pp['birthplace'] = $profile['birthplace'];
	$pp['nationality'] = $profile['nationality'];
	$pp['throwing_hand'] = $profile['throwing_hand'];
	$pp['height'] = intval($profile['height']);
	$pp['weight'] = intval($profile['weight']);
	$pp['position'] = $profile['position'];
	$pp['story'] = $profile['story'];
	$pp['achievements'] = $profile['achievements'];
	$pp['profile_image'] = $profile['profile_image'];
	$pp['public'] = $profile['public'];
}

$publicfields = explode("|", $profile['public']);

//common page
pageTopHeadOpen($title);
include_once 'script/disable_enter.js.inc';
include_once 'script/common.js.inc';
include_once 'lib/yui.functions.php';
pageTopHeadClose($title);
leftMenu($LAYOUT_ID);
contentStart();

//content
$html .= "<form method='post' enctype='multipart/form-data' action='?view=user/playerprofile&amp;player=$playerId'>\n";

$html .= "<table>";
$html .= "<tr><td colspan='2'>" . _("Player details") . "</td><td class='center'>" . _("Show in public profile") . "</td></tr>";

if (CUSTOMIZATIONS == "slkl") {
	$query = sprintf("SELECT membership, license, external_type, external_validity FROM uo_license WHERE accreditation_id=%d", (int)$pp['accreditation_id']);
	$row = DBQueryToRow($query);
	$html .= "<tr><td class='infocell'>" . _("License Id") . ":</td>";

	if (isSuperAdmin()) {
		$html .= "<td><input class='input' maxlength='10' size='10' name='accreditationId' value='" . utf8entities($pp['accreditation_id']) . "'/></td>";
	} else {
		$html .= "<td>" . $pp['accreditation_id'] . "";
		$html .= "<input class='input' hidden='hidden' maxlength='10' size='10' name='accreditationId' value='" . utf8entities($pp['accreditation_id']) . "'/></td>";
	}
	$html .= "<td class='center'><input type='checkbox' name='public[]' disabled='disabled' value=''/></td></tr>\n";

	$html .= "<tr><td class='infocell'>" . _("License") . ":</td>";
	if (!empty($row['external_validity'])) {
		$html .= "<td>" . U_($row['external_validity']) . "</td>";
	} elseif (!empty($row['membership'])) {
		$html .= "<td>" . $row['license'] . "</td>";
	} else {
		$html .= "<td>-</td>";
	}
	$html .= "<td class='center'><input type='checkbox' name='public[]' disabled='disabled' value=''/></td></tr>\n";

	$html .= "<tr><td class='infocell'>" . _("Membership") . ":</td>";
	if (!empty($row['external_type'])) {
		$html .= "<td>" . U_($row['external_type']) . "</td>";
	} elseif (!empty($row['membership'])) {
		$html .= "<td>" . $row['membership'] . "</td>";
	} else {
		$html .= "<td>-</td>";
	}
	$html .= "<td class='center'><input type='checkbox' name='public[]' disabled='disabled' value=''/></td></tr>\n";
}

$html .= "<tr><td class='infocell'>" . _("Jersey number") . ":</td>";
$html .= "<td><input class='input' maxlength='3' size='3' name='num' value='" . utf8entities($pp['num']) . "'/></td>";
$html .= "<td class='center'><input type='checkbox' name='public[]' checked='checked' disabled='disabled' value=''/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("First name") . ":</td>";
$html .= "<td><input class='input' maxlength='40' size='40' name='firstname' value='" . utf8entities($pp['firstname']) . "'/></td>";
$html .= "<td class='center'><input type='checkbox' name='public[]' checked='checked' disabled='disabled' value=''/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Last name") . ":</td>";
$html .= "<td><input class='input' maxlength='40' size='40' name='lastname' value='" . utf8entities($pp['lastname']) . "'/></td>";
$html .= "<td class='center'><input type='checkbox' name='public[]' checked='checked' disabled='disabled' value=''/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Nickname") . ":</td>";
$html .= "<td><input class='input' maxlength='20' name='nickname' value='" . utf8entities($pp['nickname']) . "'/></td>";
$html .= privacyselection("nickname", $publicfields);

$html .= "<tr><td class='infocell'>" . _("E-mail") . ":</td>";
$html .= "<td><input class='input' size='50' maxlength='100' name='email' value='" . utf8entities($pp['email']) . "'/></td>";
$html .= "<td class='center'><input type='checkbox' name='public[]' disabled='disabled' value='email'/></td></tr>\n";

$html .= "<tr><td class='infocell' style='vertical-align:top'>" . _("Additional information") . ":</td>";
$html .= "<td><textarea class='input' rows='2' cols='70' name='info'>" . utf8entities($pp['info']) . "</textarea></td>";
$html .= "<td class='center'><input type='checkbox' name='public[]' disabled='disabled' value='info'/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("National membership number") . ":</td>";
$html .= "<td><input class='input' size='12' maxlength='10' name='national_id' id='national_id' value='" . utf8entities($pp['national_id']) . "'/></td>";
$html .= "<td class='center'><input type='checkbox' name='public[]' disabled='disabled' value=''/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Gender") . ":</td>";
$html .= "<td><select class='dropdown' name='gender'>";


$html .= "<option class='dropdown' value=''></option>";
if ($pp['gender'] != "F") {
	$html .= "<option class='dropdown' selected='selected' value='F'>" . _("Female") . "</option>";
} else {
	$html .= "<option class='dropdown' value='F'>" . _("Female") . "</option>";
}
if ($pp['gender'] == "M") {
	$html .= "<option class='dropdown' selected='selected' value='M'>" . _("Male") . "</option>";
} else {
	$html .= "<option class='dropdown' value='M'>" . _("Male") . "</option>";
}
if ($pp['gender'] == "O") {
	$html .= "<option class='dropdown' selected='selected' value='O'>" . _("Other") . "</option>";
} else {
	$html .= "<option class='dropdown' value='o'>" . _("Other") . "</option>";
}


$html .= "</select></td>";
$html .= privacyselection("gender", $publicfields);

$html .= "<tr><td class='infocell'>" . _("Date of birth") . " (" . _("dd.mm.yyyy") . "):</td>";
$html .= "<td><input class='input' size='12' maxlength='10' name='birthdate' id='birthdate' value='" . ShortDate($pp['birthdate']) . "'/></td>";
$html .= privacyselection("birthdate", $publicfields);

$html .= "<tr><td class='infocell'>" . _("Place of birth") . ":</td>";
$html .= "<td><input class='input' maxlength='20' name='birthplace' value='" . utf8entities($pp['birthplace']) . "'/></td>";
$html .= privacyselection("birthplace", $publicfields);

$html .= "<tr><td class='infocell'>" . _("Nationality") . ":</td>";
$html .= "<td><input class='input' maxlength='20' name='nationality' value='" . utf8entities($pp['nationality']) . "'/></td>";
$html .= privacyselection("nationality", $publicfields);

$html .= "<tr><td class='infocell'>" . _("Hand") . ":</td>";
$html .= "<td><select class='dropdown' name='throwing_hand'>\n";
$types = array("", "right", "left", "both");

/* for gettext */
_("left");
_("right");
_("both");

foreach ($types as $type) {
	if ($pp['throwing_hand'] == $type)
		$html .= "<option class='dropdown' selected='selected' value='$type'>" . U_($type) . "</option>\n";
	else
		$html .= "<option class='dropdown' value='$type'>" . U_($type) . "</option>\n";
}

$html .= "</select></td>";
$html .= privacyselection("throwing_hand", $publicfields);

$html .= "<tr><td class='infocell'>" . _("Height") . ":</td>";
$html .= "<td><input class='input' size='3' maxlength='10' name='height' value='" . utf8entities($pp['height']) . "'/> " . _("cm") . "</td>";
$html .= privacyselection("height", $publicfields);

$html .= "<tr><td class='infocell'>" . _("Weight") . ":</td>";
$html .= "<td><input class='input' size='3' maxlength='10' name='weight' value='" . utf8entities($pp['weight']) . "'/> " . _("kg") . "</td>";
$html .= privacyselection("weight", $publicfields);

$html .= "<tr><td class='infocell'>" . _("Field position/role") . ":</td>";
$html .= "<td><input class='input' size='50' maxlength='50' name='position' value='" . utf8entities($pp['position']) . "'/></td>";
$html .= privacyselection("position", $publicfields);

$html .= "<tr><td class='infocell' style='vertical-align:top'>" . _("Description") . ":</td>";
$html .= "<td><textarea class='input' rows='10' cols='70' name='story'>" . utf8entities($pp['story']) . "</textarea> </td>";
$html .= privacyselection("story", $publicfields);

$html .= "<tr><td class='infocell' style='vertical-align:top'>" . _("Achievements") . ":</td>";
$html .= "<td><textarea class='input' rows='10' cols='70' name='achievements'>" . utf8entities($pp['achievements']) . "</textarea> </td>";
$html .= privacyselection("achievements", $publicfields);

$html .= "<tr><td class='infocell' colspan='2'>" . _("Web pages (homepage, blogs, images, videos)") . ":</td>";
$html .= "<td class='center'><input type='checkbox' name='public[]' checked='checked' disabled='disabled' value=''/></td></tr>";
$html .= "<tr><td colspan='3'>";
$html .= "<table border='0'>";

$urls = GetUrlList("player", $player['profile_id']);

foreach ($urls as $url) {
	$html .= "<tr style='border-bottom-style:solid;border-bottom-width:1px;'>";
	$html .= "<td colspan='3'><img width='16' height='16' src='images/linkicons/" . $url['type'] . ".png' alt='" . $url['type'] . "'/> ";
	if (!empty($url['name'])) {
		$html .= "<a href='" . $url['url'] . "'>" . $url['name'] . "</a> (" . $url['url'] . ")";
	} else {
		$html .= "<a href='" . $url['url'] . "'>" . $url['url'] . "</a>";
	}

	$html .= "</td>";
	$html .= "<td class='right'><input class='deletebutton' type='image' src='images/remove.png' name='removeurl' value='X' alt='X' onclick='setId(" . $url['url_id'] . ");'/></td>";
	$html .= "</tr>";
}

//empty line
if (count($urls)) {
	$html .= "<tr>";
	$html .= "<td colspan='3'>&nbsp;</td>";
	$html .= "</tr>";
}

$html .= "<tr>";
$html .= "<td>" . _("Type") . "</td>";
$html .= "<td>" . _("URL") . "</td>";
$html .= "<td>" . _("Name") . " (" . _("optional") . ")</td>";
$html .= "</tr>";

$urltypes = GetUrlTypes();
for ($i = 0; $i < $max_new_links; $i++) {
	$html .= "<tr>";
	$html .= "<td><select class='dropdown' name='urltype$i'>\n";
	foreach ($urltypes as $type) {
		$html .= "<option value='" . utf8entities($type['type']) . "'>" . utf8entities($type['name']) . "</option>\n";
	}
	$html .= "</select></td>";
	$html .= "<td><input class='input' maxlength='500' size='40' name='url$i' value=''/></td>";
	$html .= "<td><input class='input' maxlength='500' size='40' name='urlname$i' value=''/></td>";
	$html .= "</tr>";
}

$html .= "</table>";
$html .= "</td></tr>\n";


$html .= "<tr><td class='infocell' style='vertical-align:top'>" . _("Current image") . ":</td>";
if (!empty($pp['profile_image'])) {
	$html .= "<td><a href='" . UPLOAD_DIR . "players/" . $player['profile_id'] . "/" . utf8entities($pp['profile_image']) . "'>";
	$html .= "<img src='" . UPLOAD_DIR . "players/" . $player['profile_id'] . "/thumbs/" . utf8entities($pp['profile_image']) . "' alt='" . _("Profile image") . "'/></a></td>";
	$html .= privacyselection("profile_image", $publicfields);

	$html .= "<tr><td></td><td class='infocell'></td>";
	$html .= "<td><input class='button' type='submit' name='remove' value='" . _("Delete image") . "' /></td></tr>\n";
} else {
	$html .= "<td>" . _("No image") . "</td>\n";
	$html .= "<td class='center'><input type='checkbox' hidden='hidden' name='public[]' checked='checked' value='profile_image'/></td></tr>\n";
}
$html .= "<tr><td class='infocell'>" . _("New image") . ":</td>";
$html .= "<td colspan='2'><input class='input' type='file' size='50' name='picture'/></td></tr>\n";
$html .=  "<tr><td colspan = '2' align='right'><br/>
	  <input class='button' type='submit' name='save' value='" . _("Save") . "' />
	  <input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='$backurl'\"/>
	  <input type='hidden' name='backurl' value='$backurl'/>
	  <input type='hidden' name='MAX_FILE_SIZE' value='$max_file_size'/>
	  </td></tr>\n";
$html .= "</table>\n";
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>";
$html .= "<p><a href='?view=playercard&amp;series=0&amp;player=" . $playerId . "'>" . _("Check public player card") . "</a></p>";
echo $html;

//common end
contentEnd();
pageEnd();

function privacyselection($fieldname, $flags)
{
	if (in_array($fieldname, $flags)) {
		return "<td class='center'><input type='checkbox' name='public[]' checked='checked' value='$fieldname'/></td></tr>\n";
	} else {
		return "<td class='center'><input type='checkbox' name='public[]' value='$fieldname'/></td></tr>\n";
	}
}
