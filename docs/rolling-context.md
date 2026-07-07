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
- Current Great Imports working line in chat: `Great Imports 0.4.54 — unified event/location evidence layer`.
- Plugin ZIPs/build artifacts have not been committed to this repository in this pass; this update is a context/checkpoint update only.

## Current Great Imports boundaries

- EM Local Importer is a UI reference only.
- Do not copy EM Local Importer backend rules, source behavior, location logic, Events Manager handoff logic, or code into Great Imports.
- Location Detector is the evidence-model reference for Great Imports.
- Location Detector-style evidence handling must apply throughout Great Imports, not only to standalone locations.
- The unified evidence layer should cover events, locations, event-location relationships, listing expansion, Public URL, CSV, JSON, ICS/iCal, platform-style sources, organizer/calendar sources, alternate URLs, duplicate/merge decisions, review-needed state, and import candidate state.
- Avoid inventing extra Great Imports rules and labeling them as user rules.
- Forbidden map/provider placeholder strings were removed from plugin source in the 0.4.53 line before the 0.4.54 unified evidence build.

## Current Great Imports feature state from chat

- Saved import UI was cleaned up through 0.4.44: auto-name from page/site title when blank, each bulk URL saved as its own saved import.
- Listing expansion was added in 0.4.45 so a saved listing URL can expand into event-detail candidates.
- Recent Imports panel was removed in 0.4.46.
- Imported/updated candidates are hidden from the visible review queue after import in 0.4.47.
- Duplicate prevention was hardened in 0.4.48 for same URL, alternate URL, event URL, ticket URL, source URL list, storage key, and fingerprint cases.
- Location/map boundary work was corrected after the user clarified which rules belong to Location Detector, which logic belongs to Great Imports, and which UI-only pieces belong to EM Local Importer.
- 0.4.54 added detector-backed event/location evidence fields: `location_decision_state`, `event_location_relation_state`, `event_location_needs_review`, and `location_evidence`.

## Current next step

Run a full simulation pass against `Great Imports 0.4.54` using:

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