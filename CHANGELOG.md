# تاریخچهٔ تغییرات

تغییرات هر نسخه در این فایل ثبت می‌شود. شماره‌ها از الگوی `MAJOR.MINOR.PATCH` پیروی می‌کنند.

## [1.5.1] - 2026-10-23

- Fix update discovery when the plugin's six-hour release cache or WordPress's update cache still reports an older release. Add an authorized POST check that bypasses success/failure caches and merges this plugin's update entry into WordPress's existing update data without installing anything or deleting other plugins' entries. The Settings button now performs that check and displays current/latest versions, update-ready/current state or a safe connection/HTTP/metadata error.
- Make Dashboard Updates → Check Again refresh GitHub metadata. Register cached-read as well as write-time update filters so GitHub discovery does not depend on a successful WordPress.org update response. Populate no_update when current and clear this plugin's obsolete notification; preserve WordPress's automatic-installation preference.
- Retry rejected optional-token metadata requests once without credentials for public releases; download public fallback assets without the invalid token and keep redirect credential stripping, expected size and SHA-256 checks. Keep normal six-hour metadata caching and five-minute failure throttling; explicit checks bypass both. Do not return raw HTTP errors, response bodies or tokens.
- Register admin/cron/CLI update hooks before the WooCommerce dependency gate, and declare the GitHub Update URI. Retain no public assets or plugin boot SQL; shipped CSS/JS is 59,625 bytes (22,363 deterministic gzip), below the unchanged 60,000-byte budget.
- Validation: 26 PHP files linted; 1,532 price, 58 market, 14 spreadsheet and 35 checks in each public/private updater mode; both source/minified DOM suites passed. A disposable PHP 8.3 / WP 7.1.3 fixture passed 20 updater lifecycle assertions, including real Plugin_Upgrader download/unpack/replacement, a new request executing the upgraded fixture, cached-read recovery while WordPress.org HTTP is blocked, forced refresh, manager denial, safe failures, and update hooks after WooCommerce deactivation. HTTP and future-version packages are fixtures; no real live-store update or visual verification is claimed. This browser could not authenticate to the live dashboard, so the exact host-side cause remains unconfirmed. Existing installations need the fixed code installed once to use its fresh-check controls.

## [1.5.0] - 2026-10-22

- Add administrator-managed automatic pricing for WallGold 18K gold, USD and EUR. Capture each selected simple product or variation's original prices and an explicit reference rate in Toman; calculate every cycle from that fixed baseline, without compounding previous updates.
- Support regular prices, existing sale prices or both, existing none/up/down/nearest rounding and custom steps/endings. An optional below-baseline reduction switch clamps the ratio and rounded result to the original price floor; declines from a higher rate toward the reference can still reduce prices.
- Require a complete, unexpired preview and explicit activation. Show sample changes and a full paginated report; prevent ordinary history Apply from bypassing market-rule activation. Pause, resume, run now, delete and change the 5–1440 minute interval without rebasing. Newly added products/variations are not silently included.
- Schedule one event per activated rule, with bounded planning (50 rows) and WooCommerce CRUD writes (10 rows), a replacement event for interrupted workers, existing undo/history and receipt reconciliation after partial writes. The interval starts after a cycle completes; WP-Cron needs site traffic or a server cron. Pausing/deleting preserves current prices and prior reports.
- Reject overlapping product membership, stale table versions and changed currency/precision/permissions; skip manually changed prices. Validate source schema, unit/divide flags and quote age; retry source failures without stale-price writes. Use a fixed public endpoint, 60-second shared rate cache and 15-second failure throttle; send no product data or credentials.
- Load market PHP only on use. Keep rule metadata in one non-autoload option (20 rules maximum) and one fixed baseline table (5,000 products/variations per rule). Do not fetch rates at page startup or enqueue storefront assets. Actual price-change history is retained; unchanged cycles create no empty price jobs. Move the main static grid into an admin-only PHP template to retain the unchanged 60,000-byte budget: shipped CSS/JS totals 59,494 bytes (22,318 deterministic gzip bytes).
- Validation: 25 PHP files linted; 1,532 price, 58 market arithmetic/feed, 14 spreadsheet and 20 public/private updater checks; source/minified DOM suites passed. Disposable PHP 8.3 / WP 7.1.3 / WooCommerce 11.2.1 passed 275 combined assertions in each HPOS and legacy mode. Cold baseline capture measured 25 queries for both 2 and 40 variations; product-grid reads remain 17/17, with zero boot SQL and public assets. Integration HTTP is mocked; the live public feed contract was checked separately. Browser visual rendering, real MySQL concurrency, actual dashboard upgrade and live-store installation remain unverified.

## [1.4.0] - 2026-10-22

