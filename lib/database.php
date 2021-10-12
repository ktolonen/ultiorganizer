<?php

/**
 * @file
 * This file contains general functions to access and query database.
 *
 */

function GetServerName()
{
  if (isset($_SERVER['SERVER_NAME'])) {
    return $_SERVER['SERVER_NAME'];
  } elseif (isset($_SERVER['HTTP_HOST'])) {
    return $_SERVER['HTTP_HOST'];
  } else {
    die("Cannot find server address");
  }
}

$serverName = GetServerName();
//include prefix can be used to locate root level of directory tree.
$include_prefix = "";
while (!(is_readable($include_prefix . 'conf/config.inc.php') || is_readable($include_prefix . 'conf/' . $serverName . ".config.inc.php"))) {
  $include_prefix .= "../";
}

require_once $include_prefix . 'lib/gettext/gettext.inc.php';
include_once $include_prefix . 'lib/common.functions.php';

if (is_readable($include_prefix . 'conf/' . $serverName . ".config.inc.php")) {
  require_once $include_prefix . 'conf/' . $serverName . ".config.inc.php";
} else {
  require_once $include_prefix . 'conf/config.inc.php';
}

include_once $include_prefix . 'sql/upgrade_db.php';

//When adding new update function into upgrade_db.php change this number
//Also when you change the database, please add a database definition into
// 'lib/table-definition-cache' with the database version in the file name.
// You can get it by getting ext/restful/show_tables.php
define('DB_VERSION', 76); //Database version matching to upgrade functions.

$mysqlconnectionref;

/**
 * Open database connection.
 */
function OpenConnection()
{

  global $mysqlconnectionref;

  //connect to database
  $mysqlconnectionref = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
  if (mysqli_connect_errno()) {
    die('Failed to connect to server: ' . mysqli_connect_error());
  }

  //select schema
  $db = mysqli_select_db($mysqlconnectionref, DB_DATABASE);
  mysqli_set_charset($mysqlconnectionref, 'utf8');

  if (!$db) {
    die("Unable to select database");
  }

  //check if database is up-to-date
  if (!isset($_SESSION['dbversion'])) {
    CheckDB();
    $_SESSION['dbversion'] = getDBVersion();
  }
}

/**
 * Closes database connection.
 */
function CloseConnection()
{
  global $mysqlconnectionref;
  mysqli_close($mysqlconnectionref);
  $mysqlconnectionref = 0;
}

/**
 * Checks if there is need to update database and execute upgrade functions.
 */
function CheckDB()
{
  $installedDb = getDBVersion();
  for ($i = $installedDb; $i <= DB_VERSION; $i++) {
    $upgradeFunc = 'upgrade' . $i;
    LogDbUpgrade($i);
    $upgradeFunc();
    $query = sprintf("insert into uo_database (version, updated) values (%d, now())", $i + 1);
    runQuery($query);
    LogDbUpgrade($i, true);
  }
}
function DBEscapeString($escapestr)
{
  global $mysqlconnectionref;
  return mysqli_real_escape_string($mysqlconnectionref, $escapestr);
}

/**
 * Returns ultiorganizer database internal version number.
 *
 * @return integer version number
 */
function getDBVersion()
{
  global $mysqlconnectionref;
  $query = "SELECT max(version) as version FROM uo_database";
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    $query = "SELECT max(version) as version FROM pelik_database";
    $result = mysqli_query($mysqlconnectionref, $query);
  }
  if (!$result) return 0;
  if (!$row = mysqli_fetch_assoc($result)) {
    return 0;
  } else return $row['version'];
}

/**
 * Executes sql query and  returns result as an mysql array.
 *
 * @param string $query database query
 * @return mysqli_result of rows
 */
function DBQuery($query)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return $result;
}

/**
 * Executes sql query and returns the ID generated the query.
 *
 * @param string $query database query
 * @return int
 */
function DBQueryInsert($query)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return mysqli_insert_id($mysqlconnectionref);
}

/**
 * Executes sql query and  returns result as an value.
 *
 * @param string $query database query
 * @return  string of first cell on first row
 */
function DBQueryToValue($query, $docasting = false)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }

  if (mysqli_num_rows($result)) {
    $row = mysqli_fetch_row($result);
    if ($docasting) {
      $row = DBCastArray($result, $row);
    }
    return $row[0];
  } else {
    return -1;
  }
}

