<?php

include_once __DIR__ . '/auth.php';
include_once $include_prefix . 'lib/configuration.functions.php';
include_once $include_prefix . 'lib/url.functions.php';
include_once $include_prefix . 'lib/version.functions.php';

$LAYOUT_ID = SERVERCONFIGURATION;
$title = _("Server configuration");
$html = "";

if (!isSuperAdmin()) {
    Forbidden(isset($_SESSION['uid']) ? $_SESSION['uid'] : 'anonymous');
}

$cacheCleared = null;
if (!empty($_POST['wipecache'])) {
    $cacheCleared = CacheWipePersistent();
}

if (!empty($_POST['save'])) {

    $settings = [];

    $setting = [];
    $setting['name'] = "GoogleMapsAPIKey";
    $setting['value'] = $_POST['GoogleMapsAPIKey'];
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "PageTitle";
    $setting['value'] = $_POST['PageTitle'];
    $settings[] = $setting;


    $setting = [];
    $setting['name'] = "ShowDefenseStats";
    if (!empty($_POST['ShowDefenseStats'])) {
        $setting['value'] = "true";
    } else {
        $setting['value'] = "false";
    }
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "ReadOnlyServer";
    if (!empty($_POST['ReadOnlyServer'])) {
        $setting['value'] = "true";
    } else {
        $setting['value'] = "false";
    }
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "SoftMaintenanceMode";
    if (!empty($_POST['SoftMaintenanceMode'])) {
        $setting['value'] = "true";
    } else {
        $setting['value'] = "false";
    }
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "DisableVisitorLogging";
    if (!empty($_POST['DisableVisitorLogging'])) {
        $setting['value'] = "true";
    } else {
        $setting['value'] = "false";
    }
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "HomeTeamResponsible";
    if (!empty($_POST['HomeTeamResponsible'])) {
        $setting['value'] = "yes";
    } else {
        $setting['value'] = "no";
    }
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "EmailSource";
    $setting['value'] = $_POST['EmailSource'];
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "DefaultTimezone";
    $setting['value'] = $_POST['DefaultTimezone'];
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "DefaultLocale";
    $setting['value'] = $_POST['DefaultLocale'];
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "PersistentCacheEnabled";
    $setting['value'] = !empty($_POST['PersistentCacheEnabled']) ? "true" : "false";
    $settings[] = $setting;

    $setting = [];
    $setting['name'] = "PersistentCacheTtlSeconds";
    $setting['value'] = (string) max(1, (int) ($_POST['PersistentCacheTtlSeconds'] ?? 30));
    $settings[] = $setting;

    SetServerConf($settings);

    for ($i = 0; !empty($_POST["urlid$i"]); $i++) {
        $url = [
            "url_id" => $_POST["urlid$i"],
            "owner" => "ultiorganizer",
            "owner_id" => 0,
            "type" => $_POST["urltype$i"],
            "ordering" => $_POST["urlorder$i"],
            "url" => $_POST["url$i"],
            "ismedialink" => 0,
            "name" => $_POST["urlname$i"],
            "mediaowner" => "",
            "publisher_id" => "",
        ];

        if (strpos($url['url'], "@")) {
            SetMail($url);
        } else {
            SetUrl($url);
        }
    }
    if (!empty($_POST["newurl"])) {
        $url = [
            "owner" => "ultiorganizer",
            "owner_id" => 0,
            "type" => $_POST["newurltype"],
            "ordering" => $_POST["newurlorder"],
            "url" => $_POST["newurl"],
            "ismedialink" => 0,
            "name" => $_POST["newurlname"],
            "mediaowner" => "",
            "publisher_id" => "",
        ];
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

function ServerConfInfoRow($label, $value)
{
    return "<tr><td class='infocell'>" . utf8entities($label) . ":</td><td>" . utf8entities($value) . "</td></tr>\n";
}

function ServerConfConstantValue($name)
{
    if (!defined($name)) {
        $defaults = [
            'ENABLE_ADMIN_DB_ACCESS' => 'disabled',
            'DISABLE_SELF_REGISTRATION' => false,
            'NO_EMAIL' => false,
            'ALLOW_INSTALL' => false,
            'ANONYMOUS_RESULT_INPUT' => false,
            'API_RATE_LIMIT' => 120,
            'API_RATE_WINDOW' => 60,
        ];

        if (!array_key_exists($name, $defaults)) {
            return "";
        }

        $value = $defaults[$name];
    } else {
        $value = constant($name);
    }

    if (is_bool($value)) {
        return $value ? "true" : "false";
    }

    return (string) $value;
}

function ServerConfConfigInfoRows()
{
    $constants = [
        'DB_HOST',
        'DB_DATABASE',
        'BASEURL',
        'CUSTOMIZATIONS',
        'ENABLE_ADMIN_DB_ACCESS',
        'DISABLE_SELF_REGISTRATION',
        'NO_EMAIL',
        'ALLOW_INSTALL',
        'ANONYMOUS_RESULT_INPUT',
        'API_RATE_LIMIT',
        'API_RATE_WINDOW',
    ];

    $html = "";
    foreach ($constants as $constant) {
        $html .= ServerConfInfoRow($constant, ServerConfConstantValue($constant));
    }

    return $html;
}

//common page
pageTop($title);
leftMenu($LAYOUT_ID);
contentStart();
if (isSuperAdmin()) {
    $html .= "<p><a href='?view=admin/test'>" . _("Show phpinfo()") . "</a></p>\n";
}

$ultiorganizerVersion = GetUltiorganizerVersionInfo();
$databaseVersion = GetDatabaseVersionInfo();
$customizationVersion = GetCustomizationVersionInfo();

$html .= "<h1>" . _("Version information") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= ServerConfInfoRow(_("Ultiorganizer version"), $ultiorganizerVersion['version']);
$html .= ServerConfInfoRow(_("Database version"), $databaseVersion['version']);
$html .= ServerConfInfoRow(_("Customization"), $customizationVersion['id']);
$html .= ServerConfInfoRow(_("Customization version"), $customizationVersion['version']);
$html .= "</table>\n";

$html .= "<h1>" . _("Installation configuration") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= ServerConfConfigInfoRows();
$html .= "</table>\n";

$htmltmp1 = "";
$htmltmp2 = "";
$htmltmp3 = "";

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

    if ($setting['name'] == "EmailSource") {
        $htmltmp1 .= "<tr>";
        $htmltmp1 .= "<td class='infocell'>" . _("System email sender address") . ":</td>";
        $htmltmp1 .= "<td><input class='input' size='70' name='EmailSource' value='" . utf8entities($setting['value']) . "'/></td>";
        $htmltmp1 .= "</tr>\n";
    }

    if ($setting['name'] == "ShowDefenseStats") {
        $htmltmp2 .= "<tr>";
        $htmltmp2 .= "<td class='infocell'>" . _("Show Defence statistics") . ":</td>";
        if ($setting['value'] == "true") {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='ShowDefenseStats' checked='checked'/></td>";
        } else {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='ShowDefenseStats'/></td>";
        }
        $htmltmp2 .= "</tr>\n";
    }

    if ($setting['name'] == "ReadOnlyServer") {
        $htmltmp2 .= "<tr>";
        $htmltmp2 .= "<td class='infocell'>" . _("Read-only Server") . "?</td>";
        if ($setting['value'] == "true") {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='ReadOnlyServer' checked='checked'/></td>";
        } else {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='ReadOnlyServer'/></td>";
        }
        $htmltmp2 .= "</tr>\n";
    }

    if ($setting['name'] == "SoftMaintenanceMode") {
        $htmltmp2 .= "<tr>";
        $htmltmp2 .= "<td class='infocell'>" . _("Soft maintenance mode") . ":</td>";
        if ($setting['value'] == "true") {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='SoftMaintenanceMode' checked='checked'/></td>";
        } else {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='SoftMaintenanceMode'/></td>";
        }
        $htmltmp2 .= "</tr>\n";
    }

    if ($setting['name'] == "DisableVisitorLogging") {
        $htmltmp2 .= "<tr>";
        $htmltmp2 .= "<td class='infocell'>" . _("Disable visitor logging") . ":</td>";
        if ($setting['value'] == "true") {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='DisableVisitorLogging' checked='checked'/></td>";
        } else {
            $htmltmp2 .= "<td><input class='input' type='checkbox' name='DisableVisitorLogging'/></td>";
        }
        $htmltmp2 .= "</tr>\n";
    }

    if ($setting['name'] == "PersistentCacheEnabled") {
        $htmltmp3 .= "<tr>";
        $htmltmp3 .= "<td class='infocell'>" . _("Persistent cache") . ":</td>";
        if ($setting['value'] == "true") {
            $htmltmp3 .= "<td><input class='input' type='checkbox' name='PersistentCacheEnabled' checked='checked'/></td>";
        } else {
            $htmltmp3 .= "<td><input class='input' type='checkbox' name='PersistentCacheEnabled'/></td>";
        }
        $htmltmp3 .= "</tr>\n";
    }

    if ($setting['name'] == "PersistentCacheTtlSeconds") {
        $htmltmp3 .= "<tr>";
        $htmltmp3 .= "<td class='infocell'>" . _("Cache TTL (seconds)") . ":</td>";
        $htmltmp3 .= "<td><input class='input' type='number' min='1' max='3600' name='PersistentCacheTtlSeconds' value='" . utf8entities($setting['value']) . "'/></td>";
        $htmltmp3 .= "</tr>\n";

        $cacheStats = PersistentCacheStats();
        if ($cacheStats['bytes'] < 1024 * 1024) {
            $cacheSize = sprintf("%.1f KB", $cacheStats['bytes'] / 1024);
        } else {
            $cacheSize = sprintf("%.1f MB", $cacheStats['bytes'] / 1024 / 1024);
        }
        $htmltmp3 .= "<tr>";
        $htmltmp3 .= "<td class='infocell'>" . _("Cache size") . ":</td>";
        $htmltmp3 .= "<td>" . sprintf(_("%d files, %s"), $cacheStats['files'], $cacheSize) . "</td>";
        $htmltmp3 .= "</tr>\n";
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
$html .= "<hr/>";
$html .= "<form method='post' action='?view=admin/serverconf' id='Form'>";

$html .= "<h1>" . _("UI settings") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= "<tr><th>" . _("Type") . "</th><th>" . _("Order") . "</th><th>" . _("Name") . "</th><th>" . _("URL") . "</th><th></th></tr>\n";
$urls = GetUrlListByTypeArray(["menulink", "menumail", "admin"], 0);
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


$html .= "<h1>" . _("Internal settings") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= $htmltmp2;
$html .= "</table>\n";

$html .= "<h1>" . _("Cache settings") . "</h1>";
$html .= "<table style='white-space: nowrap' cellpadding='2'>\n";
$html .= $htmltmp3;
$html .= "</table>\n";

$html .= "<p><input class='button' name='save' type='submit' value='" . _("Save") . "'/>";
//$html .= "<input type='hidden' name='save' value='hiddensave'/>\n";
$html .= "<input type='hidden' id='hiddenDeleteId' name='hiddenDeleteId'/></p>";
$html .= "</form>";

if ($cacheCleared !== null) {
    $html .= "<p>" . sprintf(_("%d cache files cleared."), $cacheCleared) . "</p>";
}
$html .= "<form method='post' action='?view=admin/serverconf' id='WipeCacheForm'>";
$html .= "<p><input class='button' name='wipecache' type='submit' value='" . _("Clear cache") . "' onclick=\"return confirm('" . _("Clear cache?") . "');\"/></p>";
$html .= "</form>";

echo $html;
contentEnd();

pageEnd();
