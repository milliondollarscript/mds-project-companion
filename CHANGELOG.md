# Changelog

## Unreleased

- Replaced the private uninstall toggle with the core extension cleanup policy and retained fail-safe preservation for settings, connections, secrets, cache, audit records, and schedules.

## 0.2.0 - 2026-07-29

- Added the real HTTPS connector client for platform-owned contract `1.0` and API major `v1`.
- Added explicit compatibility discovery, short-lived pairing, credential testing, rotation, and revocation.
- Added least-privilege scope selection based on enabled modules and available public write adapters.
- Added canonical preview hashes and strict request/response validation for project context, capability snapshots, drafts, aggregate metrics, and acknowledgements.
- Added explicit connector-safe Plan, Create, Distribute, and Grow reads with local display caches.
- Added acknowledgement-only hosted action responses with no local mutation.
- Added compatibility fixtures and deterministic transport tests pinned to Project Platform commit `76ae97c`.
- Kept publishing, scheduled synchronization, repository and Codex access, personal analytics, bulk sync, automatic local actions, private extension storage, and unapproved draft or metric sources excluded.

## 0.1.0 - 2026-07-28

- Added safe MDS readiness and API-major checks.
- Added four independently disabled-by-default modules.
- Added local capability discovery, recommendations, and setup checklists.
- Added exact sharing previews, explicit consent, and redacted audit history.
- Added a brand-neutral platform-client abstraction with a disabled production client.
- Added development-only deterministic mock fixtures.
- Added the generic project-context export adapter.
- Documented the hosted connector requirements without defining a hosted contract.
