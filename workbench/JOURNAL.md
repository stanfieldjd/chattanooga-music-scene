# Workbench Journal

Append-only record of material workbench state changes. Do not rewrite prior entries to make later work appear cleaner; append corrections or superseding entries.

## 2026-09-07 — Workbench initialization

- Created dedicated branch `workbench/mars` from verified `main` commit `d3167ab8d084523c63d22004955d781962e41623`.
- Established persistent status, task queue, protocol, machine-readable state, task template, and current CMS Admin runtime-integration task.
- Kept the workbench separate from `feature/chattanooga-cms-admin` so unfinished plugin source is not implicitly promoted or merged.
- Added an integrity check to validate required workbench files, JSON state, and shell syntax.

## 2026-09-07 — CMS Admin laboratory

- Established `workbench/labs/chattanooga-cms-admin` for isolated plugin engineering and testing.
- Mirrored the verified CMS Admin source checkpoint `0b34773ebc8073cb657477770b34cabc280f5892` by reusing the exact Git blobs; the feature branch itself was not modified.
- Added source-manifest verification, PHP syntax testing, architecture/security scanning, and stubbed WordPress Abilities registration tests.
- Added a read-only WordPress runtime capability probe for Abilities API, updater classes, filesystem support, ZipArchive, backup-path writability, cache APIs, and policy constants.
- Added a capability matrix that keeps runtime-dependent features UNKNOWN/CONDITIONAL until execution evidence exists.
- Added CI matrix targets for PHP 7.4 and PHP 8.2.

## 2026-09-07 — Baseline/candidate split and real WordPress registry

- Preserved `plugin/` as the immutable exact source checkpoint.
- Added `candidate/` as the mutable coding surface so experiments cannot destroy the source-evidence baseline.
- Updated lint, security, source-shape, and stub-registration tests to run against the candidate.
- Added a disposable WordPress 7.1 + MySQL runtime job.
- Confirmed WordPress 7.1 exposes the native Abilities API and WordPress upgrader/filesystem classes required by the current architecture.
- Confirmed WP-CLI bootstrap did not automatically fire the Abilities lifecycle in the initial read-only probe; added an explicit disposable lifecycle/registry test rather than treating API presence as registration.
- Verified all expected 24 CMS Admin abilities through WordPress's real ability registry.

## 2026-09-07 — Backup and rollback execution evidence

- Added a disposable fixture plugin used only inside the workbench runtime.
- Created a real database backup and verified its recorded SHA-256 checksum.
- Created and verified a fixture-plugin rollback archive.
- Deliberately mutated the fixture plugin state in the disposable runtime.
- Restored the component from the rollback archive and verified the original fixture bytes returned exactly.
- Recorded reference runtime evidence at CMS Admin Workbench Lab run `34118396822`, test commit `76f7f8a05f07014b13712b011cfec9fb69d0b666`.
- Kept DreamHost/Chattanooga production state, actual MCP discovery, database/core restore, update transactions, and broader site-administration abilities explicitly unresolved.

## 2026-09-07 — Theme update rollback and core rollback fidelity

- Advanced `workbench/mars` to test commit `32f1db5b36f1c8c5bfb94bb725bb474adaabdb81` without modifying `main` or `feature/chattanooga-cms-admin`.
- Added a disposable Twenty Twenty-One 1.8 fixture, updated it through `CMSA_Updates::update_theme()`, verified the rollback archive, restored it, and verified both the original 1.8 version and exact `style.css` SHA-256 returned.
- Added a WordPress core rollback probe that created and verified a core+database snapshot, deliberately mutated `wp-includes/version.php`, `readme.html`, and a database sentinel, then restored the snapshot and verified both file hashes and the database value returned exactly.
- CMS Admin Workbench Lab run `34160856274` passed PHP 7.4, PHP 8.2, and the complete disposable WordPress 7.1 runtime job, including the new theme-update rollback and core-backup/restore steps.
- Core upgrader execution remains untested. DreamHost filesystem behavior and actual Chattanooga MCP discovery remain unverified production gates.

## 2026-09-07 — Ability permission matrix and REST isolation

