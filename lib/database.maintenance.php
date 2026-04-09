<?php
require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

/**
 * Runtime maintenance and automatic schema upgrade coordination.
 */

function DBMaintenanceRuntimeDir()
{
  if (defined('MAINTENANCE_RUNTIME_DIR') && trim((string)MAINTENANCE_RUNTIME_DIR) !== '') {
    return rtrim((string)MAINTENANCE_RUNTIME_DIR, "/\\");
  }

  $base = function_exists('sys_get_temp_dir') ? sys_get_temp_dir() : '/tmp';
  return rtrim($base, "/\\") . '/ultiorganizer-maintenance-' . substr(md5(dirname(__DIR__)), 0, 12);
}

function DBEnsureMaintenanceRuntimeDir()
{
  $dir = DBMaintenanceRuntimeDir();
  if (is_dir($dir)) {
    return is_writable($dir);
  }

  if (@mkdir($dir, 0775, true)) {
    return true;
  }

  error_log('Failed to create maintenance runtime directory: ' . $dir);
  return false;
}

function DBMaintenanceFlagPath()
{
  return DBMaintenanceRuntimeDir() . '/maintenance.flag';
}

function DBMaintenanceLockPath()
{
  return DBMaintenanceRuntimeDir() . '/maintenance.lock';
}

function DBTimestamp()
{
  return gmdate('c');
}

function DBHtmlEscape($value)
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function DBAutomaticMaintenancePayload($status, $target, $meta = array())
{
  $payload = array(
    'mode=automatic',
    'status=' . $status,
    'target=' . (int)$target,
  );

  if ($status === 'running') {
    $payload[] = 'started_at=' . (isset($meta['started_at']) ? $meta['started_at'] : DBTimestamp());
  } elseif ($status === 'failed') {
    $payload[] = 'failed_at=' . (isset($meta['failed_at']) ? $meta['failed_at'] : DBTimestamp());
    $payload[] = 'error=' . (isset($meta['error']) ? $meta['error'] : 'Upgrade failed. Check server logs.');
  }

  return implode("\n", $payload) . "\n";
}

function DBParseAutomaticMaintenanceFlag($raw)
{
  $normalized = str_replace("\r\n", "\n", (string)$raw);
  $trimmed = rtrim($normalized, "\n");
  if ($trimmed === '') {
    return array('mode' => 'manual', 'reason' => 'empty');
  }

  $lines = explode("\n", $trimmed);
  $lineCount = count($lines);

  if ($lineCount === 3) {
    $expected = array('mode', 'status', 'target');
  } elseif ($lineCount === 4) {
    $expected = array('mode', 'status', 'target', 'started_at');
  } elseif ($lineCount === 5) {
    $expected = array('mode', 'status', 'target', 'failed_at', 'error');
  } else {
    return array('mode' => 'manual', 'reason' => 'line_count');
  }

  $parsed = array();
  foreach ($expected as $index => $key) {
    if (!isset($lines[$index]) || strpos($lines[$index], '=') === false) {
      return array('mode' => 'manual', 'reason' => 'malformed');
    }

    list($lineKey, $value) = explode('=', $lines[$index], 2);
    if ($lineKey !== $key) {
      return array('mode' => 'manual', 'reason' => 'unexpected_key');
    }
    $parsed[$lineKey] = $value;
  }

  if ($parsed['mode'] !== 'automatic') {
    return array('mode' => 'manual', 'reason' => 'invalid_mode');
  }

  if (!in_array($parsed['status'], array('pending', 'running', 'failed'), true)) {
    return array('mode' => 'manual', 'reason' => 'invalid_status');
  }

  if (!preg_match('/^[0-9]+$/', $parsed['target']) || (int)$parsed['target'] <= 0 || (int)$parsed['target'] !== DB_VERSION) {
    return array('mode' => 'manual', 'reason' => 'invalid_target');
  }

  if ($parsed['status'] === 'pending' && $lineCount !== 3) {
    return array('mode' => 'manual', 'reason' => 'invalid_pending');
  }

  if ($parsed['status'] === 'running' && $lineCount !== 4) {
    return array('mode' => 'manual', 'reason' => 'invalid_running');
  }

  if ($parsed['status'] === 'failed' && $lineCount !== 5) {
    return array('mode' => 'manual', 'reason' => 'invalid_failed');
  }

  if ($parsed['status'] === 'running' && $parsed['started_at'] === '') {
    return array('mode' => 'manual', 'reason' => 'missing_started_at');
  }

  if ($parsed['status'] === 'failed' && ($parsed['failed_at'] === '' || $parsed['error'] === '')) {
    return array('mode' => 'manual', 'reason' => 'missing_failure_meta');
  }

  return array(
    'mode' => 'automatic',
    'status' => $parsed['status'],
    'target' => (int)$parsed['target'],
    'started_at' => isset($parsed['started_at']) ? $parsed['started_at'] : null,
    'failed_at' => isset($parsed['failed_at']) ? $parsed['failed_at'] : null,
    'error' => isset($parsed['error']) ? $parsed['error'] : null,
    'raw' => $normalized,
  );
}

