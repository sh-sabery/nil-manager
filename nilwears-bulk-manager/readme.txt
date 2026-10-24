=== Nil Manager by Nil Web ===
Contributors: sh-sabery
Requires at least: 6.4
Tested up to: 7.1.3
Requires PHP: 7.4
Stable tag: 1.9.2
Requires Plugins: woocommerce
License: GPL-2.0-or-later

Persian, variation-aware WooCommerce product operations. Tested with WooCommerce 11.2.1, including HPOS on and off.

== Description ==
Manage products, prices, stock, purchase cost, XLSX/CSV, images and customer reports in a scoped RTL admin screen. No storefront CSS/JavaScript/font is enqueued. Optional PHP modules load on use; temporary tool state is bounded per user. Essential cost metadata and undo history intentionally use database storage.

See README-fa.html for usage, and the repository README.md, PERFORMANCE.md and CHANGELOG.md for measurements, limits and release history. Public-repository updates need no token from v1.4. Older versions without a token require one manual v1.4 ZIP installation. An optional token supports private access.

== Changelog ==
= 1.9.1 =
Guarded native MySQL/MariaDB functional and concurrency validation with HPOS on/off; publication now requires the database matrix. No pricing/UI/runtime dependency change. See SQL-VALIDATION.md and BETA-GUIDE.md in the repository.
= 1.9.0 =
A local start screen with manual price, stock/discount and market journeys; shared product scope, isolated forms, stale-preview closure and an independent on-demand history workspace. Preserve descendant pseudo-class spacing in production CSS so rounded input/focus styles apply. See PRODUCT-PLAN.md in the repository for commercial scope and remaining work.
= 1.8.1 =
Adopt the Nil Manager product identity and Nil Web publisher across the admin defaults, plugin metadata, update details, banner, guide and worksheet. Preserve custom branding, installed plugin identity and stored data. Commercial licensing and Zhaket distribution are not included.
= 1.8.0 =
Persian date inputs/displays/exports in the site time zone; bounded resource processing and batched product reads; REST/taxonomy/CSV/XML/media/update hardening. See the repository AUDIT.md for measured results and limits.
= 1.7.0 =
Dedicated regular-price discount creation/removal with rounding and exact sale-date undo. Stock set/add/subtract/unavailable with shared-parent protection. Softer inputs/buttons and grouped action cards.
= 1.6.0 =
Select a category or multiple named products directly in Pricing, with variation-aware scope review. Updated responsive navigation, selection/edit counters, and local WordPress update icons/details banner.
= 1.5.1 =
Fresh update checks bypass the release cache and refresh WordPress update data. Display connection errors, fall back to public access for invalid optional tokens, and keep update hooks available without active WooCommerce.
= 1.5.0 =
Fixed-baseline scheduled pricing with WallGold 18K gold, USD and EUR. Reference rates in Toman, optional reduction below baseline, rounding, configurable intervals, explicit activation, pause/resume and source/manual-edit protections.
= 1.4.0 =
In-app Persian help, dedicated pricing workspace, safe example recipes and live sample calculation. Immediate saved-brand application with an unsaved preview, refreshed responsive styling and tokenless public-release updates.
= 1.3.0 =
Append variations to existing parents from the form or mapped XLSX/CSV, save personal views and price rules, and use native WooCommerce COGS when enabled. Preview legacy-cost migration; retain conflicting native costs.
= 1.2.0 =
Seven admin workspaces, production minification, batched variation loading, on-demand image scans, bounded cache slots and explicit history cleanup. No whole-site GTmetrix score is guaranteed.