- Add an in-app Persian Help workspace and header shortcut: a three-step pricing guide, worked 10% + ending-9000 example, fixed/percentage/reduction recipes and FAQs for scope, units, variations, stock, COGS, files, branding, errors and undo.
- Move bulk pricing, stock, schedules and history into a dedicated Pricing workspace. Add a visible entry from the product table, grouped fieldsets, current-scope explanation and an illustrative client-side price calculator. Example buttons only fill controls; product edits still require the immutable server preview and explicit confirmation.
- Fix branding feedback: apply the server-confirmed title, accent, logo and WooCommerce submenu label immediately after saving. Show unsaved edits in a separate identity preview, preserve the applied identity after failed saves, replace/clear logos without duplicates, and show saved branding after a fresh page load.
- Normalize three-digit brand colors, choose light/dark button text for the chosen accent, reject invalid/non-HTTPS logo addresses explicitly and preserve administrator-only branding controls during initial asynchronous loading.
- Refresh the RTL interface with eight workspaces, consistent surfaces, clearer typography/controls, responsive form groups, horizontally scrollable mobile navigation and isolated table scrolling. Keep Vazirmatn local; add no UI framework or public storefront assets.
- Render static admin forms/help as admin-only PHP templates, retaining zero additional template-rendering SQL and the unchanged 60,000-byte admin CSS/JS cap. Shipped CSS/JS totals 59,239 bytes (21,077 deterministic gzip bytes).
- Enable tokenless GitHub metadata checks and verified asset downloads for the now-public repository; keep optional private-token access and credential-free redirects. Versions before 1.4 without a configured token need one manual ZIP replacement before future tokenless dashboard updates.
- Validation: 21 PHP files linted; 1,532 pricing checks, 14 spreadsheet checks, 10 private and 10 public updater HTTP checks, and both source/minified DOM suites passed. Disposable PHP 8.3 / WordPress 7.1.3 / WooCommerce 11.2.1 passed 218 combined assertions in each HPOS and legacy fixture, including persisted branding, invalid-logo rejection, normalized colors, role restrictions and query-free admin templates. Cold product queries remain 17 for both 2 and 40 variations, with native COGS on/off. A reproducible mock-data preview generator is included; browser visual review was not completed because Cloud Browser rejected local file navigation. No live-store installation, real dashboard upgrade or MySQL concurrency validation was performed.

## [1.3.0] - 2026-10-22

- Add explicit preview/apply for missing variations on existing variable parents, with custom/global attribute options, duplicate/wildcard coverage detection, unique SKU validation, stale-parent checks and retry receipts. Keep existing children; validate all rows before transactional creation (maximum 100 variations and 20 parents per preview).
- Add new-variation XLSX/CSV staging for existing parents matched by ID or SKU, plus separate creation preview. Extend existing attribute options and preserve global term labels; require every variation dimension and the appropriate taxonomy permissions.
- Save personal views (filters, visible columns and column order) and reusable price rules, bounded to 20 entries / 32 KB per user. Loading a price rule fills controls and still requires price preview/confirmation; bind rules to currency and price precision.
- Use WooCommerce's native COGS product APIs whenever enabled, including effective inherited/additive variation costs, absolute-cost editing, inheritance reset and exact native-definition undo. Keep the legacy backend only while native COGS is disabled; block stale cost jobs if the backend changes.
- Offer explicit preview migration of eligible legacy purchase costs, retaining legacy metadata and skipping conflicting native/inherited costs. No silent migration, dual-write or historical-order backfill.
- Read native recorded order-line costs for profit reports while COGS is active and stop writing duplicate Nil cost snapshots; retain historical legacy snapshots as fallback when a native line cost is absent.
- Preserve zero boot SQL, zero public assets and the existing 60,000-byte admin CSS/JS budget: shipped assets total 59,870 bytes (20,819 gzip bytes). New variation/preset PHP modules load on demand; variation plans expire in one bounded per-user slot.
- Validation: PHP lint (19 files), 1,532 pricing checks, 14 spreadsheet checks, 10 updater HTTP checks and source/minified DOM tests pass. Disposable PHP 8.3 / WordPress 7.1.3 / WooCommerce 11.2.1 suites passed 209 combined assertions in each HPOS and legacy mode, including late-SKU rollback and recorded native report costs. Cold table requests measured 17 queries for both 2 and 40 variations with native COGS enabled and disabled. One initial legacy run stopped at the pre-existing conservative image-reference assertion; the unchanged assertion passed on rerun with expanded failure diagnostics. Real MySQL concurrency, live-store visuals, dashboard upgrade and site performance scores remain unverified; no live installation is performed.

## [1.2.0] - 2026-10-22

