# Great Imports Direction

This file is the maintained project direction file for Great Imports. It is not a chat log.

## Repository roles

- `stanfieldjd/Great-imports`: plugin source code, implementation files, patches, and release source.
- `stanfieldjd/chattanooga-music-scene`: decisions, instructions, accepted direction, rejected direction, and patch guidance.

## Standing development rules

- Do not patch without research under any circumstance.
- Use a causality approach: identify cause, trace path, observe effect, correct the cause, then check regressions.
- Use the chessboard approach: evaluate the whole plugin before moving one piece.
- Organize source using WordPress plugin file structure. Do not build one large PHP file.
- Preserve accepted behavior unless a change is explicitly requested.
- Keep filtering/classification separate from rejection.
- Keep partial evidence visible rather than collapsing candidates to empty.
- Full-view-first is an unforgiving hard rule: collect and preserve broad raw source/API/page evidence before choosing relevance, normalizing, filtering, accepting/rejecting, or deciding what matters.
- Relevance decisions must come after exploratory evidence is captured and visible. Do not design reports/import paths that expose only selected fields unless explicitly requested.
- Do not hardcode source-specific shortcuts unless explicitly approved.
- Blocked or unreadable sources must be recorded as blocked/failed; do not fabricate event data.
- Do not commit plugin ZIP/build artifacts as source.
- Do not store API keys, client secrets, private tokens, or public tokens in GitHub project files.

## Standing Coordinate Governance

This section supersedes the earlier absolute “address-only handoff” rule where it conflicts.

- Events Manager remains the system of record for locations, map display, and final coordinate storage.
- Great Imports must not fabricate coordinates.
- Great Imports may carry coordinates into Events Manager only when they are verified coordinate evidence, not guesses.
- Verified coordinate evidence includes explicit venue latitude/longitude from a source API when one exists, explicit latitude/longitude discovered in captured public-page evidence, or a reviewer-approved geocode result from a configured provider.
- Great Imports must record coordinate provenance: source type, source URL or endpoint, source field path, capture run/evidence ID, and whether reviewer approval was required.
- Great Imports must distinguish source-supplied coordinates from geocoded coordinates.
- Source-supplied coordinates may be imported automatically only when the source identifies them as venue/location coordinates for the same reviewed address.
- Geocoded coordinates require a review/resolution step unless the user explicitly enables trusted automatic geocoding.
- Great Imports may write verified coordinates into Events Manager’s normal location coordinate fields so the website map works, but only as an Events Manager location write with provenance and reporting.
- Great Imports must never overwrite complete existing Events Manager coordinates unless the reviewer explicitly chooses replacement.
- Great Imports must never remove Events Manager-produced coordinates.
- When a matching Events Manager location already has complete coordinates, Great Imports should reuse it instead of creating an unresolved duplicate.
- When no verified coordinates exist, Great Imports may create an address-only draft/review location and place it in a map-resolution queue.
- Reports must show whether coordinates came from source evidence, reviewer-approved geocoding, existing EM location data, or EM browser/admin workflow.
- Reports must continue to redact raw coordinate values by default while showing presence, completeness, provenance, and decision path.

### Public URL Coordinate Evidence

Most public event URLs will not provide an API. Great Imports must therefore treat the captured public page as the primary evidence source for generic URL imports.

Allowed public-page coordinate evidence includes:

- JSON-LD, microdata, RDFa, or other structured event/venue/location markup that contains explicit latitude and longitude.
- Embedded map configuration or page scripts that contain explicit latitude and longitude tied to the displayed venue/location.
- Map links or iframe URLs that contain explicit latitude and longitude tied to the venue/location.
- Source-visible venue payloads, hydration data, or application state that identify the same reviewed location and include explicit latitude and longitude.

Public-page coordinates are source-supplied coordinates, not Great Imports geocoding, when they are explicitly present in captured page evidence.

Public-page coordinate evidence must not be inferred from nearby text, map center defaults, unrelated search results, or approximate city/region coordinates. If the page does not contain explicit venue/location coordinates, the importer must use the reviewed geocoding/resolution workflow instead.

The Eventbrite importer reference is useful because it shows the successful Events Manager handoff shape: source coordinates can be written into Events Manager location coordinate storage so the map works. Great Imports may follow that handoff shape only after its own evidence capture, provenance recording, duplicate-location checks, and overwrite rules are satisfied.


## Current patch direction — 2026-07-09

