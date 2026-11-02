<?php

require_once __DIR__ . '/include_only.guard.php';
denyDirectLibAccess(__FILE__);

require_once __DIR__ . '/user.functions.php';

function ApiHashToken($token)
{
    return hash('sha256', (string) $token);
}

function ApiTokenLookup($token)
{
    $tokenHash = ApiHashToken($token);
    $query = sprintf(
        "SELECT token_id, token_hash, scope_type, scope_id, revoked
     FROM uo_api_token
     WHERE token_hash='%s'
     LIMIT 1",
        DBEscapeString($tokenHash),
    );

    // Must bypass the persistent cache: token revocation needs to take effect
    // immediately. A cached row would let a revoked token authenticate for up
    // to the TTL window after revocation.
    $row = DBQueryToRowUncached($query, true);
    if (empty($row) || !empty($row['revoked'])) {
        return null;
    }

    return $row;
}

function ApiTokenTouch($tokenId)
{
    $query = sprintf(
        "UPDATE uo_api_token SET last_used=NOW() WHERE token_id=%d",
        (int) $tokenId,
    );
    DBQuery($query);
}

function ApiRateLimitCheck($rateKey, $limit, $windowSeconds)
{
    $now = time();
    $windowStart = $now - ($now % $windowSeconds);

    $bump = function ($key) use ($windowStart) {
        DBQuery(sprintf(
            "INSERT INTO uo_api_rate_limit (rate_key, window_start, request_count)
            VALUES ('%s', %d, LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE
                request_count=LAST_INSERT_ID(IF(window_start=VALUES(window_start), request_count + 1, 1)),
                window_start=VALUES(window_start)",
            DBEscapeString($key),
            (int) $windowStart,
        ));
        return (int) DBQueryToValueUncached("SELECT LAST_INSERT_ID()", true);
    };

    $count = $bump($rateKey);

    // The sentinel row lets exactly one request per window prune past windows.
    if ($count === 1 && $bump('prune') === 1) {
        DBQuery(sprintf(
            "DELETE FROM uo_api_rate_limit WHERE window_start < %d",
            (int) $windowStart,
        ));
    }

    return [
        'allowed' => ($count <= $limit),
        'remaining' => max(0, $limit - $count),
        'reset' => $windowStart + $windowSeconds,
    ];
}

function ApiTokenList()
{
    $query = "SELECT token_id, token_value, label, scope_type, scope_id, revoked, created_at, last_used
    FROM uo_api_token
    ORDER BY created_at DESC, token_id DESC";
    return DBQueryToArray($query);
}

function ApiTokenCreate($label, $scopeType, $scopeId)
{
    $token = bin2hex(random_bytes(24));
    $tokenHash = ApiHashToken($token);
    $query = sprintf(
        "INSERT INTO uo_api_token (token_hash, token_value, label, scope_type, scope_id)
     VALUES ('%s', '%s', '%s', '%s', %s)",
        DBEscapeString($tokenHash),
        DBEscapeString($token),
        DBEscapeString($label),
        DBEscapeString($scopeType),
        ($scopeId === '' || $scopeId === null) ? "NULL" : "'" . DBEscapeString($scopeId) . "'",
    );
    $tokenId = DBQueryInsert($query);

    return [
        'token_id' => $tokenId,
        'token' => $token,
    ];
}

function ApiTokenSetRevoked($tokenId, $revoked)
{
    $query = sprintf(
        "UPDATE uo_api_token SET revoked=%d WHERE token_id=%d",
        (int) $revoked,
        (int) $tokenId,
    );
    DBQuery($query);
}

function ApiTokenDelete($tokenId)
{
    // As in UserApiTokensDelete(): once the token is gone, nothing ties its
    // receipts' User-Agent to the user any more.
    DBQuery(sprintf(
        "UPDATE uo_api_receipt r JOIN uo_api_token t ON (t.token_id=r.api_token)
			SET r.user_agent=NULL WHERE t.token_id=%d AND t.scope_type='user'",
        (int) $tokenId,
    ));
    $query = sprintf(
        "DELETE FROM uo_api_token WHERE token_id=%d",
        (int) $tokenId,
    );
    DBQuery($query);
}

/**
 * The logged-in user's own active user-scoped token as ['token_id' => int,
 * 'token' => string, 'last_used' => ?string], or null when there is none or
 * for anyone but that user.
 */
function ApiUserToken($userId)
{
    if (!isLoggedIn() || (string) $userId !== (string) $_SESSION['uid']) {
        return null;
    }
    $row = DBQueryToRowUncached(sprintf(
        "SELECT token_id, token_value, last_used FROM uo_api_token
        WHERE scope_type='user' AND scope_id='%s' AND revoked=0
        ORDER BY created_at DESC, token_id DESC
        LIMIT 1",
        DBEscapeString((string) $userId),
    ), true);
    if (empty($row)) {
        return null;
    }
    return ['token_id' => (int) $row['token_id'], 'token' => $row['token_value'], 'last_used' => $row['last_used']];
}

/**
 * Revokes the logged-in user's active user-scoped tokens. The rows stay so
 * admins and history rows can still name them. Returns false for anyone but
 * that user.
 */
function ApiUserTokenRevoke($userId)
{
    if (!isLoggedIn() || (string) $userId !== (string) $_SESSION['uid']) {
        return false;
    }
    DBQuery(sprintf(
        "UPDATE uo_api_token SET revoked=1 WHERE scope_type='user' AND scope_id='%s' AND revoked=0",
        DBEscapeString((string) $userId),
    ));
    return true;
}

/**
 * Revokes the logged-in user's active user-scoped tokens and creates a new
 * one, so a lost device stops working. Returns ['token_id' => int,
 * 'token' => string], or null for anyone but that user.
 */
function ApiUserTokenReplace($userId)
{
    if (!ApiUserTokenRevoke($userId)) {
        return null;
    }
    return ApiTokenCreate("Scorekeeping app", 'user', (string) $userId);
}
