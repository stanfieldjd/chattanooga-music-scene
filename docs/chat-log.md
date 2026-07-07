# Chat Log

This file records conversation checkpoints connected to work in this repository.

## Standing rule

Whenever ChatGPT updates files in this repository, it should also update this chat log and `docs/rolling-context.md` in the same GitHub update pass.

This is not automatic background logging. It is part of each requested GitHub update workflow.

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