/**
 * Executes sql query and returns number of rows in resultset
 *
 * @param string $query database query
 * @return number of rows
 */
function DBQueryRowCount($query)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }

  return mysqli_num_rows($result);
}
/**
 * Executes sql query and copy returns to php array.
 *
 * @param string $query database query
 * @return Array of rows
 */
function DBQueryToArray($query, $docasting = false)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return DBResourceToArray($result, $docasting);
}


/**
 * Converts a db resource to an array
 *
 * @param $result The database resource returned from mysql_query
 * @return array of rows
 */
function DBResourceToArray($result, $docasting = false)
{
  $retarray = array();
  while ($row = mysqli_fetch_assoc($result)) {
    if ($docasting) {
      $row = DBCastArray($result, $row);
    }
    $retarray[] = $row;
  }
  return $retarray;
}

/**
 * Executes sql query and copy returns to php array of first row.
 *
 * @param string $query database query
 * @return first row in array
 */
function DBQueryToRow($query, $docasting = false)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    die('Invalid query: ("' . $query . '")' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  $ret = mysqli_fetch_assoc($result);
  if ($docasting && $ret) {
    $ret = DBCastArray($result, $ret);
  }
  return $ret;
}

/**
 * Set data into database by updating existing row.
 * @param string $name Name of the table to update
 * @param array $row Data to insert: key=>field, value=>data
 */
function DBSetRow($name, $data, $cond)
{

  $values = array_values($data);
  $fields = array_keys($data);

  $query = "UPDATE " . DBEscapeString($name) . " SET ";

  for ($i = 0; $i < count($fields); $i++) {
    $query .= DBEscapeString($fields[$i]) . "='" . $values[$i] . "', ";
  }
  $query = rtrim($query, ', ');
  $query .= " WHERE ";
  $query .= $cond;
  return DBQuery($query);
}

/**
 * Copy mysql_associative array row to regular php array.
 *
 * @param $result return value of mysql_query
 * @param $row mysql_associative array row
 * @return php array of $row
 */
function DBCastArray($result, $row)
{
  $ret = array();
  $i = 0;
  foreach ($row as $key => $value) {
    if (mysqli_fetch_field_direct($result, $i)->type == "int") {
      $ret[$key] = (int) $value;
    } else {
      $ret[$key] = $value;
    }
    $i++;
  }
  return $ret;
}

/**
 * Get current system status.
 *
 * @return string of resuls
 */
function DBStat()
{
  global $mysqlconnectionref;
  $result = mysqli_stat($mysqlconnectionref);
  if (!$result) {
    die('Invalid result' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return $result;
}

/**
 * Get Client info.
 *
 * @return result string
 */
function DBClientInfo()
{
  global $mysqlconnectionref;
  $result = mysqli_get_client_info($mysqlconnectionref);
  if (!$result) {
    die('Invalid result' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return $result;
}

/**
 * Get Host info.
 *
 * @return result string
 */
function DBHostInfo()
{
  global $mysqlconnectionref;
  $result = mysqli_get_host_info($mysqlconnectionref);
  if (!$result) {
    die('Invalid result' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return $result;
}

/**
 * Get Server info.
 *
 * @return result string
 */
function DBServerInfo()
{
  global $mysqlconnectionref;
  $result = mysqli_get_server_info($mysqlconnectionref);
  if (!$result) {
    die('Invalid result' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return $result;
}

/**
 * Get Protocol info.
 *
 * @return result string
 */
function DBProtocolInfo()
{
  global $mysqlconnectionref;
  $result = mysqli_get_proto_info($mysqlconnectionref);
  if (!$result) {
    die('Invalid result' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return $result;
}

if (function_exists('mysql_set_charset') === false) {
  /**
   * Sets the client character set.
   *
   * Note: This function requires MySQL 5.0.7 or later.
   *
   * @see http://www.php.net/mysql-set-charset
   * @param string $charset A valid character set name
   * @param resource $link_identifier The MySQL connection
   * @return TRUE on success or FALSE on failure
   */
  function mysql_set_charset($charset, $link_identifier = 0)
  {
    global $mysqlconnectionref;
    if ($link_identifier == null) {
      return mysqli_query($mysqlconnectionref, 'SET CHARACTER SET "' . $charset . '"');
    } else {
      return mysqli_query($mysqlconnectionref, 'SET CHARACTER SET "' . $charset . '"', $link_identifier);
    }
  }
}
