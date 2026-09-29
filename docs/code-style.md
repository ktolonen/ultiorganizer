# Code style

PHP follows [PER Coding Style 2.0](https://www.php-fig.org/per/coding-style/) (PER-CS 2.0), the PHP-FIG successor to PSR-12, enforced by PHP-CS-Fixer and PHPStan.

When choosing a style or tooling default, pick the current community convention rather than matching legacy code, and reformat the existing files if needed. For example, JS uses 2-space indentation although the older `script/` files used tabs.

## Conventions at a glance

- **Indentation**: four spaces. No tabs.
- **Line endings**: LF only.
- **Encoding**: UTF-8 without BOM.
- **Line length**: soft 120 characters; do not split URLs, paths, or translation strings.
- **PHP tags**: full `<?php` only. Do not use short tags. Files containing only PHP must omit the closing `?>`.
- **Classes**: the codebase is procedural; class rules apply only where classes exist.
- **Imports**: `use` statements are alphabetically ordered, grouped by classes, functions, and constants.
- **Braces**: opening brace of a function/method on a new line; opening brace of control structures on the same line.
- **Control structures**: one space after the keyword, one space before the opening brace, no space after the opening parenthesis or before the closing parenthesis.
- **Arrays**: short syntax `[...]` only.
- **Trailing commas**: required in multi-line arrays, argument lists, and parameter lists.
- **Booleans, null**: lowercase (`true`, `false`, `null`).
- **Type keywords**: lowercase (`int`, `string`, `bool`).
- **Comparison**: prefer strict comparison (`===`, `!==`) when types are known.
- **Comments**: match the sparse density of the surrounding code. A small edit to existing code needs none — the reasoning belongs in the commit message. Reserve docblocks for genuinely new shared helpers, and keep them short.
- **No trailing whitespace**, **no whitespace on blank lines**, **single newline at end of file**.

## Tools

Both are Composer dev dependencies (`composer install`, preinstalled in the `dev` image).

### PHP-CS-Fixer (formatter)

[`.php-cs-fixer.dist.php`](../.php-cs-fixer.dist.php) applies `@PER-CS2.0` plus the conventions above.

```sh
composer format          # rewrite files in place
composer format:check    # report violations without writing
```

### PHPStan (static analysis)

[`phpstan.neon.dist`](../phpstan.neon.dist), level 5. The baseline is empty, so all findings must be resolved.

```sh
composer lint            # analyse against current baseline
composer lint:baseline   # regenerate the baseline (use sparingly)
```

### Combined check

```sh
composer check           # format:check + lint
```

## Excluded directories

Both tools skip `vendor/`, `live/`, `dist/`, and the third-party `lib/tfpdf/`, `lib/yuiloader/`, `lib/phpqrcode/`, `lib/feed_generator/` and `lib/hsvclass/`. PHPStan also skips `conf/`.

## Pre-commit hook

[`.githooks/pre-commit`](../.githooks/pre-commit) runs PHP-CS-Fixer (fixing and re-staging) and PHPStan on staged PHP files. CI runs the same checks and decides what may merge. Enable the hook once per clone:

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

The project-wide reformat commit is listed in [`.git-blame-ignore-revs`](../.git-blame-ignore-revs):

```sh
git config blame.ignoreRevsFile .git-blame-ignore-revs
```

## Editor integration

Point PhpStorm or VS Code extensions at `.php-cs-fixer.dist.php` and `phpstan.neon.dist`. [`.editorconfig`](../.editorconfig) pins UTF-8, LF, final newline, 4-space PHP, and 2-space JS, `.inc`, YAML, JSON, NEON and Markdown.

## JavaScript

Hand-written client JavaScript lives under `script/`: plain `.js` files and `script/*.inc` `<script>` snippets included into PHP pages (some use YUI globals). The vendored `script/yui/` and the built `live/assets/` bundle are not linted.

JS is ES5 with 2-space indentation, linted by ESLint 9 using [`eslint.config.js`](../eslint.config.js). The toolchain lives in the `dev` image under `/opt/eslint/`; there is deliberately no root `package.json`.

```sh
docker compose -f docs/dev/compose.yaml exec -T dev eslint script
docker compose -f docs/dev/compose.yaml exec -T dev eslint --fix script
```

Rules: `eslint:recommended`, `indent: 2`, `no-trailing-spaces`, `ecmaVersion: 5` with `sourceType: "script"`, and `no-unused-vars` as a warning with `args: "none"`, because many functions are globals for inline HTML handlers.

The pre-commit hook does not run ESLint. Inline `<script>` blocks in `.php` files are not linted (they are page-local glue, often with PHP interpolation); put new shared JS under `script/`.
