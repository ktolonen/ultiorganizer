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

include_once $include_prefix . 'lib/common.functions.php';

if (is_readable($include_prefix . 'conf/' . $serverName . ".config.inc.php")) {
  require_once $include_prefix . 'conf/' . $serverName . ".config.inc.php";
} else {
  require_once $include_prefix . 'conf/config.inc.php';
}

include_once $include_prefix . 'sql/upgrade_db.php';

//When adding new update function into upgrade_db.php change this number.
//When you change the database, export the current schema from the running database.
define('DB_VERSION', 88); //Database version matching to upgrade functions.

$mysqlconnectionref;

/**
 * Open database connection.
 */
function OpenConnection()
{

  global $mysqlconnectionref;
  $connectionErrorMessage = 'Service is temporarily unavailable. Please try again shortly. If the problem persists, please contact the event organizer.';

  //connect to database
  try {
    $mysqlconnectionref = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
  } catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die($connectionErrorMessage);
  }
  if (mysqli_connect_errno()) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die($connectionErrorMessage);
  }

  //select schema
  $db = mysqli_select_db($mysqlconnectionref, DB_DATABASE);
  mysqli_set_charset($mysqlconnectionref, 'utf8mb4');

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
  // Ensure we always start from the first available upgrade function (upgrade46).
  $installedDb = (int)getDBVersion();
  $startVersion = max($installedDb, 46);
  $installedVersions = array();
  $versionResult = mysqli_query($GLOBALS['mysqlconnectionref'], "SELECT version FROM uo_database WHERE version IS NOT NULL");
  if ($versionResult) {
    while ($row = mysqli_fetch_assoc($versionResult)) {
      $installedVersions[(int)$row['version']] = true;
    }
  }

  for ($i = $startVersion; $i <= DB_VERSION; $i++) {
    $upgradeFunc = 'upgrade' . $i;
    if (!function_exists($upgradeFunc)) {
      continue;
    }

    $nextVersion = $i + 1;
    if (isset($installedVersions[$nextVersion])) {
      continue;
    }

    LogDbUpgrade($i);
    $upgradeFunc();
    $query = sprintf(
      "INSERT INTO uo_database (version, updated)
       SELECT %d, NOW()
       FROM DUAL
       WHERE NOT EXISTS (SELECT 1 FROM uo_database WHERE version=%d)",
      $nextVersion,
      $nextVersion
    );
    runQuery($query);
    $installedVersions[$nextVersion] = true;
    LogDbUpgrade($i, true);
  }
}
function DBEscapeString($escapestr)
{
  global $mysqlconnectionref;
  $value = (string)$escapestr;

  // Ensure the value is valid UTF-8 before escaping, otherwise MySQL rejects it.
  if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
    $value = convertToUtf8($value);
  }
  if (function_exists('iconv')) {
    $clean = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
    if ($clean !== false) {
      $value = $clean;
    }
  }

  return mysqli_real_escape_string($mysqlconnectionref, $value);
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
  if (!$result) return 0;
  if (!$row = mysqli_fetch_assoc($result)) {
    return 0;
  }

  // max() can return NULL when the table is empty; treat that as version 0.
  return (int)$row['version'];
}

/**
 * Executes sql query and returns result as a mysqli array.
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
 * Prepare a sql query and return mysqli statement.
 *
 * @param string $query database query
 * @return mysqli_stmt|false
 */
function DBPrepare($query)
{
  global $mysqlconnectionref;
  return mysqli_prepare($mysqlconnectionref, $query);
}

/**
 * Get last database error string.
 *
 * @return string
 */
function DBError()
{
  global $mysqlconnectionref;
  return mysqli_error($mysqlconnectionref);
}

/**
 * Bind parameters for a prepared statement.
 *
 * @return bool
 */
function DBStmtBindParam($stmt, $types, &...$vars)
{
  return mysqli_stmt_bind_param($stmt, $types, ...$vars);
}

/**
 * Execute a prepared statement.
 *
 * @return bool
 */
function DBStmtExecute($stmt)
{
  return mysqli_stmt_execute($stmt);
}

/**
 * Get result for a prepared statement.
 *
 * @return mysqli_result|false
 */
function DBStmtGetResult($stmt)
{
  return mysqli_stmt_get_result($stmt);
}

/**
 * Get error string for a prepared statement.
 *
 * @return string
 */
function DBStmtError($stmt)
{
  return mysqli_stmt_error($stmt);
}

/**
 * Close a prepared statement.
 *
 * @return bool
 */
