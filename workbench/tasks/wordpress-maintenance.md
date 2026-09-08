# Task: WordPress / Plugin / Theme Maintenance

Status: LIVE_UPDATE_INVENTORY_VERIFIED — NO UPDATE AUTHORIZED

## Objective

Maintain Chattanooga Music Scene core, plugins, and themes through the bounded Chattanooga CMS Admin maintenance layer, one component at a time, with rollback and post-update verification.

## Target set

- Read-only live update inventory for WordPress core, installed plugins, and installed themes.
- Future component updates only when separately authorized for the exact live target.
- Workbench records on `workbench/mars`.

## Exclusion set

- No plugin, theme, or WordPress core update without target-specific live deployment authorization.
- No auto-update policy changes.
- No plugin/theme activation, deactivation, install, or deletion.
- No source changes to `main`, `feature/chattanooga-cms-admin`, or live production plugin/theme files.
- No content, taxonomy, navigation, member, event, location, booking, payment, or permission mutation.

## Evidence

Fresh production `chattanooga-cms-admin__list-updates` execution on 2026-09-08 reports WordPress `7.1` as current/latest and identifies 9 plugin updates plus 4 inactive-theme updates.

### Plugin updates currently offered

1. `tuxedo-big-file-uploads/tuxedo_big_file_uploads.php` — Big File Uploads `2.1.9` → `2.2.0`.
2. `hide-page-and-post-title/hide-page-and-post-title.php` — Hide Page And Post Title `1.5.8` → `1.6.2`.
3. `plugin-check/plugin.php` — Plugin Check (PCP) `2.0.0` → `2.1.0`.
4. `capability-manager-enhanced/capsman-enhanced.php` — PublishPress Capabilities `2.50.0` → `2.50.1`.
5. `google-site-kit/google-site-kit.php` — Site Kit by Google `1.185.0` → `1.187.0`.
6. `woocommerce/woocommerce.php` — WooCommerce `11.0.1` → `11.1.0`.
7. `woocommerce-shipping/woocommerce-shipping.php` — WooCommerce Shipping `2.3.13` → `2.3.16`.
8. `woocommerce-services/woocommerce-services.php` — WooCommerce Tax `3.6.12` → `3.6.15`.
9. `insert-headers-and-footers/ihaf.php` — WPCode Lite `2.3.8` → `2.3.9`.

All nine are active and currently have auto-update disabled.

### Theme updates currently offered

1. `buddyx` — BuddyX `5.1.5` → `5.1.7` — inactive parent theme while `BuddyX Child - River Rhythms v5` is active.
2. `twentytwentyfour` — Twenty Twenty-Four `1.5` → `1.6` — inactive.
3. `twentytwentythree` — Twenty Twenty-Three `1.6` → `1.7` — inactive.
4. `twentytwentytwo` — Twenty Twenty-Two `2.1` → `2.2` — inactive.

WordPress core reports `7.1` as `latest`; no core update is currently offered.

## Planned mutation set

None in the current authorization state. A future live update must be a separate transaction for one exact component and must start from a fresh inventory and health/rollback check.

## Risks

- Update inventory can become stale between inspection and a future write.
- Updating WooCommerce or its service extensions can materially affect checkout, tax, shipping, and compatibility surfaces.
- Updating the BuddyX parent theme can materially affect the active child theme even though the parent itself is inactive.
- Updating security/permissions or code-injection-adjacent plugins can alter administrative behavior.
- A successful package install is not sufficient acceptance; the component version, activation state, site bootstrap, and relevant functional surface must be verified after each update.

## Rollback point

No production mutation has been performed in this task position. The current read-only inventory is the checkpoint. Any future component update must first create and verify the rollback artifact required by Chattanooga CMS Admin for that component.

## Acceptance tests

Current inspection phase:

- WordPress core update state refreshed successfully.
- Installed plugin update state refreshed successfully.
- Installed theme update state refreshed successfully.
- No live maintenance mutation occurred.

Future one-component update transaction:

- Exact pre-update version re-read and matches the authorized target.
- Candidate health/backup prerequisites pass.
- Component rollback archive/snapshot is created and verified.
- Only the authorized component is updated.
- Expected target version is installed and activation state is preserved.
- Site bootstrap and relevant functional checks pass.
- Unrelated components remain unchanged.
- Rollback remains available or is executed if validation fails.

## Source branch and observed source state

- Production source branch remains `main` at verified baseline `d3167ab8d084523c63d22004955d781962e41623`.
- Chattanooga CMS Admin candidate checkpoint remains `62e46bb64514973a640f1abd13ff5f90248580f8`.
- Workbench branch: `workbench/mars`.

## Production state

- WordPress `7.1` is current/latest.
- 9 active plugins have offered updates.
- 4 inactive themes have offered updates.
- No update, auto-update policy, activation state, source, or live content change was performed by this inspection.

## Recalculated next position

The maintenance inventory is verified. Live update execution remains blocked on separate target-specific deployment authorization; do not infer that authorization from continuation or from the existence of available updates.