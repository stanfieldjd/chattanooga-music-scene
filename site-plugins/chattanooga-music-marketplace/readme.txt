=== Chattanooga Music Marketplace ===
Contributors: chattanoogamusicscene
Tags: marketplace, listings, products
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later

Dedicated Marketplace presentation for Chattanooga Music Scene.

== Purpose ==

This feature keeps the Marketplace independent from unrelated site features. It
combines published, catalog-visible store products with the existing community
listing stream while leaving the community listing system responsible for its
submission, category, search, contact and location workflows.

There is no separate customer-facing store label. Store products are rendered as
Marketplace items alongside community listings and use their normal product page
when opened.

== Deployment guard ==

The feature remains inactive while the Marketplace page still contains the legacy
standalone [products] block. This prevents duplicate product output during a
future deployment transition. The existing community listing shortcode must also
remain present.

== Filter behavior ==

The first unfiltered Marketplace listing page can receive store products. Existing
category, search and location filtering remains untouched; store products are not
inserted into a filtered result until a verified cross-source filter mapping is
implemented.

== Safety ==

* Only published, catalog-visible store products enter the stream.
* Hidden checkout-bridge products stay excluded through catalog visibility.
* Later listing pages do not repeat store products.
* The Weekend Feature is not loaded, modified or depended on by this feature.
