# Persistent Cache

Cross-request, time-limited file cache for read-only database results. The cache
is wired into the four read helpers in `lib/database.php` — every `SELECT` that
flows through `DBQueryToValue`, `DBQueryToRow`, `DBQueryToArray`, or
`DBQueryRowCount` is automatically cached during GET requests with no per-call
changes required.

Invalidation is **TTL-only**: the configured TTL (default 5 seconds) bounds how
stale any cached read can become. Mutation helpers do not call any explicit
invalidation function.

This cache complements the request-local cache in `lib/cache.functions.php`:

| | Request-local cache | Persistent cache |
|---|---|---|
| Lifetime | Single PHP request | Configurable TTL (default 5 s) |
| Storage | `$GLOBALS['runtime_cache']` | Files under `PERSISTENT_CACHE_DIR` |
| Use case | Deduplicate repeated helper calls on one page | Offload DB CPU across many concurrent GET requests |

## When the cache is consulted

`DBQueryCacheable($query)` returns true only when **all** of the following hold:

- `PersistentCacheEnabled` is on.
- `$_SERVER['REQUEST_METHOD']` is `GET`. POST/PUT/DELETE pages always bypass the
  cache so they never see stale reads after their own writes.
- The statement starts with `SELECT` (defensive — non-SELECT statements should
  not be routed through the read helpers anyway).

CLI scripts and background jobs have no `REQUEST_METHOD` and therefore bypass
the cache.

## Request-scoped bypass

A request can opt out of the persistent cache entirely by calling
`DisablePersistentCacheForRequest()` once at startup, before the first cacheable
read. `DBQueryCacheable()` then returns false for every read in that request, so
all SELECTs go straight to the database.

**All login-gated surfaces bypass the cache.** `lib/auth.guard.php` calls
`DisablePersistentCacheForRequest()` before it does anything else, so every page
behind that guard reads live: `admin/`, `user/`, `mobile/`, `scorekeeper/`,
`spiritkeeper/`, `result.php`, and the `cust/*` member pages. The guard requires
`lib/persistent-cache.functions.php` directly because a few callers include it
before `lib/database.php`.

The reason is the Post/Redirect/Get pattern these pages use: an editor submits a
form (POST, writes the DB), then the browser follows a redirect to a GET that
re-renders the same screen. The POST itself is never cached, but that following
GET can hit a still-valid cache entry populated by the *previous* GET on the same
screen (within the TTL window) and show pre-write state — prompting the editor to
re-enter the change. Editing traffic is negligible in volume, so nothing is lost
by reading live.

`scorekeeper/index.php` and `spiritkeeper/index.php` additionally call the bypass
right after `OpenConnection()`. Keep those calls: they fire before their auth
include and so also cover each app's own bootstrap reads.

Public spectator pages do not include the auth guard and keep the cache — that is
the traffic it was built to offload.

Query with `IsPersistentCacheBypassed()`.

## Configuration

**SYSTEM_FLAG** (`conf/config.inc.php`):

```php
// Directory for persistent cache files. Must be writable by the web server.
define('PERSISTENT_CACHE_DIR', '/tmp/ultiorganizer-cache');
```

If `PERSISTENT_CACHE_DIR` is undefined, the helper falls back to
`sys_get_temp_dir() . '/ultiorganizer-cache'` so upgraded installs that have
not edited `conf/config.inc.php` still benefit from caching. Set it to an
empty string (`define('PERSISTENT_CACHE_DIR', '')`) to disable the filesystem
cache explicitly. If the resolved directory cannot be created or written to,
the helper falls back to running the resolver uncached.

Files live in a per-install subdirectory named after
`md5(DB_HOST|DB_DATABASE|DB_USER)` so multiple Ultiorganizer deployments that
share the same `PERSISTENT_CACHE_DIR` do not collide on identical SELECT
strings — even when two installs use the same schema name on different MySQL
hosts (e.g. prod and staging both named `ultiorganizer`).

**INSTALLATION_SETTING** (editable in `admin/serverconf.php` > Internal settings):

| Name | Default | Description |
|---|---|---|
| `PersistentCacheEnabled` | `true` | Master on/off switch. Turn off to bypass caching without a code deploy. |
| `PersistentCacheTtlSeconds` | `5` | Default TTL in seconds. Short enough that mutations show up quickly without explicit invalidation. |

## Direct API

The helper functions remain available for code that needs explicit TTL control
or namespace-wide invalidation, but the database-layer caching above is
sufficient for the live-scoring read paths and should be preferred.

```php
// Return cached value or compute and store it.
CacheRememberFor(
    string $namespace,
    mixed $key,
    int $ttlSeconds,
    callable $resolver,
    ?callable $shouldStore = null,
): mixed

// Delete one cached entry. Pass $key = null to clear the whole namespace.
CacheForgetPersistent(string $namespace, mixed $key = null): void

// Remove all cache files. Returns file count removed.
CacheWipePersistent(): int

// Disable the persistent cache for the rest of this request (live-entry apps).
DisablePersistentCacheForRequest(): void

// Whether the persistent cache has been bypassed for this request.
IsPersistentCacheBypassed(): bool
```

## Trade-offs

- **Pro:** zero per-helper wiring; new SELECT helpers benefit automatically.
- **Pro:** writes during POST flows bypass the cache, so the typical
  read → write → render cycle in admin/scorekeeping pages is unaffected.
- **Con:** within a single GET request, a `read → write → read` pattern on the
  same row could return the cached pre-write value. This is rare in GET handlers
  and bounded to the TTL window in any case.
- **Con:** every distinct SELECT whose result is admitted becomes a cache file.
  Under heavy use the cache directory grows; clean it periodically with
  `CacheWipePersistent()` or filesystem TTL tooling.

## Cache admission

The database wrappers that return row data pass a cache-admission callback to
`CacheRememberFor()`. If one of these queries returns no rows, neither a cache
file nor a lock file is retained. The value that represents no rows depends on
the wrapper:

| Wrapper | No-row value |
| --- | --- |
| `DBQueryToValue()` | `null` |
| `DBQueryToRow()` | `null` or `false` |
| `DBQueryToArray()` | `[]` |

The distinction is based on result rows rather than PHP truthiness. For
example, a scalar `0`, `"0"`, or empty string returned by an existing row
remains cacheable. `DBQueryRowCount()` does not use this admission rule: it
caches the integer `0` as a valid row-count result. For the three row-data
wrappers, if a previously populated cache entry expires and the same query then
returns no rows, the stale cache file is removed.

## Cache key derivation

Keys are built with `CacheRuntimeKey($namespace, $key)` (from
`lib/cache.functions.php`), then hashed to a safe filename:

```
{sanitised_namespace}_{md5(namespace:keytext)}.cache
```

The database layer uses namespaces `db_query_value`, `db_query_row`,
`db_query_array`, and `db_query_rowcount`, with the SQL string and casting flag
as the key components.

## Stampede control

On cache miss or expiry, the helper tries to acquire a non-blocking exclusive
lock (`flock LOCK_EX|LOCK_NB`) on a sibling `.lock` file. The winner recomputes
and writes; competing workers return the stale payload if available, or fall
through to the resolver if no stale file exists.

## Serialisation

Values are serialised with PHP's native `serialize()` / `unserialize()`.
`unserialize` is called with `allowed_classes: false` to prevent PHP object
injection. The four read helpers all return scalars or arrays of scalars, so
serialisation is safe.

## Write safety

Writes go to a `*.tmp.<pid>` file first, then `rename()` atomically replaces the
destination, so readers never see partial data. If `rename()` fails the
temporary file is removed.
