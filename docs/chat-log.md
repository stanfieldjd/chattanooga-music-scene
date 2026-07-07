# Chat Log

This file records conversation checkpoints connected to work in this repository.

## Standing rule

Whenever ChatGPT updates files in this repository, it should also update this chat log and `docs/rolling-context.md` in the same GitHub update pass.

This is not automatic background logging. It is part of each requested GitHub update workflow.

## 2026-07-06 — Great Imports 0.4.54 ZIP verification

### User request

The user uploaded `great-imports-v0.4.54-unified-evidence-layer.zip`, described it as the last download ChatGPT had provided, and asked ChatGPT to verify it and update GitHub.

### Verification performed

The uploaded ZIP was inspected directly from the sandbox path. Results:

- ZIP SHA-256: `47e3b4092d03ef790f9cf42abd3b66c3c3d19af7c5918fa8ac941dbd86a33682`
- ZIP size: `138934` bytes
- Top-level plugin folder: `great-imports/`
- PHP files: `17`
- Path traversal check: passed; no absolute paths or `..` path components.
- Plugin header version: `0.4.54`.
- Runtime constant `GREAT_IMPORTS_VERSION`: `0.4.54`.
- Class constant `GI_Great_Imports::VERSION`: `0.4.54`.
- `readme.txt` stable tag: `0.4.54`.
- PHP syntax lint: passed for all 17 PHP files using `php -l`.
- Forbidden string scan: no matches for `OpenStreetMap`, `Open Street Map`, `Nominatim`, `OSM`, `Source collector`, or `source collector`.
- Evidence-string scan found references across the extracted source: `location_decision_state` 20, `event_location_relation_state` 9, `event_location_needs_review` 8, `location_evidence` 28.

### GitHub update performed

Added a verification report to `stanfieldjd/Great-importer`:

- `docs/verification-great-imports-v0.4.54-unified-evidence-layer.md`

Commit:

- `deb2403d6a13899b798e19d93d17932fc8f143d0`

### Source sync status

The ZIP verifies as a valid `0.4.54` plugin build and should be treated as the authoritative source set for the next safe repository source sync. The report records the exact file hashes and intended root layout.

The full extracted source files were not destructively written over the current GitHub source in this pass because the active GitHub connector does not provide a safe local ZIP-to-repository directory sync action. A partial manual text update would risk corrupting the modular plugin layout.

### Files changed

- `stanfieldjd/Great-importer`: `docs/verification-great-imports-v0.4.54-unified-evidence-layer.md`
- `stanfieldjd/chattanooga-music-scene`: `docs/chat-log.md`
- `stanfieldjd/chattanooga-music-scene`: `docs/rolling-context.md`

### Current next step

Use the verified ZIP contents as the authoritative `0.4.54` source set and perform a safe full repository source sync through a bulk-safe method that can write `great-imports.php`, `readme.txt`, `uninstall.php`, and all `includes/...` files together.

## 2026-07-06 — Great Imports simulation matrix update

### User request

The user instructed ChatGPT to use `stanfieldjd/chattanooga-music-scene`, read `docs/rolling-context.md` first, update the plugin files in `stanfieldjd/Great-importer`, and also update `docs/chat-log.md` and `docs/rolling-context.md` in the same pass.

### Context read first

`docs/rolling-context.md` said the current Great Imports working line was `Great Imports 0.4.54 — unified event/location evidence layer` and the next step was a full simulation pass covering the uploaded Events Manager source, Location Detector reference, EM Local Importer UI reference, mixed Public URL fixtures, listing expansion, organizer/calendar/platform-style URLs, blocked/unreadable URL fixtures, invalid URLs, CSV, JSON, ICS/iCal, saved recurring imports, review/import workflow, duplicate prevention, imported-candidate hiding, placeholder/string integrity checks, and uninstall/delete safety.

### Assistant action

Updated `stanfieldjd/Great-importer` by adding `includes/class-gi-great-imports-simulation-matrix.php`.

The new plugin-side file is side-effect free and records the current simulation matrix, required event/location evidence fields, expected event-location relation states, and pass criteria. It does not fetch URLs, write Events Manager records, change scheduled imports, or mutate stored candidates.

### Files changed

- `stanfieldjd/Great-importer`: `includes/class-gi-great-imports-simulation-matrix.php`
- `stanfieldjd/chattanooga-music-scene`: `docs/chat-log.md`
- `stanfieldjd/chattanooga-music-scene`: `docs/rolling-context.md`

### Current next step

The Great Imports main loader remains the large `great-imports.php` file. The next code move should be to wire the simulation matrix or its equivalent checks into the active test/report workflow only after a safe full-file patch path is available, then run the full simulation pass against the current build.

## 2026-07-06 — Great Imports checkpoint and context update

### User request

The user instructed ChatGPT to use `stanfieldjd/chattanooga-music-scene`, read `docs/rolling-context.md` first, and update both `docs/chat-log.md` and `docs/rolling-context.md` in the same pass.

### Current Great Imports status captured

The working plugin discussion reached `Great Imports 0.4.54 — unified event/location evidence layer`.

The user clarified several important boundaries:

- EM Local Importer is a UI reference only.
- EM Local Importer backend rules, location logic, source handling, and Events Manager handoff logic must not be copied into Great Imports.
- Location Detector logic is the model for evidence handling throughout Great Imports, including events, locations, event-location relationships, source evidence, and duplicate/merge decisions.
- The detector/evidence logic should not be applied only to single locations; it must apply across Public URL, listing expansion, CSV, JSON, ICS/iCal, platform-style sources, organizer/calendar sources, alternate URLs, and import candidate review.
- Forbidden map/provider placeholder strings were removed from plugin source in the 0.4.53 line before the 0.4.54 evidence integration work.
- The uploaded Events Manager ZIP was added to simulation context for compatibility and gap checks.

### Assistant action

Updated the repository tracking files with the latest working state and standing boundaries.

### Files changed

- `docs/chat-log.md`
- `docs/rolling-context.md`

### Current next step

Continue from `Great Imports 0.4.54` with a full simulation pass against the current build, uploaded Events Manager source, Location Detector reference, EM Local UI reference, mixed URL fixtures, CSV, JSON, ICS/iCal, saved imports, duplicate prevention, imported-candidate hiding, and placeholder/string integrity checks.

## 2026-07-06 — Chat tracking setup

### User request

The user asked whether the entire chat could be sent to GitHub so ChatGPT can keep track. The user clarified they meant the chat itself: every time files are updated in GitHub, also update a chat file.

### Assistant action

Initialized repository-level chat tracking.

### Files changed

- `docs/chat-log.md`
- `docs/rolling-context.md`

### Rule established

When GitHub files are updated for this repo, update the chat tracking files in the same pass.

### Current state

Chat tracking is initialized for this repository.
