<?php

$sql_file = 'sql/ultiorganizer.sql';
$db_error = 0;
$db_creation_error = 0;
// Check prerequisites
$create_conf_path=0;
$path = 'conf';
$filename = '$path/config.inc.php';
if (is_writable($filename)) {
  $file_ok=1;
} else {
  if (file_exists($filename)) {
    $file_error .= "$filename is not writable.";
  } else {
    if (is_writable($path)) {
      $file_ok=1;
    } else {
      if (file_exists($path)) {
        $file_error .= "$path is not writable.";
      } else {
        if (is_writable('.')) {
          $file_ok=1;
	  $create_conf_path=1;
        } else {
          $file_error .= "Pelikone root -directory is not writable.";
        }
      }
    }
  }
}

if ($create_conf_path) {
  mkdir($path);
}

if (version_compare(PHP_VERSION, '4.4.0') >= 0) {
   $php_version_ok = 1;
}

if ($file_ok && $php_version_ok) {
  $prereqs_ok=1;
}

// Default values
$hostname=isset($_POST['hostname']) ? mysql_escape_string($_POST['hostname']) : "localhost";
$username=isset($_POST['username']) ? mysql_escape_string($_POST['username']) : "pelikone";
$password=isset($_POST['password']) ? mysql_escape_string($_POST['password']) : "pelikone";
$database=isset($_POST['database']) ? mysql_escape_string($_POST['database']) : "pelikone";
$locales=isset($_POST['locales']) ? mysql_escape_string($_POST['locales']) : "en_GB.utf8:English,fi_FI.utf8:Suomi";
$default_timezone=isset($_POST['default_timezone']) ? mysql_escape_string($_POST['default_timezone']) : "Europe/Helsinki";
$upload_path=isset($_POST['upload_path']) ? mysql_escape_string($_POST['upload_path']) : "images/uploads";
$date_format=isset($_POST['date_format']) ? mysql_escape_string($_POST['date_format']) : "%d.%m.%Y %H:%M";

// Check input
if ($_POST) {
  // check contents (all field filled etc)
  $link = @mysql_connect($hostname, $username, $password);
  if (!$link) {
    $db_error = "Cannot connect to DB server ($hostname) with user ($username).";
  } else {
    $db_selected = mysql_select_db($database, $link);
    if (!$db_selected) {
      $db_error = "DB login ok but cannot select database ($database).";
    }
    // Here additional DB tests (permissions etc)
  }

  if (!is_writable($_POST['upload_path'])) {
    if (file_exists($upload_path)) {
      $upload_path_error = "Given upload path not writable.";
    } else {
      mkdir($upload_path);
    }
  }
  // Here additional checks for input
}

if ($_POST && $file_ok) {
    if (!$fh = fopen('conf/config.inc.php', 'w')) {
         echo "Cannot open file ($filename)";
         exit;
    }
  fwrite($fh, "<?php\n");
  fwrite($fh, "define('DB_HOST', '$hostname');\n");
  fwrite($fh, "define('DB_USER', '$username');\n");
  fwrite($fh, "define('DB_PASSWORD', '$password');\n");
  fwrite($fh, "define('DB_DATABASE', '$database');\n");

  fwrite($fh, "global \$locales;\n");
  fwrite($fh, "\$locales = array(");
  $splitted_locales = preg_split('/,/', $locales);
  foreach ($splitted_locales as $locale) {
      $pair = preg_split('/:/', $locale);
      fwrite($fh, "\"$pair[0]\" => \"$pair[1]\",");
  }
  fwrite($fh, ");\n");
  fwrite($fh, "define('DEFAULT_TIMEZONE', '$default_timezone');\n");
  fwrite($fh, "define('UPLOAD_DIR', '$upload_path');\n");
  fwrite($fh, "define('DATE_FORMAT', '$date_format');\n");
  fwrite($fh, "define('WORD_DELIMITER', '/([\;\,\-_\s\/\.])/');\n");
  fwrite($fh, "define('CUSTOMIZATIONS', 'wfdf');\n");
  $default_locale = preg_split('/:/', $splitted_locales[0]);
  fwrite($fh, "define('DEFAULT_LOCALE', '$default_locale[0]');\n");

  fwrite($fh, "?>");
  fclose($fh);
}
if( function_exists('date_default_timezone_set') ){
	date_default_timezone_set('UTC');
}

if ($_POST && !$db_error) {
  _dropTables($database);
  // Create database
  if (!($sql_file_contents = file_get_contents($sql_file))) {
    // Error handling
  }

  $sql = trim($sql_file_contents);

  for ($i = 0; $i < strlen($sql) - 1; $i++) {
    if ($sql[$i] == ";") {
      $lines[] = substr($sql, 0, $i);
      $sql = substr($sql, $i +1);
      $i = 0;
    }
  }

  if (!empty($sql)) {
    $lines[] = $sql;
  }

  foreach ($lines as $line) {
    $line = trim($line);

    if (!empty($line)) {
      if (!mysql_query($line)) {
        echo "Problem with DB creation: " . mysql_error() . "<br>\n";
	$db_creation_error = 1;
      }
    }
  }
}
// If all ok then redirect to next page.

