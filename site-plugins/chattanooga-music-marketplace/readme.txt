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

The feature remains inactive while the Marketplace page still contains the legacy
standalone [products] block. This prevents duplicate product output during a
future deployment transition. The existing community listing shortcode must also
remain present.

== Filter behavior ==

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
* The Weekend Feature is not loaded, modified or depended on by this feature.
