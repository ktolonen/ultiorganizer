<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

/**
 * Build a stable request-local cache key.
 *
 * @param string $namespace Logical cache namespace such as "season_info"
 * @param mixed $key Namespace-specific cache key
 * @return string
 */
function CacheRuntimeKey($namespace, $key)
{
    if (is_scalar($key) || $key === null) {
        $keyText = (string) $key;
    } else {
        $keyText = md5(serialize($key));
    }

    return (string) $namespace . ':' . $keyText;
}

/**
 * Return a request-local cached value, computing it when missing.
 *
 * This cache lives only for the current PHP request. Use it for repeated,
 * deterministic helper lookups, not as a cross-request live scoring cache.
 *
 * @param string $namespace Logical cache namespace
 * @param mixed $key Namespace-specific cache key
 * @param callable $resolver Function that returns the value on cache miss
 * @return mixed
 */
function CacheRemember($namespace, $key, $resolver)
{
    $cacheKey = CacheRuntimeKey($namespace, $key);
    if (array_key_exists($cacheKey, $GLOBALS['runtime_cache'] ?? [])) {
        return $GLOBALS['runtime_cache'][$cacheKey];
    }

    $GLOBALS['runtime_cache'][$cacheKey] = $resolver();
    return $GLOBALS['runtime_cache'][$cacheKey];
}

/**
 * Clear all request-local cached values for a namespace.
 *
 * @param string $namespace Logical cache namespace
 * @return void
 */
function CacheForgetNamespace($namespace)
{
    $prefix = (string) $namespace . ':';
    foreach (array_keys($GLOBALS['runtime_cache'] ?? []) as $cacheKey) {
        if (str_starts_with((string) $cacheKey, $prefix)) {
            unset($GLOBALS['runtime_cache'][$cacheKey]);
        }
    }
}
