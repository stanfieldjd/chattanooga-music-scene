# Task Queue

This queue records unresolved work without treating stale site data as current evidence.

| Priority | Workstream | State | Next required position |
| --- | --- | --- | --- |
| 1 | Chattanooga CMS Admin | LIVE_MCP_RUNTIME_VERIFIED | Plugin 0.1.0 is active; all 82 `chattanooga-cms-admin` abilities are exposed; live health and exact event-taxonomy reads pass. Preserve this runtime boundary and refresh live evidence before any future mutation. |
| 2 | Event taxonomy integrity | LIVE_RELATIONSHIP_DEFECT_EXACTLY_VERIFIED_AWAITING_WRITE_AUTHORIZATION | Candidate exact-state reads confirm all five published Festival events still carry Live Music. A live relationship write remains a separate target-specific mutation and is not authorized by the current continuation. |
| 3 | WordPress/plugin/theme maintenance | LIVE_UPDATE_INVENTORY_VERIFIED_AWAITING_DEPLOYMENT_AUTHORIZATION | WordPress 7.1 is current; 9 active plugins and 4 inactive themes currently have offered updates. No update is authorized. Any future maintenance write must target one exact component, refresh live inventory/health first, and use rollback plus post-update verification. |
| 4 | Venue/location data quality | LIVE_DEFECT_INVENTORY_VERIFIED_PARTIAL_RESEARCH_COORDINATES_AND_WRITE_PENDING | Exact live defects are isolated and first-party address/postcode evidence is recorded. Replacement coordinates remain unresolved; Ross’s Landing has a 101-vs-201 Riverfront Parkway authoritative-source conflict. Continue read-only geospatial/conflict research; do not mutate locations without exact replacement evidence and target-specific authorization. |
| 5 | Event coverage gaps | VERIFY_FIRST | Recheck current authoritative venue calendars and create only events with defensible schedule data; do not invent end times. |

## Queue rules

- `LIVE_MCP_RUNTIME_VERIFIED` means the installed Chattanooga CMS Admin namespace, health path, and bounded exact taxonomy-read path have execution-verified successfully through the active production MCP connection. It does not authorize a content, maintenance, permission, or destructive mutation.
- `LIVE_RELATIONSHIP_DEFECT_EXACTLY_VERIFIED_AWAITING_WRITE_AUTHORIZATION` is current candidate read evidence of the established classification problem, not authorization to change live records.
- `LIVE_UPDATE_INVENTORY_VERIFIED_AWAITING_DEPLOYMENT_AUTHORIZATION` means the live update catalogue has been refreshed through Chattanooga CMS Admin, but no A3 live update is authorized. Do not infer deployment authority from continuation or from update availability.
- `LIVE_DEFECT_INVENTORY_VERIFIED_PARTIAL_RESEARCH_COORDINATES_AND_WRITE_PENDING` means production location defects and several non-coordinate replacements have authoritative evidence, but physical replacement coordinates and at least one address conflict remain unresolved and no live `update-location` authorization exists.
- Any future event-taxonomy repair must begin with fresh candidate `get-event-taxonomy` reads immediately before each mutation to obtain current exact state tokens; previously captured tokens are evidence, not reusable write authority.
- The Chattanooga CMS Admin event-taxonomy contract uses Events Manager event IDs, not WordPress event post IDs. Current verified mappings are `6810→1119`, `7800→1180`, `7803→1181`, `7804→1182`, and `7806→1183`; re-resolve if live identity changes.
- Any future location repair must begin with a fresh exact `get-location` read and authoritative evidence for every field being changed. Do not treat `0,0` alone as sufficient evidence to geocode an intentionally nonphysical logical location.
- Existing target vocabulary (`Festival`, `Music Festivals`, and `Live Music`) is sufficient for the current primary-form distinction. Do not add event term create/update/delete capability unless future evidence proves a concrete missing operation.
- `VERIFY_FIRST` means the task may have known historical context, but execution must begin with fresh evidence.
- Multisite/network support is not a product requirement and must not be reintroduced as a design or acceptance target.
- New CMS Admin abilities require a concrete Chattanooga administrative use; availability in an upstream API is not sufficient reason to add them.
- Media, recurrence, bookings, tickets, payments, account security, widgets/templates, location deletion, and generic option mutation remain non-automatic targets.
- A task becomes `COMPLETE` only after its task record acceptance tests pass.
- Failed or blocked work remains visible; do not silently remove it from the queue.
