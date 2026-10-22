=== Nilwears Product Operations ===
Contributors: sh-sabery
Requires at least: 6.4
Tested up to: 7.1.3
Requires PHP: 7.4
Stable tag: 1.3.0
Requires Plugins: woocommerce
License: GPL-2.0-or-later

Persian, variation-aware WooCommerce product operations. Tested with WooCommerce 11.2.1, including HPOS on and off.

== Description ==
Manage products, prices, stock, purchase cost, XLSX/CSV, images and customer reports in a scoped RTL admin screen. No storefront CSS/JavaScript/font is enqueued. Optional PHP modules load on use; temporary tool state is bounded per user. Essential cost metadata and undo history intentionally use database storage.

See README-fa.html for usage, and the private repository README.md, PERFORMANCE.md and CHANGELOG.md for measurements, limits and release history. Dashboard updates require a server-side read-only GitHub token.

== Changelog ==
= 1.3.0 =
Append variations to existing parents from the form or mapped XLSX/CSV, save personal views and price rules, and use native WooCommerce COGS when enabled. Preview legacy-cost migration; retain conflicting native costs.
= 1.2.0 =
Seven admin workspaces, production minification, batched variation loading, on-demand image scans, bounded cache slots and explicit history cleanup. No whole-site GTmetrix score is guaranteed.
