# Selected feature coverage — v1.8.0

The 21 selected numbers refer to the feature list discussed with the user. This is an independent implementation in the Nilwears plugin.

| Number | Capability | Implementation / limits |
|---|---|---|
| 1 | Excel-style product table | Inline price, stock, purchase cost and expandable product information; parents and variations remain separate. |
| 2 | Bulk product editing | Selection, current page and all filtered products; immutable preview jobs, history and conflict-aware undo. XLSX/CSV handles batches of product information. |
| 3 | Advanced filters | Text/exact SKU/ID, category, brand, type, publication status, stock status and price range. Parent price filters follow WooCommerce's stored parent price. |
| 4 | Brand filters | Core `product_brand`, or existing `pwb-brand` / `yith_product_brand` taxonomy. Brands must already exist. |
| 5 | Last-modified sorting | Default admin sort; ID and name alternatives. |
| 6 | Quick product creation | Simple or variable; draft/published; custom attribute combinations, up to 100 variations, SKU and retry protection. |
| 9 | Bulk price and stock | Fixed/percentage/set/round price rules and stock previews; shared parent stock and independent variation stock respected. |
| 10 | Automated pricing | Once/daily/weekly cost-based WP-Cron rules; bounded batches and recorded jobs. Traffic or a server cron is needed. |
| 11 | Rounding | Up/down/nearest, configurable step and ending; the original 10% + ending 9000 example remains supported. |
| 12 | Purchase cost | Native WooCommerce COGS when enabled, including inherited/additive variation costs; otherwise legacy per-product metadata. Explicit preview migration; no conflicting native overwrite. |
| 13 | Profit and margin reports | Indicative inventory profit/margin and export; historical order-line gross profit based on recorded native COGS or legacy captured cost, net of refunds. Unknown historical costs remain unknown. |
| 15 | XLSX / CSV | Product and report export, values-only import; bounded size/rows, formula protection, numeric report cells and text SKU cells. |
| 16 | Column mapping / file roundtrip | Map input headers, match ID/SKU, preserve version conflict checks, preview existing edits. New simple/variable rows become drafts; new variation rows target existing parents by ID/SKU and have a separate preview. |
| 17 | Image management / optimization | Page/selection bulk actions; main JPG/PNG/WebP optimization with size/quality controls. Original files remain; thumbnail files are copied rather than recompressed. |
| 18 | Real file rename | Rename the active main image and generated size copies, update attachment metadata, retain old URLs. |
| 19 | Bulk ALT | Templates using title, ID or filename; preview and per-image version checks. |
| 20 | Unused-image candidates | Check known post/meta/options references, recheck before reversible archive, restore from archive. External/custom references cannot be ruled out; permanent deletion/disk reclamation is not automatic. |
| 27 | Customer analysis | Purchase count, spend, recency and purchased-product filters, period-based loyalty and exports. Paid/refunded orders, mixed currency excluded and counted separately. |
| 48 | White labeling | Plugin/menu/header title, HTTPS logo and accent; plugin identity and update slug stay stable. |
| 49 | HPOS | Compatibility declaration, WooCommerce CRUD order access and disposable HPOS-enabled integration tests. |
| 50 | Dashboard updates | Public GitHub release updater without a token from v1.4; optional server-side read token for private access. Metadata caching, safe redirect handling and digest checks. |

See [README.md](README.md) for use and [CONTRIBUTING.md](CONTRIBUTING.md) for validation evidence and reproducible tests. This release updates the repository and installation package; it does not install itself on the live store.


Additional v1.3 requests (numbers 2 and 5 refer to the later improvement suggestions, separately from the original comparison list): append missing color/size combinations to existing variable products and save per-user filters, visible/ordered columns and reusable bulk-price rules. Existing variation wildcard coverage, duplicate SKU checks, stale-parent checks and transactional rollback are covered by disposable fixtures. See README.md for limits and cost-source switching semantics.

Version 1.4 adds in-app Persian help, a dedicated pricing workspace, non-executing example recipes, illustrative client-side price calculation and immediate post-save branding with an isolated unsaved preview. The interface is reorganized and restyled without adding a UI framework or increasing the 60 KB CSS/JS cap.

Version 1.5 adds the later market-pricing request independently of the original numbered list: WallGold 18K gold, USD and EUR; a Toman reference rate and fixed captured product prices; configurable below-baseline reductions; none/up/down/nearest rounding; preview and explicit activation; 5–1440 minute intervals editable without rebasing; pause/resume/delete, source/manual-edit protections and variation-aware CRUD. The scope is fixed when captured; frequency depends on WP-Cron and batch completion. See README.md for examples and limits.

Version 1.5.1 repairs update discovery: explicit cache-bypassing checks and dashboard Check Again, persisted WordPress update entries, cached-read detection independent of WordPress.org connectivity, safe error diagnostics, bounded public fallback for an invalid optional token, and update hooks even when WooCommerce is inactive. It does not enable automatic installation or install itself on the live store.

Version 1.6 adds an explicit Pricing scope selector for a category (including descendants, independent of grid filters) or a persistent-in-session multi-product/variation picker. Fresh bounded resolution, parent expansion/deduplication and scope review feed both manual pricing and market rules. The old cost scheduler keeps its separate table-filter scope and rejects the new modes. Desktop sidebar/mobile horizontal navigation, live local counters, and bundled SVG update/detail artwork complete the interface update.

Version 1.7 adds dedicated discount creation from regular price (percent/fixed), rounding and removal with immediate clearing/exact undo of prior sale dates. Group stock supports set/add/subtract/unavailable, existing numeric tracking for relative operations, explicit shared-parent ownership, rejection of negative results and backorders-aware unavailable behavior. Both reuse the selection and immutable preview/apply/history/undo pipeline. Softer inputs/buttons and grouped operation cards use the existing CSS/JS budget, without a new module, schema, endpoint or periodic task.

Version 1.8 adds Persian UI date inputs/displays/report exports and filenames in the WordPress site zone, while retaining standard storage and strict Gregorian/ISO REST compatibility. The accepted calendar range is 1200–1499 with leap/day/time and DST-gap checks, without PHP intl. Shared cached browser number/date formatters and product/meta/parent priming remove N+1 reads in previews and exports. Variation/file/XML/image/report/body limits, taxonomy assignment checks, safer CSV/thumbnail handling, unsafe-URL rejection and mandatory update SHA-256 harden existing paths. No new schema, automatic scan, cleanup cron or frontend library. See AUDIT.md for diagnostics and limits.

Version 1.9 adds an admin-only start screen, three focused journeys sharing explicit scope, category-name feedback, a separate on-demand history workspace and stale-preview closure without deleting server jobs. Existing product/variation, pricing, stock, market and additional tools remain. The production CSS builder preserves descendant pseudo-class whitespace so input corners/focus rules reach controls; primary hover contrast and mobile market report containment are fixed. PRODUCT-PLAN.md defines commercial scope; Zhaket licensing and distribution are not part of this release.
