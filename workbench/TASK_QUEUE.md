# Task Queue

This queue records unresolved work without treating stale site data as current evidence.

| Priority | Workstream | State | Next required position |
| --- | --- | --- | --- |
| 1 | Chattanooga CMS Admin | SINGLE_SITE_EVENTS_LAYER_ACTIVE | Finish the disposable Events Manager event/location read gate, then add only the bounded event and venue create/update lifecycle needed to administer Chattanooga Music Scene. WordPress multisite support is explicitly out of scope. Tickets/bookings and unrelated Events Manager surfaces are not current targets. |
| 2 | WordPress/plugin/theme maintenance | VERIFY_FIRST | Refresh the live update inventory only after CMS Admin is operational; update one component at a time with rollback and post-update validation. |
| 3 | Event taxonomy integrity | VERIFY_FIRST | Re-read affected live event records and enforce the primary-form distinction: festivals remain festival-class records rather than ordinary Live Music records merely because they contain performances. |
| 4 | Venue/location data quality | VERIFY_FIRST | Re-inspect unresolved bad coordinates/metadata and repair only with authoritative location evidence. |
| 5 | Event coverage gaps | VERIFY_FIRST | Recheck current authoritative venue calendars and create only events with defensible schedule data; do not invent end times. |

## Queue rules

- `VERIFY_FIRST` means the task may have known historical context, but execution must begin with fresh evidence.
- `SINGLE_SITE_EVENTS_LAYER_ACTIVE` is workbench-only engineering; it does not authorize installation or live content mutation.
- Multisite/network support is not a product requirement and must not be reintroduced as a design or acceptance target.
- New CMS Admin abilities require a concrete Chattanooga administrative use; availability in an upstream API is not sufficient reason to add them.
- A task becomes `COMPLETE` only after its task record acceptance tests pass.
- Failed or blocked work remains visible; do not silently remove it from the queue.