- Added real WordPress capability isolation testing at commit `39d699cbf6415427a0c1a5ae29eed327a43e6a78`.
- CMS Admin Workbench Lab run `34161223016` denied all 24 abilities anonymously, allowed all 24 to an administrator, and isolated them across exactly 10 required WordPress capabilities with no cross-capability grants.
- The runtime enumerated six WordPress Abilities REST routes: namespace, categories, category detail, ability list, ability detail, and ability run.
- Candidate abilities marked `show_in_rest=false` did not appear in the REST ability collection. Direct GET/POST probes for `chattanooga-cms-admin/get-health` did not expose or execute the candidate ability.
- The complete regression suite remained green after the permission/REST gate: PHP 7.4, PHP 8.2, plugin lifecycle, theme lifecycle, database restore, WordPress.org plugin update, theme update rollback, core backup/restore, and health/cache all passed.
- Runtime capability artifact id `10032670494` was uploaded with ZIP SHA-256 `8fdbe0d203ffc3980de8a115a0c1f7f6a1bee450942586684048044cc9c7ae33`.

## 2026-09-07 — Forced update validation rollback

- Added a deliberate post-update validation mismatch probe at commit `a34d8e260778762d434cf91e10fde7932f704df2`.
- The probe reset Classic Editor to 1.6, preserved its exact main-file SHA-256, retained the real WordPress.org update package, and changed only the advertised target version seen by the candidate to force post-install validation failure.
- `CMSA_Updates::update_plugin()` returned the expected validation error and reported automatic rollback success rather than accepting the mismatched post-update state.
- The restored Classic Editor version returned to 1.6 and the main-file SHA-256 exactly matched the pre-update file.
- CMS Admin Workbench Lab run `34161501638` passed the forced rollback step and the complete downstream regression suite.
- Runtime capability artifact id `10032758991` was uploaded with SHA-256 `545293ebd672297f218b1e4ced92ed4f352b59e42253c2af87ded792f6eb5bcf`.

## 2026-09-07 — Core updater transaction

- Added an actual `Core_Upgrader` transaction probe at commit `37839c3a95b21394dab139b31a5d22d12e41676d`.
- The disposable runtime was moved to WordPress 7.0 only after the earlier WordPress 7.1 regression gates completed, then the candidate itself was asked to perform the currently offered core update.
- `CMSA_Updates::update_core()` created and verified a pre-update core+database rollback snapshot and upgraded WordPress from 7.0 to 7.1.
- Post-update verification confirmed the on-disk version was 7.1, `wp-config.php` was byte-identical, a `wp-content` sentinel was byte-identical, a database sentinel was unchanged, the site still bootstrapped, and Chattanooga CMS Admin remained active.
- CMS Admin Workbench Lab run `34161768634` passed PHP 7.4, PHP 8.2, the full disposable WordPress regression suite, and the core update transaction.
- Exact transaction output: `core-update-cli: PASS from=7.0 to=7.1 ... config=unchanged wp-content=unchanged database=unchanged plugin=active`.
- Runtime capability artifact id `10032846544` was uploaded with SHA-256 `ac6892ba1fe16d9483608366fd36c50128e843a8f5d6375c101f1422ef7656a7`.
- The core automatic rollback path on a deliberately failed core update remains unverified and is the next core-specific fault-injection gate.

## 2026-09-07 — Forced core rollback and numeric database serialization repair

- Added a forced post-core validation mismatch probe at commit `481c9213faa4037f9caaa27100cc8e3e413e4051` after the normal core-updater transaction was already proven.
- The first fault-injection run `34162636544` exposed a real database-backup defect during rollback: numeric primary keys had been serialized as binary `0x...` literals, and a larger `wp_options.option_id` created during the update was coerced by MySQL into an overflowing integer value, producing a duplicate-primary-key restore failure.
- Repaired the serializer at the cause in commit `ec585e6fb3d5952e342a2931a63a782ceaa5c461`: database column definitions are inspected and numeric SQL columns are now emitted as validated numeric literals, while null, empty-string, and binary/string values retain their distinct safe encodings.
- Added a database restore regression at commit `c0a54be38ed087d3b428eaa8e76d4ba7fdcaacb6` that verifies both sentinel value restoration and exact numeric `option_id` identity across backup/restore.
- CMS Admin Workbench Lab run `34162917097` passed PHP 7.4, PHP 8.2, the complete WordPress regression chain, numeric database-ID fidelity, the normal core update transaction, and the previously failing forced-core rollback path.
- Forced-core evidence: `forced-core-update-rollback-cli: PASS attempted=7.1 rollback=7.0 ... core=exact database=restored config=unchanged wp-content=unchanged plugin=active`.
- Runtime capability artifact id `10033217958` was uploaded with SHA-256 `e5a033888454e7f583bb50cf39716874e593c0b2dc02058d16f0884b32055ae9`.
- DreamHost behavior, actual Chattanooga MCP discovery, and WordPress.org package-request privacy remain unverified and separate from this reference-runtime proof.

