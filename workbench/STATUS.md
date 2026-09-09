# Workbench Status

Last verified: 2026-09-09

## Repository baseline

- Repository: `stanfieldjd/chattanooga-music-scene`
- Production source branch: `main`
- Verified `main` head: `f1c4c128f29215698a2408880a010e49faccac58`
- Workbench branch: `workbench/mars`
- `main` and `feature/chattanooga-cms-admin` were not mutated by the latest workbench increment. The workbench source-controlled Weekend Feature dependency was advanced from 0.2.1 to 0.2.2 only on `workbench/mars` as part of an execution-verified direct source repair. The exact source-owned Chattanooga Music Marketplace 0.1.1 files from current `main` are present in the workbench only as an unchanged coexistence dependency for the Marketplace listing adapter.

## Active source workstreams

### Chattanooga CMS Admin

- Branch: `feature/chattanooga-cms-admin` remains at verified source checkpoint `0b34773ebc8073cb657477770b34cabc280f5892` and was not modified.
- Source path: `site-plugins/chattanooga-cms-admin`.
- Workbench lab: `workbench/labs/chattanooga-cms-admin`.
- Immutable lab baseline remains an exact Git-blob mirror of the verified source checkpoint.
- Mutable coding candidate: `workbench/labs/chattanooga-cms-admin/candidate`.
- Event-taxonomy candidate checkpoint remains `62e46bb64514973a640f1abd13ff5f90248580f8`.
- Media implementation source checkpoint is `fce5ef0a1cc470f483891cb60edf7a499d3584d0`; PR #9 integrated the verified media slice into `workbench/mars` at `1b73e5f8902062dc5ee9006ef30d0784438c5379`.
- Weekend Feature adapter source checkpoint is `e0e29c0786f8a2ae21d070d64d0d2408138d74f4`; PR #10 integrated the verified Weekend Feature slice into `workbench/mars` at `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98`.
- WooCommerce product source branch `work/cmsa-woocommerce-products` was accepted from source checkpoint `12b06e77e32e0988242a79d5ea5586b247355478` and cleanly integrated into `workbench/mars` at `6ec24eb534a32e9d6e4446cdf5c68f3602696081`.
- Marketplace listing source branch `work/cmsa-marketplace-listings` was accepted from final source-record checkpoint `8a171a1d0331d3e1e659c06d13ac7733cc4d1102`; PR #11 integrated the verified Marketplace listing read slice into `workbench/mars` at `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3`.
- Real reference registry after Marketplace listing integration: 97 abilities = 24 maintenance + 14 content CRUD/revision + 2 permanent content deletion + 2 status + 12 content taxonomy + 5 media + 8 navigation + 4 member-read + 2 member-mutation + 4 event/location read + 4 event/location mutation + 3 event lifecycle + 3 event taxonomy + 4 Weekend Feature + 4 WooCommerce product + 2 Marketplace listing read.
- Two bounded Marketplace listing read abilities are execution-verified in the workbench candidate: bounded listing inventory and exact listing get through AWP Classifieds 4.4.8 native collection, renderer, and authorization services.
- The Marketplace listing adapter is pinned to AWP Classifieds 4.4.8, requires administrator-level AWP authority plus object-scoped read/edit authority, uses the non-mutating `has_expired()` predicate, keeps `show_in_rest=false`, and fails closed when AWP is missing or version-incompatible.
- Marketplace listing output is explicitly allowlisted and excludes listing access/edit keys, seller/contact identity, email, phone, IP address, payment state/email/term, user records, payment rows, and arbitrary metadata. No listing create/update/delete/publication, checkout, order, customer, payment, tax, shipping, coupon, or seller-payout ability is included in this slice.
- The adapter coexists with Chattanooga Music Marketplace 0.1.1 without modifying its unified Marketplace presentation or introducing a customer-facing WooCommerce/supplier/commerce-engine label.
- Pre-integration Marketplace validation passed final source run `34383245023`, including PHP 7.4/8.2 complete source labs, WordPress 7.1 + AWP 4.4.8 runtime, AWP 4.4.7 mismatch fail-closed behavior, content/member/navigation/media, Events Manager/Weekend Feature, WooCommerce products, maintenance/backup/update rollback, Marketplace presentation regression, and workbench integrity.
- Post-integration validation for `8bcfc44598c6799ba4e8035cbc3cbccb7f4b51c3` passed Events Manager `34384047941`, Content Layer `34384047961`, WooCommerce Product `34384047891`, Mars Workbench Integrity `34384047944`, CMS Admin Workbench `34384047900`, and Chattanooga Music Marketplace `34384047916`.
- Four bounded WooCommerce product abilities are execution-verified in the workbench candidate: bounded product list, exact product get, guarded simple-product draft creation, and exact-state simple-product update through WooCommerce native product objects/data stores.
- The WooCommerce product adapter is pinned to the execution-verified WooCommerce 11.0.1 contract, keeps `show_in_rest=false`, requires native object-scoped product authority, rejects stale/no-change mutation state, verifies readback, and proves rollback after injected verification failure.
- WooCommerce product mutation remains limited to `WC_Product_Simple`; variable/grouped/external product mutation, product publication/deletion, orders, customers, payments, coupons, taxes, shipping, and arbitrary product postmeta are not exposed by this slice.
- Pre-integration WooCommerce validation passed product run `34321646228` and unchanged regression runs: integrity `34363584118`, content `34363650727`, Events Manager/Weekend Feature `34363690700`, and workbench PHP 7.4/PHP 8.2/WordPress 7.1 maintenance `34363759870`.
- Post-integration validation for `6ec24eb534a32e9d6e4446cdf5c68f3602696081` passed Mars Workbench Integrity `34364494618`, WooCommerce Product Lab `34364494683`, Events Manager/Weekend Feature `34364494803`, Content Layer `34364494823`, and CMS Admin Workbench Lab `34364494740`; the Workbench Lab passed PHP 7.4, PHP 8.2, and the complete WordPress 7.1 runtime/maintenance chain.
- Four bounded Weekend Feature abilities are execution-verified in the workbench candidate: exact status, exact settings replacement, guarded current-weekend draft generation, and guarded immediate publication through the source-controlled Weekend Feature plugin.
- The Weekend Feature adapter is pinned to source contract `0.2.2`, remains MCP-visible/public-REST-hidden, requires native WordPress authority, rejects stale/no-change state, verifies settings/schedule and generated feature readback, and rolls back injected verification failures.
- Real WordPress diagnostics exposed a source-owned first-save scheduler defect: only `update_option_cms_weekend_post_settings` was registered, so initial option creation could persist enabled settings without scheduling the Thursday cron. The direct source repair added the corresponding `add_option_...` synchronization hook in Weekend Feature 0.2.2; no adapter-owned duplicate scheduler or bypass was introduced.
- Weekend scheduler rollback now preserves and verifies the exact prior cron timestamp, recurrence schedule, and args rather than reconstructing an approximate schedule.
- Post-integration push validation for the Weekend Feature slice passed: Workbench Lab `34276153946`, Mars Workbench Integrity `34276153954`, Chattanooga Music Scene Weekend Feature `34276153963`, Events Manager Lab `34276154034`, and Content Layer Lab `34276154008`.
- Five bounded media abilities are execution-verified in the workbench candidate: list media, get media, create one bounded validated base64 attachment, update exact-state attachment metadata, and set/clear an exact-state post/page featured-image relationship.
- Media creation is capped at 8 MiB decoded, performs WordPress extension/type validation plus payload MIME verification, never fetches arbitrary remote URLs, verifies resulting file size/hash/type, and removes a just-created attachment if verification fails.
- Media metadata and featured-image writes reject stale/no-change state, verify readback, roll back or preserve the exact prior semantic relationship after injected verification failure, and preserve unrelated control objects.
- Media permissions and object scope were execution-verified: anonymous/subscriber access is denied, native upload/edit authority is required, and users lacking `edit_others_posts` are scoped to their own attachments.
- Post-media-integration push validation passed: Content Layer Lab `34271628425`, Events Manager Lab `34271628277`, Mars Workbench Integrity `34271628426`, and CMS Admin Workbench Lab `34271628265`; the latter passed PHP 7.4, PHP 8.2, WordPress 7.1 registry/permission/REST-isolation, backup/update/rollback, normal core update, and forced-core rollback coverage.
- Latest earlier post-taxonomy maintenance/workbench rerun `34245410480` passed the WordPress 7.1 runtime probe and PHP 7.4/8.2 labs.
- Latest earlier post-taxonomy content/member/navigation rerun `34245410459` passed.
- Latest earlier post-taxonomy Events Manager rerun `34245410524` passed against WordPress.org Events Manager 7.4.3.
- Event taxonomy contract verified in the reference runtime: `event-categories` and `event-tags` can be registered on `event`; assignment uses native `edit_events` authority; exact assignment and relationship clearing work; relationship clearing leaves the terms themselves intact.
- Three bounded event-taxonomy abilities are verified in the candidate: list existing event taxonomy terms, read one ordinary single event's exact category/tag relationship set, and replace that exact relationship set.
- Event taxonomy mutation requires exact event state plus exact previous relationship state, validates target terms already exist, rejects stale/no-change writes, verifies readback, rolls relationships back after injected verification failure, and preserves event core state, referenced venue state, and an unrelated control event.
- Fresh live Chattanooga evidence confirms the current `event` type exposes `event-categories` but does not expose `event-tags`; the candidate already fails closed when an allowlisted taxonomy is unavailable.
- The live target vocabulary is sufficient: `Festival` is term 247, `Music Festivals` is term 59, and `Live Music` is term 60.
- All five currently published events assigned `Festival` — WordPress post IDs `6810`, `7800`, `7803`, `7804`, and `7806` — still also carry `Live Music`.
- Live MCP event-ID mapping is now exact and independently read through Chattanooga CMS Admin: `6810→1119`, `7800→1180`, `7803→1181`, `7804→1182`, `7806→1183`.
- Fresh exact candidate relationship reads returned: event `1119` = `[59,60,247]`; `1180` = `[60,247,252,256]`; `1181` = `[59,60,247]`; `1182` = `[60,247,252]`; `1183` = `[59,60,247,252]`.
- Therefore the current festival-vs-Live-Music issue remains a relationship-classification issue under the existing project rule; no event term create/update/delete capability is justified by current evidence.
- Live installation is execution-verified: WordPress production reports `Chattanooga CMS Admin` version `0.1.0` active on WordPress 7.1 / PHP 8.2.30.
- The current MCP connection is execution-verified as WordPress user ID 2 with roles `administrator` and `bbp_keymaster`.
- The live `administrator` capability list explicitly includes `edit_events`, `manage_options`, `activate_plugins`, `install_plugins`, `update_plugins`, and the other native authorities required by the deployed candidate families.
- ChatGPT plugin permissions for `MCP Server For WordPress` are set to `Allow all actions`.
- miniOrange policy was successfully saved by the user and live discovery exposes the currently deployed 82 `chattanooga-cms-admin` abilities through the current MCP connection.
- Live `chattanooga-cms-admin__get-health` execution passed on production: database responding, direct filesystem method, plugin/theme/content directories writable, backup storage available+writable, ZipArchive available, maintenance mode off, and HTTPS enabled.
- The first exact taxonomy call using WordPress post ID `6810` correctly failed `event not found`; bounded candidate event search established that this API's `id` is the Events Manager event ID, not the WordPress event post ID. The exact five mappings above were then verified by both title and `post_id` before taxonomy reads proceeded.
- Each `get-event-taxonomy` state token exactly matched the corresponding state token returned by the fresh candidate event search at the time of inspection.
- Candidate source continues to keep `show_in_rest=false`; no public-REST relaxation or generic taxonomy workaround was needed.
- No generic `mosmcp__cpt-remove-terms`, WPCode, direct production-source workaround, or source change was used.
- Media production gate: the five workbench media abilities are E2 source/runtime verified but are NOT deployed to production. Any production promotion is a separate A3 action requiring explicit authorization and subsequent live MCP discovery/read validation.
- Weekend Feature production gate: the four new workbench Weekend Feature abilities and source contract 0.2.2 are E2 source/runtime verified but are NOT deployed to production. Live settings mutation, draft creation, or publication remains a separate target-specific production action with its own authorization class.
- WooCommerce product production gate: the four new workbench WooCommerce product abilities are E2 source/runtime verified but are NOT deployed to production. Live product creation/update, publication, deletion, order/customer access, or other commerce mutation remains outside this source-only transaction.
- Marketplace listing production gate: the two workbench Marketplace listing read abilities are E2 source/runtime verified but are NOT deployed to production. Live listing reads through these new abilities require a later A3 promotion and fresh MCP discovery; listing mutation was not added and remains outside this slice.
- Event-taxonomy production gate: a live five-event relationship repair is a separate target-specific mutation and remains unauthorized. If authorized, refresh each candidate taxonomy snapshot immediately before its write, remove only term `60` while preserving every other current category, and verify readback through the guarded candidate ability.
- Production state: plugin active; 82 MCP abilities exposed; health/read runtime acceptance passed; five exact taxonomy defects execution-verified; the five media abilities, four Weekend Feature abilities, four WooCommerce product abilities, and two Marketplace listing read abilities remain workbench-only; no live taxonomy/content/media/Weekend Feature/WooCommerce/Marketplace write was made by this functionality increment.

