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
- Live `discover_abilities` returns zero abilities for category `chattanooga-cms-admin` and zero matches for the candidate event-taxonomy namespace despite the active plugin.
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

## 2026-09-08 — Live CMS Admin MCP exposure and exact Festival taxonomy verification

- The user successfully saved the miniOrange Administrator/NHI ability policy. Fresh `discover_abilities` now returns all 82 `chattanooga-cms-admin` abilities through the production MCP connection.
- `chattanooga-cms-admin__get-health` executed successfully on production: WordPress 7.1, PHP 8.2.30, database responding, direct filesystem, writable plugin/theme/content directories, writable local backup storage, ZipArchive present, HTTPS enabled, and maintenance mode off.
- A `get-event-taxonomy` probe using WordPress post ID `6810` returned `event not found`, exposing an identifier-domain distinction rather than being treated as a plugin defect.
- Candidate `list-events` searches resolved and independently verified exact WordPress-post-to-Events-Manager mappings: `6810→1119`, `7800→1180`, `7803→1181`, `7804→1182`, and `7806→1183`.
- Fresh candidate `get-event-taxonomy` reads for `event-categories` returned exact sets: `1119=[59,60,247]`, `1180=[60,247,252,256]`, `1181=[59,60,247]`, `1182=[60,247,252]`, and `1183=[59,60,247,252]`.
- Each taxonomy event-state token matched the corresponding fresh `list-events` state token at inspection time.
- All five target relationships still contain `Live Music` term `60`; the 5/5 classification defect is now verified through the candidate's own exact-state contract.
- No `set-event-taxonomy` call or other live content/taxonomy mutation was made. `main` and `feature/chattanooga-cms-admin` remain untouched.
- Recalculated next position: production MCP exposure/read-runtime acceptance is closed. The five-event relationship repair remains a separate target-specific live mutation; refresh exact candidate state immediately before any explicitly authorized write.

## 2026-09-08 — Fresh live maintenance inventory

- Advanced the next unblocked queue item through read-only Chattanooga CMS Admin inspection; the Festival relationship repair remains separately blocked on live-write authorization.
- `chattanooga-cms-admin__list-updates` reports WordPress `7.1` as current/latest with no core update offered.
- Nine active plugins currently have offered updates: Big File Uploads `2.1.9→2.2.0`, Hide Page And Post Title `1.5.8→1.6.2`, Plugin Check `2.0.0→2.1.0`, PublishPress Capabilities `2.50.0→2.50.1`, Site Kit by Google `1.185.0→1.187.0`, WooCommerce `11.0.1→11.1.0`, WooCommerce Shipping `2.3.13→2.3.16`, WooCommerce Tax `3.6.12→3.6.15`, and WPCode Lite `2.3.8→2.3.9`.
- Four inactive themes currently have offered updates: BuddyX `5.1.5→5.1.7`, Twenty Twenty-Four `1.5→1.6`, Twenty Twenty-Three `1.6→1.7`, and Twenty Twenty-Two `2.1→2.2`.
- The active theme remains `BuddyX Child - River Rhythms v5`; BuddyX is its inactive parent and therefore remains operationally relevant to any later parent-theme update.
- Created `workbench/tasks/wordpress-maintenance.md` and recorded the exact live inventory and current authorization boundary.
- No live update, auto-update policy change, activation/deactivation, install/delete action, source modification, or content mutation was performed.
- Recalculated next position: live maintenance execution requires separate target-specific A3 authorization and must proceed one component at a time from fresh inventory/health evidence with rollback and post-update verification.

## 2026-09-08 — Live venue/location defect inventory and first-party research

