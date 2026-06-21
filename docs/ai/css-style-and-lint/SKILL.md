---
name: css-style-and-lint
description: Analyze, lint, and safely fix Ultiorganizer CSS changes. Use when Codex edits or reviews CSS files under cust/, mobile app styling, skin overrides, or shared stylesheet rules; when checking style/color consistency; or when deciding whether duplicated selectors and utility classes should be consolidated. Run Stylelint on changed CSS and apply only low-risk fixes unless the user asks for broader visual refactoring.
---

# CSS Style And Lint

## Overview

Use this skill to keep Ultiorganizer CSS changes coherent, linted, and scoped. Prefer small corrections that preserve the visual intent of existing skins.

## Scope

Start with CSS files changed in the current worktree or index:

- `git status --short`
- `git diff --name-only`
- `git diff --cached --name-only`

Filter to `*.css`. Prioritize `cust/default/ultiorganizer.css` before skin overrides because other skins may layer on top of default styles. When reviewing a skin override, compare the same selector in `cust/default/ultiorganizer.css` before changing it.

If no CSS files changed and the user asked for a general CSS audit, inspect the requested stylesheet directly.

## Tool Resolution

Use Stylelint from the dev container:

```sh
docker compose -f docs/dev/compose.yaml exec -T dev stylelint <css files>
```

Use auto-fix only on the changed CSS files:

```sh
docker compose -f docs/dev/compose.yaml exec -T dev stylelint --fix <css files>
```

If `dev` is not running, ask the user to start it with the local development command from `docs/local-development.md`. Do not add npm dependencies to the repository root; the CSS linting toolchain lives in `docs/dev/Dockerfile.dev`.

## Analysis

When auditing style and color consistency:

- Extract the repeated colors and selectors with `rg`.
- Identify whether a rule belongs in the default skin or a customization override.
- Treat `cust/default/ultiorganizer-mobile.css` as a separate mobile-app styling surface; it intentionally uses CSS variables and larger touch targets.
- Preserve installation-specific brand colors in `cust/wfdf/`, `cust/slkl/`, `cust/gummis/`, `cust/windmill/`, and other skin directories unless the user asks for normalization.
- Flag poor contrast, duplicated table/button/navigation rules, empty rules, and selectors that duplicate another selector with only a different name.

## Fixing

Make the smallest safe change:

- Apply Stylelint auto-fixes first, then inspect the diff.
- Fix parse errors, invalid properties, duplicate properties, empty blocks, and obvious typos.
- Consolidate duplicate selectors only when the same file already uses grouping nearby or the change is clearly behavior-preserving.
- Avoid broad palette changes, spacing rewrites, or visual redesigns during a lint pass.
- Do not touch user-modified CSS outside the requested scope unless the lint error blocks verification.

For UI-affecting CSS changes, verify the relevant desktop and mobile page flow when practical. Use `docs/ai/screenshot-verify/SKILL.md` for layout-sensitive changes.

## Output

Report:

- `Fixed`: lint fixes and any safe consolidation made.
- `Warnings`: style or color consistency issues left unchanged, including broader refactors.
- `Verification`: Stylelint command run and any browser/screenshot checks run.
