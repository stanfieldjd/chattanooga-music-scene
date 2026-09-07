# Task Queue

This queue records unresolved work without treating stale site data as current evidence.

| Priority | Workstream | State | Next required position |
| --- | --- | --- | --- |
| 1 | Chattanooga CMS Admin | READY_FOR_READ_ONLY_PREFLIGHT | Refresh the live Chattanooga WordPress/runtime facts exposed by the current connected surface. Do not install or mutate the candidate at this gate. Then establish a separately authorized installation/rollback transaction before deployment or MCP discovery. |
| 2 | WordPress/plugin/theme maintenance | VERIFY_FIRST | Refresh the live update inventory only after CMS Admin is operational; update one component at a time with rollback and post-update validation. |
| 3 | Event taxonomy integrity | VERIFY_FIRST | Re-read affected live event records and enforce the primary-form distinction: festivals remain festival-class records rather than ordinary Live Music records merely because they contain performances. |
| 4 | Venue/location data quality | VERIFY_FIRST | Re-inspect unresolved bad coordinates/metadata and repair only with authoritative location evidence. |
| 5 | Event coverage gaps | VERIFY_FIRST | Recheck current authoritative venue calendars and create only events with defensible schedule data; do not invent end times. |

## Queue rules

- `VERIFY_FIRST` means the task may have known historical context, but execution must begin with fresh evidence.
- `READY_FOR_READ_ONLY_PREFLIGHT` permits inspection only; it does not authorize installation, deployment, updates, deletes, or other live mutation.
- A task becomes `COMPLETE` only after its task record acceptance tests pass.
- Failed or blocked work remains visible; do not silently remove it from the queue.
