# Concentric Workspace Experiment

Status: WORKBENCH-ONLY / NOT DEPLOYED / NOT A PRODUCTION EXECUTOR

This experiment tests a transport-independent administrative workspace that routes each normalized task to the least-authoritative executor capable of completing it.

The rings are deliberately ordered:

1. WordPress executor — normal administration through WordPress semantics and the existing CMS Admin surface.
2. WP-CLI — recovery or administration when Ring 1 is unavailable or cannot satisfy the capability.
3. Host recovery — filesystem/Git/log-oriented recovery only; not a normal administration path.
4. Database recovery — explicit recovery only and only with a snapshot available.
5. Browser fallback — last-resort UI adapter for functionality with no usable machine contract.

The router does not execute commands. It receives a normalized task plus a model of currently available executors and returns either the least-authoritative eligible ring or a fail-closed result.

## Current invariants

- Authorization failure blocks the task; it never causes escalation.
- Stale expected state blocks the task; it never causes escalation.
- Destructive work that requires a snapshot blocks when no snapshot exists.
- WordPress remains preferred whenever it is healthy and exposes the requested capability.
- WP-CLI is considered before host recovery.
- Host access is recovery-only.
- Direct database recovery must be explicit and requires a snapshot.
- Browser automation is the final eligible ring, never the default.
- Every routing decision produces an evidence journal showing why each lower ring was accepted or rejected.

## Red-team probe

`router-probe.php` exercises directed failure scenarios and then runs 10,000 deterministic randomized authority-order cases. The property under test is that the router cannot select a more-authoritative ring while a lower eligible ring exists.

This workbench code intentionally has no network listener, credentials, shell dispatcher, SQL dispatcher, PHP `eval`, WordPress mutation, deployment path, or production hook. Its purpose is to falsify the routing model before any executor is built.
