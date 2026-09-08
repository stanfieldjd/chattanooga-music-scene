# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment. Product target is single-site WordPress only; multisite/network behavior is out of scope and must not be reintroduced as an acceptance requirement.

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

Reference single-site evidence: candidate `dc1ac1322c14f073e82e2ae20ad315cfd79ee6c7`; maintenance `34174322773`; content `34174322772`; integrity `34174322771`; artifact `10036756929`; SHA-256 `ff5c958c2bcdeefaaa1a87ff7d53eb45f6448c6f03679ed6f9cf0f42271d7bba`.

## Gate 10 — Events Manager disposable read runtime — PASS

Evidence: Events Manager run `34174699401` against WordPress.org Events Manager 7.4.3.

- Real Events Manager installer/model bootstrap passed in disposable WordPress 7.1 + MySQL 8.0.
- Registry: 62 total candidate abilities; four Events Manager read abilities.
- Native event/location permissions: anonymous denied, administrator allowed.
- Bounded event list/search/get passed with explicit field allowlist.
- Bounded location list/search/get passed with explicit field allowlist.
- Missing event/location IDs fail closed.
- Canonical single-event fixture fields verified: `event_archetype=event`, `event_type=single`, `event_active_status=1`.
- The prior empty collection result was a fixture defect, not a candidate adapter defect.

## Gate 11 — Ordinary event/location mutation — ACTIVE

1. Add only ordinary single-event and venue/location create/update abilities justified by Chattanooga administration.
2. Use Events Manager native model APIs and native capabilities; do not bypass through direct SQL.
3. Creation must validate required fields, use canonical ordinary-event type state, verify readback, and clean up failed/incomplete fixtures.
4. Updates require an exact expected-before state token and reject stale mutations.
5. Verify readback after update and perform rollback if injected post-write verification fails.
6. Prove unrelated event/location records remain unchanged.
7. Keep recurring events, trash/delete, tickets, bookings, payments, and media as separate gates only if an actual Chattanooga need requires them.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or event/member/content mutation is authorized by these laboratory gates.
