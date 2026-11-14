<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/cache.functions.php';

/**
 * Disable the persistent (cross-request) cache for the remainder of this request.
 *
 * Intended for live-entry apps (scorekeeper, spiritkeeper) where any staleness is
 * unacceptable and read volume is negligible. Call it once at request startup,
 * before the first cacheable read. Honored by DBQueryCacheable() in database.php.
 *
 * @return void
 */
function DisablePersistentCacheForRequest()
{
    $GLOBALS['uo_persistent_cache_bypass'] = true;
}

/**
 * Whether the persistent cache has been bypassed for this request.
 *
 * @return bool
 */
function IsPersistentCacheBypassed()
{
    return !empty($GLOBALS['uo_persistent_cache_bypass']);
}

/**
 * Return a cross-request cached value, recomputing when missing or expired.
 *
 * Writes are atomic (temp-file + rename). A non-blocking exclusive lock prevents
 * stampedes at cache expiry: the winner recomputes and writes; competing workers
 * return the stale payload while the winner holds the lock.
 *
 * Falls back to calling $resolver directly when PERSISTENT_CACHE_DIR is not
 * defined, when the cache is disabled via the PersistentCacheEnabled server
 * setting, or when the cache directory is not writable.
 *
 * Serialisation uses PHP's native serialize()/unserialize() with
 * allowed_classes=false to prevent PHP object injection. igbinary is not used
 * in the first version.
 *
 * @param string   $namespace  Logical cache namespace (e.g. "timetable_games")
 * @param mixed    $key        Namespace-specific cache key (scalar or array)
 * @param int      $ttlSeconds Seconds until expiry; 0 = use PersistentCacheTtlSeconds setting
 * @param callable $resolver   Computes and returns the fresh value on cache miss
 * @param callable|null $shouldStore Optional admission callback; receives the resolved value and returns whether
 *                                   it should be persisted
 * @return mixed
 */
function CacheRememberFor($namespace, $key, $ttlSeconds, $resolver, $shouldStore = null)
{
    if (!IsPersistentCacheEnabled()) {
        return $resolver();
    }

    $ttl = $ttlSeconds > 0 ? $ttlSeconds : GetPersistentCacheTtlSeconds();
    $filePath = PersistentCacheFilePath($namespace, $key);
    if ($filePath === null) {
        return $resolver();
    }

    $cached = PersistentCacheRead($filePath);
    if ($cached !== null && time() < $cached['expires']) {
        return $cached['payload'];
    }

    $lockFile = $filePath . '.lock';
    $lock = @fopen($lockFile, 'c');
    if ($lock === false) {
        // Lock file could not be opened (permission/disk issue, not contention).
        // Returning stale data here could keep stale forever if the lock file
        // cannot be created again; compute fresh instead. A full disk or inode
        // quota lands here, so sweep too or the cache could never shrink.
        if (random_int(1, 1000) === 1) {
            PersistentCacheCollectGarbage();
        }
        return $resolver();
    }

    if (flock($lock, LOCK_EX | LOCK_NB)) {
        // Re-read inside lock: another worker may have refreshed just before us.
        $recheck = PersistentCacheRead($filePath);
        if ($recheck !== null && time() < $recheck['expires']) {
            $value = $recheck['payload'];
        } else {
            $value = $resolver();
            if ($shouldStore === null || $shouldStore($value)) {
                PersistentCacheWrite($filePath, $value, $ttl);
                if (random_int(1, 1000) === 1) {
                    PersistentCacheCollectGarbage();
                }
            } else {
                // A previously populated query can become empty. Remove its
                // expired entry so empty misses leave no files behind.
                @unlink($filePath);
            }
        }
        // Lock files are only needed while a worker recomputes; keeping one per
        // key doubles the file count forever. A worker that opened the old inode
        // and locks it after this unlink re-reads the fresh entry above.
        @unlink($lockFile);
        flock($lock, LOCK_UN);
        fclose($lock);
        return $value;
    }

    // flock failed: another worker holds the lock (true contention). Return
    // stale value to avoid DB stampede, or compute without cache if no stale
    // value is available.
    fclose($lock);
    if ($cached !== null) {
        return $cached['payload'];
    }
    return $resolver();
}

/**
 * Delete one cached entry, or all entries for a namespace when $key is null.
 *
 * Call this from mutation helpers after a successful write so that stale data
 * is not served within the TTL window. Mirrors CacheForgetNamespace() from
 * cache.functions.php.
 *
 * @param string     $namespace
 * @param mixed|null $key  Null clears all files for the namespace.
 */
function CacheForgetPersistent($namespace, $key = null)
{
    if ($key !== null) {
        $filePath = PersistentCacheFilePath($namespace, $key);
        if ($filePath !== null) {
            @unlink($filePath);
            @unlink($filePath . '.lock');
        }
        return;
    }

    $dir = PersistentCacheDir();
    if ($dir === null) {
        return;
    }

    $safeNs = preg_replace('/[^a-zA-Z0-9]/', '_', $namespace);
    foreach (glob($dir . '/' . $safeNs . '_*.cache') as $file) {
        if (is_file($file)) {
            @unlink($file);
            @unlink($file . '.lock');
        }
    }
}

/**
 * Return the current number of cache files and their total size in bytes.
 * Returns zeros when the cache directory is not configured or readable, so
 * callers can render "no data" without special-casing.
 *
 * @return array{files: int, bytes: int}
 */
