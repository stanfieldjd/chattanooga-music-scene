# Rolling Context

This file is the compact working memory for this repository.

## Purpose

Keep a current, concise state file that ChatGPT can read before making future repository updates.

## Standing GitHub update rule

Whenever files in this repository are changed through ChatGPT, also update:

- `docs/chat-log.md`
- `docs/rolling-context.md`

The chat log should capture the conversation/action checkpoint. This rolling context should capture only the latest usable state, rules, decisions, and next steps.

## Current state — 2026-07-06

- Repository: `stanfieldjd/chattanooga-music-scene`
- Chat tracking files are initialized.
- The user wants GitHub file updates to include `docs/chat-log.md` and `docs/rolling-context.md` updates in the same pass.
- This does not run as a silent background automation after every message. It is performed whenever ChatGPT is actively updating GitHub files.
- Current Great Imports working line in chat: `Great Imports 0.4.55-simulation-matrix` following the `0.4.54 — unified event/location evidence layer` line.
- Latest Great Imports repo update: added `includes/class-gi-great-imports-simulation-matrix.php` in `stanfieldjd/Great-importer`.
- The new simulation matrix file is side-effect free. It records required simulation coverage, event/location evidence fields, event-location relation states, duplicate-prevention checks, and pass criteria. It does not fetch URLs, write Events Manager records, change scheduled imports, or mutate stored candidates.
- The main plugin loader remains `great-imports.php`, a large single-file plugin source. Its header currently reports `0.4.22` even though later evidence-layer functions exist in the file. Do not destructively replace this file without a safe full-file patch path.
- Plugin ZIPs/build artifacts have not been committed to this repository in this pass.

## Current Great Imports boundaries

- EM Local Importer is a UI reference only.
- Do not copy EM Local Importer backend rules, source behavior, location logic, Events Manager handoff logic, or code into Great Imports.
- Location Detector is the evidence-model reference for Great Imports.
- Location Detector-style evidence handling must apply throughout Great Imports, not only to standalone locations.
- The unified evidence layer should cover events, locations, event-location relationships, listing expansion, Public URL, CSV, JSON, ICS/iCal, platform-style sources, organizer/calendar sources, alternate URLs, duplicate/merge decisions, review-needed state, and import candidate state.
- Avoid inventing extra Great Imports rules and labeling them as user rules.
- Forbidden map/provider placeholder strings were removed from plugin source in the 0.4.53 line before the 0.4.54 unified evidence build.
- Blocked or unreadable source pages must remain reviewable blocked-source records with source URL and reason. Do not fabricate event details from blocked pages.
- Cleanup and uninstall behavior must remain limited to Great Imports-owned data unless the user explicitly instructs otherwise.

## Current Great Imports feature state from chat

- Saved import UI was cleaned up through 0.4.44: auto-name from page/site title when blank, each bulk URL saved as its own saved import.
- Listing expansion was added in 0.4.45 so a saved listing URL can expand into event-detail candidates.
- Recent Imports panel was removed in 0.4.46.
- Imported/updated candidates are hidden from the visible review queue after import in 0.4.47.
- Duplicate prevention was hardened in 0.4.48 for same URL, alternate URL, event URL, ticket URL, source URL list, storage key, and fingerprint cases.
- Location/map boundary work was corrected after the user clarified which rules belong to Location Detector, which logic belongs to Great Imports, and which UI-only pieces belong to EM Local Importer.
- 0.4.54 added detector-backed event/location evidence fields in the working line: `location_decision_state`, `event_location_relation_state`, `event_location_needs_review`, and `location_evidence`.
- 0.4.55-simulation-matrix now records the expected full simulation coverage and pass criteria in a plugin-side PHP source file, but it is not yet wired into the active loader/report workflow.

## Current next step

Wire the simulation matrix or equivalent checks into the active Great Imports test/report workflow only through a safe patch path for the main loader, then run a full simulation pass against the current build using:

- uploaded Events Manager source
- uploaded Location Detector reference
- uploaded EM Local Importer UI reference
- mixed Public URL fixtures
- listing pages and expanded event-detail links
- organizer/calendar/platform-style URLs
- blocked/unreadable URL fixtures
- invalid URL input
- CSV imports
- JSON imports
- ICS/iCal file and URL imports
- saved recurring imports
- review/import workflow
- duplicate prevention from same and alternate URLs
- imported-candidate hiding
- placeholder/string integrity checks
- uninstall/delete safety

## Operating rule

Before future file updates, check this file for current context. After future file updates, revise this file so it reflects the latest state.
