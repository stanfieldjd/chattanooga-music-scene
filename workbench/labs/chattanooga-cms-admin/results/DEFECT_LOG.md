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

- Status: REPAIR_UNDER_REGRESSION_TEST
- First failing evidence: CMS Admin Workbench Lab run `34138009398`, WordPress 7.1 database restore fidelity step.
- Failure: restore generated MySQL error `Unknown column '0x' in 'field list'` while replaying an INSERT containing an empty string.
- Root cause: the database dump serializer encoded every non-null value as a hexadecimal SQL literal. For an empty string, `bin2hex( '' )` produced an empty payload and therefore the invalid token `0x`.
- Direct repair: serialize `NULL` as `NULL`, an empty string as `''`, and non-empty byte strings as `0x<hex>`.
- Repair commit: `6326da2946323d46ba670577aceaeb10ff073d00` on `workbench/mars`.
- Required closure test: PHP 7.4 lab, PHP 8.2 lab, WordPress 7.1 activation, ability registry, plugin rollback, theme rollback, database sentinel restore, and health/cache must all pass in the same post-repair run.
- Production state: NOT DEPLOYED.
