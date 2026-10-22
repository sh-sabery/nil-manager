# تاریخچهٔ تغییرات

تغییرات هر نسخه در این فایل ثبت می‌شود. شماره‌ها از الگوی `MAJOR.MINOR.PATCH` پیروی می‌کنند.

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
