# Front Systems Vendor Documentation

This folder stores safe, permission-aware Front Systems API documentation for the OmniBridge integration.

The preferred source is an official OpenAPI/Swagger JSON or YAML specification from Front Systems.

The current stored spec is `openapi/frontsystems.openapi.json`, added from the user-provided file `front-systems-webshop-api-V2.json`.

Place official specs here:

```text
docs/vendor/front-systems/openapi/frontsystems.openapi.json
docs/vendor/front-systems/openapi/frontsystems.openapi.yaml
```

If Front provides a direct URL to the official spec, download it with:

```bash
./scripts/download-front-openapi.sh "<OFFICIAL_SPEC_URL>"
```

## Safety Rules

- Do not scrape or mirror Front documentation without permission.
- Do not commit credentials, API keys, tokens, cookies, private links, or restricted documentation unless Front explicitly allows it.
- Do not commit unredacted customer data.
- Manual notes should summarize confirmed behavior and link to official docs instead of copying large amounts of vendor text.
- Mark uncertain behavior as `NEEDS_FRONT_CONFIRMATION`.

## Folder Layout

- `openapi/`: official OpenAPI/Swagger specs when available.
- `manual-notes/`: internal summaries of confirmed API behavior and open questions.
- `examples/`: sanitized request and response payload examples.
- `sources.md`: source index with access notes and last-checked dates.
