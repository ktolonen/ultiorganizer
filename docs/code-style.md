# Code style

Ultiorganizer follows the [PER Coding Style 2.0](https://www.php-fig.org/per/coding-style/) (PER-CS 2.0), the modern PHP-FIG standard that supersedes PSR-12 and folds in PHP 8 features. PER-CS 2.0 inherits everything PSR-12 specifies and adds rules for enum syntax, readonly properties, and trailing commas in parameter lists.

This document summarizes the rules that apply to this repository, lists project-specific deviations, and describes the tooling that enforces them.

## Conventions at a glance

- **Indentation**: four spaces. No tabs.
- **Line endings**: LF only.
- **Encoding**: UTF-8 without BOM.
- **Line length**: soft 120 characters; do not split URLs, paths, or translation strings.
- **PHP tags**: full `<?php` only. Do not use short tags. Files containing only PHP must omit the closing `?>`.
- **Namespaces, classes, methods, properties**: this codebase is procedural; the relevant PSR rules (one class per file, namespaces) only apply where classes already exist (for example `lib/feed_generator/`, `lib/hsvclass/`).
- **Imports**: `use` statements are alphabetically ordered, grouped by classes, functions, and constants.
- **Braces**: opening brace of a function/method on a new line; opening brace of control structures on the same line.
- **Control structures**: one space after the keyword, one space before the opening brace, no space after the opening parenthesis or before the closing parenthesis.
- **Arrays**: short syntax `[...]` only.
- **Trailing commas**: required in multi-line arrays, argument lists, and parameter lists.
- **Booleans, null**: lowercase (`true`, `false`, `null`).
- **Type keywords**: lowercase (`int`, `string`, `bool`).
- **Comparison**: prefer strict comparison (`===`, `!==`) when types are known.
- **No trailing whitespace**, **no whitespace on blank lines**, **single newline at end of file**.

## Tools

The project uses two tools, installed as Composer dev dependencies and runnable from `vendor/bin`.

### PHP-CS-Fixer (formatter)

Configuration: [`.php-cs-fixer.dist.php`](../.php-cs-fixer.dist.php). It applies the `@PER-CS2.0` ruleset plus the conventions listed above.

```sh
composer format          # rewrite files in place
composer format:check    # report violations without writing
```

### PHPStan (static analysis)

Configuration: [`phpstan.neon.dist`](../phpstan.neon.dist). Currently at level 5; the baseline is empty so all findings must be resolved. New code must analyse cleanly.

```sh
composer lint            # analyse against current baseline
composer lint:baseline   # regenerate the baseline (use sparingly)
```

### Combined check

```sh
composer check           # format:check + lint
```

## Excluded directories

PHP-CS-Fixer and PHPStan exclude these third-party or runtime paths:

- `vendor/`
- `live/`
- `lib/tfpdf/`, `lib/yuiloader/`, `lib/phpqrcode/`, `lib/feed_generator/`, `lib/hsvclass/`

## Local installation

Install Composer dev dependencies once:

```sh
composer install
```

In the Docker dev workspace, Composer is preinstalled in the `dev` image; run the same command inside the container:

```sh
docker compose -f docs/dev/compose.yaml --profile devtools up -d --build dev
docker compose -f docs/dev/compose.yaml exec -T dev composer install
```

## Pre-commit hook

The same checks run automatically in GitHub Actions on every push and pull request (see [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)). The pre-commit hook is the fast local gate; CI is the source of truth for what is allowed to merge.

The repository ships a pre-commit hook at [`.githooks/pre-commit`](../.githooks/pre-commit) that runs PHP-CS-Fixer (with auto-fix and re-stage) and PHPStan against staged PHP files. Enable it once per clone:

```sh
git config core.hooksPath .githooks
```

The hook resolves the tools in this order:

1. `PHP_CS_FIXER_BIN` / `PHPSTAN_BIN` environment variables.
2. `vendor/bin/php-cs-fixer` and `vendor/bin/phpstan` (local Composer install).
3. `docker compose -f docs/dev/compose.yaml exec -T dev vendor/bin/...` if the `dev` service is running.
4. `docker compose -f docs/dev/compose.yaml exec -T app vendor/bin/...` as a final fallback.

To skip per-commit:

```sh
SKIP_PHP_CS_FIXER=1 git commit ...
SKIP_PHPSTAN=1 git commit ...
```

## Big-bang reformat history

The first project-wide PER-CS 2.0 reformat is recorded as a single commit and listed in [`.git-blame-ignore-revs`](../.git-blame-ignore-revs). To make `git blame` skip those commits locally:

```sh
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

## Editor integration

PHP-CS-Fixer and PHPStan have first-party support in PhpStorm and VS Code (via the official PHP-CS-Fixer and PHPStan extensions). Point them at `.php-cs-fixer.dist.php` and `phpstan.neon.dist`. Format-on-save is encouraged but optional; the pre-commit hook is the source of truth.

The repository also ships an [`.editorconfig`](../.editorconfig) at the root, which most editors honour out of the box. It pins UTF-8, LF endings, final newline, and per-language indentation: 4 spaces for PHP, 2 spaces for JS / `.inc` snippets, YAML, JSON, NEON, and Markdown.

## JavaScript

Hand-written client JavaScript lives under `script/`:

- `script/ultiorganizer.js` — plain `.js` file included by some pages.
- `script/*.inc` — `<script>`-wrapped snippets included into PHP pages. A couple of them reference YUI globals (`YAHOO.*`).
- `script/yui/` is vendored third-party YUI 2 and is not linted.
- `live/assets/` is a built React/Vite bundle and is not linted from this repo.

JS uses **2-space indentation** (modern community standard — Prettier, StandardJS, Airbnb, Google, Node.js core all use this). The legacy tab-indented files in `script/` were reformatted in one pass when ESLint was introduced.

ESLint 9 (flat config) is the linter. Configuration: [`eslint.config.js`](../eslint.config.js). The toolchain is installed inside the `dev` Docker image under `/opt/eslint/`; the repo intentionally does not ship a root `package.json` because Ultiorganizer is a PHP project, not an npm project.

```sh
docker compose -f docs/dev/compose.yaml exec -T dev eslint script
docker compose -f docs/dev/compose.yaml exec -T dev eslint script --fix
```

Notable rule choices:

- `eslint:recommended` as the base.
- `indent: ["error", 2]` to enforce the 2-space convention.
- `no-trailing-spaces: "error"`.
- `no-unused-vars: ["warn", { args: "none" }]` — many top-level functions are intentionally exposed as globals for inline HTML handlers and look "unused" to ESLint; the warning is left in place so genuinely dead code still surfaces.
- `ecmaVersion: 5`, `sourceType: "script"` — matches the existing pre-ES6 style. Bump these if newer syntax is needed.

The pre-commit hook does **not** currently run ESLint; lint JS manually when changing files under `script/`.

Inline JS inside `<script>` blocks in `.php` files is intentionally **not linted**. Most of it is page-local glue (YUI calendar setup, Google Maps helpers, form-specific handlers) that references DOM IDs and server-rendered config only available on that page, and roughly half of those blocks contain `<?= ?>` / `<?php ... ?>` interpolation that ESLint cannot parse without a custom preprocessor. New shared utility JS should still go under `script/` so it gets lint coverage.
