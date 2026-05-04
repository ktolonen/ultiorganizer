<?php
require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

/**
 * Current database schema version expected by this codebase.
 *
 * Update this constant whenever you add a new `upgradeNN()` step in
 * `sql/upgrade_db.php`, and export the current schema from the upgraded database.
 */
define('DB_VERSION', 89);

/**
 * Maximum age in seconds before an automatic upgrade lock is considered stale.
 */
define('DB_MAINTENANCE_LOCK_TIMEOUT', 600);

/**
 * Resolve the current host name used for host-specific config lookup.
 *
 * @return string
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

/**
 * Walk up parent directories until the readable config file is found.
 *
 * The returned prefix is used by legacy entry points that include `lib/database.php`
 * from different directory depths and still expect root-relative includes to work.
 *
 * @return string Relative prefix such as `""`, `"../"`, or `"../../"`
 */
function FindIncludePrefix()
{
  $includePrefix = "";
  $maxLevels = 25;

  for ($level = 0; $level <= $maxLevels; $level++) {
    if (is_readable($includePrefix . 'conf/config.inc.php')) {
      return $includePrefix;
    }
    $includePrefix .= "../";
  }

  die("Cannot locate configuration file");
}

//include prefix can be used to locate root level of directory tree.
$include_prefix = FindIncludePrefix();

include_once $include_prefix . 'lib/common.functions.php';

require_once $include_prefix . 'conf/config.inc.php';

include_once $include_prefix . 'sql/upgrade_db.php';

$mysqlconnectionref;

/**
 * Exception type used when DB helpers are switched into exception mode.
 */
class DBOperationException extends RuntimeException
{
}

/**
 * Return the generic public-facing DB failure message.
 *
 * @return string
 */
function DBUserErrorMessage()
{
  return 'Service is temporarily unavailable. Please try again shortly. If the problem persists, please contact the event organizer.';
}

/**
 * Tell whether DB helpers should throw instead of terminating the request.
 *
 * Exception mode is used by the automatic upgrade path so it can mark the
 * maintenance flag as failed instead of aborting mid-request.
 *
 * @return bool
 */
function DBShouldThrowExceptions()
{
  return !empty($GLOBALS['db_throw_exceptions']);
}

/**
 * Toggle exception mode for low-level DB helper failures.
 *
 * @param bool $enabled
 * @return void
 */
function DBSetExceptionMode($enabled)
{
  $GLOBALS['db_throw_exceptions'] = (bool)$enabled;
}

/**
 * Log a DB failure and either throw or terminate with a generic user message.
 *
 * @param string $context Short helper-specific failure description
 * @param string|null $query SQL text related to the failure, when available
 * @param string|null $error Driver error text, when available
 * @return never
 * @throws DBOperationException When exception mode is enabled
 */
function DBAbort($context, $query = null, $error = null)
{
  $details = array($context);

  if ($query !== null && $query !== '') {
    $details[] = 'query=' . str_replace(array("\r", "\n"), ' ', trim((string)$query));
  }

  if ($error !== null && $error !== '') {
    $details[] = 'error=' . trim((string)$error);
  }

  error_log(implode(' | ', $details));
  if (DBShouldThrowExceptions()) {
    throw new DBOperationException(DBUserErrorMessage());
  }
  die(DBUserErrorMessage());
}

require_once __DIR__ . '/database.maintenance.php';

/**
 * Open the mysqli connection, select the schema, and run maintenance gating.
 *
 * This is the main DB bootstrap entrypoint for web requests. It establishes the
 * global connection handle, sets the connection charset, and then lets the
 * maintenance module block requests or run controlled schema upgrades when needed.
 *
 * @return void
 */
function OpenConnection()
{

  global $mysqlconnectionref;

  //connect to database
  try {
    $mysqlconnectionref = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD);
  } catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die(DBUserErrorMessage());
  }
  if (mysqli_connect_errno()) {
    error_log('Database connection failed: ' . mysqli_connect_error());
    die(DBUserErrorMessage());
  }

  //select schema
  $db = mysqli_select_db($mysqlconnectionref, DB_DATABASE);
  mysqli_set_charset($mysqlconnectionref, 'utf8mb4');

  if (!$db) {
    die("Unable to select database");
  }

  DBHandleMaintenanceState();
}

/**
 * Close the active mysqli connection and clear the global handle.
 *
 * @return void
 */
function CloseConnection()
{
  global $mysqlconnectionref;
  mysqli_close($mysqlconnectionref);
  $mysqlconnectionref = 0;
}

/**
 * Run versioned schema upgrades until the database reaches `DB_VERSION`.
 *
 * This mutating function is intentionally called only through the maintenance
 * gate, not from ordinary request flow. Each `upgradeNN()` function upgrades
 * the schema to version `NN`, and successful execution records `NN` in
 * `uo_database`.
 *
 * @return void
 */
