# Runtime Cache Guidance

Use `lib/cache.functions.php` for request-local caching of repeated deterministic helper lookups.

This cache is intentionally small in scope:

- It lives only for the current PHP request.
- It should be used only when the same helper can repeat the same database lookup several times during one request (= one page load).
- It should not be used as a cross-request live scoring cache.

Example: use runtime caching when one routed page calls `SeasonInfo($seasonId)` early for event metadata and later calls helpers such as `SeasonName($seasonId)`, `Seasontype($seasonId)`, or `IsSeasonInMaintenance($seasonId)` for the same event. The first call can load the row, and later helpers can reuse it during that same page load.

When a cached helper reads data that can be changed in the same request, clear the relevant namespace from the mutation helper after the write succeeds.

## Recapture Database Logs

Use the local MariaDB table logs to compare query counts before and after cache changes.

Start a fresh capture:

```sh
docker compose -f docs/dev/compose.yaml exec -T db sh -lc \
'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "
SET GLOBAL log_output = '\''TABLE'\'';
SET GLOBAL general_log = OFF;
SET GLOBAL slow_query_log = OFF;
TRUNCATE TABLE mysql.general_log;
TRUNCATE TABLE mysql.slow_log;
SET GLOBAL long_query_time = 0;
SET GLOBAL general_log = ON;
SET GLOBAL slow_query_log = ON;
"'
```

Run the same crawl or page flow, then inspect query frequency:

```sh
docker compose -f docs/dev/compose.yaml exec -T db sh -lc \
'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "
SELECT COUNT(*) AS hits, argument
FROM mysql.general_log
WHERE command_type = '\''Query'\''
GROUP BY argument
ORDER BY hits DESC
LIMIT 80;
"'
```

Inspect timing:

```sh
docker compose -f docs/dev/compose.yaml exec -T db sh -lc \
'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "
SELECT
  COUNT(*) AS hits,
  SUM(TIME_TO_SEC(query_time)) AS total_sec,
  AVG(TIME_TO_SEC(query_time)) * 1000 AS avg_ms,
  MAX(TIME_TO_SEC(query_time)) * 1000 AS max_ms,
  rows_examined,
  sql_text
FROM mysql.slow_log
WHERE sql_text NOT LIKE '\''%mysql.general_log%'\''
  AND sql_text NOT LIKE '\''%mysql.slow_log%'\''
GROUP BY sql_text, rows_examined
ORDER BY total_sec DESC
LIMIT 50;
"'
```

Turn logging off after the capture:

```sh
docker compose -f docs/dev/compose.yaml exec -T db sh -lc \
'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "
SET GLOBAL general_log = OFF;
SET GLOBAL slow_query_log = OFF;
"'
```
