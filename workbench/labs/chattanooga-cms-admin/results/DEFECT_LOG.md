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
- Production state: NOT DEPLOYED.

## CMSA-LAB-003 — nonexistent historical package fixture

- Status: TEST_FIXTURE_CORRECTED
- First failing evidence: CMS Admin Workbench Lab run `34138514253`, `Prepare legacy plugin update fixture` step.
- Failure: WordPress.org returned HTTP 404 for requested Classic Editor version `1.6.0`; the CMS Admin package transaction probe did not execute.
- Root cause: the test requested a release identifier that does not exist in the official Classic Editor release history. The published historical release is `1.6`.
- Direct repair: workflow and package probe now use Classic Editor `1.6` as the update precondition.
- Repair commits: `528a55bd3534621f0e6a901d7199dfb383951d7c` and `754872f2af08394556d98cfb71ff0a30e1c8cd12` on `workbench/mars`.
- Required closure test: the package transaction probe must execute and independently verify plugin install/activate/deactivate, theme install, Classic Editor update from 1.6 to a newer release, and creation of a rollback backup id.
- Production state: NOT DEPLOYED.
