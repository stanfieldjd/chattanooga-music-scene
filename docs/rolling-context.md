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
- Latest user-provided artifact: `great-imports-v0.4.54-unified-evidence-layer.zip`.
- The uploaded ZIP verified as a valid `Great Imports 0.4.54` plugin build.
- ZIP SHA-256: `47e3b4092d03ef790f9cf42abd3b66c3c3d19af7c5918fa8ac941dbd86a33682`.
- ZIP verification passed: clean path traversal check, 17 PHP files linted with `php -l`, plugin header/runtime/class/readme versions all `0.4.54`, forbidden map/provider placeholder string scan clean, and evidence strings present across extracted source.
- Latest Great Imports repo update: added `docs/verification-great-imports-v0.4.54-unified-evidence-layer.md` in `stanfieldjd/Great-importer`.
- Great-importer verification-report commit: `deb2403d6a13899b798e19d93d17932fc8f143d0`.
- The verified ZIP contains the authoritative modular source layout rooted at `great-imports/`: `great-imports.php`, `readme.txt`, `uninstall.php`, and `includes/...` files should live at repo root when synced.
- The full extracted source files were not destructively written over the current GitHub source in the verification pass because the active GitHub connector does not provide a safe local ZIP-to-repository directory sync action. Avoid partial manual text updates that would corrupt the modular plugin layout.
- Previous Great Imports repo update: added `includes/class-gi-great-imports-simulation-matrix.php` in `stanfieldjd/Great-importer` as a side-effect-free simulation matrix. That file is not part of the verified `0.4.54` ZIP source set.
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
- 0.4.54 verified from uploaded ZIP: modular source layout, unified evidence references, clean forbidden-string scan, and PHP lint pass.
- 0.4.55-simulation-matrix was added earlier as a repo-side source file but is not part of the verified 0.4.54 ZIP.

## Current next step

Perform a safe full repository source sync using the verified ZIP contents as the authoritative `0.4.54` source set. The sync should write these files together, not partially:

- `great-imports.php`
- `readme.txt`
- `uninstall.php`
- all files under `includes/core/`
- all files under `includes/admin/`
- all files under `includes/integrations/`
- all files under `includes/sources/`
- all files under `includes/events/`
- all files under `includes/reports/`
- all files under `includes/locations/`

After source sync, compare repository file hashes against `docs/verification-great-imports-v0.4.54-unified-evidence-layer.md`, then run the full simulation pass using:

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
