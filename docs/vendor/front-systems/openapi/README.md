# Front Systems OpenAPI Specs

Store official Front Systems OpenAPI/Swagger files in this directory.

Accepted filenames:

```text
frontsystems.openapi.json
frontsystems.openapi.yaml
```

If Front provides a direct URL, download the spec with:

```bash
./scripts/download-front-openapi.sh "<OFFICIAL_SPEC_URL>"
```

The download script writes `downloaded-at.txt` with:

- Source URL, with query strings redacted for safety
- UTC download timestamp
- Saved file path
- Safety warning

If you download the file manually, place it in this directory and update `downloaded-at.txt` or `../sources.md` with the source, access level, date, and version notes.

To verify that a spec file is present, run:

```bash
./scripts/generate-front-client.sh
```

Generated API clients are not configured yet. A future task can choose a generator such as OpenAPI Generator, Swagger Codegen, or a Laravel/PHP HTTP client generator.

