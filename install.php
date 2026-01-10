<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='en' lang='en'>

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <meta http-equiv="Content-Style-Type" content="text/css" />
  <meta http-equiv="Content-Script-Type" content="text/javascript" />
  <meta http-equiv="Pragma" content="no-cache" />
  <meta http-equiv="Expires" content="-1" />
  <title>Ultiorganizer - Installation</title>
  <link rel="stylesheet" href="cust/default/ultiorganizer.css" type="text/css" />
</head>

<body style='overflow-y: scroll;'>
  <div class='page' style='padding: 20px'>

    <?php
    if (is_file('conf/config.inc.php')) {
      include_once 'conf/config.inc.php';
    }

    // page 1: pre-requisites
    // page 2: database setup
    // page 3: ultiorganizer configurations
    // page 4: site settings
    // page 5: administration account
    // page 6: postconditions
    $page = intval(isset($_GET["page"]) ? $_GET["page"] : 0);
    $html = "";
    if (!empty($_POST['continue'])) {
      $page++;
    }

    $html = "<h1>Welcome to installing Ultiorganizer</h1>";
    $html .= "<form method='post' action='install.php?page=" . $page . "'>";

    switch ($page) {
      case 0:
        $html .= "<h2>Step 1: Prerequisites check</h2>";
        $html .= prerequisites();
        break;
      case 1:
        $html .= "<h2>Step 2: Database connection</h2>";
        $html .= database();
        break;
      case 2:
        $html .= "<h2>Step 3: Server defaults</h2>";
        $html .= configurations();
        break;
      case 3:
        $html .= "<h2>Step 4: Site settings</h2>";
        $html .= site_settings();
        break;
      case 4:
        $html .= "<h2>Step 5: Administration account</h2>";
        $html .= administration();
        break;
      case 5:
        $html .= "<h2>Step 6: Clean up</h2>";
        $html .= postconditions();
        break;
      default:
        header('Location: ' . BASEURL);
        break;
    }

    $html .= "</form>";
    echo $html;

?>
  </div>
</body>

</html>

<?php
function installHashPassword($password)
{
  if (function_exists('password_hash')) {
    return password_hash($password, PASSWORD_DEFAULT);
  }

  return md5($password);
}

function prerequisites()
{
  $passed = true;
  $html = "";
  $html .= "<table style='width:100%'>";

  //PHP version
  $html .= "<tr>";
  $html .= "<td>PHP version</td>";
  $html .= "<td>" . phpversion() . " installed (requires 8.3+)</td>";
  if (PHP_VERSION_ID >= 80300) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
    $passed = false;
  }
  $html .= "</tr>";

  //PHP extensions
  $html .= "<tr>";
  $html .= "<td>PHP Extension: mysql or mysqli</td>";
  $html .= "<td>required</td>";
  if (extension_loaded("mysql") || extension_loaded("mysqli")) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
    $passed = false;
  }
  $html .= "</tr>";

  $html .= "<tr>";
  $html .= "<td>PHP Extension: mbstring</td>";
  $html .= "<td>required</td>";
  if (extension_loaded("mbstring")) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
    $passed = false;
  }
  $html .= "</tr>";

  $html .= "<tr>";
  $html .= "<td>PHP Extension: gettext</td>";
  $html .= "<td>recommended</td>";
  if (extension_loaded("gettext")) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
  }
  $html .= "</tr>";

  $html .= "<tr>";
  $html .= "<td>PHP Extension: curl</td>";
  $html .= "<td>recommended</td>";
  if (extension_loaded("curl")) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
  }
  $html .= "</tr>";

  $html .= "<tr>";
  $html .= "<td>PHP Extension: gd</td>";
  $html .= "<td>recommended</td>";
  if (extension_loaded("gd")) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
  }
  $html .= "</tr>";

  //Database configuration file
  $file = "conf/config.inc.php";
  $html .= "<tr>";
  $html .= "<td>Database configuration file</td>";
  $html .= "<td>$file</td>";
  if (!is_readable($file)) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td><span style='color:red'>failed</span> (file already exists)</td>";
    $passed = false;
  }
  $html .= "</tr>";

  //Write acceess
  $directory = "conf/";
  $html .= "<tr>";
  $html .= "<td>Write access to folder</td>";
  $html .= "<td>$directory</td>";
  if (is_writable($directory)) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
    $passed = false;
  }
  $html .= "</tr>";

  //read acceess
  $file = "sql/ultiorganizer.sql";
  $html .= "<tr>";
  $html .= "<td>Database initalization file</td>";
  $html .= "<td>$file</td>";
  if (is_readable($file)) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>failed</td>";
    $passed = false;
  }
  $html .= "</tr>";
  $html .= "</table>";

  $html .= "<p>";
  if ($passed) {
    $html .= "<input class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  } else {
    $html .= "<input disabled='disabled' class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  }
  $html .= " <input class='button' name='refresh' id='refresh' type='submit' value='Refresh'/> ";

  $html .= "</p>";

  return $html;
}

