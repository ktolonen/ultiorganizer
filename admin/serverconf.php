<?php
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/facebook.functions.php';
include_once $include_prefix . 'lib/url.functions.php';

$LAYOUT_ID = SERVERCONFIGURATION;
$title = _("Server configuration");
$html = "";

if (!empty($_POST['save'])) {

	$settings = array();

	$setting = array();
	$setting['name'] = "GoogleMapsAPIKey";
	$setting['value'] = $_POST['GoogleMapsAPIKey'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "PageTitle";
	$setting['value'] = $_POST['PageTitle'];
	$settings[] = $setting;


	$setting = array();
	$setting['name'] = "TwitterEnabled";
	if (!empty($_POST['TwitterEnabled'])) {
		$setting['value'] = "true";
	} else {
		$setting['value'] = "false";
	}
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "TwitterConsumerKey";
	$setting['value'] = $_POST['TwitterConsumerKey'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "TwitterConsumerSecret";
	$setting['value'] = $_POST['TwitterConsumerSecret'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "TwitterOAuthCallback";
	$setting['value'] = $_POST['TwitterOAuthCallback'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "FacebookEnabled";
	if (!empty($_POST['FacebookEnabled'])) {
		$setting['value'] = "true";
	} else {
		$setting['value'] = "false";
	}
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "FacebookAppKey";
	$setting['value'] = $_POST['FacebookAppKey'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "FacebookAppId";
	$setting['value'] = $_POST['FacebookAppId'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "FacebookAppSecret";
	$setting['value'] = $_POST['FacebookAppSecret'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "FacebookGameMessage";
	$setting['value'] = $_POST['FacebookGameMessage'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "FacebookUpdatePage";
	$setting['value'] = $_POST['FacebookUpdatePage'];
	$settings[] = $setting;

	if (isset($_POST['FacebookUpdateId']) && (strlen($_POST['FacebookUpdateId']) > 0)) {
		$setting = array();
		$setting['name'] = "FacebookUpdateId";
		$setting['value'] = $_POST['FacebookUpdateId'];
		$settings[] = $setting;
		$setting = array();
		$setting['name'] = "FacebookUpdateToken";
		$setting['value'] = GetFacebookAppToken($_POST['FacebookUpdatePage']);
		$settings[] = $setting;
	}

	if (isset($_POST['FacebookUnauthorize']) && $_POST['FacebookUnauthorize'] == "yes") {
		FBUnauthorizeApp();
	}

	$setting = array();
	$setting['name'] = "ShowDefenseStats";
	if (!empty($_POST['ShowDefenseStats'])) {
		$setting['value'] = "true";
	} else {
		$setting['value'] = "false";
	}
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "HomeTeamResponsible";
	if (!empty($_POST['HomeTeamResponsible'])) {
		$setting['value'] = "yes";
	} else {
		$setting['value'] = "no";
	}
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "EmailSource";
	$setting['value'] = $_POST['EmailSource'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "DefaultTimezone";
	$setting['value'] = $_POST['DefaultTimezone'];
	$settings[] = $setting;

	$setting = array();
	$setting['name'] = "DefaultLocale";
	$setting['value'] = $_POST['DefaultLocale'];
	$settings[] = $setting;

	SetServerConf($settings);

	for ($i = 0; !empty($_POST["urlid$i"]); $i++) {
		$url = array(
			"url_id" => $_POST["urlid$i"],
			"owner" => "ultiorganizer",
			"owner_id" => 0,
			"type" => $_POST["urltype$i"],
			"ordering" => $_POST["urlorder$i"],
			"url" => $_POST["url$i"],
			"ismedialink" => 0,
			"name" => $_POST["urlname$i"],
			"mediaowner" => "",
			"publisher_id" => ""
		);

		if (strpos($url['url'], "@")) {
			SetMail($url);
		} else {
			SetUrl($url);
		}
	}
	if (!empty($_POST["newurl"])) {
		$url = array(
			"owner" => "ultiorganizer",
			"owner_id" => 0,
			"type" => $_POST["newurltype"],
			"ordering" => $_POST["newurlorder"],
			"url" => $_POST["newurl"],
			"ismedialink" => 0,
			"name" => $_POST["newurlname"],
			"mediaowner" => "",
			"publisher_id" => ""
		);
		if ($_POST["newurltype"] == "menumail") {
			AddMail($url);
		} else {
			AddUrl($url);
		}
	}
	$serverConf = GetSimpleServerConf();
} elseif (!empty($_POST['remove_x'])) {
	$id = $_POST['hiddenDeleteId'];
	RemoveUrl($id);
}

$settings = GetServerConf();

//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();
$html .= "<p><a href='admin/test.php'>" . _("Show phpinfo()") . "</a></p>\n";

$htmltmp1 = "";
$htmltmp2 = "";

foreach ($settings as $setting) {

	//Page  title
	if ($setting['name'] == "PageTitle") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Page title") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='PageTitle' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}

	//google maps key
	if ($setting['name'] == "GoogleMapsAPIKey") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Google Maps key") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='GoogleMapsAPIKey' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}

	//twitter
	if ($setting['name'] == "TwitterEnabled") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Twitter enabled") . ":</td>";
		if ($setting['value'] == "true") {
			$htmltmp1 .= "<td><input class='input' type='checkbox' name='TwitterEnabled' checked='checked'/></td>";
		} else {
			$htmltmp1 .= "<td><input class='input' type='checkbox' name='TwitterEnabled'/></td>";
		}
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "TwitterConsumerKey") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Twitter Consumer Key") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='TwitterConsumerKey' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "TwitterConsumerSecret") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Twitter Consumer Secret") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='TwitterConsumerSecret' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "TwitterOAuthCallback") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Twitter OAuth Callback") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='TwitterOAuthCallback' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	//Facebook
	if ($setting['name'] == "FacebookEnabled") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Facebook enabled") . ":</td>";
		if ($setting['value'] == "true") {
			$htmltmp1 .= "<td><input class='input' type='checkbox' name='FacebookEnabled' checked='checked'/></td>";
		} else {
			$htmltmp1 .= "<td><input class='input' type='checkbox' name='FacebookEnabled'/></td>";
		}
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "FacebookAppId") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Facebook Application Id") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='FacebookAppId' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "FacebookAppKey") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Facebook Application Key") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='FacebookAppKey' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "FacebookAppSecret") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Facebook Application Secret") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='FacebookAppSecret' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "FacebookGameMessage") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Facebook Game Message") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='FacebookGameMessage' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}
	if ($setting['name'] == "FacebookUpdatePage") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("Facebook Update Page") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='FacebookUpdatePage' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}

	if ($setting['name'] == "EmailSource") {
		$htmltmp1 .= "<tr>";
		$htmltmp1 .= "<td class='infocell'>" . _("System email sender address") . ":</td>";
		$htmltmp1 .= "<td><input class='input' size='70' name='EmailSource' value='" . utf8entities($setting['value']) . "'/></td>";
		$htmltmp1 .= "</tr>\n";
	}

	if ($setting['name'] == "ShowDefenseStats") {
		$htmltmp2 .= "<tr>";
		$htmltmp2 .= "<td class='infocell'>" . _("Show Defense statistics") . ":</td>";
		if ($setting['value'] == "true") {
			$htmltmp2 .= "<td><input class='input' type='checkbox' name='ShowDefenseStats' checked='checked'/></td>";
		} else {
			$htmltmp2 .= "<td><input class='input' type='checkbox' name='ShowDefenseStats'/></td>";
		}
		$htmltmp2 .= "</tr>\n";
	}

	if ($setting['name'] == "HomeTeamResponsible") {
		$htmltmp2 .= "<tr>";
		$htmltmp2 .= "<td class='infocell'>" . _("Home team is game responsible") . ":</td>";
		if ($setting['value'] == "yes") {
			$htmltmp2 .= "<td><input class='input' type='checkbox' name='HomeTeamResponsible' checked='checked'/></td>";
		} else {
			$htmltmp2 .= "<td><input class='input' type='checkbox' name='HomeTeamResponsible'/></td>";
		}
		$htmltmp2 .= "</tr>\n";
	}

	if ($setting['name'] == "DefaultTimezone") {
		$htmltmp2 .= "<tr>";
		$htmltmp2 .= "<td class='infocell'>" . _("Default Timezone") . ": </td><td>";
		$dateTimeZone = GetTimeZoneArray();
		$htmltmp2 .= "<select class='dropdown' id='DefaultTimezone' name='DefaultTimezone'>\n";
		foreach ($dateTimeZone as $tz) {
			if ($setting['value'] == $tz) {
				$htmltmp2 .= "<option selected='selected' value='$tz'>" . utf8entities($tz) . "</option>\n";
			} else {
				$htmltmp2 .= "<option value='$tz'>" . utf8entities($tz) . "</option>\n";
			}
		}
		$htmltmp2 .= "</select>\n";
		$htmltmp2 .= "</td></tr>\n";
	}

	if ($setting['name'] == "DefaultLocale") {
		$htmltmp2 .= "<tr>";
		$htmltmp2 .= "<td class='infocell'>" . _("Default Locale") . ": </td><td>";
		$alllocales = getAvailableLocalizations();
		$htmltmp2 .= "<select class='dropdown' id='DefaultLocale' name='DefaultLocale'>\n";
		foreach ($alllocales as $loc) {
			if ($setting['value'] == $loc) {
				$htmltmp2 .= "<option selected='selected' value='$loc'>" . utf8entities($loc) . "</option>\n";
			} else {
				$htmltmp2 .= "<option value='$loc'>" . utf8entities($loc) . "</option>\n";
			}
		}
		$htmltmp2 .= "</select>\n";
		$htmltmp2 .= "</td></tr>\n";
	}
}

