<?php
include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/team.functions.php';
include_once $include_prefix . 'lib/common.functions.php';
include_once $include_prefix . 'lib/season.functions.php';
include_once $include_prefix . 'lib/player.functions.php';
include_once $include_prefix . 'lib/pool.functions.php';
include_once $include_prefix . 'lib/reservation.functions.php';
include_once $include_prefix . 'lib/url.functions.php';

$max_file_size = 5 * 1024 * 1024; //5 MB
$max_new_links = 3;
$html = "";

$teamId = intval(iget("team"));

// Short-circuit if team lookup fails to avoid null offsets.
$team = TeamInfo($teamId);
if (!$team) {
	echo "<p class='warning'>" . _("Team not found.") . "</p>";
	return;
}

if (isset($_SERVER['HTTP_REFERER']))
	$backurl = utf8entities($_SERVER['HTTP_REFERER']);
else
	$backurl = "?view=user/teamplayers&team=$teamId";

$title = _("Team details") . ": " . utf8entities($team['name']);

//team profile
$tp = array(
	"team_id" => $teamId,
	"profile_image" => "",
	"abbreviation" => "",
	"captain" => "",
	"coach" => "",
	"story" => "",
	"achievements" => ""
);

if (isset($_POST['save'])) {
	$backurl = utf8entities($_POST['backurl']);
	$tp['captain'] = $_POST['captain'];
	$tp['abbreviation'] = $_POST['abbreviation'];
	if (strlen($tp['abbreviation']) < 2) {
		$tp['abbreviation'] = $team['abbreviation'];
		$html .= "<p class='warning'>" . _("Abbreviation too short.") . "</p>";
	}
	$allteams = SeriesTeams($team['series']);
	foreach ($allteams as $t) {
		if ($tp['team_id'] != $teamId && $tp['abbreviation'] == $t['abbreviation']) {
			$tp['abbreviation'] = $team['abbreviation'];
			$html .= "<p class='warning'>" . _("Abbreviation already used by") . " " . utf8entities($t['name']) . ".</p>";
			break;
		}
	}
	$tp['coach'] = $_POST['coach'];
	$tp['story'] = $_POST['story'];
	$tp['achievements'] = $_POST['achievements'];
	SetTeamProfile($tp);

	for ($i = 0; $i < $max_new_links; $i++) {

		if (!empty($_POST["url$i"])) {
			$name = "";
			if (!empty($_POST["urlname$i"])) {
				$name = $_POST["urlname$i"];
			}
			AddTeamProfileUrl($teamId, $_POST["urltype$i"], $_POST["url$i"], $name);
		}
	}

	if (is_uploaded_file($_FILES['picture']['tmp_name'])) {
		$html .= UploadTeamImage($teamId);
	}
} elseif (isset($_POST['remove'])) {
	RemoveTeamProfileImage($teamId);
} elseif (isset($_POST['removeurl_x'])) {
	$id = $_POST['hiddenDeleteId'];
	RemoveTeamProfileUrl($teamId, $id);
}
$team = TeamInfo($teamId);
$profile = TeamProfile($teamId);
if ($profile) {
	$tp['captain'] = $profile['captain'];
	$tp['abbreviation'] = $team['abbreviation'];
	$tp['coach'] = $profile['coach'];
	$tp['story'] = $profile['story'];
	$tp['achievements'] = $profile['achievements'];
	$tp['profile_image'] = $profile['profile_image'];
}

$html .= file_get_contents('script/disable_enter.js.inc');

$menutabs[_("Roster")] = "?view=user/teamplayers&team=$teamId";
$menutabs[_("Team Profile")] = "?view=user/teamprofile&team=$teamId";
$menutabs[_("Club Profile")] = "?view=user/clubprofile&team=$teamId";
$html .= pageMenu($menutabs, "", false);

//content
$html .= "<h1>" . utf8entities($team['name']) . "</h1>";

$html .= "<form method='post' enctype='multipart/form-data' action='?view=user/teamprofile&amp;team=$teamId'>\n";

$html .= "<table>";
$html .= "<tr><td class='infocell'>" . _("Abbreviation") . ":</td>";
$html .= "<td><input class='input' maxlength='15' size='10' name='abbreviation' value='" . utf8entities($tp['abbreviation']) . "'/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Coach") . ":</td>";
$html .= "<td><input class='input' maxlength='100' size='50' name='coach' value='" . utf8entities($tp['coach']) . "'/></td></tr>\n";

$html .= "<tr><td class='infocell'>" . _("Captain") . ":</td>";
$html .= "<td><input class='input' maxlength='100' size='50' name='captain' value='" . utf8entities($tp['captain']) . "'/></td></tr>\n";

$html .= "<tr><td class='infocell' style='vertical-align:top'>" . _("Description") . ":</td>";
$html .= "<td><textarea class='input' rows='10' cols='80' name='story'>" . utf8entities($tp['story']) . "</textarea> </td></tr>\n";

$html .= "<tr><td class='infocell' style='vertical-align:top'>" . _("Achievements") . ":</td>";
$html .= "<td><textarea class='input' rows='10' cols='80' name='achievements'>" . utf8entities($tp['achievements']) . "</textarea> </td></tr>\n";

$html .= "<tr><td class='infocell' colspan='2'>" . _("Web pages (homepage, blogs, images, videos)") . ":</td></tr>";
$html .= "<tr><td colspan='2'>";
$html .= "<table border='0'>";

$urls = GetUrlList("team", $teamId);

foreach ($urls as $url) {
	$urlHref = utf8entities($url['url']);
	$urlName = utf8entities($url['name']);
	$html .= "<tr style='border-bottom-style:solid;border-bottom-width:1px;'>";
	$html .= "<td colspan='3'><img width='16' height='16' src='images/linkicons/" . $url['type'] . ".png' alt='" . $url['type'] . "'/> ";
	if (!empty($url['name'])) {
		$html .= "<a href='" . $urlHref . "'>" . $urlName . "</a> (" . $urlHref . ")";
	} else {
		$html .= "<a href='" . $urlHref . "'>" . $urlHref . "</a>";
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
if (!empty($tp['profile_image'])) {
	$html .= "<td><a href='" . UPLOAD_DIR . "teams/$teamId/" . $tp['profile_image'] . "'>";
	$html .= "<img src='" . UPLOAD_DIR . "teams/$teamId/thumbs/" . $tp['profile_image'] . "' alt='" . _("Profile image") . "'/></a></td></tr>";
	$html .= "<tr><td class='infocell'></td>";
	$html .= "<td><input class='button' type='submit' name='remove' value='" . _("Delete image") . "' /></td></tr>\n";
} else {
	$html .= "<td>" . _("No image") . "</td></tr>\n";
}

$html .= "<tr><td class='infocell'>" . _("New image") . ":</td>";
$html .= "<td><input class='input' type='file' size='50' name='picture'/></td></tr>\n";


$html .=  "<tr><td colspan = '2' align='right'><br/>
	  <input class='button' type='submit' name='save' value='" . _("Save") . "' />
	  <input class='button' type='button' name='takaisin'  value='" . _("Return") . "' onclick=\"window.location.href='$backurl'\"/>
	  <input type='hidden' name='backurl' value='$backurl'/>
	  <input type='hidden' name='MAX_FILE_SIZE' value='$max_file_size'/>
	  </td></tr>\n";
$html .= "</table>\n";
$html .= "<div><input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></div>";
$html .= "</form>";
$html .= "<p><a href='?view=teamcard&amp;team=" . $teamId . "'>" . _("Check Team card") . "</a></p>";

showPage($title, $html);