## 2026-09-07 — WordPress package-network privacy capture

- Added request-level privacy instrumentation at commit `df1ef39ea8dd76d61f20136150d269f3ffd74845` around the candidate's WordPress.org plugin/theme package operations.
- Seeded five disposable representative private-marker classes: member email, private content, order-like data, credential-like data, and local backup content.
- Scanned captured WordPress HTTP request URLs and arguments for raw, URL-encoded, and base64 forms of every marker while persisting only safe request metadata.
- CMS Admin Workbench Lab run `34163308270` captured 12 package-related requests. Only `api.wordpress.org` and `downloads.wordpress.org` were observed, and none of the five private-marker classes appeared in any captured request.
- Exact privacy result: `privacy_requests=12 hosts=api.wordpress.org,downloads.wordpress.org private_markers=absent`.
- PHP 7.4, PHP 8.2, all 24 ability gates, numeric database restore fidelity, plugin/theme transactions, normal core update, and forced core rollback all remained green after the instrumentation.
- Runtime capability artifact id `10033342899` was uploaded with SHA-256 `4bb738234ee27cdb12b98d67cc4cd2cc92574626eba29b6add296ca76fd26501`.
- This proves the exercised WordPress.org package operations in the disposable reference runtime did not transmit the seeded private markers; it is not a claim about unrelated plugins, WooCommerce-specific traffic, or the live Chattanooga environment.

## 2026-09-07 — Error-output redaction fault injection and repair

- Added a deliberate sensitive-marker error probe at commit `ede8a3599df196e0fc1987b8af0639af7f992efb` without first changing the candidate implementation.
- CMS Admin Workbench Lab run `34163733148` failed the new gate with `plugin_api_error_leaked_marker` and `database_restore_error_leaked_marker`, proving raw upstream plugin-API and MySQL restore diagnostics crossed the public error boundary.
- Repaired the error boundary at commit `a21e834b7c866c696dd34015e223f099c1dd1f3d`: installer/updater/rollback/filesystem/lifecycle/core/database paths now return bounded candidate-owned public errors and safe rollback/state metadata instead of upstream diagnostic strings.
- CMS Admin Workbench Lab run `34164107475` passed PHP 7.4, PHP 8.2, the full WordPress regression chain, and the new redaction gate.
- Exact closure output: `error-redaction-cli: PASS boundaries=plugin-api,plugin-updater,database-restore marker=absent rollback=verified`.
- Runtime capability artifact id `10033608695` was uploaded with SHA-256 `bcb15f9090f4a4180fa43a9dbe9e5c2b27350042f912af16af39be4e717065c9`.

## 2026-09-07 — Expanded theme lifecycle transaction

- Extended the existing theme lifecycle probe at commit `f52ee6431fb0111ea5e9499466a2f04fe034aa71` rather than adding a duplicate lifecycle path.
- The probe retained backup-protected theme deletion/restore and added candidate-controlled switch to `cmsa-lab-theme`, persistent theme auto-update enable/disable verification, return to the original active theme, and restoration of the original auto-update policy state.
- CMS Admin Workbench Lab run `34164417137` passed PHP 7.4, PHP 8.2, the expanded lifecycle step, redaction regression, package privacy, plugin/theme update rollback, core backup/restore, normal core update, and forced core rollback.
- Exact lifecycle output: `theme-lifecycle-cli: PASS backup=theme-20260907-214842-itg8t3ot switch=cmsa-lab-theme return=twentytwentyfive auto_update=enable-disable restored=disabled`.
- Error redaction remained green in the same run: `error-redaction-cli: PASS boundaries=plugin-api,plugin-updater,database-restore marker=absent rollback=verified`.
- Runtime capability artifact id `10033701473` was uploaded with SHA-256 `e74eef622406878219d6cbd89d429befbfae2b2fe93d02771b1de013686a2712`.
- The next active workbench gate is real WordPress multisite execution of the health/cache branch; single-site proof is not being substituted for multisite evidence.

