# Chattanooga CMS Admin Workbench Test Plan

All mutation tests use disposable WordPress fixtures. Reference/runtime success never implies production deployment.

## Gates 0–8 — PASS

- Source architecture, PHP 7.4/8.2, privacy/static boundaries.
- Real WordPress 7.1 Abilities API registration and REST isolation.
- Backup/restore, corrupt/missing rejection, unavailable storage, partial-write and ZIP-finalization fail-closed behavior.
- Plugin/theme/core lifecycle/update and forced rollback, package privacy, bounded error output, single-site/multisite cache.
- Post/page CRUD/revisions/trash/restore and object permissions.
- Publication/status transitions with conflicts and publish authority.
- Category/post-tag term and relationship operations with conflicts and rollback.
- Bounded member reads with credential/usermeta/session boundaries.

## Gate 9 — Member profile/role mutation — PASS

Evidence: candidate `119c32800af409b7c6d3e61afcd2e14abb68083d`; content run `34171114778`; maintenance `34171114708`; multisite `34171114718`; integrity `34171114712`.

- Registry: 58 abilities.
- Profile mutation: expected-state conflict, display-name/URL update, readback, deliberate verification mismatch rollback.
- Account-email input is outside this mutation contract and is rejected; original email/state remains unchanged.
- Role mutation: expected-state conflict, exact replacement, editable-role validation, self-role guard, deliberate corruption rollback.
- Permission split: `edit_users` profile only; `promote_users` role operation.
- Mail side-effect guard: zero notification attempts.

## Gate 10 — Events Manager disposable runtime — ACTIVE

1. Install WordPress 7.1 + MySQL 8.0 in a dedicated workflow.
2. Install and activate the current WordPress.org Events Manager plugin in that disposable runtime.
3. Record plugin version and prove required public classes/functions/data model exist.
4. Add candidate bounded event/location list/get abilities only after the real plugin model is proven.
5. Verify permissions, response allowlists and empty/not-found behavior.
6. Add create/update/trash transactions only as later sub-gates with exact before-state and rollback/readback checks.
7. Keep ticket/booking/payment and media operations separate until specifically required and tested.

## Live Chattanooga

Read-only preflight remains partial. No live candidate install or event/member/content mutation is authorized by these laboratory gates.
