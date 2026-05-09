---
name: format-and-lint
description: Run PHP formatter and static analyser on Ultiorganizer changes, then fix or report findings. Use after editing PHP files to apply PER-CS 2.0 formatting via PHP-CS-Fixer and to surface PHPStan findings against the project baseline. Apply auto-fixable formatting; for static-analysis findings, fix when the cause is obvious in the changed code, otherwise report and leave the baseline untouched.
metadata:
  short-description: Format and lint changed PHP, fix or report findings
---

# Format and lint PHP changes

Run the project formatter and static analyser on changed PHP files, then either fix the findings or report them. Use this skill after implementing or editing PHP code, before handing back to the user.

Always read these references first:

- `docs/code-style.md`
- `composer.json`
- `.php-cs-fixer.dist.php`
- `phpstan.neon.dist`

## Scope

Limit the run to PHP files the user has modified in the current worktree. Use:

- `git status --short`
- `git diff --name-only`
- `git diff --cached --name-only`

Filter to `*.php` and skip the excluded directories listed in `docs/code-style.md` (`vendor/`, `conf/`, `live/`, `images/`, `locale/`, `script/`, `lib/tfpdf/`, `lib/yuiloader/`, `lib/phpqrcode/`, `lib/feed_generator/`, `lib/hsvclass/`).

If no PHP files changed, report `no PHP changes` and stop.

## Tool resolution

Prefer the local Composer install. If `vendor/bin/php-cs-fixer` and `vendor/bin/phpstan` are missing, fall back to the dev container:

```sh
docker compose -f docs/dev/compose.yaml exec -T dev vendor/bin/php-cs-fixer ...
docker compose -f docs/dev/compose.yaml exec -T dev vendor/bin/phpstan ...
```

If the `dev` service is not running but `app` is, run inside `app` instead. If neither container is available, ask the user to install dev dependencies (`composer install`) or to start the `dev` workspace (`docs/local-development.md`).

## Formatting step (PHP-CS-Fixer)

1. Run the formatter on the changed files only, in fix mode:

   ```sh
   vendor/bin/php-cs-fixer fix -- <changed files>
   ```

2. Stage the rewritten files for the user. Do not commit. The user controls commit timing.

3. If the formatter rewrites unrelated whitespace far outside the user's intended change, flag it before staging so the user can decide whether to keep the cleanup or scope it down.

## Static-analysis step (PHPStan)

1. Run the analyser on the changed files only:

   ```sh
   vendor/bin/phpstan analyse --no-progress --memory-limit=1G -- <changed files>
   ```

2. Apply fixes when the cause is in the user's change and the fix is mechanical (missing return type, wrong argument type, undefined variable, unused import). Re-run the analyser to confirm.

3. Do not regenerate the baseline (`composer lint:baseline`) to silence findings. The baseline only changes when the user explicitly asks for it.

4. If a finding is in pre-existing code that the user touched only incidentally, report it as a warning rather than fixing it.

## Output

Group results under:

- `Fixed`: formatting rewrites and static-analysis findings the skill resolved.
- `Errors`: static-analysis findings that block clean output and were not fixed.
- `Warnings`: pre-existing findings adjacent to the change, plus any wide-reaching formatter rewrites the user should review.

Each finding should include:

- file path
- line reference when available
- the rule or message from the tool
- the fix applied, or the recommended fix if not applied

Keep the report concise. Do not duplicate raw tool output verbatim; summarise.

## Out of scope

- Running the full-repo big-bang reformat. That is a one-time operation tracked in `.git-blame-ignore-revs`.
- Editing the PHPStan baseline.
- Adjusting `.php-cs-fixer.dist.php` or `phpstan.neon.dist` rule sets. If a rule is wrong for the project, report it as a warning instead of changing the config.
