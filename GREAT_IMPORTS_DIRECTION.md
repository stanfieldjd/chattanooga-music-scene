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
- Do not hardcode source-specific shortcuts unless explicitly approved.
- Blocked or unreadable sources must be recorded as blocked/failed; do not fabricate event data.
- Do not commit plugin ZIP/build artifacts as source.

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

Next likely move:

After this initial Eventbrite candidate path is tested in WordPress, the next patch should connect review candidate actions to explicit accept/reject/update behavior before adding scheduler or Events Manager handoff.
