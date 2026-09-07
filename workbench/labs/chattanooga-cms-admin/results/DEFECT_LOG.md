# Chattanooga CMS Admin Workbench Defect Log

This log records defects discovered by the disposable workbench tests. A defect is not closed merely because source was edited; closure requires the relevant regression path to pass.

## CMSA-LAB-001 — single-site cache clear fatal

- Status: RESOLVED_IN_WORKBENCH
- First failing evidence: CMS Admin Workbench Lab run `34137365750`, WordPress 7.1 runtime probe.
- Failure: `CMSA_Health::clear_cache()` called `clean_blog_cache()` unconditionally and produced a fatal error on a normal single-site WordPress install where that multisite function was unavailable.
- Root cause: single-site and multisite cache invalidation were treated as the same API surface.
- Direct repair: gate `clean_blog_cache()` behind multisite/function availability; use WordPress options-cache invalidation (`alloptions` and `notoptions`) on single-site while retaining object-cache and WP Super Cache clearing when available.
- Regression evidence: run `34137540169`, commit `44aaf8cbf9b82e1dc0ac85b98714b62ea1dfb001`; PHP 7.4, PHP 8.2, and WordPress 7.1 runtime jobs all passed, including health/cache control.
- Production state: NOT DEPLOYED.

## CMSA-LAB-002 — invalid SQL token for empty database values

- Status: RESOLVED_IN_WORKBENCH
- First failing evidence: CMS Admin Workbench Lab run `34138009398`, WordPress 7.1 database restore fidelity step.
- Failure: restore generated MySQL error `Unknown column '0x' in 'field list'` while replaying an INSERT containing an empty string.
- Root cause: the database dump serializer encoded every non-null value as a hexadecimal SQL literal. For an empty string, `bin2hex( '' )` produced an empty payload and therefore the invalid token `0x`.
- Direct repair: serialize `NULL` as `NULL`, an empty string as `''`, and non-empty byte strings as `0x<hex>`.
- Repair commit: `6326da2946323d46ba670577aceaeb10ff073d00` on `workbench/mars`.
- Regression evidence: run `34138303643`; PHP 7.4 and PHP 8.2 static labs passed, and the same disposable WordPress 7.1 runtime passed activation, 24-ability registration, plugin rollback, theme rollback, database sentinel restore, and health/cache verification.
- Production state: NOT_DEPLOYED.

## CMSA-LAB-003 — nonexistent historical package fixture

- Status: TEST_FIXTURE_CORRECTED_AND_VERIFIED
- First failing evidence: CMS Admin Workbench Lab run `34138514253`, `Prepare legacy plugin update fixture` step.
- Failure: WordPress.org returned HTTP 404 for requested Classic Editor version `1.6.0`; the CMS Admin package transaction probe did not execute.
- Root cause: the test requested a release identifier that does not exist in the official Classic Editor release history. The published historical release is `1.6`.
- Direct repair: workflow and package probe now use Classic Editor `1.6` as the update precondition.
- Repair commits: `528a55bd3534621f0e6a901d7199dfb383951d7c` and `754872f2af08394556d98cfb71ff0a30e1c8cd12` on `workbench/mars`.
- Closure evidence: repeated later full runs, including `34164417137`, successfully installed the 1.6 fixture, updated it to 1.7.0 through the candidate transaction, and passed rollback tests.
- Production state: NOT_DEPLOYED.

## CMSA-LAB-004 — numeric database columns serialized as byte literals

- Status: RESOLVED_IN_WORKBENCH
- First failing evidence: forced-core rollback run `34162636544`.
- Failure: rollback database restore encountered an overflowing/duplicate numeric primary-key condition after WordPress core update created larger option IDs.
- Root cause: the backup serializer encoded numeric primary keys as binary `0x...` literals; MySQL coerced those byte literals into unintended huge integer values during restore.
- Direct repair: inspect database column definitions and emit validated numeric SQL columns as numeric literals, while retaining distinct encodings for null, empty string, and non-empty byte/string values.
- Repair commit: `ec585e6fb3d5952e342a2931a63a782ceaa5c461`.
- Regression extension: commit `c0a54be38ed087d3b428eaa8e76d4ba7fdcaacb6` verifies exact numeric `wp_options.option_id` identity across backup/restore.
- Closure evidence: run `34162917097` passed numeric primary-key fidelity and the previously failing forced-core rollback; later full runs remain green.
- Production state: NOT_DEPLOYED.

