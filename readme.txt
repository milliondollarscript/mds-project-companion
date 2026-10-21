=== Million Dollar Script - Project Companion ===
Contributors: milliondollarscript
Tags: million dollar script, planning, recommendations, privacy, integration
Requires at least: 6.0
Tested up to: 7.1.3
Requires PHP: 8.1
Stable tag: 0.2.1
License: GPL-3.0+
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Plan Million Dollar Script projects locally, review extension recommendations, and control consent-bound hosted sharing.

== Description ==

Project Companion provides local project planning, capability discovery, deterministic extension recommendations, setup checklists, data-sharing previews, consent controls, and a redacted audit history.

When a site operator configures a valid hosted API origin, administrators can check connector compatibility, pair one hosted project, test or revoke the connection, send exact approved project context or capability snapshots, and fetch connector-safe Plan, Create, Distribute, and Grow views. It continues to work without a hosted account.

== Installation ==

1. Install and activate Million Dollar Script.
2. Install and activate Project Companion.
3. Open Million Dollar Script > Project Companion.
4. Optionally complete the local project profile.
5. Enable only the modules you want to prepare.

== Frequently Asked Questions ==

= Does enabling a module send data? =

No. Module and field settings only prepare local controls. Every future operation still requires an exact preview and explicit administrator approval.

= Does version 0.2.0 connect to a hosted service? =

It can, when the site operator configures a valid hosted API origin. Pairing and every transfer remain explicit administrator actions. Without a configured origin, the client stays safely disabled.

= Can it publish products or run hosted actions? =

No. Product and offer writes are draft-only, and they are not exposed until an approved public source adapter exists. Action responses are acknowledgements only and never mutate the local site.

= Is the mock client included? =

Development fixtures live in the source test tree and are excluded from distributable packages.

== Changelog ==

= 0.2.1 =
* Tested with WordPress 7.1.3 and WooCommerce 11.2.1, and the plugin states the exact versions it was tested with, so WordPress and WooCommerce no longer report it as untested on a current site.

= 0.2.0 =

* Added connector contract 1.0 compatibility, pairing, credential lifecycle, consent-bound transfers, hosted reads, and compatibility tests.
* Preserved local-first operation, draft-only writes, acknowledgement-only actions, and deferred-capability exclusions.

= 0.1.0 =

* Initial local-first foundation.
