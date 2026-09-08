# Task: Event Coverage Gaps

Status: LIVE_INVENTORY_REFRESH_IN_PROGRESS — READ-ONLY AUTHORITATIVE COVERAGE AUDIT

## Objective

Validate current upcoming Chattanooga-area live-music event coverage against current authoritative venue/organizer calendars and identify defensible gaps in Chattanooga Music Scene.

## Target set

- Fresh live Chattanooga CMS Admin event inventory.
- Selected current first-party venue/organizer calendars for Chattanooga-area live-music programming.
- Workbench evidence and gap candidates only.

## Exclusion set

- No live event create, update, trash, restore, or delete.
- No inferred or invented end times.
- No recurrence, booking, ticket, payment, venue, taxonomy, member, navigation, plugin/theme/core, permission, or source mutation.
- No event may be treated as missing solely from title similarity without date/venue identity checks.
- No aggregator listing substitutes for a competent first-party/current authoritative schedule when one is available.

## Control record

- `OBJECTIVE`: validate current future/upcoming event coverage against current authoritative venue/organizer calendars; identify defensible gaps.
- `TARGET_SET`: current live event inventory plus selected Chattanooga-area venue/organizer calendars.
- `EXCLUSION_SET`: no live event mutation; no invented end times; no unrelated site/source changes.
- `EVIDENCE`: fresh candidate `list-events` reads plus current first-party schedule pages.
- `MUTATION_SET`: workbench branch records only.
- `RISK_SET`: duplicate entries caused by aliases/title variants; stale calendar pages; doors-versus-show-start confusion; multi-day ambiguity; cancellations/postponements; timezone mistakes; venue identity ambiguity; ticket pages without complete schedule data.
- `ROLLBACK_POINT`: no live mutation; workbench findings can be corrected by a later workbench commit.
- `ACCEPTANCE_TESTS`: live inventory freshly checked; authoritative current sources used; each prospective gap has a defensible title/date/start and venue identity; end time is recorded only when explicitly published; unknowns remain explicit; no live event write occurs.

## Research precedence

Before general web discovery, examine Library of Congress applicability for the proposition of current 2026 Chattanooga-area venue/organizer concert schedules. Record `LOC_APPLICABLE`, `LOC_NOT_APPLICABLE`, `LOC_NOT_FOUND`, or `LOC_INCONCLUSIVE`. Only after that examination may general web discovery be used.

## Execution plan

1. Refresh bounded live event inventory through `chattanooga-cms-admin__list-events`.
2. Select a small set of active/prominent venue or organizer calendars from current live location/event evidence.
3. Apply the Library of Congress precedence examination to this event-schedule research question.
4. Inspect current first-party schedule pages only after the LOC gate.
5. For each concrete first-party event candidate, search the live inventory by distinctive artist/title and verify date/venue identity before classifying it as present or missing.
6. Record only defensible gaps; preserve unknowns.
7. Update STATUS, TASK_QUEUE, machine-readable state, and JOURNAL; validate workbench integrity.

## Current position

The task is read-only. A live event creation or correction, if later justified, is a separate target-specific mutation requiring fresh exact event/venue evidence and separate authorization.