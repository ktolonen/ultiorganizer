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
- Require rate limiting keyed by token and IP, with `429` and `X-RateLimit-*` headers on limit responses.

## Public v1 scope

- Public API endpoints expose event lists, divisions, teams, games, gameplay, and version metadata.
- Token authentication can be installation, event, or user scoped.
- Event-scoped endpoints require the event to be marked visible in the public API.
- Historical data outside those endpoint shapes is not part of the current v1 surface.

## Documentation

OpenAPI documentation lives alongside the API in `api/openapi.json`.

## Access and examples

API documentation is available at:

- `https://your-host/api/v1/openapi` for production with rewrite rules enabled.
- `http://localhost:8080/api/v1/openapi` in the documented Docker development stack.
- `http://localhost:8000/api/index.php/v1/openapi` when using PHP's built-in server without rewrite rules.

Tokens are managed through the admin UI at `?view=admin/apitokens`.

Example requests:

```sh
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/events"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/version"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/teams?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/divisions?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/games?event=2025"
curl -H "Authorization: Bearer YOUR_TOKEN" "https://your-host/api/v1/gameplay?game=123"
```

`/api/v1/version` returns the Ultiorganizer application compatibility version,
the API route version, the installed database version recorded in the database,
and the active customization id and version. The Ultiorganizer application
version is read from `version.php`. The active customization version is read
from `cust/<customization>/version.php`, which may return a version string or
define `CUSTOMIZATION_VERSION`; missing customization metadata reports `0.0`.