- Verify the stable compatibility target WordPress 7.1.3 / WooCommerce 11.2.1, retaining HPOS and variation-aware flows.
- Remove schema-version SQL from public boot, load optional tools on demand, batch variation post/meta/taxonomy reads and reuse the primed metadata cache for purchase cost.
- Make expensive unused-image scans explicit, selected and bounded to five images per request; listing no longer scans post/meta/options references.
- Bound temporary imports, media previews and insights to one expiring slot per tool/user; move new creation receipts from WordPress options to their products and cap media backup metadata at three snapshots.
- Add explicit bounded cleanup of abandoned drafts and separately confirmed old completed history, preserving active jobs and other users' history.
- Introduce seven accessible RTL workspaces, collapsible advanced filters, sticky headers/product columns, visible table actions and responsive controls.
- Serve minified production CSS/JS only in the plugin admin screen. Check production/source consistency and a 60 KB CSS/JS budget in CI; exclude development assets from installation ZIPs.
- Validation: source/minified DOM tests, 1,532 pricing checks, 14 spreadsheet checks and 10 updater HTTP checks; HPOS-enabled and legacy-storage performance suites each passed 156 combined assertions with 17 cold-cache queries for both 2 and 40 variations, zero boot SQL and no storefront assets. See PERFORMANCE.md and CONTRIBUTING.md for measurements and limitations; no whole-site GTmetrix score is promised.

## [1.1.0] - 2026-10-22

- Extend the Persian product grid with editable names, SKU, descriptions, categories, brands, purchase cost, indicative profit and margin. Keep preview, stale-write checks and undo for these fields.
- Add brand/type/publication/stock/price filters and last-modified sorting; create draft or published simple/variable products with up to 100 generated attribute combinations and retry protection.
- Add bulk stock previews and cost-based pricing; saved once/daily/weekly WP-Cron rules run in bounded batches and retain price-job history.
- Add bounded CSV/XLSX product exports, column mapping, version-protected imports, previews for existing simple/variable/variation edits, and draft creation for new simple/variable rows. New standalone variation rows are rejected; use variable creation.
- Add image previews, bulk ALT templates, real file and thumbnail renaming, main-image optimization, conservative unused-image candidates, reversible archival and restoration. Old files remain for existing URLs; archive does not reclaim disk space.
- Add customer purchase/recency/product filters and Excel reports. Report net sales and known historical gross profit after refunds; capture unit purchase cost on future order items, without inventing historical costs.
- Add title/logo/accent white labeling, WooCommerce HPOS declaration and WooCommerce CRUD order reads, and dashboard updates from private GitHub releases using a server-only read token.
- Validation: 1,532 pricing checks, 14 spreadsheet checks, 10 private-updater HTTP mock checks, expanded DOM interaction checks, and disposable WP 7.1.3 / WC 11.2.1 integration: 138 legacy-storage assertions and 140 HPOS assertions. All plugin/test PHP files lint successfully. SQLite shims do not prove MySQL checkout-concurrency safety; real dashboard token setup and live-site installation are separate tasks.

## [1.0.0] - 2026-10-21

### امکانات اولیه

- جدول فارسی مدیریت قیمت اصلی، قیمت حراج و موجودی محصولات ساده و ورییشن‌های رنگ/سایز.
- تفکیک موجودی مستقل ورییشن از موجودی مشترک والد.
- افزایش و کاهش مبلغی یا درصدی، تعیین قیمت ثابت و رندکردن مستقل.
- رندکردن رو به بالا، رو به پایین و نزدیک‌ترین با گام و پایان قابل تنظیم، از جمله پایان ۹۰۰۰.
- اعمال تغییر روی انتخاب‌ها، صفحه فعلی یا تمام خانواده‌های محصولات فیلترشده.
- جست‌وجوی نام، SKU و شناسه و فیلتر دسته‌بندی.
- پیش‌نمایش قبل از ذخیره، اعتبارسنجی قیمت حراج و تشخیص تغییر محصول بعد از بارگذاری.
- ذخیره مرحله‌ای با امکان ادامه، تاریخچه، بازگردانی و گزارش CSV.
- فونت وزیرمتن به‌صورت محلی و راهنمای نصب فارسی.

### اعتبارسنجی

- ۱۵۳۲ بررسی قیمت و آزمون‌های خودکار تعامل جدول پاس شده‌اند.
- ۵۰ بررسی ووکامرس در محیط موقت WordPress 7.1.3 / WooCommerce 11.2.1 پاس شده‌اند.
- محیط آزمون ووکامرس SQLite با شبیه‌سازی دستورات قفل MySQL بوده است؛ هم‌زمانی واقعی سفارش‌ها و سازگاری با افزونه‌های سایت نیل هنوز بررسی نشده‌اند.

### نگهداری پروژه

- نسخهٔ اولیهٔ کد، تست‌ها، راهنمای توسعه و روال ساخت ZIP برای نگهداری در Git آماده شده‌اند.
- روال GitHub Actions برای تست و ساخت تگ و انتشار ZIP از شمارهٔ VERSION بعد از ارسال تغییرات به main اضافه شده است؛ نسخه‌های منتشرشده بازنویسی نمی‌شوند.
