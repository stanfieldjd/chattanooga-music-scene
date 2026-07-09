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

Current next step:

Install/update `0.1.3`, re-run the same Eventbrite URL, then download the Exploratory Report again. The report should now include an `exploratory_api_payloads` bundle with the extra endpoint responses in addition to the normalized candidate data.
