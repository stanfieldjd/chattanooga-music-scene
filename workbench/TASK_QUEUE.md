# Task Queue

This queue records unresolved work without treating stale site data as current evidence.

| Priority | Workstream | State | Next required position |
| --- | --- | --- | --- |
| 1 | Chattanooga CMS Admin | LIVE_PLUGIN_ACTIVE_MCP_EXPOSURE_BLOCKED | Plugin 0.1.0 is active, but the current MCP connector exposes zero `chattanooga-cms-admin/*` abilities. Resolve the live MCP ability exposure/governance gate, then rerun discovery plus candidate health/read validation. |
| 2 | Event taxonomy integrity | LIVE_RELATIONSHIP_DEFECT_FRESHLY_REVERIFIED | Post-install read-only evidence still shows all five published Festival-category events also carry Live Music. Do not mutate until candidate exact-state abilities are exposed and the live relationship write is explicitly authorized. |
| 3 | WordPress/plugin/theme maintenance | VERIFY_FIRST | Refresh the live update inventory only after CMS Admin is operational through MCP; update one component at a time with rollback and post-update validation. |
| 4 | Venue/location data quality | VERIFY_FIRST | Re-inspect unresolved bad coordinates/metadata and repair only with authoritative location evidence. |
| 5 | Event coverage gaps | VERIFY_FIRST | Recheck current authoritative venue calendars and create only events with defensible schedule data; do not invent end times. |

## Queue rules

- `LIVE_PLUGIN_ACTIVE_MCP_EXPOSURE_BLOCKED` means WordPress execution-verifies Chattanooga CMS Admin as active, while the active MCP connection does not expose its namespace. It does not authorize a generic mutation workaround.
- `LIVE_RELATIONSHIP_DEFECT_FRESHLY_REVERIFIED` is current read-only evidence of the established classification problem, not authorization to change live records.
- Any future event-taxonomy repair must begin with fresh candidate `get-event-taxonomy` reads to obtain exact state tokens; generic CPT reads are not valid substitutes for those tokens.
- Existing target vocabulary (`Festival`, `Music Festivals`, and `Live Music`) is sufficient for the current primary-form distinction. Do not add event term create/update/delete capability unless future evidence proves a concrete missing operation.
- `VERIFY_FIRST` means the task may have known historical context, but execution must begin with fresh evidence.
- Multisite/network support is not a product requirement and must not be reintroduced as a design or acceptance target.
- New CMS Admin abilities require a concrete Chattanooga administrative use; availability in an upstream API is not sufficient reason to add them.
- Media, recurrence, bookings, tickets, payments, account security, widgets/templates, location deletion, and generic option mutation remain non-automatic targets.
- A task becomes `COMPLETE` only after its task record acceptance tests pass.
- Failed or blocked work remains visible; do not silently remove it from the queue.
