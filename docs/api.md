# API

This page mirrors the current API guidance from `AGENTS.md`.

## Current design

- API lives under `/api`.
- Versioned routes use paths like `/api/v1/...`.
- `api/index.php` is the dedicated entry point.
- Responses are JSON only.

## Expectations

- Use consistent `status`, `data`, and `error` payloads with HTTP status codes.
- Keep normalization and filtering in `/api`.
- Keep SQL and shared data access in `lib/` as the single source of truth.
- Require rate limiting keyed by token and IP, with `429` and `Retry-After` on limit responses.

## Initial scope

- Public data first.
- Token authentication can be installation, event, or user scoped.
- Early endpoints mirror `teams.php`, `games.php`, and `gameplay.php`, excluding historical data.

## Documentation

OpenAPI documentation lives alongside the API in `api/openapi.json`.

## Access and examples

API documentation is available at:

- `https://your-host/api/v1/openapi` for production with rewrite rules enabled.
- `http://localhost:8000/api/index.php/v1/openapi` when using PHP's built-in server.

Tokens are managed through the admin UI at `?view=admin/apitokens`.

Example requests:

```sh
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/events"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/teams?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/divisions?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/gameplay?game=123"
```
