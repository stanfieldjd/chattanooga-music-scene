# Chattanooga CMS Admin Capability Roadmap

The goal is a typed, auditable AI administration surface for Chattanooga Music Scene. Broad AI access is implemented as explicit abilities, not as an arbitrary command backdoor. No additional management vendor is required by this architecture.

## Layer A — System maintenance

Current workbench candidate.

- health and version inventory;
- plugin/theme/core update inventory;
- local backup/checksum/rollback;
- plugin/theme install, activate, deactivate, delete, auto-update policy;
- cache clearing;
- audit log.

## Layer B — WordPress content administration

Planned typed abilities:

- posts/pages: list, inspect, create, update, trash/delete under exact authorization;
- media: inspect metadata, upload/replace/delete, featured-image relationships;
- taxonomies/terms: list, assign, remove, create/update;
- menus/navigation and site options where explicitly required;
- comments/moderation if used.

Tests must prove exact target selection, revision/rollback behavior, and no unrelated content changes.

## Layer C — Member/account administration

Planned typed abilities for the AI to access and administer Chattanooga Music Scene member information directly when required:

- user/member search and read;
- roles/capabilities;
- profile fields used by the site;
- account status and moderation operations;
- BuddyBoss/BuddyX member/community data that is stored locally.

Design rule: data may be returned to the authorized AI workflow, but the plugin must not forward member records to unrelated vendor telemetry, dashboards, analytics endpoints, or management clouds.

## Layer D — Events Manager

Planned typed abilities:

- events, locations, bookings, categories/tags and recurrence data;
- exact date/time/timezone fields;
- taxonomy-only mutation paths where appropriate;
- explicit destructive event/location operations;
- validation against Events Manager's stored model after every mutation.

The workbench will include regression fixtures for known schedule/timerange and location-association failure modes before these operations are promoted.

## Layer E — WooCommerce / marketplace

Planned typed abilities:

- product/listing inventory and metadata;
- orders and order status;
- customer/order relationships;
- coupons/tax/shipping configuration only when required;
- marketplace bridge/custom plugin state.

Financial/member data is permitted to the authorized AI surface but must not be silently replicated to unrelated third-party services.

## Layer F — BuddyBoss/community workflows

Planned after schema inspection of the actual Chattanooga installation:

- groups;
- activity/community content;
- invitations/moderation;
- notifications where local APIs support controlled operations.

No ability is invented from assumptions about BuddyBoss storage; the workbench must first inspect authoritative plugin APIs/schema.

## Layer G — Site-specific Chattanooga plugins

Adapters for:

- Weekend Feature;
- Great Imports;
- CMS Market Checkout Bridge;
- CMS Role Equivalency;
- Chattanooga Event Retention;
- Chattanooga Music Scene Admin App;
- Simple Content Cards;
- other verified local custom code.

Source-controlled custom plugins remain governed by their repositories/source paths; generic WordPress.org updater behavior must not overwrite them.

## Layer H — Transport/privacy verification

Before the third-party MCP transport is replaced or retained as a bootstrap path, the workbench must establish:

- exactly what requests and responses pass through the transport;
- whether it contacts a vendor service beyond Chattanooga Music Scene and OpenAI;
- whether any telemetry is emitted;
- whether a self-hosted transport can expose the same typed abilities directly to ChatGPT/OpenAI within available product constraints.

The target trust boundary remains: user + ChatGPT/OpenAI + Chattanooga Music Scene, without an unnecessary additional management-data processor.

## Layer I — Hosting/server operations

Only bounded server operations that can be made auditable and reversible:

- filesystem/disk diagnostics;
- PHP/OPcache status and safe controls where hosting permits;
- cron/scheduled-task inspection;
- protected logs;
- backup storage health.

No generic arbitrary shell endpoint is planned. A server operation must have its own typed contract, permission rule, risk model, and acceptance test.
