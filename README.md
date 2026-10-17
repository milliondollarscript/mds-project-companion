# Million Dollar Script - Project Companion

Project Companion is a free, local-first Million Dollar Script extension for planning a project, reviewing transparent extension recommendations, and controlling information shared with a configured hosted project platform.

Version 0.2.0 implements the platform-owned connector contract `1.0` on API major `v1`. Hosted branding and origins remain configurable. The extension works without a hosted account and makes no hosted request during ordinary administration page loads.

## Current features

- Optional local project profile
- Independent Plan, Create, Distribute, and Grow module toggles
- Normalized MDS capability snapshot
- Deterministic extension recommendations and filters
- Exact data-sharing previews with explicit approval
- Redacted local integration audit
- Explicit compatibility discovery and device-style pairing
- Scoped connector credential testing, rotation, and revocation
- Consent-bound project-context and capability-snapshot transfers
- Plan, Create, Distribute, and Grow connector-safe reads
- Draft-only, aggregate-only, and acknowledgement-only client boundaries
- Disabled client when no valid hosted API origin is configured
- Development-only deterministic mock client and fixtures
- Generic administrator-entered project context adapter

Draft exports and aggregate-metric transfers remain unavailable in the administration UI until an owning extension exposes an approved public adapter or aggregate source contract. Publishing, scheduled sync, repository access, Codex access, personal analytics, bulk sync, and automatic local actions are outside connector `1.0`.

Project Companion does not implement an AI provider or transfer provider credentials. Hosted BYOK configuration and local-model execution remain inside their owning platform or specialist extension boundaries.

## Requirements

- WordPress 6.0 or newer
- PHP 8.1 or newer
- Million Dollar Script 3.0 with extension API major 1

See [Usage](docs/USAGE.md), [Privacy](docs/PRIVACY.md), [Security](docs/SECURITY.md), and [Development](docs/DEVELOPMENT.md).