- Fresh live `list-locations` inspection and exact `get-location` reads isolated definite production data defects without modifying WordPress.
- Locations `333` 1885 Grill Ooltewah, `307` Artistic Civic Theatre, and `277` Bessie Smith Cultural Center each store impossible/default coordinates `47.4,1.6` while their records identify Tennessee/Georgia physical addresses.
- Location `84` Ross’s Landing stores `https://www.riverfrontnights.com/` in the geographic `region` field. Locations `437` Baby Hughy’s Rock Spring and `444` Farm to Fork have empty postcodes and `0,0` coordinates.
- Location `331` Multiple Chattanooga Venues was preserved as a control: its own content defines it as a citywide/multi-venue logical location, so `0,0` is not treated as sufficient evidence for automatic geocoding.
- Library of Congress precedence was examined first for the proposition of current 2026 operational venue address/postcode/coordinate data. Direct locations inspected: `https://www.loc.gov/`, `https://catalog.loc.gov/`, `https://www.loc.gov/search/`, and `https://search.catalog.loc.gov/`. The direct LOC search endpoint was 403 in the retrieval environment and the current catalog search required JavaScript. The proposition was recorded `LOC_NOT_APPLICABLE` because accessible LOC holdings/scope are not competent current operational-location sources for these fields; historical/cultural intersections are separate propositions.
- After the LOC examination, current first-party/government sources established: 1885 Grill Ooltewah = `9469 Bradmore Lane Suite 101, Ooltewah, TN 37363`; Artistic Civic Theatre = `907 Gaston St., Dalton GA 30720`; Bessie Smith Cultural Center = `200 East M.L. King Boulevard` in Chattanooga; Baby Hughy’s Rock Spring = `8047 US-27, Rock Spring, GA 30739`; Farm to Fork = `120 General Lee Street, Ringgold, GA 30736`.
- Ross’s Landing has a material source conflict: City of Chattanooga Parks currently lists `101 Riverfront Pkwy`, while the current National Park Service page lists `201 Riverfront Pkwy, Chattanooga, Tennessee 37402` and identifies the park as managed by the City. The live record uses `201`; no address change was selected.
- The research therefore establishes some exact non-coordinate corrections (1885 suite/state normalization, Artistic Civic Theatre street number, Baby Hughy’s postcode, Farm to Fork postcode) but does not establish authoritative replacement coordinates. Those coordinate values remain `UNKNOWN`.
- Created and updated `workbench/tasks/venue-location-data-quality.md`; STATUS, TASK_QUEUE, and machine-readable state were advanced to the partial-research position.
- No `update-location`, event/content mutation, plugin/theme update, source change outside the workbench, or unrelated live mutation was performed.
- Recalculated next position: continue read-only geospatial evidence collection and the Ross’s Landing conflict analysis. Any live location write remains blocked until exact target values and separate target-specific authorization exist.

## 2026-09-08 — CMS Admin media administration integration

- Opened a source-only media-administration slice from the verified `workbench/mars` position; production, `main`, and `feature/chattanooga-cms-admin` were excluded from mutation.
- Added five bounded abilities to the workbench candidate: media list/get, validated base64 attachment creation, exact-state metadata update, and exact-state featured-image set/clear.
- Media creation is limited to 8 MiB decoded data, uses WordPress extension/type validation plus payload MIME inspection, never fetches arbitrary remote URLs, verifies the resulting file, and deletes only the newly created attachment if post-create verification fails.
- Metadata and featured-image mutations require exact prior state, reject stale/no-change requests, verify readback, and restore or preserve the exact prior semantic state after injected verification failures.
- Pre-integration execution at source checkpoint `fce5ef0a1cc470f483891cb60edf7a499d3584d0` passed Content Layer `34271173933`, Workbench Lab `34271173931`, Events Manager `34271173926`, and integrity `34271174027`; the WordPress 7.1 registry reported 87 abilities and the media probes passed permission, validation, rollback, and isolation gates.
- PR #9 integrated the verified source slice into `workbench/mars` at merge commit `1b73e5f8902062dc5ee9006ef30d0784438c5379`.
- Post-integration push validation passed Content Layer `34271628425`, Events Manager `34271628277`, Mars integrity `34271628426`, and Workbench Lab `34271628265`, including PHP 7.4/8.2, WordPress 7.1 registry/REST isolation, backup/update/rollback, normal core update, and forced core rollback.
- Workbench task/status/queue/state records were advanced to distinguish the 87-ability workbench candidate from the still-deployed 82-ability production runtime.
- No media capability from this slice was deployed to production; no live media/content mutation occurred.
- Recalculated position: source-only functionality engineering may continue from the verified 87-ability workbench candidate. Production media deployment, the five-event Festival relationship repair, and other live mutations remain separate explicit authorization gates.

