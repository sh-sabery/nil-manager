<div dir="rtl">

# مدیریت قیمت و موجودی نیل

افزونهٔ اختصاصی ووکامرس برای مدیریت قیمت و موجودی محصولات ساده و ورییشن‌های رنگ/سایز، با پیش‌نمایش، تغییر گروهی، رندکردن قابل تنظیم و بازگردانی.

**نسخهٔ فعلی: 1.3.0**

## نصب

فایل `nilwears-bulk-manager-vX.Y.Z.zip` را از بخش Releases هر نسخه دریافت و از مسیر **افزونه‌ها ← افزودن ← بارگذاری افزونه** نصب کنید. بعد از فعال‌سازی، صفحه از مسیر **ووکامرس ← قیمت و موجودی نیل** در دسترس است.

برای ساخت محلی ZIP، از دستور ساخت پایین استفاده کنید. فایل Source code خودکار GitHub، بستهٔ نصب ووکامرس نیست؛ ZIP اختصاصی همان نسخه را بگیرید.

راهنمای کامل فارسی داخل [README-fa.html](nilwears-bulk-manager/README-fa.html) قرار دارد. پیش‌نیازها: WordPress 6.4+، WooCommerce 8.5+ و PHP 7.4+ از نوع ۶۴ بیتی؛ ذخیره به جداول InnoDB و پشتیبانی MySQL/MariaDB از GET_LOCK نیاز دارد.

## مثال رندکردن

برای **۱۰٪ افزایش و پایان قیمت ۹۰۰۰**: عملیات درصدی، مقدار `10`، گام `10000`، پایان `9000` و جهت رو به بالا. مثال: **۴۹۵٬۰۰۰ → ۵۴۹٬۰۰۰**. تمام اعداد با واحد فعلی ووکامرس هستند؛ تبدیل ریال و تومان انجام نمی‌شود.

## فایل‌های پروژه

| مسیر | کاربرد |
|---|---|
| `nilwears-bulk-manager/` | فایل‌های قابل نصب افزونه |
| `tests/` | آزمون‌های قیمت، تعامل جدول و سناریوهای ووکامرس |
| `CHANGELOG.md` | تغییرات هر نسخه |
| `VERSION` | شمارهٔ نسخه |
| `scripts/build.py` | ساخت ZIP و کنترل هماهنگی شمارهٔ نسخه‌ها |
| `.github/workflows/` | آزمون و انتشار نسخه‌ها در GitHub |

## توسعه و آزمون

برای توسعه، Node.js نسخه ۲۲ یا بالاتر، Python 3 و PHP لازم است. این وابستگی‌ها فقط ابزار توسعه هستند و روی سایت نصب نمی‌شوند.

</div>

```bash
npm ci
npm run check:js
npm run test:ui
php tests/pricing.php
python3 scripts/build.py
```

<div dir="rtl">

خروجی در پوشهٔ `dist` ساخته می‌شود. تست ووکامرس در `tests/integration.php` فقط برای محیط موقت آزمایشی است و محصول آزمایشی می‌سازد؛ آن را روی فروشگاه واقعی اجرا نکنید. جزئیات و محدودیت‌ها در [راهنمای توسعه](CONTRIBUTING.md) آمده‌اند.

## روال هر آپدیت

۱. تغییر کد و اجرای تست‌های مرتبط.

۲. افزایش نسخه در `VERSION`، هدر و ثابت نسخهٔ فایل اصلی PHP، `package.json` و قفل وابستگی‌ها. اصلاح باگ: PATCH؛ قابلیت سازگار: MINOR؛ تغییر ناسازگار: MAJOR.

۳. ثبت تغییرات همان نسخه و تاریخ در `CHANGELOG.md`، همراه با نتیجهٔ تست و محدودیت‌های مهم.

۴. ثبت commit و ارسال آن به شاخهٔ `main`.

۵. GitHub Actions تست‌ها را اجرا می‌کند و برای شمارهٔ جدید، تگ مثل `v1.0.1` و یک Release شامل ZIP قابل نصب و متن همان بخش CHANGELOG می‌سازد. انتشار با ارسال تگ دستی هم قابل اجراست. نسخه‌های منتشرشده، تگ‌ها و فایل‌هایشان بازنویسی نمی‌شوند. آماده‌بودن ZIP به‌معنی نصب خودکار روی نیل نیست.

