=== Chattanooga Music Scene Core ===
Contributors: chattanoogamusicscene
Tags: events, weekend, publishing
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Site-specific publishing tools for Chattanooga Music Scene.

== Weekend posts ==

The Weekend Posts tool reads published Events Manager events occurring Friday
through Sunday and generates one normal WordPress post. Each event title, image,
and details link points to the event's page on Chattanooga Music Scene.

The generated post is a standard WordPress post so an existing Jetpack Social
automatic-sharing connection can process it through the normal publication
transition. No Facebook credentials are stored by this plugin.

Automatic publishing is disabled until both the enable checkbox and a Thursday
time are saved. If the weekend contains no published events, the run records an
error and creates no post.

== Administration ==

Open Tools > Weekend Posts to:

* See the selected Friday-Sunday date range and event count.
* Generate or update the week's draft.
* Publish the week's verified draft.
* Select the Thursday publication time.
* Enable or disable automatic publishing.
* Review the next scheduled run and last-run result.

== Safety behavior ==

* A deterministic weekend key prevents duplicate weekly posts.
* A published guide is never overwritten automatically.
* A draft is updated in place when regenerated.
* Empty weekends do not create empty posts.
* Only published Events Manager events are included.