Requested move: begin with a one-time simple import from Eventbrite.

Research result:

- The new `stanfieldjd/Great-imports` repository was empty, so the first move is an initial modular source seed.
- Current scope is intentionally narrow: one Eventbrite event URL, one manual admin action, one review candidate result.
- WordPress-safe form handling requires capability checks and nonce verification.
- User-controlled URL fetching must be handled with WordPress safe HTTP methods.
- Event extraction starts with schema.org Event JSON-LD from public Eventbrite event pages.

Implemented source direction:

- Add modular plugin bootstrap and includes files.
- Add one top-level Great Imports admin page.
- Add one Eventbrite URL field and an `Import once` action.
- Validate HTTPS Eventbrite URLs only.
- Fetch the public Eventbrite page.
- Parse schema.org Event JSON-LD.
- Store the result as a private internal review candidate.
- Show recent review candidates in the admin UI.

Protected boundaries for this move:

- No scheduler.
- No saved recurring imports.
- No CSV, JSON, or ICS upload path.
- No direct Events Manager event creation yet.
- No location creation yet.
- No geocoding.
- No map-provider logic.
- No source collector control.

## UI correction — 2026-07-09

User instruction: put the plugin name on the admin bar, not under Tools.

Research result:

- The plugin was already registered as a top-level WordPress admin menu with `add_menu_page`, not under Tools.
- WordPress admin toolbar placement is handled through the `admin_bar_menu` hook and `WP_Admin_Bar::add_node()`.

Implemented source direction:

- Keep `Great Imports` as a top-level admin menu.
- Add `Great Imports` to the WordPress admin toolbar for users with `manage_options` capability.
- Toolbar link points to `admin.php?page=great-imports`.
- Do not add a Tools submenu.

## First live Eventbrite test fixture — 2026-07-09

User-selected Eventbrite detail URL:

`https://www.eventbrite.com/e/amy-ray-band-of-indigo-girls-live-at-the-boneyard-71226-tickets-1978629906313?aff=ebdssbcategorybrowse`

Research result:

- The URL is an Eventbrite detail-page shape and is valid for the current one-time Eventbrite importer.
- Browser fetch from ChatGPT was rate-limited by Eventbrite with HTTP 429, so the live page content was not verified through this environment.
- The `aff=ebdssbcategorybrowse` query parameter is tracking-only for this import use case and should not become part of duplicate identity.

Implemented source direction:

- Normalize accepted Eventbrite URLs by stripping known tracking-only query parameters such as `aff`, `utm_*`, `fbclid`, `gclid`, `_gl`, and `_ga`.
- Preserve the Eventbrite event path as the source identity.
- Do not strip unknown functional query parameters globally.

## Deep-dive result for first Eventbrite fixture — 2026-07-09

Deep dive performed after user requested deeper research.

Verified from live/open attempts:

- Direct open of the provided Eventbrite URL returned HTTP 429 Too Many Requests from Eventbrite in ChatGPT's web environment.
- Direct open of the normalized Eventbrite URL without `aff` also returned HTTP 429 Too Many Requests.
- Direct open of the Eventbrite Chattanooga live-music category page returned HTTP 405 Method Not Allowed in ChatGPT's web environment.

Verified from public search attempts:

- Exact searches for the Eventbrite numeric ID `1978629906313`, the full event slug, and exact title variants did not return an indexed Eventbrite event-detail result in ChatGPT's web search results.
- Search did return general public references confirming Amy Ray is a member of Indigo Girls, but this does not verify the Eventbrite event details.

URL-level evidence only:

- Host: `www.eventbrite.com`.
- Path shape: `/e/{slug}-tickets-{numeric_id}`.
- Numeric Eventbrite-like ID from path: `1978629906313`.
- Slug text: `amy-ray-band-of-indigo-girls-live-at-the-boneyard-71226`.
- The slug suggests, but does not independently verify, title/date/venue data. Do not treat slug-derived values as confirmed event fields unless the page content or another source verifies them.

Importer consequence:

- If the WordPress server receives HTTP 429, blocked HTML, empty body, CAPTCHA/protection page, or no schema.org Event JSON-LD, Great Imports must report the failure and must not create fabricated event data.
- If the WordPress server can fetch the page successfully, Great Imports should parse only the verified fields present in schema.org Event JSON-LD and leave missing fields empty for review.

## Uploaded Eventbrite importer reference — 2026-07-09

