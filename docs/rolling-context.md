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
- Latest user-provided source artifacts: `great-imports-v0.4.54-unified-evidence-layer.zip`, `great-imports-chat-text-export.zip`, and `events-manager.zip`.
- The uploaded Great Imports ZIP verified as a valid `Great Imports 0.4.54` plugin build.
- Great Imports ZIP SHA-256: `47e3b4092d03ef790f9cf42abd3b66c3c3d19af7c5918fa8ac941dbd86a33682`.
- Events Manager ZIP SHA-256: `d4f73c17d5480eaa5b4d816cacabd58d3a3bb8437ae6935a84180ed37140ae74`.
- Events Manager version from uploaded source: `7.3.6`.
- Latest Great-importer repo updates:
  - Verification report: `docs/verification-great-imports-v0.4.54-unified-evidence-layer.md`, commit `deb2403d6a13899b798e19d93d17932fc8f143d0`.
  - Full simulation report: `docs/full-simulation-great-imports-v0.4.54-with-events-manager-7.3.6.md`, commit `3f4cd0eb3ae2f7249b919c0a88a64fd97a03ff0c`.
- Full local simulation passed: Great Imports `17/17` PHP files linted, Events Manager `723/723` PHP files linted, and dynamic harness `23/23` tests passed.
- The simulation covered bootstrap/admin/REST/scheduling, weekly/monthly cron schedules, CSV/JSON/ICS imports, Public URL source profiling, listing discovery, blocked-source no-fabrication behavior, event normalization, event/location evidence state, duplicate prevention, multi-source merge, Events Manager payload boundary, address-only location payloads, saved imports, cleanup scope, and imported-candidate hiding.
- Boundary scans passed for forbidden provider/source-control strings, coordinate/geocode terms, and EM/front-end override hooks.
- Review note: `map_link` address-evidence terminology appears in four source files. Simulation confirmed it does not call a map/geocode provider and does not create latitude/longitude/coordinate fields. If the desired rule is zero map-related wording of any kind, rename or remove it in the next patch.
- The verified ZIP contains the authoritative modular source layout rooted at `great-imports/`: `great-imports.php`, `readme.txt`, `uninstall.php`, and `includes/...` files should live at repo root when synced.
- The previous repo-side file `includes/class-gi-great-imports-simulation-matrix.php` is not part of the verified `0.4.54` ZIP source set and should be removed during source sync.
- Plugin ZIPs/build artifacts should not be committed to GitHub.

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
- 0.4.54 full local simulation with Events Manager 7.3.6 passed `23/23` dynamic harness tests.
- 0.4.55-simulation-matrix was added earlier as a repo-side source file but is not part of the verified 0.4.54 ZIP and should be removed during source sync.

## Current next step

Perform a safe full repository source sync using the verified and simulated `0.4.54` ZIP contents as the authoritative source set. The sync should write these files together, not partially:

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

During source sync, remove repo-side files that are not part of the verified `0.4.54` source set, especially `includes/class-gi-great-imports-simulation-matrix.php`. Do not commit ZIP artifacts.

After source sync, compare repository file hashes against `docs/verification-great-imports-v0.4.54-unified-evidence-layer.md`, then rerun the full simulation report against the repo copy.

## Operating rule

Before future file updates, check this file for current context. After future file updates, revise this file so it reflects the latest state.
