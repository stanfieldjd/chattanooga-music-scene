=== Chattanooga Music Scene Weekend Feature ===
Contributors: chattanoogamusicscene
Tags: events, weekend, publishing, marketplace
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.2.4
License: GPLv2 or later

Site-specific publishing and marketplace tools for Chattanooga Music Scene.

== Weekend posts ==

The Weekend Posts tool reads published Events Manager events occurring Friday
through Sunday and generates a dedicated Weekend Feature. It stays out of the
ordinary post feed and appears on The Scene through [cms_weekend_feature]. Each
event title, image, and details link points to the event's page on Chattanooga
Music Scene.

The generated post is a standard WordPress post so an existing Jetpack Social
automatic-sharing connection can process it through the normal publication
transition. No Facebook credentials are stored by this plugin.

Automatic publishing is disabled until both the enable checkbox and a Thursday
time are saved. If the weekend contains no published events, the run records an
error and creates no post.

== Marketplace ==

The Marketplace integration uses AWP Classifieds' supported rendering hooks to
place published, catalog-visible store products directly into the existing
Marketplace listing stream. Product cards use the same public Marketplace flow
without adding a separate store section or exposing the commerce engine as a
customer-facing label.

The integration runs only on the Chattanooga Music Marketplace page. Existing
classified controls, categories, searches, and location information remain under
AWP Classifieds and are not removed or replaced.

The integration does not activate while the legacy [products] shortcode remains
on the Marketplace page. This prevents duplicate product output during deployment.
After the updated plugin is installed, remove the old standalone product block
from the page; the interleaved Marketplace feed then becomes active.

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
* Hidden or unpublished store products are excluded from the Marketplace stream.
* Marketplace interleaving remains inactive until the legacy standalone product block is removed.
* Source-level tests run in CI but are excluded from the installable production package.