User uploaded `import-eventbrite-events.1.8.1.zip` and clarified that previously identified differences are options, not conflicts.

Corrected interpretation:

- Treat the uploaded plugin as a reference implementation and option source, not as a list of rejected conflicts.
- Coordinates/maps, direct publishing, location creation, scheduling, image import, cleanup, API/token mode, and Events Manager handoff are optional capabilities that may be adopted later if explicitly selected and implemented under Great Imports rules.
- The only hard boundary is that Great Imports must not fabricate data. Anything unreadable, blocked, missing, or uncertain must remain explicit and visible.
- Other capabilities are not rejected; they are staged options that need review-first behavior, source provenance, safe settings, and user approval before activation.

Useful reference direction:

- The uploaded plugin shows an Eventbrite event-ID/API style path.
- Great Imports should still accept full Eventbrite URLs, extract the numeric event ID internally, and then decide whether to use an API-based path, HTML/JSON-LD path, or both.

## Eventbrite API credentials reference — 2026-07-09

User provided a screenshot of Eventbrite Developer Links → API Keys for an app named `wordpress`.

Visible credential types in the screenshot:

- API key.
- Client secret.
- Private token.
- Public token.

Security handling direction:

- Do not store the actual credential values in GitHub or project direction files.
- Treat the screenshot as confirmation that Eventbrite API credentials exist for the account/app, not as a source file.
- Any future plugin credential UI must mask secret values, store them in WordPress options only, and avoid logging/exporting secrets in diagnostics.
- API calls should prefer the private token only if an API path is explicitly approved.
- Because the screenshot exposed secret values in chat/image form, rotating/regenerating the Eventbrite credentials is recommended before live production use.

## Eventbrite API implementation patch — 2026-07-09

User instructed: do what is needed to make it work.

Research and source basis:

- The uploaded `import-eventbrite-events.1.8.1.zip` uses an Eventbrite event-ID/API path with endpoints shaped as `/v3/events/{event_id}/?expand=venue,ticket_availability,organizer,organizer.logo,category` and `/v3/events/{event_id}/description`.
- Great Imports should not require the user to paste only the numeric ID; it should accept the full Eventbrite URL and extract the ID internally.
- Credentials must be entered locally in WordPress, not committed to GitHub.

Implemented in `stanfieldjd/Great-imports` version `0.1.1`:

- Added masked Eventbrite private-token setting in the Great Imports admin page.
- Stored the token in a WordPress option with autoload disabled.
- Never displays the saved token after save.
- Added ability to clear the saved token.
- Extracts Eventbrite numeric event IDs from full detail URLs such as `/e/...-tickets-1978629906313`.
- Tries authenticated Eventbrite API first when a private token is configured.
- Uses an Authorization header, not a token query string.
- Fetches event details with venue, ticket availability, organizer, organizer logo, and category expansions.
- Fetches the separate Eventbrite description endpoint.
- Normalizes only verified API fields into review-candidate metadata.
- Does not use latitude/longitude from Eventbrite even when present.
- Falls back to public HTML/JSON-LD if API fails.
- If API and HTML fallback both fail, records a `source_unreadable` candidate with the source URL, event ID, fetch method, and error message instead of fabricating event data.
- Recent candidates table now shows status and fetch method.

## Exploratory report patch — 2026-07-09

User requested a clean full report for everything imported/tracked, including all data retrieved from the URL.

Implemented in `stanfieldjd/Great-imports` version `0.1.2`:

- Added `GI_Exploratory_Report` as a separate report exporter class.
- Added `Download Exploratory Report` button to the Great Imports admin page.
- Report downloads as sanitized JSON.
- Report includes plugin version, WordPress/PHP environment, Events Manager detection, Eventbrite private-token configured status, summary counts, all Great Imports candidates, post title/content/status/dates, and all `_gi_*` candidate metadata.
- Report includes source URL, submitted URL, Eventbrite numeric ID, fetch method, API/HTML status codes, error messages, candidate status, normalized fields, raw Eventbrite event API payloads, raw Eventbrite description endpoint payloads, raw JSON-LD event data, and API error payloads when captured.
- Report redacts field names containing token, secret, password, authorization, bearer, API key, or client secret.
- Report does not export actual Eventbrite credentials.
- The plugin records a `source_unreadable` candidate when no verified event data can be retrieved, so failed sources remain visible in the report.

## Exploratory correction patch — 2026-07-09

User corrected that version `0.1.2` still selected relevant data instead of pulling broader exploratory source evidence.