function DBReadMaintenanceState()
{
  if (!DBEnsureMaintenanceRuntimeDir()) {
    return array('mode' => 'manual', 'status' => null, 'path' => DBMaintenanceFlagPath(), 'reason' => 'runtime_dir_unavailable');
  }

  $path = DBMaintenanceFlagPath();
  if (!is_file($path)) {
    return array('mode' => 'none', 'status' => null, 'path' => $path);
  }

  $raw = @file_get_contents($path);
  if ($raw === false) {
    return array('mode' => 'manual', 'status' => null, 'path' => $path, 'reason' => 'unreadable');
  }

  $parsed = DBParseAutomaticMaintenanceFlag($raw);
  $parsed['path'] = $path;
  return $parsed;
}

function DBWriteMaintenanceFlag($content)
{
  if (!DBEnsureMaintenanceRuntimeDir()) {
    return false;
  }

  $path = DBMaintenanceFlagPath();
  if (@file_put_contents($path, $content, LOCK_EX) === false) {
    error_log('Failed to write maintenance flag: ' . $path);
    return false;
  }
  return true;
}

function DBCreateAutomaticMaintenanceFlag()
{
  return DBWriteMaintenanceFlag(DBAutomaticMaintenancePayload('pending', DB_VERSION));
}

function DBRemoveAutomaticMaintenanceFlag()
{
  $path = DBMaintenanceFlagPath();
  if (!is_file($path)) {
    return true;
  }
  if (!@unlink($path)) {
    error_log('Failed to remove automatic maintenance flag: ' . $path);
    return false;
  }
  return true;
}

function DBReleaseUpgradeLock()
{
  $lockPath = DBMaintenanceLockPath();
  if (is_file($lockPath) && !@unlink($lockPath)) {
    error_log('Failed to remove maintenance lock: ' . $lockPath);
  }
}

function DBIsUpgradeLockStale()
{
  $lockPath = DBMaintenanceLockPath();
  if (!is_file($lockPath)) {
    return false;
  }

  $mtime = @filemtime($lockPath);
  if ($mtime === false) {
    return false;
  }

  return ($mtime + DB_MAINTENANCE_LOCK_TIMEOUT) < time();
}

function DBTryAcquireUpgradeLock()
{
  if (!DBEnsureMaintenanceRuntimeDir()) {
    return false;
  }

  $lockPath = DBMaintenanceLockPath();

  if (is_file($lockPath) && DBIsUpgradeLockStale()) {
    @unlink($lockPath);
  }

  $handle = @fopen($lockPath, 'x');
  if ($handle === false) {
    return false;
  }

  fwrite($handle, 'pid=' . getmypid() . "\nacquired_at=" . DBTimestamp() . "\n");
  fclose($handle);
  return true;
}

function DBMaintenanceResponseData($state)
{
  if (($state['mode'] ?? 'none') === 'automatic') {
    $status = $state['status'] ?? 'pending';
    if ($status === 'failed') {
      return array(
        'title' => 'Database upgrade failed',
        'message' => 'Ultiorganizer is in maintenance mode because an automatic database upgrade failed. Check server logs and the maintenance flag before retrying.',
      );
    }

    return array(
      'title' => 'Database upgrade in progress',
      'message' => 'Ultiorganizer is temporarily unavailable while a database upgrade is running. Please try again in a moment.',
    );
  }

  return array(
    'title' => 'Maintenance',
    'message' => 'Ultiorganizer is currently under maintenance. Please try again later.',
  );
}

function DBRequestPrefersJson()
{
  $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
  $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

  if (strpos($scriptName, '/api/') !== false || substr($scriptName, -8) === 'json.php') {
    return true;
  }

  return stripos($accept, 'application/json') !== false;
}