function CheckDB()
{
  // Start from the next schema version after the installed one.
  $installedDb = (int)getDBVersion();
  $startVersion = max($installedDb + 1, 46);
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

    if (isset($installedVersions[$i])) {
      continue;
    }

    LogDbUpgrade($i);
    $upgradeFunc();
    $query = sprintf(
      "INSERT INTO uo_database (version, updated)
       SELECT %d, NOW()
       FROM DUAL
       WHERE NOT EXISTS (SELECT 1 FROM uo_database WHERE version=%d)",
      $i,
      $i
    );
    runQuery($query);
    $installedVersions[$i] = true;
    LogDbUpgrade($i, true);
  }
}

/**
 * Escape a scalar value for safe SQL string interpolation.
 *
 * This helper also normalizes invalid UTF-8 before escaping so MySQL does not
 * reject malformed text input.
 *
 * @param mixed $escapestr
 * @return string
 */
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
 * Return the highest recorded Ultiorganizer schema version in `uo_database`.
 *
 * @return int Internal schema version, or `0` when the table is missing or empty
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
 * Execute raw SQL and return the mysqli result resource.
 *
 * Use this only when callers genuinely need cursor-style access. For ordinary
 * reads, prefer `DBQueryToRow()`, `DBQueryToValue()`, or `DBQueryToArray()`.
 *
 * @param string $query Database query
 * @return mysqli_result
 */
function DBQuery($query)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    DBAbort('DBQuery failed', $query, mysqli_error($mysqlconnectionref));
  }
  return $result;
}

/**
 * Execute SQL statements from a line-based dump on the current connection.
 *
 * @param array $lines SQL dump content split into lines
 * @return void
 */
function DBReplaySqlLines($lines)
{
  $statement = '';

  foreach ($lines as $line) {
    if (substr($line, 0, 2) == '--' || trim($line) === '') {
      continue;
    }

    $statement .= $line;
    if (substr(trim($line), -1, 1) == ';') {
      DBQuery($statement);
      $statement = '';
    }
  }
}

/**
 * Prepare SQL and return the mysqli statement handle.
 *
 * @param string $query Database query
 * @return mysqli_stmt|false
 */
function DBPrepare($query)
{
  global $mysqlconnectionref;
  return mysqli_prepare($mysqlconnectionref, $query);
}

/**
 * Return the last error message from the active mysqli connection.
 *
 * @return string
 */
function DBError()
{
  global $mysqlconnectionref;
  return mysqli_error($mysqlconnectionref);
}

/**
 * Bind PHP variables to a prepared statement.
 *
 * @param mysqli_stmt $stmt
 * @param string $types mysqli bind type string such as `ssi`
 * @param mixed ...$vars Variables passed by reference to mysqli
 * @return bool
 */
function DBStmtBindParam($stmt, $types, &...$vars)
{
  return mysqli_stmt_bind_param($stmt, $types, ...$vars);
}

/**
 * Execute a prepared statement.
 *
 * @param mysqli_stmt $stmt
 * @return bool
 */
function DBStmtExecute($stmt)
{
  return mysqli_stmt_execute($stmt);
}

/**
 * Fetch a mysqli result resource from a prepared statement.
 *
 * @param mysqli_stmt $stmt
 * @return mysqli_result|false
 */
function DBStmtGetResult($stmt)
{
  return mysqli_stmt_get_result($stmt);
}

/**
 * Return the last error string for a prepared statement.
 *
 * @param mysqli_stmt $stmt
 * @return string
 */
function DBStmtError($stmt)
{
  return mysqli_stmt_error($stmt);
}

/**
 * Close a prepared statement.
 *
 * @param mysqli_stmt $stmt
 * @return bool
 */
function DBStmtClose($stmt)
{
  return mysqli_stmt_close($stmt);
}

/**
 * Execute SQL and return the connection's last inserted auto-increment id.
 *
 * @param string $query Database query
 * @return int
 */
function DBQueryInsert($query)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    DBAbort('DBQueryInsert failed', $query, mysqli_error($mysqlconnectionref));
  }
  return mysqli_insert_id($mysqlconnectionref);
}

/**
 * Execute SQL and return the first cell from the first row.
 *
 * @param string $query Database query
 * @param bool $docasting When true, cast numeric scalar values using field metadata
 * @return mixed|null first cell on first row, or null when the query returns no rows
 */
function DBQueryToValue($query, $docasting = false)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    DBAbort('DBQueryToValue failed', $query, mysqli_error($mysqlconnectionref));
  }

  if (mysqli_num_rows($result)) {
    $row = mysqli_fetch_row($result);
    if ($docasting) {
      $row = DBCastArray($result, $row);
    }
    return $row[0];
  } else {
    return null;
  }
}

/**
 * Execute SQL and return the row count of the result set.
 *
 * @param string $query Database query
 * @return int Number of rows
 */
function DBQueryRowCount($query)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    DBAbort('DBQueryRowCount failed', $query, mysqli_error($mysqlconnectionref));
  }

  return mysqli_num_rows($result);
}
/**
 * Execute SQL and materialize the full result set into an array of rows.
 *
 * @param string $query Database query
 * @param bool $docasting When true, cast row values using field metadata
 * @return array Array of associative rows
 */
