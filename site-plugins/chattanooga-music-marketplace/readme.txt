=== Chattanooga Music Marketplace ===
Contributors: chattanoogamusicscene
Tags: marketplace, listings, products
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Dedicated Marketplace presentation for Chattanooga Music Scene.

== Purpose ==

This feature keeps the Marketplace independent from unrelated site features. It
combines published, catalog-visible store products with the existing community
listing stream while leaving the community listing system responsible for its
submission, contact and location workflows.

There is no separate customer-facing store label. Store products are rendered as
Marketplace items alongside community listings and use their normal product page
when opened.

== Deployment guard ==

The feature activates only when the Marketplace page uses the dedicated
[cms_marketplace] shortcode. It remains inactive while either legacy
[AWPCPCLASSIFIEDSUI] or standalone [products] output is still present on that
page. This fail-closed transition prevents duplicate or split Marketplace output
while the old page content is being replaced.

The dedicated shortcode renders the community-listing stream and catalog-visible
store products together. The old community-listing and store-product shortcodes
therefore must be removed from the Marketplace page when the transition is made;
they must not be hidden with CSS or left behind as duplicate renderers.

== Browse and search behavior ==

The same unified renderer is used by the AWP Classifieds Browse and Search routes
through their public replacement hooks. If another integration has already
provided non-null replacement output, this feature leaves that output unchanged
instead of taking ownership of the route.

Unfiltered Marketplace results interleave catalog-visible store products with
community listings. Text search and price filters also apply to store products.
A community category includes store products only when that category has an exact
name or slug match in the store catalog; unrelated products are never inserted by
guesswork.

Location filters remain fully active. Store products currently have no Marketplace
location record, so they do not enter a location-filtered result. This preserves
the meaning of locations instead of treating Chattanooga itself as a redundant
location label.

== Safety ==

* Only published, catalog-visible store products enter the stream.
* Hidden or internal products stay excluded through catalog visibility.
* Later listing pages do not repeat store products.
* Category mappings are evidence-based exact matches, not inferred aliases.
* AWP Browse and Search keep any replacement output already owned by another integration.
* The Weekend Feature is not loaded, modified or depended on by this feature.
