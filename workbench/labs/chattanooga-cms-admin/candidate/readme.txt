=== Chattanooga CMS Admin ===
Contributors: chattanooga-music-scene
Requires at least: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0

Site-owned administration abilities for Chattanooga Music Scene.

== Purpose ==

This plugin extends the WordPress Abilities API with administrative operations intended for Chattanooga Music Scene's AI administration workflow.

The control boundary is Chattanooga Music Scene and its authorized AI client. The plugin contains no vendor telemetry, analytics SDK, licensing service, cloud backup service, or remote management dashboard.

WordPress.org is contacted only when WordPress itself needs official update metadata or an official plugin/theme package. No member records are intentionally sent as part of those operations.

== Abilities ==

Read/inspection:
* chattanooga-cms-admin/get-health
* chattanooga-cms-admin/list-updates
* chattanooga-cms-admin/list-plugins
* chattanooga-cms-admin/list-themes
* chattanooga-cms-admin/list-backups
* chattanooga-cms-admin/verify-backup
* chattanooga-cms-admin/get-audit-log

Maintenance:
* chattanooga-cms-admin/create-backup
* chattanooga-cms-admin/update-plugin
* chattanooga-cms-admin/update-theme
* chattanooga-cms-admin/update-core
* chattanooga-cms-admin/install-plugin
* chattanooga-cms-admin/activate-plugin
* chattanooga-cms-admin/deactivate-plugin
* chattanooga-cms-admin/set-plugin-auto-update
* chattanooga-cms-admin/install-theme
* chattanooga-cms-admin/switch-theme
* chattanooga-cms-admin/set-theme-auto-update
* chattanooga-cms-admin/clear-cache

Destructive lifecycle operations:
* chattanooga-cms-admin/delete-plugin
* chattanooga-cms-admin/delete-theme

Rollback:
* chattanooga-cms-admin/restore-component-backup
* chattanooga-cms-admin/restore-database-backup
* chattanooga-cms-admin/restore-core-backup

== Backup design ==

Backups are local. The plugin first attempts to store them one directory above the WordPress installation. If that location is not writable it falls back to a protected directory under wp-content. CMSA_BACKUP_DIR may be defined to force a specific local path.

Database snapshots are written as deterministic SQL containing table definitions and hex-encoded values. Filesystem archives use ZipArchive and are SHA-256 verified before rollback.

Plugin and theme updates require a verified component rollback archive before the updater runs. WordPress core updates require a verified core-file and database rollback snapshot.

Plugin and theme deletion also require a verified component rollback archive. Chattanooga CMS Admin refuses to delete itself, and refuses to delete the active theme or the active theme's parent.

== Security ==

Every ability uses a WordPress capability check. No ability uses __return_true for administrative access.

Abilities are marked public for authenticated AI/MCP discovery while direct REST execution is disabled. Destructive operations are registered as separate abilities and annotated as destructive.

No generic shell, arbitrary SQL, arbitrary PHP execution, or arbitrary filesystem command ability is provided.

The local audit log stores administrative action metadata but intentionally does not duplicate member records, passwords, tokens, or arbitrary request payloads.

== Changelog ==

= 0.1.0 =
* Initial source-controlled implementation.
* Local backups and SHA-256 verification.
* Transactional plugin, theme, and core update operations.
* WordPress.org plugin/theme installation.
* Plugin activation/deactivation and theme switching.
* Backup-protected plugin/theme deletion.
* Plugin/theme auto-update policy controls.
* Cache and health inspection.
* Local administrative audit retrieval.
* Explicit rollback abilities.
