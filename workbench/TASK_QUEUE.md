# Task Queue

This queue records unresolved work without treating stale site data as current evidence.

| Priority | Workstream | State | Next required position |
| --- | --- | --- | --- |
| 1 | Chattanooga CMS Admin | READY_FOR_RUNTIME_GATE | Establish an authorized installation path, deploy the validated plugin, discover its abilities, run backup/health checks, and verify rollback behavior before using update operations. |
| 2 | WordPress/plugin/theme maintenance | VERIFY_FIRST | Refresh the live update inventory after CMS Admin is operational; update one component at a time with rollback and post-update validation. |
| 3 | Event taxonomy integrity | VERIFY_FIRST | Re-read affected live event records and enforce the primary-form distinction: festivals remain festival-class records rather than ordinary Live Music records merely because they contain performances. |
| 4 | Venue/location data quality | VERIFY_FIRST | Re-inspect unresolved bad coordinates/metadata and repair only with authoritative location evidence. |
| 5 | Event coverage gaps | VERIFY_FIRST | Recheck current authoritative venue calendars and create only events with defensible schedule data; do not invent end times. |

## Queue rules

- `VERIFY_FIRST` means the task may have known historical context, but execution must begin with fresh evidence.
- A task becomes `COMPLETE` only after its task record acceptance tests pass.
- Failed or blocked work remains visible; do not silently remove it from the queue.
