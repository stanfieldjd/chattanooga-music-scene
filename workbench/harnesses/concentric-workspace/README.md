# Concentric Workspace Experiment

Status: WORKBENCH-ONLY / NOT DEPLOYED / NOT A PRODUCTION EXECUTOR

This experiment tests a transport-independent administrative workspace that routes each normalized task to the least-authoritative executor capable of completing it, then requires evidence-backed verification before the task can settle as complete.

The rings are deliberately ordered:

1. WordPress executor — normal administration through WordPress semantics and the existing CMS Admin surface.
2. WP-CLI — recovery or administration when Ring 1 is unavailable or cannot satisfy the capability.
3. Host recovery — filesystem/Git/log-oriented recovery only; not a normal administration path.
4. Database recovery — explicit recovery only and only with a snapshot available.
5. Browser fallback — last-resort UI adapter for functionality with no usable machine contract.

The router does not execute commands. It receives a normalized task plus a model of currently available executors and returns either the least-authoritative eligible ring or a fail-closed result.

The evidence journal separately models task completion. It records an append-only hash chain for planning, before-state capture, routing, execution, verification, and rollback. It stores hashes of state/result payloads rather than treating raw state as the audit primitive.

## Current invariants

- Authorization failure blocks the task; it never causes escalation.
- Stale expected state blocks the task; it never causes escalation.
- Destructive work that requires a snapshot blocks when no snapshot exists.
- WordPress remains preferred whenever it is healthy and exposes the requested capability.
- WP-CLI is considered before host recovery.
- Host access is recovery-only.
- Direct database recovery must be explicit and requires a snapshot.
- Browser automation is the final eligible ring, never the default.
- Every routing decision records why each lower ring was accepted or rejected.
- Mutating tasks cannot execute before before-state capture.
- Execution cannot be recorded twice.
- Completion requires explicit post-action verification.
- Failed verification of a mutating task becomes `needs_rollback`, not success.
- Successful rollback settles explicitly as `rolled_back`.
- Evidence-chain tampering is detectable.

## Red-team probes

`router-probe.php` exercises directed failure scenarios and then runs 10,000 deterministic randomized authority-order cases. The property under test is that the router cannot select a more-authoritative ring while a lower eligible ring exists.

`evidence-probe.php` exercises successful reads, blocked unsafe mutations, verified writes, failed verification with rollback, deliberate evidence tampering, and 5,000 randomized lifecycle sequences. The properties under test are that no mutating execution can occur without before-state capture, no task can settle as complete without verification, failed writes require rollback state, and the journal hash chain detects modification.

This workbench code intentionally has no network listener, credentials, shell dispatcher, SQL dispatcher, PHP `eval`, WordPress mutation, deployment path, or production hook. Its purpose is to falsify the workspace model before any executor is built.
