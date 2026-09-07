# Chattanooga CMS Admin Workbench Test Plan

Tests are ordered so lower-risk prerequisites block higher-risk operations. Disposable WordPress fixtures are required before any Chattanooga mutation.

## Gate 0 — Source and architecture
Status: PASS
- Immutable baseline verification; PHP 7.4/8.2 lint.
- No arbitrary shell/PHP/SQL, generic candidate REST route, or direct candidate vendor transport.
- Ability manifests are aggregated automatically.
- Privacy boundary rejects credential/session/usermeta access and isolates `WP_User_Query` to the typed member service.

## Gate 1 — Real WordPress 7.1 registration
Status: PASS
- Current registry total: 56 abilities = 24 maintenance + 14 CRUD/revision + 2 status + 12 taxonomy + 4 member-read.
- Evidence: content run `34170460924`, `wordpress-ability-registration: PASS (56 abilities)`.

## Gate 2 — Backup/restore integrity
Status: PASS
- Database/component/theme/core backup and exact restore.
- Numeric primary-key fidelity.
- Corrupt/missing material rejected before mutation.
- All backup locations unavailable fails closed.
- Progressive writes complete; stalled writes fail and incomplete SQL is removed.
- ZIP close failure/zero-byte output rejected and incomplete archive removed.
Evidence: maintenance run `34170460953`.

## Gate 3 — Permission, exposure and error model
Status: PASS
- 24 maintenance abilities isolated across intended WordPress capabilities.
- 14 content abilities: anonymous denied; administrator allowed; post-only limited user isolated from page abilities; object-level ownership checks enforced.
- Status abilities enforce target-specific publish authority.
- 12 taxonomy abilities enforce taxonomy/object capabilities and relationship authority.
- 4 member-read abilities require `list_users`; anonymous/subscriber denied unless capability explicitly granted.
- Candidate remains `show_in_rest=false`; bounded/redacted public error model remains green.

## Gate 4 — Lifecycle/update/core/privacy/cache
Status: PASS
- Plugin/theme lifecycle, updates and rollback.
- Core 7.0→7.1 transaction and deliberate failure rollback.
- WordPress.org package privacy markers absent.
- Single-site and real WordPress 7.1 multisite cache branches passed.
Evidence: maintenance `34170460953`, multisite `34170324597`.

## Gate 5 — Post/page CRUD/revision
Status: PASS
- Bounded list/get, draft create, exact modified-time conflicts, update revision, revision restore, trash/restore, parent validation, author scope, unrelated-content isolation.
Exact output remains `content-transaction-cli: PASS post=draft-conflict-update-revision-trash-restore page=parent-update-trash-restore unrelated=unchanged`.

## Gate 6 — Publication/status
Status: PASS
- Exact expected timestamp/status.
- draft/pending/private/publish/future transitions.
- explicit future UTC scheduling and publish-now normalization.
- publish capability enforcement and rollback path.
Exact output remains `content-status-cli: PASS conflicts=timestamp,status post=pending-private-publish-future-publish page=pending-publish-draft limited=publish-denied unrelated=unchanged`.

## Gate 7 — Taxonomy/category/tag
Status: PASS
- `category` and `post_tag` only.
- bounded list/get/create/update; duplicate/parent validation; term state token conflicts.
- deliberate term update mismatch rolls back.
- post term relationships require exact expected sets.
- default category preserved; stale relationship conflicts rejected; deliberate relationship corruption rolls back.
- page taxonomy relationship rejected when unsupported; unrelated content unchanged.
Evidence: taxonomy source `443bfd3d1bf27c10291185f6f594195ccf3849df`, content run lineage ending in `34170460924`.
- Term deletion remains a separate destructive gate.

## Gate 8 — Member/account read administration
Status: PASS
- Four read-only abilities: list-members, get-member, list-member-roles, get-member-roles.
- permission result: `member-permission-cli: PASS abilities=4 anonymous=denied admin=allowed list_users=required`.
- transaction result: `member-read-cli: PASS list=bounded search=email role=verified detail=bounded roles=verified credentials=absent`.
- list response omits email; detail response uses explicit allowlist.
- no password, activation key, session token, arbitrary usermeta, whole WP_User export, or direct users-table access.
- all runtime data is disposable dummy-user data.
Evidence: content run `34170460924`; static/full maintenance run `34170460953`.

## Gate 9 — Member profile/role mutation
Status: ACTIVE NEXT
Planned contract:
- disposable dummy users only;
- selected standard profile fields only, with explicit expected-before state token;
- exact role-state token and target role validation;
- actor must hold appropriate user-management authority;
- readback verification after every mutation;
- automatic rollback to exact prior profile/role state on verification failure;
- no password/reset, arbitrary protected metadata, session-token operations, account deletion, or notification side effects in this gate.

## Gate 10 — Events Manager administration
Status: DISCOVERED / NOT IMPLEMENTED
- Existing Chattanooga transport read-only discovery confirmed typed event/location/booking/ticket/category/tag contracts.
- Build disposable adapter fixtures from verified Events Manager model before any live mutation.

## Chattanooga/DreamHost read-only preflight
Status: PARTIAL PASS / NON-MUTATING
Verified: WordPress 7.1, PHP 8.2.30, MySQL 8.0.41, relevant WordPress directory writability, WP Super Cache active, current MCP surface. Unknown: free disk capacity, filesystem method, live ZipArchive, outside-web-root backup parent writability.

## Production installation/runtime
Status: NOT AUTHORIZED BY WORKBENCH TESTING ALONE
Requires separate production rollback point, exact candidate installation/activation, actual candidate MCP discovery, local production backup verification, then only explicitly authorized live mutations.

## Rule for new coding
Every candidate change must identify the desired/failing test where practical, preserve typed boundaries, run all applicable regressions, and update evidence. Compiling alone is never promotion evidence.