## 2026-09-08 — CMS Admin Weekend Feature adapter integration

- Opened a source-only Layer G Weekend Feature adapter from the verified 87-ability workbench position; production, `main`, `feature/chattanooga-cms-admin`, live Weekend Feature settings/posts, and miniOrange policy were excluded from mutation.
- Added four typed Chattanooga CMS Admin abilities: bounded Weekend Feature status, exact settings replacement, guarded current-weekend draft generation, and guarded immediate publication through the source-owned generation path.
- Real WordPress first-save diagnostics exposed a source defect in Weekend Feature 0.2.1: only the option-update hook synchronized the scheduler, so initial option creation could persist enabled settings without creating the Thursday cron event.
- Repaired that source defect directly in the workbench Weekend Feature plugin, advancing its verified contract to 0.2.2 and registering the source-owned add-option synchronization hook rather than adding an adapter bypass or duplicate scheduler implementation.
- Strengthened adapter rollback so settings fault injection restores the exact prior cron timestamp, recurrence schedule, and args as well as the exact prior option state.
- Source checkpoint `e0e29c0786f8a2ae21d070d64d0d2408138d74f4` passed the complete source regression set, including 91-ability registration, dependency fail-closed behavior, exact settings/schedule state, stale/no-change rejection, draft generation, immediate publication in disposable WordPress, injected rollback, and unrelated post/event isolation.
- PR #10 integrated the verified source slice into `workbench/mars` at merge commit `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98`.
- Post-integration push validation passed Workbench Lab `34276153946`, Mars integrity `34276153954`, Weekend Feature `34276153963`, Events Manager `34276154034`, and Content Layer `34276154008`.
- The source/workbench task is complete at 91 candidate abilities. Production remains at the independently verified 82 exposed abilities; neither the five media abilities nor the four Weekend Feature abilities/source contract 0.2.2 were deployed by this work.
- No live Weekend Feature settings change, draft generation, publication, taxonomy/content/media mutation, miniOrange policy change, or production deployment occurred.
- Recalculated position: continue source-only Chattanooga CMS Admin functionality engineering from the verified 91-ability workbench candidate. Any production media/Weekend promotion and the five-event Festival relationship repair remain separate explicit authorization gates.

## 2026-09-09 — CMS Admin WooCommerce product administration integration

- Continued the source-only Chattanooga CMS Admin functionality workflow from the verified 91-ability workbench candidate and kept production WordPress, `main`, `feature/chattanooga-cms-admin`, live WooCommerce products/orders/customers, and miniOrange policy outside the mutation set.
- Built a dedicated typed WooCommerce product adapter instead of broadening the generic WordPress content/postmeta service. The accepted slice adds four abilities: bounded product list, exact product get, guarded `WC_Product_Simple` draft creation, and exact-state `WC_Product_Simple` update through WooCommerce native product objects/data stores.
- The initial WooCommerce runtime gate rejected two incorrect assumptions before implementation: WooCommerce 11.0.1 has no `wc_get_product_statuses()` helper, and product `edit_product`/`read_product`/`delete_product` capabilities are object-scoped mapped capabilities rather than global primitive permissions.
- The first complete product transaction run exposed an unrelated-order isolation mismatch. Dedicated diagnosis established that `wc_create_order()` represented a zero total as `"0"` in memory while a persisted reload represented the same zero as `"0.00"` before any product operation; the probe was repaired at its baseline rather than weakening or deleting the isolation assertion.
- Workflow `34321646228` passed the corrected 95-ability WooCommerce transaction suite, including create/get/list/update, stale/no-change rejection, exact-state conflict behavior, injected-failure rollback, REST isolation, and unrelated product/post/event/media/customer/order preservation.
- Unchanged pre-integration regressions then passed: Mars integrity `34363584118`, Content Layer `34363650727`, Events Manager/Weekend Feature `34363690700`, and CMS Admin Workbench `34363759870` with PHP 7.4, PHP 8.2, and the WordPress 7.1 maintenance/runtime chain. Temporary source-branch trigger scaffolding was removed before integration.
- The accepted source tree was integrated cleanly into `workbench/mars` at `6ec24eb534a32e9d6e4446cdf5c68f3602696081`, preserving pre-slice workbench commit `f9bacf3291bd382030c945521414ae25aa86967c` as the rollback parent and excluding temporary trigger history.
- Post-integration validation passed all five triggered workflows: Mars integrity `34364494618`, WooCommerce Product Lab `34364494683`, Events Manager/Weekend Feature `34364494803`, Content Layer `34364494823`, and CMS Admin Workbench `34364494740`; the Workbench run passed PHP 7.4, PHP 8.2, and the complete WordPress 7.1 runtime/maintenance job.
- The WooCommerce task record was advanced to `WORKBENCH_INTEGRATED_VERIFIED` at commit `4947b2e58cf79f46a532037e5b1cf2a8c12c496a`. Protocol state reconciliation then advanced STATUS at `8d8ffda2741b80a605e95f0a8d51dcbcb8487b03`, TASK_QUEUE at `a158a70399f3404e5009b21f51fd541fc723dcfb`, and machine state at `a9d625ce5ef270db6e9863e6b11400ef027e5c20` to the verified 95-ability position; the final integrity gate follows this journal append.
- Production remains independently verified at 82 exposed abilities. The four WooCommerce product abilities are workbench-only; no production deployment, live product mutation, product publication/deletion, order/customer/financial access, or other live commerce effect occurred.
- Recalculated position: the WooCommerce product source slice is integrated and execution-verified at 95 workbench abilities. Continue source-only functionality engineering only after the reconciled workbench state passes its integrity gate; all production promotion and live mutation boundaries remain separate.

