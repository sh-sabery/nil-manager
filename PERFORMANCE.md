# Performance contract — v1.5.1

## Scope

“Zero bloat” means no unnecessary storefront assets, boot-time database work or unbounded temporary receipts. It does **not** mean zero storage or zero SQL while editing products, calculating reports or saving historical order costs. Essential costs, explicit pricing schedules and undo history have a purpose and are retained.

The latest stable releases verified on 2026-10-22 Tehran time are WordPress 7.1.3 and WooCommerce 11.2.1. Compatibility is exercised in disposable PHP 8.3 installations with HPOS enabled and disabled. WordPress beta releases are outside this stable-release claim.

## Measurable limits

| Area | Contract |
|---|---|
| Public CSS, JavaScript and fonts | 0 plugin enqueues on public pages; also none on unrelated admin screens |
| Plugin boot | 0 SQL issued by the plugin's boot function; schema-version work only during authorized admin initialization |
| Admin assets | Four minified vanilla-JS files and one minified stylesheet, 59,625 bytes total; 22,363 bytes with deterministic gzip in the local build. Server compression must be enabled to realize that transfer size. |
| Font | Local Vazirmatn WOFF2, 46,308 bytes, requested only by the plugin admin stylesheet; no CDN font |
| Build budget | CSS/JS limited to 60,000 uncompressed bytes and checked in CI; readable source files remain in git and are excluded from the installation ZIP |
| In-app help and static forms | Rendered as templates only on the plugin admin page; 0 additional SQL, no extra script, library or remote asset |
| Optional PHP tools | Spreadsheet, media, maintenance, variation creation, presets, market pricing and report code load on use; update hooks load in admin/cron contexts |
| Product pages | 15 parent/simple products per page; variation posts, metadata and taxonomy caches loaded together; legacy cost reads use the primed WordPress metadata cache; native costs use WooCommerce CRUD |
| Media listing | No reference scans; scans require an explicit selection and run in batches of at most 5 images; archive rechecks references |
| Temporary state | One active XLSX/CSV file, one media preview and one insights report and one new-variation plan per user, each with a one-hour expiry; starting another invalidates the older token |
| Creation receipts | One small token on its product/created variation instead of an indefinite new WordPress option per creation |
| Saved views and rules | One user-meta record per user, at most 20 entries and 32 KB; loaded only when requested |
| Media backup metadata | Last 3 metadata snapshots; physical old files stay to preserve old URLs |
| Maintenance | Explicit user action, at most 100 owned jobs per request; remove abandoned drafts older than 7 days; completed history deletion requires confirmation and an age of at least 30 days (UI defaults to 90) |
| Market pricing | One non-autoload metadata option (20 rules); fixed baseline table, one row per participating product (5,000 per rule). No market request at boot or page startup. Only explicit rate/preview actions and activated rules fetch the fixed public feed; shared 60-second cache and 15-second error throttle. Capture/plan at most 50 rows; apply at most 10 per request. One scheduled event per active rule; no empty price job for unchanged cycles. Actual change/undo history intentionally uses storage. |
| Background tasks | No periodic analytics or media scans; only user-created price schedules/market rules and normal WordPress update mechanisms |

## Verification and practical limits

The integration regression compares cold-cache reads of a parent with 2 variations and a parent with 40 variations. The fixtures measure both legacy cost metadata and native COGS. Each measured 17 SQL queries for both cases (the first 40-variation probe issued 97). The regression requires query count to remain within a small fixed margin rather than grow linearly with the variation count. It also verifies zero SQL during plugin boot, no unrelated-screen assets, no reference-scan SQL during media listing, bounded cache replacement and cleanup ownership/state protections. Market baseline capture measured 25 queries with 2 variations and 25 with 40; cache priming includes parents, and one multirow insert captures each batch. These counts include endpoint checks, settings locks and baseline storage; they are not a zero-SQL editing claim. See CONTRIBUTING.md for measured results.

DOM tests run against both readable sources and the exact production minified JavaScript. They cover table edits, shared stock, selection, preview/apply/undo, imports, quick creation, branding, tab navigation, saved column layouts and adding variations to existing parents, help recipes, sample-price calculation, immediate branding changes/failures and startup branding after a fresh document. Market tests also cover explicit rate loading, scoped capture, full preview, activation, interval changes and pause/resume without duplicate controls. They do not substitute for visual testing of the actual store's admin theme.

GTmetrix scores describe the whole tested page and are at most 100, not above 100. This plugin cannot guarantee a whole-site GTmetrix score: theme, hosting, images, other plugins, scripts, cache, network and test conditions matter. No live-store GTmetrix test, real MySQL concurrency test or automatic live installation is implied by this release. The SQLite fixture emulates MySQL locking only. With native COGS enabled, WooCommerce records the order cost and Nil does not duplicate its unit-cost metadata. Without COGS, Nil captures the small immutable cost metadata needed for future historical profit.

References: [WordPress releases](https://wordpress.org/download/releases/), [WooCommerce changelog](https://developer.woocommerce.com/changelog/), [GTmetrix scoring](https://gtmetrix.com/blog/everything-you-need-to-know-about-the-new-gtmetrix-report-powered-by-lighthouse/).

## Market timing and retention

Market cycles use a fixed captured cohort and immutable original prices, not a growing price history as a baseline. One replacement event is scheduled before each worker step to recover from interruptions. Completion schedules the next interval (5–1440 minutes); long batches and traffic-dependent WP-Cron can delay the wall-clock cadence. A server cron is required for reliable scheduling. Source failures retry, while permission/currency failures pause; no stale-rate writes are allowed. WordPress/MySQL advisory locks serialize rule, settings and product writes, but the disposable SQLite tests do not establish real MySQL concurrency behavior.

Deleting a rule removes its baseline records and pending event but preserves current prices and historical reports. Pausing retains baselines. Source failures and unchanged cycles update only bounded rule state; completed price-change history is retained for undo and can be removed through the existing explicit history cleanup. Frequent schedules with many changed products can produce substantial purposeful history; there is no automatic history deletion or claim of zero database growth.

## Update discovery

Updater hooks stay confined to admin, cron and WP-CLI contexts and are registered before the WooCommerce dependency gate. Reading WordPress's plugin-update transient merges this plugin's verified release metadata, including a no-update entry when current; it does not require a successful WordPress.org update response. Cached ordinary reads retain the six-hour metadata cache and five-minute failure throttle. An authorized explicit check or dashboard Check Again bypasses those caches once, without periodic extra jobs, storefront work, installing software or replacing other plugins' update entries. Failed optional-token requests can retry anonymously once for a public release. Error metadata is one five-minute site transient containing only a safe category/message, status and timestamp.
