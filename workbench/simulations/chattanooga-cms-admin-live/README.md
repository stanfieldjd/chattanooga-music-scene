# Chattanooga CMS Admin Live Simulation

This directory defines the disposable, editable WordPress simulation for Chattanooga CMS Admin.

## Purpose

The simulation exists so Chattanooga CMS Admin can be changed and exercised against a running WordPress site without changing production or `main`.

The plugin under test remains the canonical source path:

`site-plugins/chattanooga-cms-admin/`

There is no copied simulation plugin. The WordPress container bind-mounts that directory directly, so source edits on the simulation branch are visible to the running simulated site immediately.

The two other Chattanooga-owned site plugins are also live-mounted from the same branch so the optional representative site-stack mode can exercise integration without copying their source:

- `site-plugins/chattanooga-music-marketplace/`
- `site-plugins/chattanooga-music-scene-core/`

## Isolation rules

- Production Chattanooga Music Scene is not contacted or modified.
- Production credentials, databases, uploads, and private site data are not used.
- The simulation uses its own disposable database volume.
- The clean v2 acceptance harness remains separate and unchanged.
- A simulation result is not a production result.
- Promotion from the simulation branch remains a separate source/deployment decision.
- Chattanooga CMS Admin's built-in MCP remains the only MCP transport in this simulation. `site-stack.sh` refuses to proceed if the miniOrange MCP plugin is installed.

## Requirements

- Docker with `docker compose` support.
- `curl` on the host for the external native-MCP HTTP write smoke test.
- Network access only when `site-stack.sh` must install the pinned WordPress.org provider packages.

## Start the core simulation

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

The core smoke loop verifies native MCP discovery/authorization, the required administrator tool surface, posts/pages/taxonomies/menus, comments and comment moderation, media upload plus media metadata/delete paths, anonymous mutation boundaries, a real external native-MCP post write, a real external native-MCP media upload, persistence, and disposable cleanup.

## Representative Chattanooga site stack

After the core simulation is running, use:

```bash
bash site-stack.sh
```

This optional mode keeps the Chattanooga-owned plugin source live-mounted and installs/activates the pinned provider versions already used by the repository compatibility baselines:

- Events Manager `7.4.3`
- WooCommerce `11.0.1`
- Rank Math SEO `1.0.278`
- AWP Classifieds `4.4.8`

It activates Chattanooga CMS Admin, Chattanooga Music Marketplace, and Chattanooga Music Scene Core/Weekend Feature, then runs the existing Events Manager, WooCommerce, Rank Math, and Chattanooga site-plugin integration probes before rerunning the native MCP smoke suite.

This mode intentionally does not install the old miniOrange MCP plugin or another MCP proxy. AWP Classifieds remains present as a site provider, but its prior miniOrange-derived private Ability compatibility probe is not used in this native-only simulation.

## Reset

To destroy all simulated WordPress/database state and start clean:

```bash
docker compose down -v --remove-orphans
bash bootstrap.sh
```

This reset affects only the disposable simulation volumes. It does not delete or reset repository source.

## Verification layers

`smoke.sh` is the fast editable loop. `site-stack.sh` adds representative provider and Chattanooga-plugin integration. The GitHub workflow `.github/workflows/chattanooga-cms-admin-live-simulation.yml` provides a reproducible WordPress 7.1 native-MCP execution path for the simulation branch.

The GitHub workflow is manual-only; creating or editing simulation source does not automatically trigger an external workflow run.