$html .= "<form method='post' action='?view=admin/serverconf' id='Form'>";

$html .= "<h1>" . _("UI settings") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= "<tr><th>" . _("Type") . "</th><th>" . _("Order") . "</th><th>" . _("Name") . "</th><th>" . _("Url") . "</th><th></th></tr>\n";
$urls = GetUrlListByTypeArray(array("menulink", "menumail", "admin"), 0);
$i = 0;
foreach ($urls as $url) {
	$html .= "<tr>";
	$html .= "<td>" . $url['type'] . "<input type='hidden' name='urltype" . $i . "' value='" . utf8entities($url['type']) . "'/></td>";
	$html .= "<td><input class='input' size='3' maxlength='2' name='urlorder" . $i . "' value='" . utf8entities($url['ordering']) . "'/></td>";
	$html .= "<td><input class='input' size='30' maxlength='150' name='urlname" . $i . "' value='" . utf8entities($url['name']) . "'/></td>";
	$html .= "<td><input class='input' size='40' maxlength='500' name='url" . $i . "' value='" . utf8entities($url['url']) . "'/></td>";
	$html .= "<td class='center'><input class='deletebutton' type='image' src='images/remove.png' alt='X' name='remove' value='" . _("X") . "' onclick=\"setId(" . $url['url_id'] . ");\"/></td>";
	$html .= "<td><input type='hidden' name='urlid" . $i . "' value='" . utf8entities($url['url_id']) . "'/></td>";
	$html .= "</tr>\n";
	$i++;
}
$html .= "<tr><td><select class='dropdown' name='newurltype'>\n";
$html .= "<option value='menulink'>" . _("Menu link") . "</option>\n";
$html .= "<option value='menumail'>" . _("Menu mail") . "</option>\n";
$html .= "<option value='admin'>" . _("Administrator") . "</option>\n";
$html .= "</select></td>";
$html .= "<td><input class='input' size='3' maxlength='2' name='newurlorder' value=''/></td>";
$html .= "<td><input class='input' size='30' maxlength='150' name='newurlname' value=''/></td>";
$html .= "<td><input class='input' size='40' maxlength='500' name='newurl' value=''/></td>";
$html .= "</tr>\n";
$html .= "</table>\n";