## CMSA-LAB-005 — raw upstream diagnostics crossed public error boundary

- Status: RESOLVED_IN_WORKBENCH
- First failing evidence: run `34163733148`, `Verify error-output redaction`.
- Failure: seeded sensitive markers were returned through raw plugin-API diagnostics and raw MySQL database-restore diagnostics (`plugin_api_error_leaked_marker`, `database_restore_error_leaked_marker`).
- Root cause: several candidate paths returned upstream `WP_Error` objects or raw error text directly, bypassing a bounded public error contract.
- Direct repair: normalize public installer/updater/rollback/filesystem/lifecycle/core/database errors to candidate-owned bounded messages and safe state metadata; do not expose upstream diagnostic strings.
- Repair commit: `a21e834b7c866c696dd34015e223f099c1dd1f3d`.
- Closure evidence: run `34164107475` and later full run `34164417137` both passed `error-redaction-cli: PASS boundaries=plugin-api,plugin-updater,database-restore marker=absent rollback=verified`.
- Production state: NOT_DEPLOYED.

## CMSA-LAB-006 — database backup accepted unchecked partial writes

- Status: RESOLVED_IN_WORKBENCH
- First failing evidence: CMS Admin Workbench Lab run `34166429871` after adding the failing-first storage-integrity test.
- Failure: the candidate had no complete-write helper and `dump_database()` used raw `fwrite()` calls without proving that every requested byte reached the backup stream.
- Root cause: write success was treated as Boolean rather than byte-counted progress, so a short/stalled write such as a disk-full condition could leave truncated SQL that was later eligible for hashing/metadata.
- Direct repair: add `write_stream_all()` to continue progressive short writes until complete, fail when a write returns false/zero progress, delete incomplete SQL on any dump failure, check flush/finalization, and require complete metadata writes.
- Repair commit: `2e0230b01171e0bf1e030b9b3e6391c228401510`.
- Test correction: commit `0a069bab0b636ffa457e3a9b12c11c847a9f218c` narrowed the source-shape assertion to `dump_database()` so the helper's own checked `fwrite()` was not misclassified as an unchecked call.
- Closure evidence: run `34166633321` passed PHP 7.4, PHP 8.2, the complete WordPress 7.1 regression chain, and `backup-write-integrity-test` for progressive/stalled writes.
- Production state: NOT_DEPLOYED.

## CMSA-LAB-007 — component/core archive finalization was unchecked

- Status: RESOLVED_IN_WORKBENCH
- First failing evidence: CMS Admin Workbench Lab run `34166749580` at commit `cf3e6d2ab9dc47c348aa3cbcbfb958bafe80a35f`.
- Failure: both component and core archive writers called `ZipArchive::close()` without checking whether finalization succeeded; an incomplete archive could therefore proceed toward backup metadata.
- Root cause: archive population success was incorrectly treated as equivalent to durable archive finalization.
- Direct repair: require successful `ZipArchive::close()`, reject missing/zero-byte output, delete incomplete output, and return bounded `cmsa_zip_finalize` errors before metadata is accepted.
- Repair commit: `286a8d15d905b60380b5173cb084fabfd7c3ce43`.
- Closure evidence: CMS Admin Workbench Lab run `34166835095` passed PHP 7.4, PHP 8.2, the complete WordPress 7.1 regression chain, and exact output `backup-write-integrity-test: PASS progressive-partials=completed stalled-write=rejected database-dump=guarded archives=finalization-guarded`; multisite run `34166835071` also passed on the same candidate checkpoint.
- Production state: NOT_DEPLOYED.