function DBStmtClose($stmt)
{
  return mysqli_stmt_close($stmt);
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
 * @param $result The database resource returned from mysqli_query
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
 * Fetch next row from result set as associative array.
 *
 * @param $result The database resource returned from mysqli_query
 * @return array|null
 */
function DBFetchAssoc($result, $docasting = false)
{
  $row = mysqli_fetch_assoc($result);
  if ($docasting && $row) {
    $row = DBCastArray($result, $row);
  }
  return $row;
}

/**
 * Fetch all rows from result set as associative arrays.
 *
 * @param $result The database resource returned from mysqli_query
 * @return array
 */
function DBFetchAllAssoc($result, $docasting = false)
{
  if (!$docasting) {
    return mysqli_fetch_all($result, MYSQLI_ASSOC);
  }

  $rows = array();
  while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = DBCastArray($result, $row);
  }
  return $rows;
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

  $columns = GetTableColumns($name);
  if (empty($columns)) {
    die("Invalid table '" . $name . "'");
  }

  if (strpos($cond, ';') !== false) {
    die("Invalid condition");
  }

  $query = "UPDATE " . $name . " SET ";

  for ($i = 0; $i < count($fields); $i++) {
    $fieldKey = strtolower($fields[$i]);
    if (!isset($columns[$fieldKey])) {
      die("Invalid field '" . $fields[$i] . "' for table '" . $name . "'");
    }

    if ($columns[$fieldKey] === 'int') {
      if ($values[$i] === null) {
        $query .= $fields[$i] . "=NULL, ";
      } else {
        $query .= $fields[$i] . "=" . (int)$values[$i] . ", ";
      }
    } else {
      $query .= $fields[$i] . "='" . DBEscapeString($values[$i]) . "', ";
    }
  }
  $query = rtrim($query, ', ');
  $query .= " WHERE ";
  $query .= $cond;
  return DBQuery($query);
}

/**
 * Copy mysqli_associative array row to regular php array.
 *
 * @param $result return value of mysqli_query
 * @param $row mysqli_associative array row
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
  $info = mysqli_get_client_info();
  if ($info === false) {
    die('Invalid result' . "<br/>\n" . mysqli_error($mysqlconnectionref));
  }
  return $info;
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

// wrapper for number of rows
function DBNumRows($res) {

  return mysqli_num_rows($res);
}

// wrapper for fetch row
function DBFetchRow($res) {

  return mysqli_fetch_row($res);
}

// wrapper for fetch array
function DBFetchArray($res) {

  return mysqli_fetch_array($res);
}

// wrapper for mysql insert id
function DBInsertId() {
  global $mysqlconnectionref;

  return mysqli_insert_id($mysqlconnectionref);
}

// wrapper for mysql data seek
function DBDataSeek($res,$off) {

  return mysqli_data_seek($res,$off);
}

// wrapper for mysql affected rows
function DBAffectedRows() {
  global $mysqlconnectionref;

  return mysqli_affected_rows($mysqlconnectionref);
}


/**
 * Get the name of the specified field in a result
 *
 * @param $result
 * @param $field_offset
 * @return bool
 */
function DBFieldName($result, $field_offset = 0)
{
  $props = mysqli_fetch_field_direct($result, $field_offset);
  return is_object($props) ? $props->name : false;
}


/**
 * Get the type of the specified field in a result
 * @param mysqli_result $result
 * @param $field_offset
 * @return string
 */
function DBFieldType(mysqli_result $result, $field_offset = 0)
{
  $unknown = 'unknown';
  $info = mysqli_fetch_field_direct($result, $field_offset);
  if (!is_object($info) || !isset($info->type)) {
    return $unknown;
  }
  switch ($info->type) {
    case MYSQLI_TYPE_FLOAT:
    case MYSQLI_TYPE_DOUBLE:
    case MYSQLI_TYPE_DECIMAL:
    case MYSQLI_TYPE_NEWDECIMAL:
      return 'real';
    case MYSQLI_TYPE_BIT:
      return 'bit';
    case MYSQLI_TYPE_TINY:
      return 'tinyint';
    case MYSQLI_TYPE_TIME:
      return 'time';
    case MYSQLI_TYPE_DATE:
      return 'date';
    case MYSQLI_TYPE_DATETIME:
      return 'datetime';
    case MYSQLI_TYPE_TIMESTAMP:
      return 'timestamp';
    case MYSQLI_TYPE_YEAR:
      return 'year';
    case MYSQLI_TYPE_STRING:
    case MYSQLI_TYPE_VAR_STRING:
      return 'string';
    case MYSQLI_TYPE_SHORT:
    case MYSQLI_TYPE_LONG:
    case MYSQLI_TYPE_LONGLONG:
    case MYSQLI_TYPE_INT24:
      return 'int';
    case MYSQLI_TYPE_CHAR:
      return 'char';
    case MYSQLI_TYPE_ENUM:
      return 'enum';
    case MYSQLI_TYPE_TINY_BLOB:
    case MYSQLI_TYPE_MEDIUM_BLOB:
    case MYSQLI_TYPE_LONG_BLOB:
    case MYSQLI_TYPE_BLOB:
      return 'blob';
    case MYSQLI_TYPE_NULL:
      return 'null';
    case MYSQLI_TYPE_NEWDATE:
    case MYSQLI_TYPE_INTERVAL:
    case MYSQLI_TYPE_SET:
    case MYSQLI_TYPE_GEOMETRY:
    default:
      return $unknown;
  }
}
