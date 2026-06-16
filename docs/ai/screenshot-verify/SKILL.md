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

## Measuring element dimensions

For layout debugging — "does `.page` actually grow?", "is `.page_top` the same width as the content?" — use the bundled CDP script instead of guessing from screenshots.

```bash
docker compose -f docs/dev/compose.yaml exec -T dev \
  node /workspace/.claude/skills/screenshot-verify/scripts/measure.js \
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
