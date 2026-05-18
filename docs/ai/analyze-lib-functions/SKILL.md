---
name: analyze-lib-functions
description: Analyze PHP function declarations and usage in Ultiorganizer lib files. Use when asked to inventory functions in lib/*.php, count direct or callback-style usages across the repository, identify dead-code candidates, compare usage for a specific lib file, or prepare safe cleanup work around unused PHP helpers.
---

# Analyze Lib Functions

## Workflow

1. Run the analyzer from the repository root:

```bash
python3 docs/ai/analyze-lib-functions/analyze-lib-functions.py --limit 40
```

2. For a single file, pass a top-level or nested `lib/` PHP file:

```bash
python3 docs/ai/analyze-lib-functions/analyze-lib-functions.py lib/database.php --limit 20
```

3. For bundled third-party or nested lib files, add `--recursive`:

```bash
python3 docs/ai/analyze-lib-functions/analyze-lib-functions.py --recursive --limit 40
```

4. For machine-readable triage, use JSON:

```bash
python3 docs/ai/analyze-lib-functions/analyze-lib-functions.py --format json
```

## Interpreting Results

- Treat `usage_count` as `direct_usage_count + dynamic_reference_count`.
- Treat `Global dead-code candidates` as review candidates, not deletion proof.
- Ignore method candidates for global helper cleanup unless the task explicitly asks about class/interface methods.
- Check candidates with `rg -n "FunctionName"` before deleting; dynamic calls, customizations, plugins, routes, and external integrations can still evade static detection.
- Be cautious with compatibility polyfills under `if (!function_exists(...))`; they can appear unused while still documenting older runtime support.

## Before Deleting Code

1. Categorize each candidate as app helper, compatibility polyfill, class/interface method, customization/plugin hook, install/upgrade helper, or external integration surface.
2. Delete only small batches of obvious private app helpers.
3. Run PHP formatting and lint checks for changed PHP files using `docs/ai/format-and-lint/SKILL.md`.
4. If database access changes, run `docs/ai/review-database-access/SKILL.md`.
5. Exercise the relevant page flow or run the harness smoke/integration checks when the removed helper is user-facing.
