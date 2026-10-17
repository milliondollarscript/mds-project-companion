# Development

Project Companion uses version-neutral public MDS APIs and canonical `million-dollar-script/...` hooks. Private `MillionDollarScript\V3\...` classes, legacy MDS2 APIs, ambiguous core constants, and another extension's private classes, tables, or options are prohibited.

## Structure

- `src/Admin` owns administrator-triggered workflows.
- `src/Discovery` builds the normalized local capability snapshot.
- `src/Recommendations` contains transparent deterministic rules.
- `src/Sharing` defines contract fields, canonical previews, and approved payloads.
- `src/Audit` owns redacted event storage and retention.
- `src/Platform` owns contract negotiation, connection state, and the brand-neutral client boundary.
- `src/Platform/Http` owns outbound policy, transport, strict request guards, response guards, and the real connector client.
- `src/Export` owns public export adapter contracts.

The mock client, fake transport, and fixtures are under `tests/` and are development-only. Production code never loads them. Release packages exclude the complete test tree.

## Authoritative connector inputs

Runtime contract mappings are pinned to Project Platform commit `76ae97c`:

- `api/connector-openapi.yaml`
- `api/fixtures/connector-v1/compatibility.json`
- `docs/reviews/0003-companion-connector-acceptance.md`

Project Platform owns the OpenAPI, schemas, routes, authentication, scopes, token behavior, errors, operation limits, and version negotiation. Change the client mapping only after a reviewed platform contract revision. Do not update the extension and platform contract by independently editing equivalent values.

The vendored compatibility fixture must remain byte-for-byte identical to its platform source. The focused suite checks the platform artifact hashes when the platform repository is available beside the MDS workspace.

## Client rules

- Business logic depends on `PlatformClient`; only `HttpClient` performs connector HTTP.
- A missing or invalid API origin selects `DisabledClient`.
- Test doubles are injected through `Transport` or the documented client filter.
- No request occurs while rendering an administration page.
- New writes require a canonical preview algorithm, strict outgoing schema guard, explicit administrator action, nonce, capability check, idempotency key, response schema, redacted audit event, and focused tests.
- Another extension's object may be exported only through a documented public source contract and an extension-owned adapter.
- Product and offer results must stay draft-only.
- Hosted action responses stay acknowledgement-only.
- BYOK and local-model provider behavior remains in the hosted platform or specialist extension. Project Companion does not add an AI provider.

## Focused checks

```text
find repos/extensions/free/mds-project-companion -name '*.php' -print0 | xargs -0 -n1 php -l
php repos/extensions/free/mds-project-companion/tests/run.php
php repos/extensions/free/mds-project-companion/tests/release-scan.php
php scripts/mds3-release-audit.php --package-dir repos/extensions/free/mds-project-companion --package-kind extension --no-installed --no-extensions
git diff --check -- repos/extensions/free/mds-project-companion
```

Use `./scripts/sync-extension.sh free/mds-project-companion` only when local WordPress rendering or smoke verification is approved and needed.

## Deferred connector capabilities

Connector `1.0` excludes webhooks, scheduled synchronization, publishing, repository access, Codex access, personal analytics, bulk sync, automatic local actions, extension purchasing or activation, customer data, orders, payments, messages, and private sponsor data. A platform-owned contract revision and separate security review are required before any of these boundaries can change.