function DBRenderMaintenanceResponse($state)
{
  $payload = DBMaintenanceResponseData($state);

  if (!headers_sent()) {
    http_response_code(503);
    header('Retry-After: 60');
  }

  if (DBRequestPrefersJson()) {
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode(
      array(
        'error' => 'maintenance',
        'title' => $payload['title'],
        'message' => $payload['message'],
      )
    );
    exit();
  }

  if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
  }

  echo "<!DOCTYPE html>\n";
  echo "<html lang='en'>\n";
  echo "<head><meta charset='UTF-8'/><meta name='viewport' content='width=device-width, initial-scale=1'/><title>" . DBHtmlEscape($payload['title']) . "</title></head>\n";
  echo "<body><main style='max-width:42rem;margin:4rem auto;padding:0 1rem;font-family:sans-serif;line-height:1.5'>";
  echo "<h1>" . DBHtmlEscape($payload['title']) . "</h1>";
  echo "<p>" . DBHtmlEscape($payload['message']) . "</p>";
  echo "</main></body></html>\n";
  exit();
}

function DBMarkAutomaticUpgradeFailed($message)
{
  $safeMessage = trim((string)$message);
  if ($safeMessage === '') {
    $safeMessage = 'Upgrade failed. Check server logs.';
  }
  $safeMessage = preg_replace('/\s+/', ' ', $safeMessage);
  $safeMessage = substr($safeMessage, 0, 200);

  DBWriteMaintenanceFlag(DBAutomaticMaintenancePayload('failed', DB_VERSION, array(
    'failed_at' => DBTimestamp(),
    'error' => $safeMessage,
  )));
}

function DBRunAutomaticUpgrade()
{
  if (!DBTryAcquireUpgradeLock()) {
    return false;
  }

  DBSetExceptionMode(true);

  try {
    if (!DBWriteMaintenanceFlag(DBAutomaticMaintenancePayload('running', DB_VERSION, array(
      'started_at' => DBTimestamp(),
    )))) {
      DBMarkAutomaticUpgradeFailed('Failed to mark upgrade as running.');
      return false;
    }

    CheckDB();
    if (getDBVersion() !== DB_VERSION) {
      DBMarkAutomaticUpgradeFailed('Upgrade finished without reaching target version.');
      return false;
    }

    if (!DBRemoveAutomaticMaintenanceFlag()) {
      DBMarkAutomaticUpgradeFailed('Upgrade succeeded but maintenance flag removal failed.');
      return false;
    }

    return true;
  } catch (Throwable $e) {
    error_log('Automatic database upgrade failed: ' . $e->getMessage());
    DBMarkAutomaticUpgradeFailed('Automatic database upgrade failed. Check server logs.');
    return false;
  } finally {
    DBSetExceptionMode(false);
    DBReleaseUpgradeLock();
  }
}

function DBHandleMaintenanceState()
{
  $dbVersion = getDBVersion();
  $state = DBReadMaintenanceState();

  if ($state['mode'] === 'manual') {
    DBRenderMaintenanceResponse($state);
  }

  if ($state['mode'] === 'automatic') {
    if ($dbVersion === DB_VERSION && in_array($state['status'], array('pending', 'running'), true)) {
      if (DBRemoveAutomaticMaintenanceFlag()) {
        return;
      }
      DBMarkAutomaticUpgradeFailed('Database is current but automatic maintenance flag removal failed.');
      DBRenderMaintenanceResponse(DBReadMaintenanceState());
    }

    if ($state['status'] === 'failed') {
      DBRenderMaintenanceResponse($state);
    }

    if ($dbVersion !== DB_VERSION) {
      if (DBRunAutomaticUpgrade() && getDBVersion() === DB_VERSION) {
        return;
      }
      DBRenderMaintenanceResponse(DBReadMaintenanceState());
    }

    DBRenderMaintenanceResponse($state);
  }

  if ($dbVersion === DB_VERSION) {
    return;
  }

  if (!DBCreateAutomaticMaintenanceFlag()) {
    error_log('Failed to create automatic maintenance flag while database version mismatched.');
    DBRenderMaintenanceResponse(array('mode' => 'automatic', 'status' => 'failed'));
  }

  if (DBRunAutomaticUpgrade() && getDBVersion() === DB_VERSION) {
    return;
  }

  DBRenderMaintenanceResponse(DBReadMaintenanceState());
}
