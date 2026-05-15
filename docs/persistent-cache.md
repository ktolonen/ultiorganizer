# Persistent Cache

Use `lib/persistent-cache.functions.php` for cross-request, time-limited caching
of expensive read-only helper results such as aggregate standings, schedule rows,
and player scoreboards.

This cache complements the request-local cache in `lib/cache.functions.php`:

| | Request-local cache | Persistent cache |
|---|---|---|
| Lifetime | Single PHP request | Configurable TTL (seconds) |
| Storage | `$GLOBALS['runtime_cache']` | Files under `PERSISTENT_CACHE_DIR` |
| Use case | Deduplicate repeated helper calls on one page | Offload DB CPU across many concurrent requests |

## Configuration

**SYSTEM_FLAG** (`conf/config.inc.php`):

```php
// Directory for persistent cache files. Must be writable by the web server.
// Set to '' to disable filesystem caching (the admin toggle still applies).
define('PERSISTENT_CACHE_DIR', '/tmp/ultiorganizer-cache');
```

**INSTALLATION_SETTING** (editable in `admin/serverconf.php` > Internal settings):

| Name | Default | Description |
|---|---|---|
| `PersistentCacheEnabled` | `true` | Master on/off switch. Turn off to bypass caching without a code deploy. |
| `PersistentCacheTtlSeconds` | `30` | Default TTL in seconds. Call sites may override with an explicit positive value. |

## Public API

```php
// Return cached value or compute and store it.
CacheRememberFor(string $namespace, mixed $key, int $ttlSeconds, callable $resolver): mixed

// Delete one cached entry. Pass $key = null to clear the whole namespace.
CacheForgetPersistent(string $namespace, mixed $key = null): void

// Remove all cache files. Returns file count removed.
CacheWipePersistent(): int
```

### Example

```php
require_once __DIR__ . '/../lib/persistent-cache.functions.php';

function TimetableGames($seasonId, $seriesId) {
    return CacheRememberFor(
        'timetable_games',
        [$seasonId, $seriesId],
        0,              // 0 = use PersistentCacheTtlSeconds setting
        function () use ($seasonId, $seriesId) {
            // ... original DB query ...
        }
    );
}
```

### Invalidation

Call `CacheForgetPersistent($namespace, $key)` from mutation helpers after writes,
so stale data is not served within the TTL window:

```php
function SaveGameResult($gameId, ...) {
    // ... write to DB ...
    CacheForgetPersistent('timetable_games', [$seasonId, $seriesId]);
}
```

## Cache key derivation

Keys are built with `CacheRuntimeKey($namespace, $key)` (from
`lib/cache.functions.php`), then hashed to a safe filename:

```
{sanitised_namespace}_{md5(namespace:keytext)}.cache
```

The namespace prefix enables glob-based namespace-wide invalidation.

## Stampede control

On cache miss or expiry, the helper tries to acquire a non-blocking exclusive
lock (`flock LOCK_EX|LOCK_NB`) on a sibling `.lock` file. The winner recomputes
and writes; competing workers return the stale payload if available, or fall
through to the resolver if no stale file exists.

## Serialisation

Values are serialised with PHP's native `serialize()` / `unserialize()`.
`unserialize` is called with `allowed_classes: false` to prevent PHP object
injection. igbinary is not used in this version.

## Write safety

Writes go to a `*.tmp.<pid>` file first, then `rename()` atomically replaces the
destination, so readers never see partial data. If `rename()` fails the temporary
file is removed.
