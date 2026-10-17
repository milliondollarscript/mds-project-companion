# Hosted Connector API Requirements

Status: satisfied by Project Platform connector contract `1.0` at commit `76ae97c`.

The authoritative contract remains in the Project Platform repository:

- `api/connector-openapi.yaml`
- `api/fixtures/connector-v1/compatibility.json`
- `docs/reviews/0003-companion-connector-acceptance.md`

This document records the extension boundary. It does not redefine platform-owned routes, authentication, scopes, token formats, schemas, errors, limits, or version negotiation.

## Consumed operations

The client consumes the contract operations for:

- Capability and compatibility discovery
- Begin and complete pairing
- Installation state and explicit connection testing
- Credential rotation and installation revocation
- Approved capability snapshots
- Approved generic project context
- Plan summaries and recommendations
- Create journeys
- Product and offer drafts
- Distribution summaries
- Approved non-personal aggregate metrics
- Growth summaries
- Hosted action acknowledgements

Normal page loads perform none of these operations.

## Extension consent boundary

Module activation is not consent. Project Companion requires:

- A stable source field ID
- Source module and purpose
- Classification and personal-data disclosure
- An exact administrator-visible value
- A canonical preview hash
- Sorted selected field IDs
- An explicit consent timestamp and retention class
- A fresh nonce-protected administrator approval
- A redacted local audit event

Project context and capability snapshots are available now. Draft and metric transport methods are implemented and compatibility tested, but their administration workflows remain unavailable until an owning extension exposes an approved public adapter or aggregate source contract.

## Security boundary

- Production transport is HTTPS-only with certificate verification.
- Development HTTP is loopback-only and requires explicit debug configuration.
- Origins pass SSRF controls before each request.
- Authentication values never enter URLs.
- Pairing, connector, and platform-user credentials remain distinct.
- Mutations are idempotent and retries preserve the same key.
- Non-retryable credential rotation receives no automatic retry.
- Requests and responses are bounded.
- Success and problem responses receive operation-specific schema checks.
- Revocation clears local secrets and hosted caches.

## Preserved exclusions

The extension does not implement:

- Webhooks or scheduled/background synchronization
- Publishing or automatic hosted-to-local actions
- Repository or Codex access
- AI provider or model execution
- Personal analytics or raw events
- Bulk synchronization
- Extension purchase, installation, activation, configuration, or licensing
- Customer, order, payment, message, or private sponsor transfer
- Access to another extension's private classes, tables, or options

Any change to these exclusions requires a new platform-owned contract revision and security review.

## Branding and deployment

The API origin, application link, documentation link, and display name are configuration values. The extension contains no permanent hosted brand or production domain. Contract support does not authorize deployment, publication, live-service changes, or platform repository changes.
