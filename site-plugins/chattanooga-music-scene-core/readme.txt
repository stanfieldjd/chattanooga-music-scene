=== Chattanooga Music Scene Weekend Feature ===
Contributors: chattanoogamusicscene
Tags: events, weekend, publishing
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.2.18
License: GPLv2 or later

Site-specific publishing tools for Chattanooga Music Scene.

== Weekend posts ==

The Weekend Posts tool reads published Events Manager events occurring Friday
through Sunday and generates a dedicated Weekend Feature with up to five
curated highlights while preserving Friday, Saturday, and Sunday representation
when those days have eligible events.

The Scene placement is explicit and shortcode-only. Add
[cms_weekend_feature] where the separate Weekend Feature section should appear.
The plugin does not inject or rewrite page content through the_content. The
Scene presentation uses an editorial image-and-paper-card treatment designed to
match the page's collage language while remaining separate from Featured
Stories.
Between published editions, the same section remains visible with an upcoming
guide message and a link to the Shows calendar. It never links to an expired
guide as though that guide were current.

Each event title, image, and details link in the full Weekend Feature points to
the event's page on Chattanooga Music Scene.

The generated feature is a standard public WordPress custom post so an existing
Jetpack Social automatic-sharing connection can process it through the normal
publication transition. No Facebook credentials are stored by this plugin.

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
* Only eligible published Events Manager events are considered.
* Future generated guides persist their curated highlights directly instead of replacing content at render time.
