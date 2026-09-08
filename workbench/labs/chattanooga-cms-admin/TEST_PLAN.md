# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment. Product target is single-site WordPress only; multisite/network behavior is out of scope and must not be reintroduced as an acceptance requirement. Third-party plugin source is immutable dependency code for this workstream; only Chattanooga CMS Admin and its disposable harness are editable.

## Gates 0–9 — PASS

- Source architecture, PHP 7.4/8.2, privacy/static boundaries.
- Real WordPress 7.1 Abilities API registration and REST isolation.
- Backup/restore, corrupt/missing rejection, unavailable storage, partial-write and ZIP-finalization fail-closed behavior.
- Plugin/theme/core lifecycle/update and forced rollback, package privacy, bounded error output, single-site cache.
- Post/page CRUD/revisions/trash/restore and object permissions.
- Publication/status transitions with conflicts and publish authority.
- Category/post-tag term and relationship operations with conflicts and rollback.
- Bounded member reads with credential/usermeta/session boundaries.
- Member display-name/URL and role-state mutation with conflict/readback/rollback and zero mail attempts.

Current reference evidence: candidate checkpoint `2eaed7eab4575ffc4c5db036a513924494d50cf4`; event/location source correction `9874d5192cef627292df09bd81fd1f7aaa59315d`; maintenance `34183243913`; content/member `34183243906`; integrity `34183243890`; artifact `10039648550`; SHA-256 `ba3c42d62ea5c0f87df81cea6eabcb6614724a2cf53d2b30d35bbc87291393d3`.

## Gate 10 — Events Manager disposable read runtime — PASS

Evidence: Events Manager run `34183462063` against WordPress.org Events Manager 7.4.3.

- Real Events Manager installer/model bootstrap passed in disposable WordPress 7.1 + MySQL 8.0.
- Registry: 66 total candidate abilities; four event/location read abilities.
- Native event/location permissions: anonymous denied, administrator allowed.
- Bounded event list/search/get passed with explicit field allowlist.
- Bounded location list/search/get passed with explicit field allowlist.
- Missing event/location IDs fail closed.
- Canonical ordinary single-event fixture state verified.

## Gate 11 — Ordinary event/location mutation — PASS

- Four typed abilities cover create/update for ordinary single events and physical venues/locations.
- Native Events Manager model APIs and capabilities are used; candidate code does not patch or modify Events Manager.
- Creation validates required fields, canonical ordinary-event state, requested publication state, readback, and cleanup after failed/incomplete creation.
- Updates require exact expected-before state tokens and reject stale mutations.
- Injected post-write verification failures trigger rollback and exact managed-state restoration.
- Publish authority and object-level event/location authority are enforced.
- Metadata-only event updates preserve dependency-owned active/booking/private state outside the ability contract.
- Event creation/update snapshots a referenced existing venue and restores/verifies it if the dependency call changes venue state as a side effect; failure to preserve it causes the CMS Admin transaction to fail closed.
- Unrelated event/location records remain unchanged.
- Clean diagnostic-free event/location workflow passed as run `34183462063`.

## Gate 12 — Site administration autonomy gap review — ACTIVE

1. Inventory the existing 66 CMS Admin abilities by actual Chattanooga administration workflow.
2. Identify concrete administration actions still impossible through the typed admin surface.
3. Rank gaps by frequency, operational necessity, reversibility, and risk.
4. Implement only justified CMS Admin abilities; do not expand installed third-party plugins merely because they expose additional features.
5. Keep destructive, notification-producing, security-sensitive, media, recurring-event, booking, ticket, and payment operations separate until an actual site workflow justifies them.
6. Apply exact-state conflicts, native authority, readback verification, rollback, and isolation according to the mutation's risk and state model.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or content/member/event mutation is authorized by these laboratory gates. Candidate installation and live MCP discovery remain separate production gates.
