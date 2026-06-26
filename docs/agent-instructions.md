# Agent Instructions

Future Codex/AI agents must follow these rules.

## Before Coding

- Read the relevant files in `docs/`.
- Check existing code and patterns before adding new ones.
- Keep changes small and maintainable.
- Prefer small PRs.
- Add or update docs with every feature.

## Safety

- Do not modify production data.
- Use staging/test credentials only.
- Do not store secrets in code.
- Never log secrets or full tokens.
- Redact sensitive payload data.
- Keep `OMNIBRIDGE_ALLOW_PRODUCTION_WRITES=false` unless production work is explicitly requested and approved.
- Keep `OMNIBRIDGE_ALLOW_CONNECTION_TEST_HTTP=false` unless staging-safe read-only checks are intentionally being verified.
- Keep WooCommerce plugin thin.
- Keep business logic in the Laravel platform.

## Front Systems

- Treat `docs/vendor/front-systems/openapi/frontsystems.openapi.json` as the primary endpoint schema source.
- Use `docs/vendor/front-systems/front-api-endpoint-summary.md` for quick orientation before reading the full OpenAPI file.
- Do not assume Front supports a UI feature unless documented or explicitly confirmed.
- Do not build custom Front UI assumptions.
- Mark uncertain behavior as `TODO` or `NEEDS_FRONT_CONFIRMATION`.
- Public webhook URLs must use `webhook_endpoints.path_token`, not organization slugs.
- Product mapping terminology should use `gtin`, `external_sku`, and `front_product_ext_id`.

## Integration Feature Checklist

For every integration feature, define:

- Source system
- Target system
- Master of data
- Event trigger
- Idempotency key
- Failure behavior
- Reconciliation behavior

Duplicate inbound events must not dispatch duplicate queue jobs.

## Tests

- Add tests for mapping logic.
- Add tests for idempotency.
- Add tests for webhook verification.
- Add tests for gift card double redemption prevention.

## Merchant Experience

- Make setup understandable for non-developers.
- Prefer clear dashboard status over hidden logs.
- Explain failures in merchant-friendly language while preserving technical details for developers.
- Treat product sync as selected, validated, queued work. Never add a blind full-catalog sync.
- Keep normal store-owner pages plain-language. Put webhook URLs, raw logs, queue details, and API settings under Advanced.
- Product sync preview runs are local planning records only until a separate explicit write-test task enables writes behind guards.
