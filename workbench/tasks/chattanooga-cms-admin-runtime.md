# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_MAINTENANCE_CONTENT_TAXONOMY_MEMBER_ADMIN_VERIFIED — EVENTS MANAGER LAB ACTIVE / LIVE PREFLIGHT PARTIAL / PRODUCTION NOT DEPLOYED

## Current verified candidate

- Candidate checkpoint: `119c32800af409b7c6d3e61afcd2e14abb68083d`.
- Full maintenance regression: `34171114708` — PHP 7.4, PHP 8.2, disposable WordPress 7.1 passed.
- Runtime artifact: `10035759884`, SHA-256 `eac121d727b24129406bde3832ffdced8bdba7c2c5cbb8e29c0059923723f0f0`.
- Content/member run: `34171114778` passed.
- Multisite run: `34171114718` passed.
- Integrity run: `34171114712` passed.
- Real registry: 58 abilities = 24 maintenance + 14 content CRUD/revision + 2 status + 12 taxonomy + 4 member-read + 2 member-mutation.

## Verified member administration

- Bounded member list/search/detail and role reads.
- Profile mutation is limited to display name and URL with exact expected profile state, readback verification, and rollback on injected verification failure.
- Account email is readable and participates in the profile conflict token but is not mutable in this ability; attempted email-only mutation is rejected.
- Exact role-state replacement validates editable roles, prevents changing the current account's own role state, verifies readback, and rolls back on injected corruption.
- Mutation permission split: `edit_users` for profile; `promote_users` for roles, with execution-level object authority.
- Runtime mail guard verified zero notification attempts from these mutations.
- Passwords, reset operations, activation keys, session tokens, arbitrary usermeta, account creation, and permanent user deletion remain separate security/destructive contracts.

## Active next gate — Events Manager

Read-only discovery against the existing Chattanooga transport confirmed 39 site abilities covering events, locations, tickets, bookings, categories/tags, availability and related operations. No live mutation was performed.

Next implementation is a dedicated disposable Events Manager workbench runtime. It must install/activate Events Manager in a fresh WordPress 7.1 lab and prove the plugin/API model before Chattanooga CMS Admin gains any event mutation ability. Start with bounded event/location reads, then create/update/trash as separate tested transactions. Booking/payment operations remain separate higher-risk gates.

## Production boundary

`main`, `feature/chattanooga-cms-admin`, DreamHost production, existing Chattanooga content/member/event records, and the current MCP transport are not mutation targets for workbench development. Candidate installation and live MCP discovery remain separately authorized production gates.
