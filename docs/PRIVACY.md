# Privacy

Project Companion is local-first. The project profile, module settings, sharing preferences, discovery cache, connection metadata, cached connector-safe views, pending previews, and audit history are stored on the WordPress site.

## Data sharing

No information leaves the site because a module or field is enabled. Every transfer requires a fresh exact preview, a valid WordPress nonce, the `manage_options` capability, and explicit administrator approval.

Each preview shows the stable field ID, value, source module, purpose, personal-data risk, requirement status, enabled state, retention class, and canonical SHA-256 preview hash. Consent is bound to the sorted selected field IDs and exact payload values. A stale or changed preview fails closed.

Available transfers are:

- Administrator-entered generic project context for Plan
- Public Million Dollar Script version and capability information for Create

Project summaries may contain personal information if an administrator enters it, so that field is marked accordingly. The initial sources exclude orders, customers, payments, messages, private sponsor data, raw event histories, and personal analytics.

Draft exports and aggregate metrics stay unavailable until an owning extension exposes an approved public adapter or aggregate source contract. Aggregate metrics must be non-personal and cannot include subject-level identifiers.

AI provider keys, hosted BYOK settings, local-model prompts, model responses, and repository content are not connector transfer fields.

## Hosted reads

Plan, Create, Distribute, and Grow views are fetched only through explicit administrator actions. Returned connector-safe views are cached locally for display. They are removed when the connection is revoked or the local cache is cleared.

## Credentials

The temporary authenticated platform token used to begin pairing is not stored. The pairing secret is kept in a short-lived transient. The connector credential is stored in a separate non-autoloaded WordPress option and never shown in the interface, exports, diagnostics, or audit records.

The extension does not invent custom cryptography. Protect WordPress database backups and administrator access according to the sensitivity of connector credentials.

## Audit records

Audit events contain bounded operational metadata such as event type, module, result, request ID, redacted or hashed subject identifier, local actor ID, and time. Central filtering excludes token, secret, password, API-key, authorization, cookie, credential, webhook, raw-payload, and response-body values.

Retention defaults to 365 days and can be set from 30 to 3650 days or indefinite. Expired records are removed in bounded daily batches.

## Export and clearing

Administrators can export companion-owned settings, cached discovery data, connection metadata without the credential, and redacted audit records as JSON. Separate actions clear the local profile, caches, or audit history.

Deactivation cancels scheduled cleanup and preserves data. It does not contact the hosted service.

Uninstall removes companion data only when both conditions are true:

1. Million Dollar Script **Delete Data On Uninstall** is enabled.
2. Project Companion remains selected in the extension cleanup list under Million Dollar Script settings.

If the public core policy cannot be read, data is preserved. Revoke the hosted installation before uninstalling when immediate hosted disconnection is required.