### WordPress / plugin / theme maintenance

- Fresh live `chattanooga-cms-admin__list-updates` execution reports WordPress `7.1` as current/latest; no core update is offered.
- 9 active plugins currently have offered updates: Big File Uploads `2.1.9→2.2.0`, Hide Page And Post Title `1.5.8→1.6.2`, Plugin Check `2.0.0→2.1.0`, PublishPress Capabilities `2.50.0→2.50.1`, Site Kit by Google `1.185.0→1.187.0`, WooCommerce `11.0.1→11.1.0`, WooCommerce Shipping `2.3.13→2.3.16`, WooCommerce Tax `3.6.12→3.6.15`, and WPCode Lite `2.3.8→2.3.9`.
- 4 inactive themes currently have offered updates: BuddyX `5.1.5→5.1.7`, Twenty Twenty-Four `1.5→1.6`, Twenty Twenty-Three `1.6→1.7`, and Twenty Twenty-Two `2.1→2.2`.
- The active theme remains `BuddyX Child - River Rhythms v5`; BuddyX is its inactive parent and therefore still carries active-site compatibility risk if later updated.
- No update, auto-update policy, activation state, install/delete action, live source, or content change was performed by this inventory pass.
- Maintenance execution is not authorized by the current continuation. Any later live update must target one exact component, begin with fresh inventory/health evidence, use the candidate rollback contract, and validate the post-update live state before moving to another component.

