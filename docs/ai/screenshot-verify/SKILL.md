---
name: screenshot-verify
description: Verify Ultiorganizer UI and CSS changes by taking screenshots and measuring element layout using Chromium inside the dev Docker container. Use whenever a visual or layout change needs confirmation — responsive CSS, header alignment, table widths, mobile vs desktop rendering. Don't skip this just because the code looks right; run it to get actual pixel evidence.
---

# Screenshot Verify

Takes screenshots and measures element dimensions using Chromium inside the `dev` container. The app is reachable at `http://app/` within the container network.

## Prerequisites

Dev container must be running:
```bash
docker compose -f docs/dev/compose.yaml --profile devtools up -d dev
```

Screenshots are saved to `/workspace/` (the repo mount) so they are readable from the host via the Read tool.

## Taking screenshots

Always run Chromium inside the container — the host snap build cannot write to `/tmp/` and the host has no access to `http://app/`.

Use `http://host.docker.internal:8080/` as the base URL — Chromium's DNS stack inside the container cannot resolve Docker service names like `app`, but `host.docker.internal` (mapped to the host gateway in compose.yaml) is stable across restarts.

```bash
docker compose -f docs/dev/compose.yaml exec -T dev \
  chromium --headless=new --no-sandbox --disable-gpu \
  --screenshot=/workspace/<output>.png \
  --window-size=<WIDTH>,<HEIGHT> \
  "http://host.docker.internal:8080/?view=<view>"
```

Read the resulting file with the Read tool to view it.

### Standard viewport sizes

| Scenario | `--window-size` |
|---|---|
| Desktop | `1400,900` |
| Tablet | `768,1024` |
| Mobile | `400,900` |

Always test both desktop and mobile when the change touches responsive CSS.

### Finding page URLs

```bash
curl -s "http://localhost:8080/?view=frontpage" | grep -o "?view=[^\"'< ]*" | head -20
```

Common views (use `http://host.docker.internal:8080/` as base):

| View | URL path |
|---|---|
| Front page | `?view=frontpage` |
| Games / schedule | `?view=games&season=<id>&filter=tournaments&group=all` |
| Pool status | `?view=poolstatus&pool=<id>` |

## Authenticated pages

Everything under `admin/`, `user/`, `scorekeeper/` and `spiritkeeper/` redirects
to a login form, so the recipe above only reaches public pages. Dev passwords are
hashed and are not needed: build a throwaway session through the app's own login
path instead, and hand its id to Chromium as a cookie.

Never put a password, a real session id, or a userid into a script you keep, and
never commit one. Everything below is resolved at run time.

Pick a user that already holds the rights the page requires:

```sh
UO_DB=$(sed -n "s/.*define('DB_DATABASE', *'\([^']*\)').*/\1/p" conf/config.inc.php)
UO_UID=$(docker compose -f docs/dev/compose.yaml exec -T -e UO_DB="$UO_DB" db sh -lc \
  'MYSQL_PWD="$MYSQL_PASSWORD" mariadb -u"$MYSQL_USER" "$UO_DB" -N -e \
   "SELECT userid FROM uo_userproperties WHERE name=\"userrole\" AND value=\"superadmin\" LIMIT 1;"')
```

`SetUserSessionData()` is the same function the login form calls, so the session
gets the right shape without reimplementing it. Run it **as `www-data`** — root
cannot write the session file — and let it print the cookie name:

```sh
UO_SID=$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')   # 32 hex chars
docker compose -f docs/dev/compose.yaml exec -T -e UO_SID="$UO_SID" -e UO_UID="$UO_UID" app sh -lc '
cat > /tmp/uo-session.php <<"PHP"
<?php
session_id(getenv("UO_SID"));
session_start();
chdir("/var/www/html");
$include_prefix = "/var/www/html/";
include_once "conf/config.inc.php";
include_once "lib/database.php";
OpenConnection();
include_once "lib/user.functions.php";
SetUserSessionData(getenv("UO_UID"));
session_write_close();
echo sessionCookieName(), "\n";
PHP
chmod 644 /tmp/uo-session.php
su -s /bin/sh www-data -c "UO_SID=$UO_SID UO_UID=$UO_UID php /tmp/uo-session.php"'
```

Then screenshot with a CDP script that calls `Network.setCookie` with that name
and `UO_SID` before `Page.navigate` (`ws` resolves from the container's
`NODE_PATH=/opt/eslint/node_modules`, so the script can live in `/tmp` rather
than in the repo). Delete `/tmp/uo-session.php` and `/tmp/sess_$UO_SID` in the
`app` container when finished.

Points worth knowing before debugging a blank result:

- The session id must be **32 hex characters** (`session.sid_length=32`,
  `sid_bits_per_character=4`). Anything else is rejected and the app falls back
  to `anonymous`, which looks exactly like a broken cookie.
- The cookie name is derived per installation by `sessionCookieName()`, so read
  it as above rather than assuming; it is not `PHPSESSID`.
- `include conf/config.inc.php` and `lib/database.php` do not connect on their
  own. Call `OpenConnection()` or the first query fails.
- Some helpers re-read rights from the database rather than the session
  (`SeasonTeamAdmins()` calls `getEditSeasons()`), so a session whose injected
  roles disagree with the stored ones dies with "Insufficient rights". Pick a
  user who genuinely holds the rights instead of editing roles into the session.
- Screenshots of a real dataset contain personal data. Keep them out of the
  repo, per the docs-tone rule in AGENTS.md.

## Measuring element dimensions

For layout debugging — "does `.page` actually grow?", "is `.page_top` the same width as the content?" — use the bundled CDP script instead of guessing from screenshots.

```bash
docker compose -f docs/dev/compose.yaml exec -T dev \
  node /workspace/docs/ai/screenshot-verify/scripts/measure.js \
  "http://host.docker.internal:8080/?view=<view>" <WIDTH> <HEIGHT> \
  ".page,.page_top,.games-table"
```

Output is JSON with `viewport`, `bodyScrollWidth`, and per-selector `{ width, height, scrollWidth }`. A selector that matches nothing returns `null`.

## Workflow

1. Identify the CSS class or element the change affects.
2. Find a URL that exercises it (schedule tables for overflow, forms for alignment, mobile menu for responsive layout).
3. Take screenshots at desktop + mobile viewports in parallel.
4. Read both screenshots with the Read tool.
5. If a dimension needs confirming, run the measure script.
6. Report what you saw — include the pixel values when they are the evidence.

## Key gotchas

- `--no-sandbox` is required inside Docker.
- Use `http://host.docker.internal:8080/` not `http://app/` — Chromium's DNS stack inside the container cannot resolve Docker Compose service names; it gets a `chrome-error` page instead.
- Start Chromium directly on the target URL. If you start on `about:blank` and navigate, `--window-size` is ignored and `window.innerWidth` reports 0.
- `min-width: max-content` on a container that has `width: 100%` children causes those percentage widths to resolve to a huge value during max-content layout — the page can become tens of thousands of pixels wide. The measure script will catch this where a screenshot won't.
