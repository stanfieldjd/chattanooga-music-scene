# Chattanooga CMS Admin Live Simulation

This directory defines the disposable, editable WordPress simulation for Chattanooga CMS Admin.

## Purpose

The simulation exists so Chattanooga CMS Admin can be changed and exercised against a running WordPress site without changing production or `main`.

The plugin under test remains the canonical source path:

`site-plugins/chattanooga-cms-admin/`

There is no copied simulation plugin. The WordPress container bind-mounts that directory directly, so source edits on the simulation branch are visible to the running simulated site immediately.

## Isolation rules

- Production Chattanooga Music Scene is not contacted or modified.
- Production credentials, databases, uploads, and private site data are not used.
- The simulation uses its own disposable database volume.
- The clean v2 acceptance harness remains separate and unchanged.
- A simulation result is not a production result.
- Promotion from the simulation branch remains a separate source/deployment decision.

## Requirements

- Docker with `docker compose` support.
- `curl` on the host for the external native-MCP HTTP write smoke test.

## Start the simulation

From this directory:

```bash
bash bootstrap.sh
```

The default simulated site URL is:

`http://127.0.0.1:8091`

The default simulation-only administrator is:

- user: `admin`
- password: `cmsa-simulation-only`

Override the port or password for a local run with `SIM_PORT` or `SIM_ADMIN_PASSWORD`.

## Edit loop

1. Start the simulation with `bash bootstrap.sh`.
2. Edit files only on the simulation branch under `site-plugins/chattanooga-cms-admin/`.
3. Run `bash smoke.sh` after each material edit.
4. For PHP-only edits, no plugin recopy or reinstall is required because the plugin source is bind-mounted.
5. If a change affects activation/install state, rerun `bash bootstrap.sh`.
6. Keep production and `main` unchanged until a separate promotion decision is made.

`smoke.sh` now exercises both internal WordPress/MCP contracts and a real external HTTP administrator write through the native MCP endpoint. The HTTP write test creates one temporary application password and one draft post, verifies the persisted draft, and removes both through its exit cleanup path.

## Reset

To destroy all simulated WordPress/database state and start clean:

```bash
docker compose down -v --remove-orphans
bash bootstrap.sh
```

This reset affects only the disposable simulation volumes. It does not delete or reset repository source.

## Verification layers

`smoke.sh` checks plugin activation, WordPress health, native MCP route registration, native-only transport boundaries, the administrator MCP surface, external anonymous rejection, administrator application-password authentication, REST-bridge discovery, an actual native-MCP write, persistence, and disposable-state cleanup.

The GitHub workflow `.github/workflows/chattanooga-cms-admin-live-simulation.yml` provides a reproducible WordPress 7.1 execution path for this simulation branch. It is manual-only; creating or editing simulation source does not automatically trigger an external workflow run.