function database()
{

  $db_hostname = isset($_POST['hostname']) ? trim($_POST['hostname']) : "localhost";
  $db_username = isset($_POST['username']) ? trim($_POST['username']) : "ultiorganizer";
  $db_password = isset($_POST['password']) ? trim($_POST['password']) : "ultiorganizer";
  $db_database = isset($_POST['database']) ? trim($_POST['database']) : "ultiorganizer";

  $html = "";
  $html .= "<table style='width:80%'>";
  $html .= "<tr><td>Hostaddress (database.example.com):</td><td><input class='input' type='text' name='hostname' size='50' value='$db_hostname'/></td></tr>";
  $html .= "<tr><td>Username:</td><td><input class='input' type='text' name='username' value='$db_username'/></td></tr>";
  $html .= "<tr><td>Password:</td><td><input class='input' type='password' name='password' value='$db_password'/></td></tr>";
  $html .= "<tr><td>Database name:</td><td><input class='input' type='text' name='database' value='$db_database'/></td></tr>";
  $html .= "</table>";

  $db_pass = false;

  if (!empty($_POST['testdb'])) {
    $db_pass = true;

    $html .= "<p>Connecting to database: ";
    $mysqlconnectionref = false;
    try {
      $mysqlconnectionref = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
    } catch (mysqli_sql_exception $e) {
      $mysqlconnectionref = false;
    }

    if (!$mysqlconnectionref || mysqli_connect_errno()) {
      $html .= "<span style='color:red'>Failed to connect to database.</span></p>";
      $html .= "<p style='color:#555'>Check host, username, and password. The database user must have access to '$db_database' and the MySQL server must be running.</p>";
      $db_pass = false;
    } else {
      $html .= "<span style='color:green'>ok</span></p>";
    }

    if ($db_pass) {
      $html .=  "<p>Selecting database: ";
      //select schema
      $db = mysqli_select_db($mysqlconnectionref, $db_database);
      if (!$db) {
        $html .= "<span style='color:red'>Failed. Unable to select database.</span></p>";
        $db_pass = false;
      } else {
        $html .= "<span style='color:green'>ok</span></p>";
      }


      $html .= "<p>Reading Ultiorganizer tables from given database: ";
      $tables = array();
      $db_database_sql = mysqli_real_escape_string($mysqlconnectionref, $db_database);
      try {
        $ret = mysqli_query($mysqlconnectionref, "SHOW TABLES FROM `" . $db_database_sql . "`");
      } catch (mysqli_sql_exception $e) {
        $ret = false;
      }
      if (!$ret) {
        $html .= "<span style='color:red'>Failed.</span></p>";
        $html .= "<p style='color:#555'>Database name looks invalid. Use only letters, numbers, and underscores, or wrap it in backticks when creating it.</p>";
        $db_pass = false;
      } else {
      while ($row = mysqli_fetch_row($ret)) {
        $tables[] = $row[0];
        //echo "\"",$row[0]."\",";
      }
      }
      // Base schema tables; upgrade-only tables are created via upgrade_db.php.
      $required = array(
        "uo_accreditationlog", "uo_club", "uo_country", "uo_database", "uo_enrolledteam",
        "uo_event_log", "uo_extraemail", "uo_extraemailrequest", "uo_game", "uo_game_pool",
        "uo_gameevent", "uo_goal", "uo_image", "uo_keys", "uo_location", "uo_moveteams",
        "uo_played", "uo_player", "uo_player_profile", "uo_player_stats", "uo_pool",
        "uo_pooltemplate", "uo_registerrequest", "uo_reservation", "uo_scheduling_name",
        "uo_season", "uo_season_stats", "uo_series", "uo_series_stats", "uo_setting",
        "uo_team", "uo_team_pool", "uo_team_profile", "uo_team_stats", "uo_timeout",
        "uo_urls", "uo_userproperties", "uo_users", "uo_victorypoints"
      );
      $delta = array_diff($required, $tables);
      if (!empty($delta)) {
        $html .= "<br>Missing tables: " . implode(', ', $delta) . "</p>";

        $html .= "<p>Creating Ultiorganizer tables: ";
        $ret = createtables($mysqlconnectionref);
        if (empty($ret['errors'])) {
          $html .= "<span style='color:green'>ok</span></p>";
          if (!empty($ret['warnings'])) {
            $html .= $ret['warnings'];
          }
        } else {
          $html .= "<span style='color:red'>failed</span>";
          $html .= $ret['errors'];
          if (!empty($ret['warnings'])) {
            $html .= $ret['warnings'];
          }
          $html .= "</p>";
          $db_pass = false;
        }
      } else {
        $html .= "<span style='color:green'>ok</span></p>";
      }
    }
    //mysqli_close($mysqlconnectionref);

    //write configuration file
    if ($db_pass) {
      if (!$fh = fopen('conf/config.inc.php', 'w')) {
        $html .= "<p style='color:red'>Cannot open file: conf/config.inc.php</p>";
      } else {
        fwrite($fh, "<?php\n");
        fwrite($fh, "/**\n");
        fwrite($fh, "MySQL Settings - you can get this information from your web hosting company.\n");
        fwrite($fh, "*/\n");
        fwrite($fh, "define('DB_HOST', '$db_hostname');\n");
        fwrite($fh, "define('DB_USER', '$db_username');\n");
        fwrite($fh, "define('DB_PASSWORD', '$db_password');\n");
        fwrite($fh, "define('DB_DATABASE', '$db_database');\n");
        fwrite($fh, "?>");
        fclose($fh);
      }
    }
  }

  $html .= "<p>";
  if ($db_pass) {
    $html .= "<input class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  } else {
    $html .= "<input disabled='disabled' class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  }
  $html .= " <input class='button' name='testdb' id='testdb' type='submit' value='Test Connection'/> ";

  $html .= "</p>";

  return $html;
}
function createtables($mysqlconnectionref)
{
  $errors = "";
  $warnings = "";

  //Create tables if required
  if (!($sql_file_contents = file_get_contents("sql/ultiorganizer.sql"))) {
    $errors .= "<p style='color:red'>Cannot open file: conf/config.inc.php</p>";
  } else {
    $sql = trim($sql_file_contents);

    for ($i = 0; $i < strlen($sql) - 1; $i++) {
      if ($sql[$i] == ";") {
        $lines[] = substr($sql, 0, $i);
        $sql = substr($sql, $i + 1);
        $i = 0;
      }
    }

    if (!empty($sql)) {
      $lines[] = $sql;
    }

    foreach ($lines as $line) {
      $line = trim($line);

      if (!empty($line)) {
        $result = false;
        $error_message = "";
        $error_number = 0;
        try {
          $result = mysqli_query($mysqlconnectionref, $line);
        } catch (mysqli_sql_exception $e) {
          $result = false;
          $error_message = $e->getMessage();
        }
        if (!$result) {
          if (empty($error_message)) {
            $error_message = mysqli_error($mysqlconnectionref);
          }
          $error_number = mysqli_errno($mysqlconnectionref);
          if ($error_number === 121 || $error_number === 1050 || $error_number === 1061 || strpos($error_message, "Duplicate key") !== false) {
            $warnings .= "<p style='color:#555'>Some tables or indexes already exist. Continuing without changes.</p>\n";
          } else {
            $errors .=  "<p style='color:red'>Problem with DB creation: " . $error_message . "</p>\n";
          }
        }
      }
    }
  }
  return array('errors' => $errors, 'warnings' => $warnings);
}


function configurations()
{

  $passed = false;

  $upload_dir = isset($_POST['upload_dir']) ? trim($_POST['upload_dir']) : "images/uploads/";
  $customization = isset($_POST['customization']) ? trim($_POST['customization']) : "default";
  $baseurl = isset($_POST['baseurl']) ? trim($_POST['baseurl']) : GetURLBase();


  $html = "";

  $customizations = array();
  $temp = scandir("cust/");

  foreach ($temp as $fh) {
    if (is_dir("cust/$fh") && $fh != '.' && $fh != '..') {
      $customizations[] = $fh;
    }
  }

  $html .= "<p>Server defaults written into file <i>conf/config.inc.php</i>. If you want to change these settings later, please edit the file directly.</p>";
  $html .= "<table style='width:100%'>";
  $html .= "<tr><td>Primary URL:</td><td><input class='input' type='text' size='50' name='baseurl' value='$baseurl'/></td></tr>";
  $html .= "<tr><td>Upload directory:</td><td><input class='input' type='text' size='50' name='upload_dir' value='$upload_dir'/></td></tr>";
  $html .= "<tr><td>Customization:</td><td><select class='dropdown' name='customization'>";
  foreach ($customizations as $cust) {
    if ($customization == $cust) {
      $html .= "<option class='dropdown' selected='selected' value='" . htmlentities($cust, ENT_QUOTES, "UTF-8") . "'>" . $cust . "</option>";
    } else {
      $html .= "<option class='dropdown' value='" . htmlentities($cust, ENT_QUOTES, "UTF-8") . "'>" . $cust . "</option>";
    }
  }

  $html .= "</select></td></tr>";
  $html .= "</table>";

  //write configuration file
  if (!empty($_POST['saveconf'])) {
    $passed = true;
    if (!is_writable($upload_dir)) {
      $html .= "<p style='color:red'>Upload directory $upload_dir is not writeable.</p>";
      $passed = false;
    }
    if (!$fh = fopen('conf/config.inc.php', 'w')) {
      $html .= "<p style='color:red'>Cannot open file: conf/config.inc.php</p>";
      $passed = false;
    } else {
      //re-write database configurations since those are located in same file.
      fwrite($fh, "<?php\n");
      fwrite($fh, "/**\n");
      fwrite($fh, " * MySQL Settings - you can get this information from your web hosting company.\n");
      fwrite($fh, "*/\n");
      fwrite($fh, "define('DB_HOST', '" . DB_HOST . "');\n");
      fwrite($fh, "define('DB_USER', '" . DB_USER . "');\n");
      fwrite($fh, "define('DB_PASSWORD', '" . DB_PASSWORD . "');\n");
      fwrite($fh, "define('DB_DATABASE', '" . DB_DATABASE . "');\n");

      fwrite($fh, "\n/**\n");
      fwrite($fh, " * Server Defaults.\n");
      fwrite($fh, "*/\n");
      fwrite($fh, "define('BASEURL', '$baseurl');\n");
      fwrite($fh, "define('UPLOAD_DIR', '$upload_dir');\n");
      fwrite($fh, "define('CUSTOMIZATIONS', '$customization');\n");
      fwrite($fh, "define('DATE_FORMAT', _(\"%d.%m.%Y %H:%M\"));\n");
      fwrite($fh, "define('WORD_DELIMITER', '/([\;\,\-_\s\/\.])/');\n");

      fwrite($fh, "?>");
      fclose($fh);
      $html .= "<p>Configuration saved.</p>";
    }
  }

  $html .= "<p>";
  if ($passed) {
    $html .= "<input class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  } else {
    $html .= "<input disabled='disabled' class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  }
  $html .= " <input class='button' name='saveconf' id='saveconf' type='submit' value='Write'/> ";

  $html .= "</p>";

  return $html;
}

function installSetSetting($mysqlconnectionref, $name, $value)
{
  $name_esc = mysqli_real_escape_string($mysqlconnectionref, $name);
  $value_esc = mysqli_real_escape_string($mysqlconnectionref, $value);
  $query = "SELECT setting_id FROM uo_setting WHERE name='$name_esc' LIMIT 1";
  $result = mysqli_query($mysqlconnectionref, $query);
  if ($result && ($row = mysqli_fetch_row($result))) {
    $setting_id = (int)$row[0];
    $query = "UPDATE uo_setting SET value='$value_esc' WHERE setting_id=$setting_id";
  } else {
    $query = "INSERT INTO uo_setting (name, value) VALUES ('$name_esc', '$value_esc')";
  }
  return mysqli_query($mysqlconnectionref, $query);
}

function installGetAvailableLocales()
{
  $localizations = array();
  $temp = @scandir("locale/");
  $currentLocale = setlocale(LC_MESSAGES, 0);
  $fallbackEnglishLocale = 'en_GB.utf8';

  if ($temp !== false) {
    foreach ($temp as $fh) {
      if (is_dir("locale/$fh") && $fh != '.' && $fh != '..') {
        if (setlocale(LC_MESSAGES, $fh) !== false) {
          $localizations[$fh] = $fh;
        }
      }
    }
  }

  if (!isset($localizations[$fallbackEnglishLocale])) {
    $localizations[$fallbackEnglishLocale] = $fallbackEnglishLocale;
  }
  if ($currentLocale !== false) {
    setlocale(LC_MESSAGES, $currentLocale);
  }

  return $localizations;
}

function site_settings()
{
  $passed = false;

  $page_title = isset($_POST['pagetitle']) ? trim($_POST['pagetitle']) : "Ultiorganizer - ";
  $maps_key = isset($_POST['maps']) ? trim($_POST['maps']) : "";
  $email_source = isset($_POST['mail']) ? trim($_POST['mail']) : "ultiorganizer@example.com";
  $admin_email = isset($_POST['admin']) ? trim($_POST['admin']) : "ultiorganizer_admin@example.com";
  $timezone = isset($_POST['timezone']) ? trim($_POST['timezone']) : "Europe/Helsinki";
  $locale = isset($_POST['locale']) ? trim($_POST['locale']) : "en_GB.utf8";

  $page_title_esc = htmlspecialchars($page_title, ENT_QUOTES, "UTF-8");
  $maps_key_esc = htmlspecialchars($maps_key, ENT_QUOTES, "UTF-8");
  $email_source_esc = htmlspecialchars($email_source, ENT_QUOTES, "UTF-8");
  $admin_email_esc = htmlspecialchars($admin_email, ENT_QUOTES, "UTF-8");

  $html = "";
  $html .= "<p>Optional server settings stored in the database.</p>";
  $html .= "<table style='width:100%'>";
  $html .= "<tr><td>Page title:</td><td><input class='input' type='text' size='50' name='pagetitle' value='$page_title_esc'/></td></tr>";
  $html .= "<tr><td>Google Maps key:</td><td><input class='input' type='text' size='50' name='maps' value='$maps_key_esc'/></td></tr>";
  $html .= "<tr><td>System email sender address:</td><td><input class='input' type='text' size='50' name='mail' value='$email_source_esc'/></td></tr>";
  $html .= "<tr><td>Admin contact email:</td><td><input class='input' type='text' size='50' name='admin' value='$admin_email_esc'/></td></tr>";

  $timezones = class_exists("DateTimeZone") ? DateTimeZone::listIdentifiers() : array();
  if (!empty($timezones)) {
    $html .= "<tr><td>Default timezone:</td><td><select class='dropdown' name='timezone'>";
    foreach ($timezones as $tz) {
      if ($timezone == $tz) {
        $html .= "<option selected='selected' value='$tz'>" . htmlspecialchars($tz, ENT_QUOTES, "UTF-8") . "</option>";
      } else {
        $html .= "<option value='$tz'>" . htmlspecialchars($tz, ENT_QUOTES, "UTF-8") . "</option>";
      }
    }
    $html .= "</select></td></tr>";
  } else {
    $timezone_esc = htmlspecialchars($timezone, ENT_QUOTES, "UTF-8");
    $html .= "<tr><td>Default timezone:</td><td><input class='input' type='text' size='50' name='timezone' value='$timezone_esc'/></td></tr>";
  }

  $locales = installGetAvailableLocales();
  if (!empty($locales)) {
    $html .= "<tr><td>Default locale:</td><td><select class='dropdown' name='locale'>";
    foreach ($locales as $loc) {
      if ($locale == $loc) {
        $html .= "<option selected='selected' value='$loc'>" . htmlspecialchars($loc, ENT_QUOTES, "UTF-8") . "</option>";
      } else {
        $html .= "<option value='$loc'>" . htmlspecialchars($loc, ENT_QUOTES, "UTF-8") . "</option>";
      }
    }
    $html .= "</select></td></tr>";
  } else {
    $locale_esc = htmlspecialchars($locale, ENT_QUOTES, "UTF-8");
    $html .= "<tr><td>Default locale:</td><td><input class='input' type='text' size='50' name='locale' value='$locale_esc'/></td></tr>";
  }

  $html .= "</table>";

  if (!empty($_POST['savesettings'])) {
    $passed = true;

    if ($email_source !== "" && !filter_var($email_source, FILTER_VALIDATE_EMAIL)) {
      $html .= "<p style='color:red'>System email sender address is invalid.</p>";
      $passed = false;
    }
    if ($admin_email !== "" && !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
      $html .= "<p style='color:red'>Admin contact email is invalid.</p>";
      $passed = false;
    }

    if ($passed) {
      if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASSWORD') || !defined('DB_DATABASE')) {
        $html .= "<p style='color:red'>Database configuration is missing.</p>";
        $passed = false;
      } else {
        $mysqlconnectionref = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
        if (!$mysqlconnectionref || mysqli_connect_errno()) {
          $html .= "<p style='color:red'>Failed to connect to database.</p>";
          $passed = false;
        } else {
          $updates = array(
            "PageTitle" => $page_title,
            "GoogleMapsAPIKey" => $maps_key,
            "EmailSource" => $email_source,
            "DefaultTimezone" => $timezone,
            "DefaultLocale" => $locale,
            "AdminEmail" => $admin_email
          );

          foreach ($updates as $name => $value) {
            if (!installSetSetting($mysqlconnectionref, $name, $value)) {
              $html .= "<p style='color:red'>Failed to save $name.</p>";
              $passed = false;
              break;
            }
          }
        }
      }
    }

    if ($passed) {
      $html .= "<p>Settings saved.</p>";
    }
  }

  $html .= "<p>";
  if ($passed) {
    $html .= "<input class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  } else {
    $html .= "<input disabled='disabled' class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  }
  $html .= " <input class='button' name='savesettings' id='savesettings' type='submit' value='Save'/> ";
  $html .= "</p>";

  return $html;
}

function administration()
{
  $passed = false;

  $passwd1 = isset($_POST['passwd1']) ? trim($_POST['passwd1']) : "";
  $passwd2 = isset($_POST['passwd2']) ? trim($_POST['passwd2']) : "";
  $admin_email = isset($_POST['admin_email']) ? trim($_POST['admin_email']) : "";
  if ($admin_email === "" && defined('DB_HOST') && defined('DB_USER') && defined('DB_PASSWORD') && defined('DB_DATABASE')) {
    $mysqlconnectionref = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
    if ($mysqlconnectionref && !mysqli_connect_errno()) {
      $email_result = mysqli_query($mysqlconnectionref, "SELECT value FROM uo_setting WHERE name='AdminEmail' LIMIT 1");
      if ($email_result && ($email_row = mysqli_fetch_row($email_result))) {
        $admin_email = $email_row[0];
      }
    }
  }

  $html = "";

  $html .= "<p>Change password for Ultiorganizer addministration account.</p>";
  $html .= "<table style='width:100%'>";
  $html .= "<tr><td>Username:</td><td><input type='text' disabled='disabled' name='admin' value='admin'/></td></tr>";
  $html .= "<tr><td>Admin email:</td><td><input type='text' name='admin_email' value='" . htmlspecialchars($admin_email, ENT_QUOTES, "UTF-8") . "'/></td></tr>";
  $html .= "<tr><td>Password:</td><td><input type='password' name='passwd1' value='$passwd1'/></td></tr>";
  $html .= "<tr><td>Password (again):</td><td><input type='password' name='passwd2' value='$passwd2'/></td></tr>";
  $html .= "</table>";

  if (!empty($_POST['saveconf'])) {
    $passed = true;

    if (empty($passwd1) || (strlen($passwd1) < 5 || strlen($passwd1) > 20)) {
      $html .= "<p style='color:red'>" . _("Invalid password (min. 5, max. 20 letters).") . "</p>";
      $passed = false;
    }

    if ($passwd1 != $passwd2) {
      $html .= "<p style='color:red'>Password doesn't match.</p>";
      $passed = false;
    }

    if ($passed) {
      $mysqlconnectionref = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_DATABASE);
      if (!$mysqlconnectionref || mysqli_connect_errno()) {
        $html .= "<p style='color:red'>Failed to connect to database.</p>";
        $passed = false;
      } else {
        $db = mysqli_select_db($mysqlconnectionref, DB_DATABASE);
        if (!$db) {
          $html .= "<p style='color:red'>Failed to select database.</p>";
          $passed = false;
        } else {
          $result = mysqli_query($mysqlconnectionref, "SELECT userid FROM uo_users WHERE userid='admin' LIMIT 1");
          if ($result && mysqli_num_rows($result) > 0) {
            $html .= "<p>Admin user already exists; password change skipped.</p>";
          } else {
            mysqli_set_charset($mysqlconnectionref, 'utf8');
            $hash = mysqli_real_escape_string($mysqlconnectionref, installHashPassword($passwd1));
            $email_value = $admin_email !== "" ? "'" . mysqli_real_escape_string($mysqlconnectionref, $admin_email) . "'" : "NULL";
            $query = sprintf(
              "INSERT INTO uo_users (userid, password, name, email, last_login) VALUES ('admin', '%s', 'Administrator', %s, NULL)",
              $hash,
              $email_value
            );
            $result = mysqli_query($mysqlconnectionref, $query);
            if ($result) {
              $html .= "<p>Admin user created.</p>";
            } else {
              $html .= "<p style='color:red'>Failed to create admin user.</p>";
              $passed = false;
            }
          }
        }
      }
      //mysqli_close($mysqlconnectionref);
    }
  }

  $html .= "<p>";
  if ($passed) {
    $html .= "<input class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  } else {
    $html .= "<input disabled='disabled' class='button' name='continue' id='continue' type='submit' value='Continue'/>";
  }
  $html .= " <input class='button' name='saveconf' id='createadmin' type='submit' value='Change password'/> ";

  $html .= "</p>";

  return $html;
}

function postconditions()
{
  $passed = true;
  $html = "";
  $html .= "<table style='width:100%'>";


  //Database configuration file
  $file = "conf/config.inc.php";
  @chmod($file, 0644);
  $html .= "<tr>";
  $html .= "<td>Remove write access</td>";
  $html .= "<td>$file</td>";
  if (!is_writeable($file)) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>manual action required (file writeable)</td>";
    $passed = false;
  }
  $html .= "</tr>";

  //Write acceess
  $directory = "conf/";
  @chmod($directory, 0755);
  $html .= "<tr>";
  $html .= "<td>Remove write access</td>";
  $html .= "<td>$directory</td>";
  if (!is_writable($directory)) {
    $html .= "<td style='color:green'>ok</td>";
  } else {
    $html .= "<td style='color:red'>manual action required (directory writeable)</td>";
    $passed = false;
  }
  $html .= "</tr>";

  $html .= "</table>";


  $html .= "<p>";
  if ($passed) {
    $html .= "<p>To finalize installation remove intall.php (this file) from server.</p>";
    $html .= "<input class='button' name='continue' id='continue' type='submit' value='Finish'/>";
  } else {
    $html .= "<input disabled='disabled' class='button' name='continue' id='continue' type='submit' value='Finish'/>";
  }
  $html .= " <input class='button' name='refresh' id='refresh' type='submit' value='Refresh'/> ";
  $html .= "</p>";

  return $html;
}

function GetURLBase()
{
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
  $url = $scheme;
  if (isset($_SERVER['SERVER_NAME'])) {
    $url .= $_SERVER['SERVER_NAME'];
  } elseif (isset($_SERVER['HTTP_HOST'])) {
    $url .= $_SERVER['HTTP_HOST'];
  }
  if (isset($_SERVER['SCRIPT_NAME'])) {
    $url .= $_SERVER['SCRIPT_NAME'];
  } elseif (isset($_SERVER['PHP_SELF'])) {
    $url .= htmlspecialchars($_SERVER['PHP_SELF']);
  } elseif (isset($_SERVER['PATH_INFO '])) {
    $url .= $_SERVER['PATH_INFO '];
  }

  $cutpos = strrpos($url, "/");
  $url = substr($url, 0, $cutpos);
  global $include_prefix;
  if (!empty($include_prefix)) {
    $updirs = explode($include_prefix, "/");
    foreach ($updirs as $dotdot) {
      $cutpos = strrpos($url, "/");
      $url = substr($url, 0, $cutpos);
    }
  }
  return $url;
}

?>