function DBQueryToArray($query, $docasting = false)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    DBAbort('DBQueryToArray failed', $query, mysqli_error($mysqlconnectionref));
  }
  return DBResourceToArray($result, $docasting);
}


/**
 * Convert a mysqli result resource into an array of associative rows.
 *
 * Accepts either a raw mysqli result or an already-materialized row array to
 * preserve compatibility with legacy callers that still route helper output
 * through `DBFetch*()` wrappers.
 *
 * @param mysqli_result|array $result The database resource returned from mysqli_query, or an array of rows
 * @param bool $docasting When true, cast row values using field metadata
 * @return array
 */
function DBResourceToArray($result, $docasting = false)
{
  if (is_array($result)) {
    if (empty($result)) {
      return array();
    }

    $firstRow = reset($result);
    return is_array($firstRow) ? array_values($result) : array($result);
  }

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
 * @param mysqli_result|array $result The database resource returned from mysqli_query, or an array of rows
 * @param bool $docasting When true, cast row values using field metadata
 * @return array|null
 */
function DBFetchAssoc($result, $docasting = false)
{
  if (is_array($result)) {
    if (empty($result)) {
      return null;
    }

    $firstRow = reset($result);
    return is_array($firstRow) ? $firstRow : $result;
  }

  $row = mysqli_fetch_assoc($result);
  if ($docasting && $row) {
    $row = DBCastArray($result, $row);
  }
  return $row;
}

/**
 * Fetch all rows from result set as associative arrays.
 *
 * @param mysqli_result|array $result The database resource returned from mysqli_query, or an array of rows
 * @param bool $docasting When true, cast row values using field metadata
 * @return array
 */
function DBFetchAllAssoc($result, $docasting = false)
{
  if (is_array($result)) {
    if (empty($result)) {
      return array();
    }

    $firstRow = reset($result);
    return is_array($firstRow) ? array_values($result) : array($result);
  }

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
 * Execute SQL and return the first row as an associative array.
 *
 * @param string $query Database query
 * @param bool $docasting When true, cast row values using field metadata
 * @return array|null
 */
function DBQueryToRow($query, $docasting = false)
{
  global $mysqlconnectionref;
  $result = mysqli_query($mysqlconnectionref, $query);
  if (!$result) {
    DBAbort('DBQueryToRow failed', $query, mysqli_error($mysqlconnectionref));
  }
  $ret = mysqli_fetch_assoc($result);
  if ($docasting && $ret) {
    $ret = DBCastArray($result, $ret);
  }
  return $ret;
}

/**
 * Update an existing row using a validated field list and raw WHERE clause.
 *
 * Column names are checked against table metadata to avoid accidental writes to
 * unknown fields. The caller is still responsible for providing a safe condition.
 *
 * @param string $name Name of the table to update
 * @param array $data Data to update as `field => value`
 * @param string $cond Raw SQL condition appended after `WHERE`
 * @return mysqli_result
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
 * Cast a fetched row using the field types from the originating result set.
 *
 * Integer and real columns are converted to PHP numeric types. Other columns are
 * returned unchanged, and `NULL` values stay `null`.
 *
 * @param mysqli_result $result Return value of mysqli_query
 * @param array $row Row fetched from the result resource
 * @return array
 */
function DBCastArray($result, $row)
{
  $ret = array();
  $i = 0;
  foreach ($row as $key => $value) {
    if ($value === null) {
      $ret[$key] = null;
      $i++;
      continue;
    }

    switch (DBFieldType($result, $i)) {
      case 'tinyint':
      case 'int':
        $ret[$key] = (int)$value;
        break;
      case 'real':
        $ret[$key] = (float)$value;
        break;
      default:
        $ret[$key] = $value;
        break;
    }
    $i++;
  }
  return $ret;
}

/**
 * Return connection status information from `mysqli_stat()`.
 *
 * @return string
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
 * Return the mysqli client library version string.
 *
 * @return string
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
 * Return the host information for the current DB connection.
 *
 * @return string
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
 * Return the connected MySQL server version string.
 *
 * @return string
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
 * Return the MySQL protocol version used by the connection.
 *
 * @return int|string
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

/**
 * Legacy wrapper for `mysqli_num_rows()`.
 *
 * Keep for compatibility with the legacy `live/` API code.
 *
 * @param mysqli_result|array $res
 * @return int
 */
function DBNumRows($res) {
  if (is_array($res)) {
    return count($res);
  }

  return mysqli_num_rows($res);
}

/**
 * Return the field name at the given result-set column offset.
 *
 * @param mysqli_result $result
 * @param int $field_offset
 * @return string|false
 */
function DBFieldName($result, $field_offset = 0)
{
  $props = mysqli_fetch_field_direct($result, $field_offset);
  return is_object($props) ? $props->name : false;
}


/**
 * Return a normalized field type name for a result-set column.
 *
 * This maps mysqli's numeric field constants to stable string labels used by
 * `DBCastArray()` and other internal helpers.
 *
 * @param mysqli_result $result
 * @param int $field_offset
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