## 2026-09-09 — CMS Admin Marketplace listing read integration

- Continued source-only Chattanooga CMS Admin engineering from the verified 95-ability workbench candidate. Production WordPress, `main`, `feature/chattanooga-cms-admin`, live listings/products/orders/customers/payments, miniOrange policy, and Marketplace presentation behavior remained outside the mutation set.
- The source task established the exact AWP Classifieds 4.4.8 model before implementation and added only two bounded read abilities: Marketplace listing inventory and exact listing get through the native collection/renderer/authorization contract.
- The adapter uses the non-mutating `has_expired()` predicate and a strict output allowlist. Seller/contact identity, email, phone, access/edit keys, IP address, payment state/email/term, user/member records, payment rows, and arbitrary listing metadata are excluded.
- Listing creation/update/delete/publication, checkout, orders, customers, payments, taxes, shipping, coupons, seller payouts, generic postmeta/SQL mutation, and unknown Checkout Bridge internals were not added.
- The task reconciled the exact source-owned Chattanooga Music Marketplace 0.1.1 files from current `main` for coexistence testing without editing their bytes. Unified Marketplace search/category/price/location presentation remained green and no customer-facing WooCommerce/supplier/commerce-engine label was introduced.
- Final source-record checkpoint `8a171a1d0331d3e1e659c06d13ac7733cc4d1102` passed complete pre-integration run `34383245023`, including PHP 7.4/8.2 source labs, WordPress 7.1 + AWP 4.4.8 runtime, AWP 4.4.7 mismatch rejection, prior content/media/navigation/member, Events Manager/Weekend Feature, WooCommerce, maintenance/rollback, Marketplace presentation, and workbench integrity gates.
- After explicit user authorization, PR #11 integrated the verified slice into `workbench/mars` at merge commit `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3`, with prior workbench checkpoint `42bb803e3cbb0a22ea06a44584c617382d9ae7f4` preserved as the first parent rollback point.
- All six post-integration workflows on that exact merge commit passed: Events Manager `34384047941`, Content Layer `34384047961`, WooCommerce Product `34384047891`, Mars Workbench Integrity `34384047944`, CMS Admin Workbench `34384047900`, and Chattanooga Music Marketplace `34384047916`.
- STATUS, TASK_QUEUE, task record, and machine-readable state are being reconciled in the same workbench transaction to the 97-ability position; the integrity gate for this records commit follows this journal append.
- Production remains independently verified at 82 exposed abilities. The two Marketplace listing read abilities are workbench-only; no production deployment or live Marketplace mutation occurred.
- Recalculated position: continue source-only Chattanooga CMS Admin functionality engineering from the verified 97-ability workbench candidate after the reconciled records commit passes integrity. Any production promotion or live mutation remains a separate authorization gate.