Corrected interpretation:

- A true exploratory report should collect raw source/API evidence first and decide relevance later.
- Normalized candidate fields are still useful, but they must not be the only evidence retained.
- The report should expose what was requested, what was returned, what failed, and what was normalized.

Implemented in `stanfieldjd/Great-imports` version `0.1.3`:

- Added related Eventbrite exploratory API requests after a successful event-detail API response.
- Captures endpoint labels, endpoint paths, query args, success flag, status code, error text, and raw payloads.
- Captures the primary event detail payload under `exploratory_api_payloads.event_detail`.
- Captures the description endpoint payload under `exploratory_api_payloads.event_description`.
- Requests and captures `ticket_classes` from `/v3/events/{event_id}/ticket_classes/`.
- Requests and captures `public_collections` from `/v3/events/{event_id}/collections/public/?expand=image`.
- Requests and captures the venue endpoint when `venue_id` is available.
- Requests and captures the organizer endpoint when `organizer_id` is available.
- Requests and captures the category endpoint when `category_id` is available.
- Keeps failed related endpoint responses in the report instead of hiding them.
- Does not export credentials.

## Hard rule correction — 2026-07-09

User clarified that selecting relevance before full evidence capture is not acceptable and is an unforgiving hard rule.

Standing interpretation:

- Do not decide what data matters before collecting the broadest feasible source evidence.
- For import/report/debug work, pull and preserve the full view first.
- After the full evidence view exists, relevance, normalization, filtering, mapping, and handoff decisions can be made.
- Exploratory reports are evidence-gathering artifacts, not curated summaries.
- Any future importer design must separate raw evidence capture from later interpretation and action.

## Full evidence capture foundation — 2026-07-09

User asked to build the full data capture layer after clarifying that `0.1.3` was not enough.

Implemented in `stanfieldjd/Great-imports` version `0.2.0`:

- Added private `gi_evidence` post type as separate evidence storage.
- Added `GI_Evidence_Store` for evidence bundles with capture run IDs, source context, item counts, and stored raw items.
- Added `GI_HTTP_Evidence_Client` for source-agnostic raw HTTP GET capture.
- Public page capture now records request method, sanitized request headers, success flag, status code, error, duration, response headers, full body, body SHA-256, byte count, and content type.
- Added `GI_HTML_Evidence_Extractor` for broad non-decisional HTML evidence extraction.
- HTML extraction captures meta tags, title tags, links, images, canonical URLs, JSON-LD blocks, and script blocks without deciding relevance.
- Eventbrite imports now capture public page evidence and HTML extraction evidence before candidate interpretation.
- Eventbrite API captures now include response headers, endpoint labels, endpoint paths, query args, success flags, status codes, errors, and payloads.
- Candidate records now include `evidence_bundle_id` and `evidence_capture_run_id` so every candidate can be traced back to the raw evidence run.
- Exploratory reports now include both `evidence_records` and `candidates`; candidates are downstream interpretations.
- Report string handling was corrected to preserve raw evidence text while still redacting secret-like keys by field name.
- Uninstall cleanup now includes `gi_evidence` records when Great Imports data deletion is enabled.

Current next step:

Install/update `0.2.0`, re-run the same Eventbrite URL, then download the Exploratory Report again. The report should now contain a separate evidence record with raw page/API evidence first and candidate data second.

## Events Manager map handoff correction — 2026-07-11

User clarified the correct mapping behavior:

- Great Imports should treat imported location addresses like manually entered Events Manager locations.
- Great Imports should not manually add or hand source latitude/longitude into Events Manager location saves.
- The website map system depends on Events Manager owning geocoding/map behavior from the saved address.
- Source coordinates may still be captured as raw evidence, but they must not become the Events Manager import handoff.

Implemented source direction for `stanfieldjd/Great-imports` version `0.2.32`:

- Stop promoting source coordinates into normalized candidate location fields.
- Remove latitude/longitude from the Events Manager import payload.
- Stop assigning `location_latitude` and `location_longitude` on new `EM_Location` objects.
- Stop revisiting existing Great Imports-created EM locations to fill missing coordinates.
- Keep reviewed address fields, selected existing-location behavior, event import behavior, ticket handling, evidence capture, and reports otherwise unchanged.
- Update report/readme wording so Events Manager is documented as the owner of geocoding and map behavior.

## Events Manager location trace report — 2026-07-11