$html .= "<h1>" . _("3rd party API settings") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= $htmltmp1;
$html .= "</table>\n";
if (IsFacebookEnabled()) {
	$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
	if (!isset($serverConf['FacebookUpdateToken']) || (strlen($serverConf['FacebookUpdateToken']) == 0)) {
		$html .= "<tr><td><a href='javascript:authorize()'>" . _("Authorize facebook updates") . "</a>\n";
		$html .= "<input type='hidden' id='FacebookUpdateId' name='FacebookUpdateId' value=''/></td></tr>\n";
	} else {
		$html .= "<tr><td><a href='javascript:unauthorize()'>" . _("Unauthorize facebook updates") . "</a>\n";
		$html .= "<input type='hidden' id='FacebookUnauthorize' name='FacebookUnauthorize' value='no'/></td></tr>\n";
	}
	$html .= "</table>\n";
}

$html .= "<hr/>";
$html .= "<h1>" . _("Internal settings") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= $htmltmp2;
$html .= "</table>\n";
$html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
//$html .= "<input type='hidden' name='save' value='hiddensave'/>\n";
$html .= "<input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>";
echo $html;
contentEnd();
if (IsFacebookEnabled()) {
	echo "<script src='http://connect.facebook.net/en_US/all.js'></script>
<script>

FB.init({appId: '";
	echo $serverConf['FacebookAppId'];
	echo "', status: true, 
      	cookie: true, xfbml: true});
function authorize() {
	FB.login(function(response) {
		if (response.session) {
			if (response.perms && response.perms.indexOf('manage_pages') > -1) {
				window.document.getElementById('FacebookUpdateId').value = response.session.uid;
				window.document.getElementById('Form').submit();
			}
		}
	}, {perms:'offline_access,manage_pages'});
}

function unauthorize() {
	window.document.getElementById('FacebookUnauthorize').value = 'yes';
	window.document.getElementById('Form').submit();
}

</script>";
}

pageEnd();
