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

Current reference evidence: candidate checkpoint `8c2c422d6b8139fcfe571564a0d543b5d1607be2`; maintenance `34187219749`; content/member/deletion `34187219774`; integrity `34187219809`; Events Manager regression `34187219757`; artifact `10040947233`; SHA-256 `27c11afa7652f062da3a33b2ca23b04b37ca1eabd79d4ad6cdc7898287dc6947`.

## Gate 10 — Events Manager disposable read runtime — PASS

- Real Events Manager installer/model bootstrap passed in disposable WordPress 7.1 + MySQL 8.0.
- Native event/location permissions, bounded read allowlists, missing-ID failure, and canonical ordinary event fixtures remain green.

## Gate 11 — Ordinary event/location mutation — PASS

- Four typed abilities cover create/update for ordinary single events and physical venues/locations.
- Native Events Manager model APIs and capabilities are used; candidate code does not patch or modify Events Manager.
- Expected-state conflicts, readback, cleanup/rollback, publish authority, dependency-state preservation, referenced-venue isolation, and unrelated-record isolation remain green in regression run `34187219757`.

## Gate 12 — Permanent post/page deletion — PASS

Evidence: content runtime `34187219774`; 68 total registered abilities.

1. Permanent deletion is a separate destructive ability surface for core WordPress posts/pages only.
2. The target must already have `post_status=trash`; non-trash requests fail closed.
3. The caller must provide the exact current `expected_modified_gmt`; stale requests fail closed and report current state.
4. `confirm_permanent_delete` must explicitly be `true`; omission/false cannot delete.
5. Execution rechecks native object-level `delete_post` authority even after the coarse ability permission callback.
6. Deletion uses native `wp_delete_post(..., true)` and verifies the target no longer exists.
7. Post and page transactions both pass; unrelated sentinel content remains unchanged.
8. Anonymous access is denied; administrator access passes; a limited post-delete user does not acquire page-delete authority or other-author deletion authority.
9. This gate does not add arbitrary post-type deletion, user deletion, event deletion, media deletion, or any third-party plugin mutation.

## Gate 13 — Site administration autonomy gap review — ACTIVE

1. Inventory the 68 CMS Admin abilities by actual Chattanooga workflow.
2. Identify operations still impossible through the typed admin surface.
3. Prefer gaps in core/site administration before introducing additional third-party surfaces when appropriate.
4. Rank by operational frequency, necessity, reversibility, and risk.
5. Implement only justified CMS Admin abilities; installed third-party plugin source remains immutable.
6. Keep notification-producing, security-sensitive, media, recurring-event, booking, ticket, payment, and other destructive operations separate until a concrete site workflow justifies them.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or content/member/event mutation is authorized by these laboratory gates. Candidate installation and live MCP discovery remain separate production gates.
