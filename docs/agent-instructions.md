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
- Keep WooCommerce plugin thin.
- Keep business logic in the Laravel platform.

## Front Systems

- Do not assume Front supports a UI feature unless documented or explicitly confirmed.
- Do not build custom Front UI assumptions.
- Mark uncertain behavior as `TODO` or `NEEDS_FRONT_CONFIRMATION`.

## Integration Feature Checklist

For every integration feature, define:

- Source system
- Target system
- Master of data
- Event trigger
- Idempotency key
- Failure behavior
- Reconciliation behavior

## Tests

- Add tests for mapping logic.
- Add tests for idempotency.
- Add tests for webhook verification.
- Add tests for gift card double redemption prevention.

## Merchant Experience

- Make setup understandable for non-developers.
- Prefer clear dashboard status over hidden logs.
- Explain failures in merchant-friendly language while preserving technical details for developers.