## 2026-09-08 — Events Manager event taxonomy relationship administration

- Starting from the previously verified Events Manager 7.4.3 taxonomy contract probe, selected event classification/tag relationships as the next concrete Chattanooga administration gap because the queue requires preserving festival-vs-Live-Music primary-form distinctions.
- Added three bounded Chattanooga CMS Admin abilities at source commit `62e46bb64514973a640f1abd13ff5f90248580f8`: list existing event category/tag terms, read one ordinary single event's exact taxonomy relationship set, and replace that exact relationship set.
- The new mutation is allowlisted to `event-categories` and `event-tags`, requires native event edit/assignment authority, exact event state, exact previous term IDs, pre-existing target terms, readback, and exact relationship rollback after verification failure.
- Term create/update/delete was deliberately not added. Relationship clearing removes only the assignment and leaves the term itself intact.
- Events Manager runtime run `34243687468` passed on WordPress 7.1 with WordPress.org Events Manager 7.4.3. Exact new output: `events-manager-taxonomy-cli: PASS abilities=3 anonymous=denied administrator=allowed taxonomy=bounded exact-replace=verified clear=relationship-only stale=blocked no-change=blocked rollback=exact event-location-control=unchanged`.
- The same run reported `wordpress-ability-registration: PASS (82 abilities)` and all prior Events Manager read/mutation/deletion regressions remained green.
- Full maintenance regression `34243687257`, content/member/navigation regression `34243687429`, and workbench integrity `34243687283` all passed on the same source checkpoint.
- Runtime capability artifact `10063123311` has SHA-256 `0cf3d8288731e5a5be8a5a5c7ef6528332f12f01387a38a1f65668a9f07ea24b`.
- Production/source branches and live Chattanooga records were not mutated.
- Recalculated next gate: obtain fresh read-only live event-taxonomy vocabulary and relationship evidence before deciding whether existing terms are sufficient or a separate term-lifecycle capability is materially necessary.

## 2026-09-08 — Fresh live festival taxonomy evidence

- Gate 18 used only read-only connected Chattanooga abilities; no live content/taxonomy mutation, candidate installation, or MCP transport change occurred.
- The live `event` type currently reports 110 published events, 1 draft, and 11 `event-categories` terms; `event-tags` is not exposed on the live event type.
- Verified relevant category identities: `Festival` term `247` (slug `festival`, parent `0`, count `5`), `Music Festivals` term `59` (slug `music-festivals`, parent `0`, count `3`), and `Live Music` term `60` (slug `live-music`, parent `0`, Events Manager count `68`; published custom-post-type filter `63`).
- The published `Festival` filter returned exactly IDs `6810`, `7800`, `7803`, `7804`, and `7806`.
- Fresh individual event reads confirmed all five Festival-category records also carry `Live Music`; three of the five also carry `Music Festivals`.
- Direct reads identified these records as festival-form events: `3 Sisters Bluegrass Festival`, `Chattanooga Oktoberfest`, `IBMA World of Bluegrass`, `Chattanooga Bluegrass Festival`, and `Chattanooga Jazz Fest`.
- Decision: existing vocabulary is sufficient. The current defect is taxonomy relationship assignment, so no event term create/update/delete capability is justified by current evidence.
- The connector reports 11 event categories but exposes no read-only list-all-category ability; a complete 11-term name catalogue is therefore not claimed. The three terms required for the current decision were independently verified by ID and taxonomy-filtered event reads.
- The prior workbench checkpoint `7bdec44c4a28ef5d81ffa5f74789701d7dd66964` re-ran cleanly: maintenance `34244282299`, content/member/navigation `34244282476`, integrity `34244282480`, and Events Manager `34244282382` all passed. Runtime artifact `10063358784` has SHA-256 `f3f13c5a2d603fcecaca8de458df18538a9216f42c2656e6eaa7e296a44695b7`.
- Recalculated next position: do not add speculative taxonomy source capability. Candidate installation/live discovery and any live relationship repair are separate production authorization gates and must begin with fresh pre-mutation evidence.