function PersistentCacheStats()
{
    $dir = PersistentCacheDir();
    if ($dir === null) {
        return ['files' => 0, 'bytes' => 0];
    }
    $files = 0;
    $bytes = 0;
    foreach (glob($dir . '/*.cache') as $file) {
        if (is_file($file)) {
            ++$files;
            $size = @filesize($file);
            if ($size !== false) {
                $bytes += (int) $size;
            }
        }
    }
    return ['files' => $files, 'bytes' => $bytes];
}

/**
 * Remove all cache files. Returns the number of .cache files removed.
 */
function CacheWipePersistent()
{
    $dir = PersistentCacheDir();
    if ($dir === null) {
        return 0;
    }

    $count = 0;
    foreach (glob($dir . '/*.cache') as $file) {
        if (is_file($file)) {
            @unlink($file);
            @unlink($file . '.lock');
            ++$count;
        }
    }
    foreach (array_merge(glob($dir . '/*.lock'), glob($dir . '/*.tmp.*'), [$dir . '/gc.mutex']) as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    return $count;
}

/**
 * Delete expired cache entries and leftover lock and temp files.
 *
 * Entries are only overwritten when the same query runs again, so without a
 * sweep every distinct query leaves a file behind for good. A .cache file's
 * mtime holds its expiry time (see PersistentCacheWrite()); lock and temp files
 * use their creation time. Files more than $graceSeconds past that are removed.
 * Only one worker sweeps at a time, and a sweep stops after $budgetSeconds so a
 * very large backlog is cleared over several sweeps.
 *
 * @param int   $graceSeconds  Keep expired entries this long for stale reads
 * @param float $budgetSeconds Wall-clock limit for one sweep
 * @return int|null Files removed, or null when another sweep is running or the
 *                  cache directory is not available
 */
function PersistentCacheCollectGarbage($graceSeconds = 60, $budgetSeconds = 1.0)
{
    $dir = PersistentCacheDir();
    if ($dir === null) {
        return null;
    }
    $gcLock = @fopen($dir . '/gc.mutex', 'c');
    if ($gcLock === false) {
        return null;
    }
    if (!flock($gcLock, LOCK_EX | LOCK_NB)) {
        fclose($gcLock);
        return null;
    }

    $handle = @opendir($dir);
    $removed = 0;
    if ($handle !== false) {
        $deadline = microtime(true) + $budgetSeconds;
        $cutoff = time() - $graceSeconds;
        $seen = 0;
        while (($name = readdir($handle)) !== false) {
            if (++$seen % 200 === 0 && microtime(true) > $deadline) {
                break;
            }
            if (!str_ends_with($name, '.cache') && !str_ends_with($name, '.lock')
                && !str_contains($name, '.tmp.')) {
                continue;
            }
            $path = $dir . '/' . $name;
            $mtime = @filemtime($path);
            if ($mtime !== false && $mtime < $cutoff && is_file($path) && @unlink($path)) {
                ++$removed;
            }
        }
        closedir($handle);
    }

    flock($gcLock, LOCK_UN);
    fclose($gcLock);
    return $removed;
}

// --- Internal helpers ---

function PersistentCacheDir()
{
    // Resolve the base directory. Undefined falls back to a safe runtime
    // default so upgraded installs that have not edited conf/config.inc.php
    // still benefit from caching. Empty string is the documented way to
    // disable filesystem caching even when the admin toggle is on.
    $base = defined('PERSISTENT_CACHE_DIR')
        ? (string) PERSISTENT_CACHE_DIR
        : sys_get_temp_dir() . '/ultiorganizer-cache';
    if (trim($base) === '') {
        return null;
    }
    // Scope to a per-install subdirectory so multiple Ultiorganizer
    // deployments that point to the same PERSISTENT_CACHE_DIR cannot read
    // each other's cached rows. Combine host, database, and user so that
    // installs sharing a schema name on different MySQL hosts (e.g. prod and
    // staging both named "ultiorganizer") still map to distinct directories.
    $instanceKey = implode('|', [
        defined('DB_HOST') ? (string) DB_HOST : '',
        defined('DB_DATABASE') ? (string) DB_DATABASE : '',
        defined('DB_USER') ? (string) DB_USER : '',
    ]);
    $dir = rtrim($base, '/') . '/' . md5($instanceKey);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        return null;
    }
    return $dir;
}

function PersistentCacheFilePath($namespace, $key)
{
    $dir = PersistentCacheDir();
    if ($dir === null) {
        return null;
    }
    $safeNs = preg_replace('/[^a-zA-Z0-9]/', '_', $namespace);
    $cacheKey = CacheRuntimeKey($namespace, $key);
    return $dir . '/' . $safeNs . '_' . md5($cacheKey) . '.cache';
}

function PersistentCacheRead($filePath)
{
    if (!file_exists($filePath)) {
        return null;
    }
    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        return null;
    }
    $data = unserialize($raw, ['allowed_classes' => false]);
    // array_key_exists distinguishes "missing key" from "key with null value"; a
    // legitimate cached null payload (e.g. DBQueryToValue with no row) must not
    // be re-fetched on every call.
    if (!is_array($data) || !array_key_exists('expires', $data) || !array_key_exists('payload', $data)) {
        return null;
    }
    return $data;
}

function PersistentCacheWrite($filePath, $value, $ttl)
{
    $expires = time() + $ttl;
    $raw = serialize(['expires' => $expires, 'payload' => $value]);
    $tmp = $filePath . '.tmp.' . getmypid();
    if (@file_put_contents($tmp, $raw, LOCK_EX) !== false) {
        // The mtime records the expiry so the garbage collector need not read files.
        @touch($tmp, $expires);
        if (!@rename($tmp, $filePath)) {
            @unlink($tmp);
        }
    }
}