شمارهٔ نسخه‌ها هنگام ساخت کنترل می‌شود؛ ناسازگاری باعث توقف انتشار خواهد شد. تغییرات خط‌به‌خط هر نسخه از مقایسهٔ تگ‌ها در GitHub قابل مشاهده است.

## وضعیت اعتبارسنجی

نتیجهٔ آزمون‌های هر نسخه در CHANGELOG.md و CONTRIBUTING.md ثبت شده است. آزمون ووکامرس از SQLite با شبیه‌سازی دستورات قفل MySQL استفاده کرده است؛ رفتار هم‌زمانی روی MySQL واقعی و سازگاری با افزونه‌های فعال نیل هنوز بررسی نشده‌اند. CI فعلی آزمون‌های قیمت و تعامل جدول را خودکار اجرا می‌کند و تست ووکامرس را خودکار اجرا نمی‌کند.

کد تحت مجوز GPL-2.0-or-later است. فونت وزیرمتن تحت مجوز SIL Open Font License در پوشهٔ assets توزیع شده است. خصوصی‌بودن ریپو مستقل از مجوز کد است.

</div>


## Version 1.1: product operations suite

[Coverage of the 21 selected features](FEATURES.md).

The WooCommerce submenu now includes product/variation editing, draft/published product creation, cost-based pricing, stock batches, XLSX/CSV interchange, scheduled pricing, image operations, customer/profit reports and branding. All existing product changes use preview jobs, conflict detection and history/undo. Product creation and media operations have separate explicit controls.

- A variable parent's price is calculated from its variations. Shared inventory belongs to the parent; independent variation stock is separate.
- Quick creation accepts attributes such as `رنگ: مشکی، سفید` and `سایز: M، L`; at most 100 combinations. Initial price, cost and quantity apply to each generated variation.
- Spreadsheet exports include `id`, `sku`, `version`, `type`, `parent_id`, cost, descriptions, taxonomy IDs and read-only indicative profit/margin/currency. Keep `version` for stale-edit protection. Category/brand IDs use `|`. Existing variations match by ID or exact SKU. New simple/variable rows become drafts. New variation rows can target an existing variable parent by `parent_id` or `parent_sku` and provide an `attributes` JSON object. See the v1.3 workflow below; brand/category taxonomies must already exist.
- XLSX/CSV: first worksheet, values only, 5,000 rows, 100 columns, 8 MB input/output and 32 MB expanded XLSX limit. XLSX requires PHP ZipArchive and SimpleXML. Formula cells are rejected; XLSX exports use literal text and numeric cells and CSV exports escape formula prefixes. Text cell types preserve leading-zero SKUs. XLSX/CSV prices use the store currency.
- Scheduled rules use purchase cost as their base to prevent unintended compounding. They run through WP-Cron (traffic-dependent); configure a server cron for reliable timing. A failed preview never seals a partial price job. Review execution history for individual rows skipped due to later changes.
- Image changes require `upload_files` and individual attachment edit permissions. Rename/optimize keeps old files and URLs plus backup metadata. Optimization targets the main JPG/PNG/WebP file, with quality/width limits, and retains the original if the result is not smaller. Existing thumbnail files are copied with new names; they are not recompressed. Unused-image detection is conservative and cannot prove absence of external/custom references. Archiving is reversible and intentionally does not reclaim disk space or permanently delete files.
- Purchase-cost profit is indicative in the product grid. Sales reports use native recorded order-line COGS when enabled, or immutable legacy unit-cost snapshots otherwise. Historical lines without a snapshot are marked unknown and excluded from known profit; no historical costs are guessed. Profit excludes tax, shipping, fees and overhead and subtracts line refunds/returned quantity. Net customer spend includes shipping/tax and subtracts order refunds. Loyalty filters refer to the selected order-creation date range; unpaid/cancelled orders are excluded. Mixed currencies are counted separately and excluded from the store-currency totals.
- Branding changes title, logo URL and accent; it does not change plugin slug, security capabilities or update identity. Settings require `manage_options`; reports additionally require `view_woocommerce_reports`.