@mysql_close($link);

function _dropTables($database) {

  $sql = "SHOW TABLES FROM $database";
  if ($result = mysql_query($sql)) {
    while ($row = mysql_fetch_row($result)) {
      $found_tables[] = $row[0];
    }

    foreach ($found_tables as $table_name) {
      $sql = "DROP TABLE $database.$table_name";
      if ($result = mysql_query($sql)) {
      }
    }
  } else {
    echo "Problems listing tables for delete ($sql): " .mysql_error();
  } 
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns='http://www.w3.org/1999/xhtml' xml:lang='fi' lang='fi'>
<head>
  <meta http-equiv="Pragma" content="no-cache"/>
  <meta http-equiv="Expires" content="-1"/>
  <link rel='icon' type='image/png' href='cust/default/favicon.png' />
  <title>Pelikoneen asennus</title>
  <link rel="stylesheet" href="cust/default/layout.css" type="text/css" />
  <link rel="stylesheet" href="cust/default/font.css" type="text/css" />
  <link rel="stylesheet" href="cust/default/default.css" type="text/css" />
</head><body<?php echo ($_POST && !$db_error && !$db_creation_error) ? " onLoad='window.location = \"index.php?view=admin/serverconf\"'" : "" ?>>
<div class='page_top'>
<table width='760px'>
<tr>
<td class='left'></td>
<td class='left'>
<a href='http://ultiorganizer.sourceforge.net' class='header_text'><img class='header_logo' src='cust/default/logo.jpg' alt='Ultiorganizer'/></a>
</td>
<td class='right' style='width:40%'></td>
</tr>
<tr><td colspan='3'>
<table width='100%'>
<tr>
<td style='width:70%' align='left'></td></tr>
</table>
</td>
</tr>
</table></div>
<div class='page_middle'>
<table><tr>
      <td class='menu_left'>

	  <table cellspacing='5' cellpadding='2'>
<tr><td>Installation</td></tr>
<tr><td class="menuseparator"></td></tr><tr><td></td></tr>
<tr><td>After giving this preliminary info the database
tables will be created and you will be taken to UI configuration</td></tr>
<tr><td>After saving those settings you are ready to use the system</td></tr>
<tr><td class="menuseparator"></td></tr><tr><td></td></tr>
</table></td>

<td align='left' valign='top'><div class='content'>
<h1>Welcome to installing Ultimate organizer</h1>
<h3>Prerequisites</h3>
<table>
<tr><td>File permissions:</td><td class='<?php echo ($file_ok) ? "OK" : "warning" ?>'><?php echo ($file_ok) ? "OK" : $file_error ?></td></tr>
<tr><td>PHP version (&gt;= 4.4.0):</td><td class='<?php echo ($php_version_ok) ? "OK" : "warning" ?>'><?php echo ($php_version_ok) ? "OK" : "FAIL" ?></td></tr>
</table>
<?php echo ($prereqs_ok) ? "" : "<p class='warning'>Fix issues and reload this page.</p>" ?>
<p>
Give necessary information to set up this installation.
</p>

<form method="post" action="install.php">

<table style='white-space: nowrap' cellpadding='2'>
 <tr><td>Hostname</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="hostname" value="<?php echo $hostname ?>" /></td></tr>
 <tr><td>Username</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="username" value="<?php echo $username ?>" /></td></tr>
 <tr><td>Password</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="password" value="<?php echo $password ?>" /></td></tr>
 <tr><td>Database</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="database" value="<?php echo $database ?>" /></td></tr>
</table>
<?php echo !empty($db_error) ? "<p class='warning'>$db_error</p>" : "" ?>
<table style='white-space: nowrap' cellpadding='2'>
 <tr><td>Locales (separated by semicolons and commas)</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="locales" value="<?php echo $locales ?>" /></td></tr>
 <tr><td>Default timezone</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="default_timezone" value="<?php echo $default_timezone ?>" /></td></tr>
 <tr><td>Upload directory</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="upload_path" value="<?php echo $upload_path ?>" /></td></tr>
 <tr><td>Date format</td><td><input type="text" <?php echo ($prereqs_ok) ? "" : "disabled='1' " ?>name="date_format" value="<?php echo $date_format ?>" /></td></tr>
</table>
<?php echo !empty($db_error) ? "<p class='warning'>$upload_path_error</p>" : "" ?>

<div style="text-align: center">
 <input type='submit' value='<--' disabled='disabled'/>
 <input type='submit' value='-->' <?php echo ($prereqs_ok) ? "" : "disabled='disabled' " ?>/>
</div>
</form>
</div></td></tr></table></div>
</body></html>