## 2026-09-08 — Live CMS Admin installation and MCP exposure checkpoint

- Production WordPress execution-verifies `Chattanooga CMS Admin` version `0.1.0` as active on WordPress 7.1 / PHP 8.2.30.
- The current MCP connection execution-verifies WordPress user ID `2` with roles `administrator` and `bbp_keymaster`.
- Live `discover_abilities` returns zero abilities for `chattanooga-cms-admin` and zero matches for the candidate event-taxonomy namespace despite the active plugin.
- Candidate source still registers the category and ability families on the native WordPress Abilities lifecycle with `public=true`, `mcp.public=true`, and capability-specific permission callbacks; reference registration remains green at 82 abilities.
- The currently exposed MCP surface does not provide miniOrange role/NHI/ability-policy inspection or mutation controls. The exact live exposure setting is therefore unresolved rather than inferred.
- Current evidence localizes the next diagnostic boundary to MCP ability exposure/governance; it does not prove a candidate registration-code defect.
- No generic taxonomy removal, WPCode, media upload, direct production-source edit, or transport replacement was used as a workaround.
- Fresh post-install reads reverified the five Festival records and their category sets: `6810` = 247/60/59, `7800` = 252/247/256/60, `7803` = 247/60/59, `7804` (live title `Chattanooga Bluegrass`) = 252/247/60, and `7806` = 252/247/60/59. The Festival/Live Music overlap remains 5/5.
- Generic CPT reads are not accepted as candidate conflict tokens. Any later repair must first obtain fresh `get-event-taxonomy` state tokens after the candidate namespace becomes visible.
- No live content or taxonomy relationship was modified.
- Recalculated next gate: expose/grant `chattanooga-cms-admin/*` through the active MCP server, rerun candidate discovery/health/read validation, and only then proceed to a separately authorized exact-state Festival relationship repair.

## 2026-09-08 — CMS Admin WordPress permission-side verification

- The live Administrator role was read directly after the MCP exposure failure.
- `edit_events` is present, along with `manage_options`, `activate_plugins`, `install_plugins`, and `update_plugins`.
- Therefore the candidate taxonomy family's WordPress capability requirement is satisfied for the current connection; role capability denial is not the cause of the missing `chattanooga-cms-admin/*` namespace.
- The unresolved production blocker remains the MCP exposure/governance boundary. No live mutation was performed.

## 2026-09-08 — miniOrange role/NHI governance boundary confirmed

- Re-ran MCP discovery and again received zero abilities for category `chattanooga-cms-admin`; the live exposure blocker remains current.
- Searched the exposed WordPress MCP surface for ability, MCP, server, and access-management controls; no miniOrange self-management ability is exposed for changing NHI/role ability grants.
- ChatGPT plugin management reports `MCP Server For WordPress` with app-specific permission mode `Allow all actions`, excluding ChatGPT-side plugin permission mode as the cause.
- miniOrange's official release notes state that v1.2.0 added per-ability MCP exposure toggles, v1.2.2 changed the NHI Registry to role-based ability grants, and v1.4.2 added a resource-by-role ability matrix.
- The same release notes state that v1.4.0 bundled abilities are reachable through the governed MCP endpoint while never being exposed through the public REST API. Candidate `show_in_rest=false` is therefore not evidence of incompatibility and remains part of the verified REST-isolation boundary.
- miniOrange's current MCP documentation describes tool discovery as exposing approved WordPress abilities after identity and role-based permission evaluation.
- Direct-repair conclusion: grant the `chattanooga-cms-admin` resource/abilities to the Administrator role for the enabled NHI used by this connection. Do not alter candidate registration code or use generic taxonomy mutation as a substitute.
- No miniOrange policy, plugin source, MCP transport, live content, or taxonomy relationship was modified.