### Venue / location data quality

- Fresh live location inventory and exact `get-location` reads confirmed a high-confidence defect set without changing production data.
- Locations `333` (1885 Grill Ooltewah), `307` (Artistic Civic Theatre), and `277` (Bessie Smith Cultural Center) each store impossible/default coordinates `47.4,1.6` despite Tennessee/Georgia physical addresses.
- First-party research after the required Library of Congress applicability examination established additional non-coordinate defects: 1885 Grill Ooltewah’s first-party address includes `Suite 101` and uppercase `TN`; Artistic Civic Theatre’s first-party theater address is `907 Gaston St`, while live stores `905 Gaston St`.
- Bessie Smith Cultural Center’s first-party visitor page confirms `200 East M.L. King Boulevard` in Chattanooga, so its live street address is semantically consistent; its incorrect coordinates remain the defect.
- Location `84` Ross’s Landing has a URL stored in the geographic `region` field. City of Chattanooga currently lists `101 Riverfront Pkwy`, while the current National Park Service page lists `201 Riverfront Pkwy, Chattanooga, Tennessee 37402` and says the site is managed by the City. The address conflict remains explicit; live currently uses `201`, so no address change is justified from the current evidence.
- Location `437` Baby Hughy’s Rock Spring has an empty postcode; the operator’s site confirms `8047 US-27, Rock Spring, GA 30739`.
- Location `444` Farm to Fork has an empty postcode; the operator’s site confirms `120 General Lee Street, Ringgold, GA 30736`.
- Many other physical venue records store `0,0`; this remains a research class, not permission to mass-geocode. Location `331` `Multiple Chattanooga Venues` is explicitly a logical multi-venue location and is excluded from automatic geocoding despite `0,0`.
- Correct replacement coordinates for the physical invalid/zero-coordinate records remain unverified. No generic geocoder output has been substituted for authoritative geospatial evidence.
- No live location write, event mutation, source change, or unrelated state change was performed.
- Next gate: continue read-only geospatial evidence collection and resolve the Ross’s Landing address/region conflict where possible. A live `update-location` transaction remains target-specific and unauthorized until exact replacement values are established and separately authorized.

### Chattanooga Music Scene Weekend Feature

- Source path: `site-plugins/chattanooga-music-scene-core`.
- Workbench source version: `0.2.2` at integration checkpoint `4121c0c1b7315b88a8719bde9fb4f1a5dab30e98`.
- Version 0.2.2 repairs first-save schedule synchronization by handling source-owned option creation as well as option update.
- Direct Weekend Feature workflow `34276153963` and integrated Events Manager workflow `34276154034` passed after the repair.
- Production source/version/state was not changed or inferred from workbench integration and must be verified independently before any deployment claim.

## Site-operation state

The workbench does not cache live WordPress state as authoritative. Before any live mutation, query Chattanooga Music Scene again and treat that result as the current site position.

## Current engineering principle

`plugin/` is the immutable source checkpoint; `candidate/` is the coding surface. Source evidence, reference-runtime evidence, Chattanooga/DreamHost evidence, deployment, live MCP exposure, and live mutation verification remain separate states. Successful GitHub/reference-runtime tests do not establish production behavior.