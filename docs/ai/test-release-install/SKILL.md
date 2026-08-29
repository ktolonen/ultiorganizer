---
name: test-release-install
description: Test an Ultiorganizer release package end to end by building it, extracting it, and installing it with install.php in an isolated Docker stack. Use when asked to test release packaging, verify a fresh installation, check that install.php still works, or confirm that a new file or directory actually reaches a released install. The developer runs the wizard by hand; this skill prepares the environment, hands it over, and verifies the installed app afterwards.
metadata:
  short-description: Build, extract and install a release package in a throwaway Docker stack
---

# Test Release Install

Exercises the path a real administrator takes: `docs/release/build-release.sh` → unzip → `install.php` → running site. The web root is the **extracted package**, not the git checkout, so a file missing from the archive fails for real instead of being silently served from the repo.

Complements `docs/ai/release-package-coverage/SKILL.md`, which checks packaging *classification* statically. This skill checks that the package actually installs and runs.

## Prerequisites

Docker, `zip`/`unzip`, and a git working tree you are willing to package. The package is built from `HEAD`; uncommitted changes are not included, and the build script warns when the branch is not `master`.

## Workflow

```sh
docs/ai/test-release-install/scripts/release-test.sh setup
```

`setup` builds an install package, extracts it to `../ultiorganizer-release-test/` (a sibling of the checkout, not inside the repo), generates a compose file, starts an isolated stack on **port 8081**, and verifies step 1 of the wizard. It prints the values the developer needs and stops.

**Then hand over and wait.** Ask the developer to open `http://localhost:8081/install.php` and click through the wizard.

**Do not drive the wizard yourself.** Posting the forms writes `conf/config.inc.php`, and step 1 then refuses to start again ("file already exists"), so a curl walkthrough burns the environment and forces a `reset`. The point of this skill is a human seeing the wizard.

When they report the install is finished:

```sh
docs/ai/test-release-install/scripts/release-test.sh smoke
```

`smoke` removes `install.php`, prints the written configuration, and loads the public pages, the standalone apps and the API, reporting status codes and any PHP diagnostics. Report the table; then ask the developer to log in as the admin account they created and click through a few admin pages, which is the part no script covers.

## Subcommands

| Command | Effect |
| --- | --- |
| `setup` | Build, extract, start the stack, verify step 1 prerequisites |
| `reset` | Re-extract the package and drop the database — back to pristine, uninstalled |
| `smoke` | Post-install: remove `install.php`, check pages and logs |
| `teardown` | Stop the stack, delete its volume and the whole test directory |

Overrides: `UO_TEST_ROOT`, `UO_TEST_PROJECT`, `UO_TEST_PORT`, `UO_TEST_ARCHIVE` (skip the build and use an existing package).

The stack runs under its own compose project with its own database volume. It never touches `docs/dev/compose.yaml` or its data — but for that reason, never run `down -v` against the dev stack while cleaning up this one.

## Values the wizard must not be left at their defaults

| Step | Field | Value |
| --- | --- | --- |
| 2 — Database connection | Host address | `db` — **not** `localhost`, which is the app container itself |
| 2 | User / password / database | `ultiorganizer` / `ultiorganizer` / `ultiorganizer` |
| 4 — Site settings | Base URL | `http://localhost:8081` — **not** `http://localhost/ultiorganizer`, or the post-install redirect breaks |

## What the results mean

- **Step 6 should be all green.** The wizard hardens `conf/` to `0555` and `conf/config.inc.php` to `0444` itself, and the test environment gives `www-data` ownership of both, so the chmod succeeds. A red row there is a real finding, not an artifact of the setup.
- **`Security warning: remove install.php from the server.`** is `index.php`'s post-install guard, not a failure. `smoke` clears it. It has to be removed from the host side: the package root is owned by the host user, so the Apache user cannot delete it.
- **302 on `/login/`, `/scorekeeper/`, `/mobile/`** is a login redirect. **404 on `/api/`** is the API router answering `{"status":"error",...,"Endpoint not found."}` — check the body, not just the code.
- A **missing file** shows up as a 404 on an asset or a PHP `require` fatal on a real page, which is why `smoke` loads pages instead of only diffing the archive listing.

## Common mistakes

| Mistake | What happens |
| --- | --- |
| Entering `localhost` as the database host | Step 2 fails to connect; `localhost` is the app container |
| `chown`ing the package with `docker compose exec` | Deleting the tree later fails, and a workdir pulled out from under the container makes `exec` itself fail with "possible container breakout detected". The script uses a throwaway `docker run` instead |
| Deleting the test tree as the host user after an install | `conf/` is `0555` and owned by `www-data`; `teardown` chowns and chmods first |
| Expecting a `mysql` client in the app container | There is none, and none is needed: `install.php` imports `sql/ultiorganizer.sql` through `mysqli` in PHP |
| Rebuilding at a new commit into a used test root | The package directory name carries the commit hash; `setup` cleans the previous one before extracting |
