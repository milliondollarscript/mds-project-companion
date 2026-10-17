# Security

Project Companion administration requires the WordPress `manage_options` capability. Every state-changing administrator action uses a dedicated WordPress nonce, unslashes and validates input, escapes output for its context, and redirects safely.

## Connector contract

The HTTP client consumes Project Platform connector contract `1.0` on API major `v1`, accepted at platform commit `76ae97c`. The platform owns routes, authentication classes, token formats, scopes, schemas, errors, limits, and version negotiation.

Project Companion centralizes those values in one pinned contract mapping and tests them against the authoritative OpenAPI and compatibility fixture. It does not add a refresh-token scheme, webhook format, stable branded origin, or alternate contract.

## Outbound request policy

- Production origins require HTTPS.
- HTTP is permitted only for explicit loopback development with `WP_DEBUG`.
- Origins cannot contain URL credentials, queries, or fragments.
- Private, reserved, localhost, `.local`, and `.internal` destinations are denied.
- DNS results are checked immediately before each request.
- WordPress unsafe-URL rejection remains enabled in production.
- TLS verification stays enabled.
- Redirects are disabled.
- Requests time out after ten seconds.
- Responses are bounded to one megabyte.
- Retryable operations receive at most two retries with the same idempotency key.
- Credential rotation is never automatically retried.

Normal administration page loads do not resolve or contact the hosted origin. Every network request is administrator initiated.

## Authentication and credential lifecycle

The platform bearer used to begin pairing, the one-time pairing secret, and the connector credential remain separate authentication classes.

- The platform bearer is request-only and never stored.
- Pairing secrets use a ten-minute transient and never appear in a URL.
- Connector credentials use a separate non-autoloaded option.
- Complete credentials and secrets are excluded from logs, diagnostics, exports, and rendered pages.
- Rotation replaces the locally stored credential only after a schema-valid success response.
- Revocation or an authoritative revoked response removes the local credential and hosted-view cache immediately.

No custom cryptography is used, and TLS verification cannot be disabled.

## Consent and write boundaries

Project-context, capability-snapshot, draft, and metric requests must match strict additional-property-denying local guards before transport.

- Selected field IDs must exactly equal the sorted preview fields.
- Canonical SHA-256 hashes must match the platform algorithm.
- Consent timestamps and retention classes are explicit.
- Unknown fields and changed payloads fail closed.
- Product and offer requests accept only `draft` results.
- Metrics accept only `non_personal_aggregate` classification and reject personal-looking identifiers.
- Action responses accept only seen, manual-review, or dismissed acknowledgement states and never trigger local mutation.

The administration UI does not expose draft or metric writes without an approved public source contract.

## Response validation and failures

Success and RFC problem responses are size bounded, valid JSON, and checked against their operation-specific contract shape. Unsupported schema, invalid JSON, oversized responses, authentication failure, authorization denial, revocation, rate limits, replay rejection, and dependency failure remain disconnected or fail closed.

Do not place credentials, customer data, raw payloads, or private hosted responses in bug reports. Include the redacted request ID, event type, package versions, and Diagnostics snapshot instead.
