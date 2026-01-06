<?php

function ApiHashToken($token)
{
  return hash('sha256', (string)$token);
}

function ApiTokenLookup($token)
{
  $tokenHash = ApiHashToken($token);
  $query = sprintf(
    "SELECT token_id, token_hash, scope_type, scope_id, revoked
     FROM uo_api_token
     WHERE token_hash='%s'
     LIMIT 1",
    DBEscapeString($tokenHash)
  );

  $row = DBQueryToRow($query, true);
  if (empty($row) || !empty($row['revoked'])) {
    return null;
  }

  return $row;
}

function ApiTokenTouch($tokenId)
{
  $query = sprintf(
    "UPDATE uo_api_token SET last_used=NOW() WHERE token_id=%d",
    (int)$tokenId
  );
  DBQuery($query);
}

function ApiRateLimitCheck($rateKey, $limit, $windowSeconds)
{
  $now = time();
  $windowStart = $now - ($now % $windowSeconds);

  $query = sprintf(
    "SELECT window_start, request_count
     FROM uo_api_rate_limit
     WHERE rate_key='%s'
     LIMIT 1",
    DBEscapeString($rateKey)
  );
  $row = DBQueryToRow($query);

  if (empty($row)) {
    $insert = sprintf(
      "INSERT INTO uo_api_rate_limit (rate_key, window_start, request_count)
       VALUES ('%s', %d, 1)",
      DBEscapeString($rateKey),
      (int)$windowStart
    );
    DBQuery($insert);
    return array(
      'allowed' => true,
      'remaining' => max(0, $limit - 1),
      'reset' => $windowStart + $windowSeconds
    );
  }

  $storedWindow = (int)$row['window_start'];
  $count = (int)$row['request_count'];

  if ($storedWindow !== (int)$windowStart) {
    $update = sprintf(
      "UPDATE uo_api_rate_limit
       SET window_start=%d, request_count=1
       WHERE rate_key='%s'",
      (int)$windowStart,
      DBEscapeString($rateKey)
    );
    DBQuery($update);
    return array(
      'allowed' => true,
      'remaining' => max(0, $limit - 1),
      'reset' => $windowStart + $windowSeconds
    );
  }

  $count++;
  $update = sprintf(
    "UPDATE uo_api_rate_limit
     SET request_count=%d
     WHERE rate_key='%s'",
    (int)$count,
    DBEscapeString($rateKey)
  );
  DBQuery($update);

  return array(
    'allowed' => ($count <= $limit),
    'remaining' => max(0, $limit - $count),
    'reset' => $windowStart + $windowSeconds
  );
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
    ($scopeId === '' || $scopeId === null) ? "NULL" : "'" . DBEscapeString($scopeId) . "'"
  );
  $tokenId = DBQueryInsert($query);

  return array(
    'token_id' => $tokenId,
    'token' => $token
  );
}

function ApiTokenSetRevoked($tokenId, $revoked)
{
  $query = sprintf(
    "UPDATE uo_api_token SET revoked=%d WHERE token_id=%d",
    (int)$revoked,
    (int)$tokenId
  );
  DBQuery($query);
}

function ApiTokenDelete($tokenId)
{
  $query = sprintf(
    "DELETE FROM uo_api_token WHERE token_id=%d",
    (int)$tokenId
  );
  DBQuery($query);
}
