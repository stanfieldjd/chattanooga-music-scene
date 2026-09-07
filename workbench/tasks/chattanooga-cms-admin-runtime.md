# Task: Chattanooga CMS Admin Runtime Integration

Status: REFERENCE_MAINTENANCE_CONTENT_TAXONOMY_MEMBER_READ_VERIFIED — MEMBER MUTATION NEXT / LIVE PREFLIGHT PARTIAL / MCP DEPLOYMENT PENDING

## Objective

Develop Chattanooga CMS Admin in the isolated GitHub workbench, verify each typed administration surface against disposable WordPress before production use, and preserve explicit production/deployment boundaries.

## Target and exclusions

- Source branch remains `feature/chattanooga-cms-admin`; source checkpoint `0b34773ebc8073cb657477770b34cabc280f5892`.
- Mutable candidate is `workbench/labs/chattanooga-cms-admin/candidate` on `workbench/mars`.
- `main`, the feature branch, existing Chattanooga content/member records, and production are not mutation targets for lab work.
- Existing MCP transport is not removed incidentally.

## Current verified candidate

- Candidate/test checkpoint: `bb34fcbc2ffbe132eaf4ac12b651e423a2ff979d`.
- Full maintenance regression: run `34170460953`, PHP 7.4 + PHP 8.2 + disposable WordPress 7.1 all passed.
- Runtime artifact: `10035547833`, SHA-256 `a1a07985311e733a875502a4508a09de5c7d844fcdc0e8f724bf3ea589bbfd39`.
- Dedicated content/member run: `34170460924`, passed.
- Multisite candidate run: `34170324597`, passed.
- Integrity run: `34170461007`, passed.
- Real registry: 56 abilities = 24 maintenance + 14 post/page CRUD/revision + 2 status + 12 taxonomy + 4 member-read.

## Completed maintenance gates

- [x] PHP 7.4 / 8.2 compatibility and real WordPress 7.1 activation.
- [x] Typed Abilities API registration, maintenance permission matrix, REST isolation.
- [x] Database/component/theme/core backup and exact rollback.
- [x] Plugin/theme/core update transactions and forced-failure rollback.
- [x] WordPress.org package privacy and bounded public error output.
- [x] Single-site + real multisite cache paths.
- [x] Corrupt/missing backup rejection, unavailable storage rejection, partial-write enforcement, ZIP finalization enforcement.

## Layer B — WordPress content

- [x] Bounded post/page list/get, draft create, conflict-checked update, native revisions, revision restore, trash/restore, page-parent checks, author scoping.
- [x] Pending/private/publish/future status transitions with publish authority, exact expected state, schedule validation, and rollback path.
- [x] Category/post-tag list/get/create/update with term state tokens and rollback.
- [x] Post category/tag relationship assignment/removal with exact expected relationship sets, default-category preservation, stale-conflict rejection, rollback, and object scoping.
- [ ] Term deletion remains a separate destructive contract.
- [ ] Permanent content deletion remains a separate destructive contract.
- [ ] Media/featured-image administration is not an automatic priority; implement only when required by the administration plan.

## Layer C — member/account administration

Reference-verified read slice:

- [x] Four typed abilities: bounded member list/search, member detail, role inventory, member-role read.
- [x] Coarse `list_users` permission isolation: anonymous denied, administrator allowed, explicit capability required.
- [x] Bounded pagination, email search, role filter, invalid-role fail closed.
- [x] Exact response-field allowlists.
- [x] Credentials, activation keys, session tokens, arbitrary usermeta, direct users-table access absent.
- [x] `WP_User_Query` allowed only inside the typed member service by static privacy gate.
- [x] Dummy-user-only runtime transactions; no live member data mutated.

Next member sub-gate:

- [ ] Conflict-checked selected profile-field updates on disposable users.
- [ ] Exact role-state replacement/add/remove with authority validation and rollback verification.
- [ ] Password/reset operations, arbitrary protected metadata, account creation notification behavior, and permanent user deletion remain separate security/destructive gates.

## Events Manager discovery

Read-only capability discovery against the existing Chattanooga transport confirmed concrete Events Manager contracts (event, location, booking, ticket, category/tag and related operations). No live event mutation was performed. Layer D implementation remains after the current Layer C slice unless recalculated by site priority.

## Chattanooga/DreamHost read-only preflight

Verified non-mutating facts: WordPress 7.1, PHP 8.2.30, MySQL 8.0.41, relevant WordPress directories writable, WP Super Cache active, existing MCP surface discoverable. Still unknown: free disk capacity, filesystem method, direct live ZipArchive availability, and preferred outside-web-root backup-parent writability.

## Production state

NOT_DEPLOYED. Candidate installation, actual candidate MCP discovery, live backup verification, and any production mutation remain separately authorized production gates.
