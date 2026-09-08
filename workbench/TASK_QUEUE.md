# Task Queue

This queue records unresolved work without treating stale site data as current evidence.

| Priority | Workstream | State | Next required position |
| --- | --- | --- | --- |
| 1 | Chattanooga CMS Admin | SOURCE_VERIFIED_PRODUCTION_GATE_PENDING | No additional taxonomy source capability is justified by current evidence. Candidate installation/live MCP discovery is a separate production gate requiring authorization and fresh pre-mutation verification. |
| 2 | Event taxonomy integrity | LIVE_RELATIONSHIP_DEFECT_VERIFIED | Fresh read-only evidence shows all five published Festival-category events also carry Live Music. Do not mutate until authorized; then re-read exact current relationships and repair only the relationship set, preserving Festival-class vocabulary and unrelated categories. |
| 3 | WordPress/plugin/theme maintenance | VERIFY_FIRST | Refresh the live update inventory only after CMS Admin is operational; update one component at a time with rollback and post-update validation. |
| 4 | Venue/location data quality | VERIFY_FIRST | Re-inspect unresolved bad coordinates/metadata and repair only with authoritative location evidence. |
| 5 | Event coverage gaps | VERIFY_FIRST | Recheck current authoritative venue calendars and create only events with defensible schedule data; do not invent end times. |

## Queue rules

- `SOURCE_VERIFIED_PRODUCTION_GATE_PENDING` means the engineering source checkpoint is complete for the current proven need; candidate installation/live discovery remains a separate production authorization gate.
- `LIVE_RELATIONSHIP_DEFECT_VERIFIED` is fresh evidence of a live relationship problem, not authorization to change live records.
- Any future event-taxonomy repair must begin with a fresh exact relationship read; cached relationship evidence must not be used as the expected-before state for a later mutation.
- Existing target vocabulary (`Festival`, `Music Festivals`, and `Live Music`) is sufficient for the current primary-form distinction. Do not add event term create/update/delete capability unless future evidence proves a concrete missing operation.
- `VERIFY_FIRST` means the task may have known historical context, but execution must begin with fresh evidence.
- Multisite/network support is not a product requirement and must not be reintroduced as a design or acceptance target.
- New CMS Admin abilities require a concrete Chattanooga administrative use; availability in an upstream API is not sufficient reason to add them.
- Media, recurrence, bookings, tickets, payments, account security, widgets/templates, location deletion, and generic option mutation remain non-automatic targets.
- A task becomes `COMPLETE` only after its task record acceptance tests pass.
- Failed or blocked work remains visible; do not silently remove it from the queue.