User requested a report that can trace the Events Manager location issue with before/after and possibly during states.

Report trace direction for `stanfieldjd/Great-imports` version `0.2.33`:

- Store an `_gi_em_import_trace` on the candidate each time the Events Manager import action runs.
- Capture the candidate's existing EM event/location IDs before import.
- Capture the reviewed address payload and whether the payload included any coordinate field.
- Capture the location-resolution strategy during import: reviewer-selected existing location, prior imported location reuse, or new location from reviewed address.
- Capture location snapshots before resolution, after location save/reuse, after event save, and again at exploratory-report generation time.
- Capture EM event snapshots after save and again at exploratory-report generation time.
- Report EM event ID, EM location ID, location post ID, admin edit URL, saved address fields, coordinate presence/absence, complete-coordinate state, and map-refresh-needed state.
- Redact raw latitude/longitude values and report only coordinate presence/absence.
- Preserve the Events Manager ownership rule: Great Imports may observe and report EM-produced coordinates but must not hand off, overwrite, backfill, or remove them.
- Do not change import behavior, coordinate handoff, ticket handling, publishing, scheduling, images, evidence capture, or geocoding.

## Events Manager browser OK-button trace — 2026-07-11

User clarified that the report should show before/after the Events Manager map-refresh OK button, not only before/after the import save.

Report trace direction for `stanfieldjd/Great-imports` version `0.2.34`:

- Add a Great Imports observer only on Events Manager location edit pages.
- Wrap the Events Manager map-refresh alert so the report can record `before_ok_alert` and `after_ok_alert`.
- Record short delayed snapshots after OK because Events Manager's Google geocoder callback is asynchronous.
- Record hidden coordinate-field state changes while the edit page remains open.
- Record a `before_location_form_submit` snapshot so the report can show whether the form was submitted with complete hidden coordinate fields.
- Store browser trace records on the Events Manager location post and copy them to matching Great Imports candidates by EM location ID.
- Report browser trace records under `events_manager_location_traces[].browser_location_trace`.
- Record only field presence, value presence, complete/incomplete state, address presence, labels, and timing. Do not send, store, or export raw latitude/longitude values.
- Preserve the Events Manager ownership rule: Great Imports may observe the browser flow, but it must not geocode, hand off coordinates, overwrite coordinates, backfill coordinates, remove coordinates, or change Events Manager save behavior.

## Events Manager map readiness guard — 2026-07-11

User requested a complete solution, not only a trace.

Implemented solution direction for `stanfieldjd/Great-imports` version `0.2.35`:

- Keep Events Manager as the owner of location geocoding, map coordinates, and coordinate persistence.
- Keep Great Imports out of source-coordinate handoff, coordinate writes, coordinate overwrites, coordinate backfills, and coordinate removals.
- On Events Manager location edit pages, show a Great Imports readiness notice based on Events Manager's own hidden coordinate fields.
- If an address exists but Events Manager's hidden coordinate fields are still incomplete, prevent the Update submit and tell the user to wait until coordinates are ready.
- If Events Manager's hidden coordinate fields are complete, allow the normal Update submit so EM persists its own produced coordinates.
- Continue recording the browser trace for report proof: before OK, after OK, delayed checks, coordinate-field state changes, and before submit.
- Store and report only coordinate-field presence/readiness, never raw latitude/longitude values.

## Coordinate Rule Rewrite — 2026-07-11

User identified that the strict address-only rule prevents a scalable importer for other public URLs.

Superseding direction:

- The previous `0.2.32` rule was too restrictive because it treated all coordinate handoff as forbidden.
- The Eventbrite reference importer works because it writes source venue coordinates directly into Events Manager post meta and the EM locations table.
- Great Imports should not copy that blindly, but it should adopt the better principle: verified coordinates are allowed when provenance and review rules are satisfied.
- The better rule is not “never coordinates”; it is “never fabricated, never unproven, never silent, never destructive.”
- Great Imports may import source-provided venue coordinates into Events Manager when they are explicitly present in captured evidence and tied to the reviewed location.
- Great Imports may support geocoding as a resolution workflow for public URLs that do not provide coordinates, but that workflow must be configured, traceable, and reviewable.
- Events Manager remains the final location/map storage layer; Great Imports may prepare or update EM location coordinate fields only under the coordinate governance rules above.
- This rewrite is the basis for a future implementation that reintroduces coordinate handling safely, instead of relying on EM’s browser-only map refresh workflow.
