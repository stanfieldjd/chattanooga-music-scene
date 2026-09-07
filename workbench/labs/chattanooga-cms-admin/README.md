# Chattanooga CMS Admin Workbench Lab

Purpose: provide a source-controlled laboratory for Chattanooga CMS Admin without changing `main`, the live WordPress site, or the `feature/chattanooga-cms-admin` source branch.

## Layout

- `plugin/` — immutable exact mirror of verified source checkpoint `0b34773ebc8073cb657477770b34cabc280f5892`; source-manifest tests protect it from accidental drift.
- `candidate/` — mutable coding copy. Experiments and repairs happen here first.
- `fixtures/` — expected source and ability manifests.
- `tests/` — GitHub-executable static and stub-runtime tests against the candidate, plus integrity verification of the immutable baseline.
- `probes/` — real WordPress runtime probes and registry tests.
- `results/` — evidence matrix. DreamHost-specific items remain UNKNOWN until tested there.
- `scripts/run-lab.sh` — deterministic lab runner used by CI.

## Test tiers

1. **Source integrity** — prove the immutable baseline is byte-identical to the verified feature-branch source.
2. **Candidate syntax compatibility** — lint the coding candidate on PHP 7.4 (declared minimum) and PHP 8.2 (current Chattanooga Music Scene runtime).
3. **Architecture/security** — reject arbitrary command execution and direct REST-route exposure; inventory outbound/network primitives without pretending a static scan proves runtime privacy.
4. **Stub registration** — load the candidate with minimal WordPress stubs and verify the expected Abilities registration shape and permission callbacks.
5. **Disposable WordPress 7.1** — install and activate the candidate on a fresh WordPress 7.1/MySQL test instance, probe core capabilities, fire the Abilities lifecycle in that disposable process, and verify all expected abilities through WordPress's real registry.
6. **Chattanooga runtime** — later run read-only probes against Chattanooga Music Scene to establish DreamHost-specific filesystem, backup-path, cache, transport, and policy behavior before live mutation.

## Coding loop

1. Preserve `plugin/` as the known source checkpoint.
2. Change only `candidate/` for experiments.
3. Run `scripts/run-lab.sh`.
4. Require both PHP CI targets and disposable WordPress 7.1 runtime registration to pass.
5. Record evidence in `results/`.
6. Only then deliberately promote a proven candidate change to a source branch. Promotion is a separate source transaction; deployment is another separate transaction.

## Evidence rule

A passing GitHub test proves only the condition it executes. It does not prove the plugin is installed on Chattanooga Music Scene, discoverable through its actual MCP transport, or compatible with DreamHost's filesystem policy. Those remain separate runtime gates.