### Dashboard updates from this private repository

Create a fine-grained GitHub token scoped only to this repository with **Contents: read**. On the WordPress server, before the `wp-settings.php` include in `wp-config.php`, define:

```php
define( 'NIL_BULK_GITHUB_TOKEN', 'SERVER_SIDE_READ_ONLY_TOKEN' );
```

Replace the placeholder on the server; do not commit the token or send it in chat. Updates use WordPress's normal Plugins / Dashboard update UI. Release metadata is cached for six hours; failed authentication for five minutes. The updater never sends the token to the redirected release-asset host and checks release size and the GitHub SHA-256 digest when provided. An unconfigured token leaves manual ZIP installation available. Site installation and credential configuration are separate from repository development.


## Version 1.2: performance and interface

See [PERFORMANCE.md](PERFORMANCE.md) for the measurable performance contract. Product operations now live in seven RTL tabs; advanced filters collapse, product columns and table headers remain visible while scrolling, and preview navigation returns to the product workspace automatically. Source and minified interfaces run the same interaction tests.

The plugin enqueues no storefront CSS/JS/font and performs no SQL during its boot function. Operations intentionally use SQL and retain the data needed for cost reports and undo. Admin files are minified and have a CI size budget. The font stays local and admin-only. Image reference scans run only when requested. Each user has one expiring file import, image preview and insights report; opening another replaces the previous one, including across browser tabs.

Settings includes manual cleanup of owned abandoned drafts older than 7 days. Completed reports older than 90 days can be removed separately after confirmation; that removes their undo capability. No automatic history deletion or extra cleanup cron is installed. Previous release caches expire normally; historical creation receipt options from v1.1 are retained rather than silently deleting legacy data.


## Version 1.3: existing variations, saved views and native COGS

- **Existing variable products:** in the creation workspace, load the parent's ID, extend its color/size options and preview missing combinations. Existing combinations, including wildcard variations, are skipped. Existing children and their prices are preserved. Review before adding; creation does not offer the price-history undo operation. At most 100 new combinations and 20 parents per preview; a replaced/expired preview is invalid after one hour.
- **File additions:** enable new-row creation, map `type=variation`, `parent_id` or `parent_sku`, `attributes`, `sku` and initial price/stock/cost. Leave `id` blank for a new child. Example attributes: `{"رنگ":"آبی","سایز":"L"}` or `{"pa_color":"blue","pa_size":"large"}`. Use the separate new-variation preview button after preparing the file. The parent must already exist; creating a new parent and its children in one file is not supported. Supply every existing variation attribute; extend options, not the number of dimensions. New global terms require WooCommerce taxonomy permissions. Duplicate SKUs fail validation; an intervening parent/combination change requires a fresh preview.
- **Personal views and rules:** in Products, open saved views, choose visible columns and reorder them with arrows, then save the current filters/layout or price rule by name. Each user can keep at most 20 combined entries (32 KB total). Loading a rule fills its controls; scope and product selection are not saved and prices still require preview/confirmation. A rule saved with another currency or precision is rejected.
- **One active cost source:** if native WooCommerce COGS is enabled, the grid, cost-based rules, files and creation use its product APIs. The table shows effective cost including parent inheritance/additive definitions. Editing a variation cost sets an absolute total; clearing it restores inheritance, while explicit zero overrides a parent. Undo restores the original native definition and additive flag. Zero/blank on a simple product follows WooCommerce's native semantics.
- **Existing cost data:** enabling COGS does not silently copy `_nil_cost_price` or fall back to it. In Settings, preview migration for the selected/page/filtered scope, review any conflicting existing/inherited native costs, and confirm the usual price preview for eligible missing costs. Conflicting costs are skipped. Legacy metadata remains available if COGS is later disabled; edits made to native costs are not mirrored back. Changing cost backend after a cost preview requires a new preview.
- **Order reporting:** with COGS active, WooCommerce records order costs and Nil reads those recorded line totals, without a second Nil cost snapshot. Historical lines can use an existing legacy snapshot when native cost is absent; missing historical costs are not reconstructed from today's product cost. No old order backfill is performed.
