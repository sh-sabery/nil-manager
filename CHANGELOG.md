# تاریخچهٔ تغییرات

تغییرات هر نسخه در این فایل ثبت می‌شود. شماره‌ها از الگوی `MAJOR.MINOR.PATCH` پیروی می‌کنند.

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
