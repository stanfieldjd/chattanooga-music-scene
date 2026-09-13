# Concentric Administrative Workspace — experiment record

Date opened: 2026-09-13
Branch: `work/cmsa-functional-redteam`
State: WORKBENCH_EXPERIMENT
Production state: NOT_DEPLOYED / NO LIVE EXECUTOR

## Hypothesis

Administrative control should not be concentrated in one transport or one high-authority executor. A persistent logical workspace should normalize tasks, bind them to explicit capability contracts, route them to the least-authoritative eligible executor, require evidence-backed verification, and escalate only when an inner ring is unavailable or contractually incapable.

CMS Admin remains the current WordPress-ring executor. This experiment does not replace, deploy, merge, or alter the live WordPress site.

## Rings under test

1. WordPress — normal administration using WordPress semantics and CMS Admin/public contracts.
2. WP-CLI — bounded fallback/recovery when Ring 1 cannot perform an allowed operation.
3. Host recovery — filesystem/Git/log recovery only.
4. Database recovery — explicit snapshot-backed recovery only.
5. Browser fallback — last-resort UI-only administration.

## Workbench components

- `workspace-router.php` — least-authority executor selection with fail-closed behavior.
- `capability-contracts.php` — explicit operation-to-ring policy; unknown capabilities are rejected rather than dynamically converted into shell/PHP/SQL access.
- `evidence-journal.php` — task lifecycle with before-state, execution, verification, rollback, and hash-chained evidence.
- `router-probe.php` — directed scenarios plus 10,000 deterministic randomized least-authority cases.
- `contract-probe.php` — contract-bound routing plus 5,000 randomized spoofed-availability cases.
- `evidence-probe.php` — directed lifecycle/tamper cases plus 5,000 randomized task lifecycles.
- `.github/workflows/concentric-workspace-experiment.yml` — dedicated non-production experiment gate.

## Current properties demonstrated locally before repository publication

- Normal WordPress-capable work remains in Ring 1.
- WordPress failure escalates to WP-CLI before host recovery when the capability contract allows it.
- Stale expected state blocks instead of escalating.
- Unauthorized work blocks instead of escalating.
- Destructive operations that require a snapshot block without one.
- Browser routing is only possible for operations whose contract explicitly permits Ring 5.
- Capability availability cannot override a contract's allowed rings.
- Unknown operations such as `shell.exec`, `php.eval`, and `sql.raw` are rejected as non-capabilities.
- Mutating task execution is forbidden until before-state is captured.
- Completion requires explicit verification.
- Failed verification of a mutating task becomes `needs_rollback`.
- Successful rollback settles as `rolled_back`, never as an unqualified success.
- Deliberate evidence mutation invalidates the journal hash chain.

## Deliberate omissions

This experiment currently contains no:

- network listener;
- authentication credential;
- DreamHost daemon or cron deployment;
- shell dispatcher;
- arbitrary WP-CLI dispatcher;
- raw SQL dispatcher;
- PHP `eval`;
- WordPress write path;
- browser automation;
- production hook.

Those omissions are intentional. The routing and evidence model must survive red-team testing before any executor is attached.

## Next experimental questions

1. Can live CMS Admin capability inventory be normalized into contracts without provider-specific code?
2. Can a bounded WP-CLI adapter expose a useful recovery subset without becoming arbitrary shell access?
3. Can expected-state tokens be derived from WordPress objects/settings in a stable way across concurrent edits?
4. What minimum snapshot primitive is sufficient for each mutation class: object readback, component archive, database snapshot, or Git revision?
5. Should browser fallback exist at all, or should UI-only functions remain explicitly unsupported?
6. Can the workspace journal remain transport-neutral so MCP, REST, GitHub queue, or a future connector are interchangeable front ends?

No production decision is implied by this record.
