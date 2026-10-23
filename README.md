<div dir="rtl">

# مدیریت قیمت و موجودی نیل

افزونهٔ اختصاصی ووکامرس برای مدیریت قیمت و موجودی محصولات ساده و ورییشن‌های رنگ/سایز، با پیش‌نمایش، تغییر گروهی، رندکردن قابل تنظیم و بازگردانی.

**نسخهٔ فعلی: 1.7.0**

## نصب

فایل `nilwears-bulk-manager-vX.Y.Z.zip` را از بخش Releases هر نسخه دریافت و از مسیر **افزونه‌ها ← افزودن ← بارگذاری افزونه** نصب کنید. بعد از فعال‌سازی، صفحه از مسیر **ووکامرس ← قیمت و موجودی نیل** در دسترس است.

برای ساخت محلی ZIP، از دستور ساخت پایین استفاده کنید. فایل Source code خودکار GitHub، بستهٔ نصب ووکامرس نیست؛ ZIP اختصاصی همان نسخه را بگیرید.

راهنمای کامل فارسی داخل [README-fa.html](nilwears-bulk-manager/README-fa.html) قرار دارد. پیش‌نیازها: WordPress 6.4+، WooCommerce 8.5+ و PHP 7.4+ از نوع ۶۴ بیتی؛ ذخیره به جداول InnoDB و پشتیبانی MySQL/MariaDB از GET_LOCK نیاز دارد.

## موجودی و تخفیف گروهی — نسخهٔ ۱.۷

در **قیمت‌گذاری** ابتدا دسته، چند محصول/ورییشن یا محدودهٔ جدول را انتخاب کنید. سپس از کارت **موجودی و تخفیف گروهی** استفاده کنید:

- **تخفیف درصدی یا مبلغی:** قیمت حراج از قیمت اصلی هر کالا محاسبه می‌شود؛ تخفیف قبلی جایگزین می‌شود و قیمت اصلی ثابت است. مثلاً ۱۵٪ از ۵۰۰۰۰۰، با گام ۱۰۰۰۰، پایان ۹۰۰۰ و رند رو به بالا، حراج ۴۲۹۰۰۰ می‌سازد. اجرای دوباره تخفیف را انباشته نمی‌کند. رندکردن باید قیمت نهایی را زیر قیمت اصلی نگه دارد؛ صفر برای تخفیف ۱۰۰٪ بدون رند مجاز است.
- **حذف تخفیف:** حراج را خالی می‌کند. ثبت و حذف تخفیف هر دو تاریخ شروع و پایان حراج قبلی را پاک می‌کنند تا تغییر فوری باشد؛ این تاریخ‌ها در پیش‌نمایش دیده می‌شوند و با بازگردانی برمی‌گردند. زمان‌بندی شمسی کمپین در این نسخه اضافه نشده است.
- **موجودی:** تعیین تعداد، افزایش، کاهش یا ناموجودکردن. تعیین تعداد، مدیریت عددی را فعال می‌کند؛ افزایش/کاهش به تعداد فعلی ثبت‌شده نیاز دارد. کاهش بیش از موجودی یا تعداد کسری رد می‌شود.
- **رنگ/سایز:** انتخاب والد شامل ورییشن‌هاست؛ والد دارای موجودی مشترک یک بار و هر ورییشن دارای موجودی مستقل جداگانه تغییر می‌کند. والد فاقد موجودی مشترک کنار گذاشته می‌شود. انتخاب تنها یک ورییشنِ وابسته به موجودی والد، خطا می‌دهد و ساختار موجودی را تغییر نمی‌دهد؛ والد را انتخاب کنید. برای موجودی مستقل تازه، از ویرایش صریح جدول استفاده کنید.
- **ناموجودکردن:** با مدیریت عددی، تعداد صفر می‌شود؛ بدون آن فقط وضعیت تغییر می‌کند. پیش‌خرید فعال در موجودی عددی باعث ردشدن عملیات ناموجودکردن می‌شود تا این تنظیم خودسرانه خاموش نشود؛ برای تغییر آن به تنظیم محصول در ووکامرس بروید.

ویرایش‌های باز جدول را ابتدا ذخیره یا پاک کنید. **پیش‌نمایش موجودی/تخفیف ← بررسی قبل/بعد ← تأیید و ذخیره** را انجام دهید. خطای هر ردیف مانع تأیید پیش‌نمایش ناقص می‌شود. بعد از اجرا، گزارش در تاریخچه قابل بازگردانی است؛ تغییر قیمت یا موجودی جدید پس از پیش‌نمایش می‌تواند ردیف متداخل را رد کند. بازگردانی تخفیف، کاهش موجودی ناشی از فروش بعدی را حفظ می‌کند.

ورودی‌ها با گوشه‌های نرم‌تر، فاصلهٔ بیشتر و فوکوس واضح‌تر، دکمه‌های هماهنگ و کارت‌های جداگانهٔ عملیات نمایش داده می‌شوند. کتابخانهٔ رابط، جدول دیتابیس یا کران تازه‌ای اضافه نشده است.

## انتخاب کالا در خودِ قیمت‌گذاری — نسخهٔ ۱.۶

در **قیمت‌گذاری ← ۱ · کالاهای این قانون ← روش انتخاب کالا**، یکی از این روش‌ها را انتخاب کنید:

- **یک دسته‌بندی مشخص:** دسته را از فهرست انتخاب کنید. همهٔ محصولات قابل ویرایش آن دسته و زیر‌دسته‌هایش، همراه ورییشن‌ها، در تمام صفحات بررسی می‌شوند. فیلترهای جدول محصولات به این انتخاب اضافه نمی‌شوند.
- **چند کالای مشخص:** نام، SKU دقیق یا شناسه را جست‌وجو کنید و کنار هر کالا «افزودن» را بزنید. انتخاب‌ها بین جست‌وجوها و صفحه‌های نتایج باقی می‌مانند؛ با × حذف می‌شوند. انتخاب والد، همهٔ ورییشن‌هایش را شامل می‌شود و انتخاب یک ورییشن فقط همان رنگ/سایز را دربر می‌گیرد. حداکثر ۵۰۰ انتخاب مستقل و ۵۰۰۰ ردیف در محدوده؛ والد و ورییشن تکراری دوباره اعمال نمی‌شوند.
- **انتخاب‌های جدول محصولات / صفحهٔ جدول / فیلتر جدول:** مسیرهای قبلی برای استفاده از جدول هم در دسترس‌اند؛ انتخاب‌های جدول همچنان مربوط به همان صفحه‌اند.

**«مشاهدهٔ کالاهای مشمول قانون»** تعداد و نام/شناسهٔ کالاها و ورییشن‌ها را نشان می‌دهد؛ برای محدودهٔ بزرگ، ۵۰ ردیف نخست نمایش داده می‌شود. بعد، قیمت‌گذاری دستی یا قانون طلا/دلار/یورو را تنظیم کنید، پیش‌نمایش قطعی تغییرها را ببینید و تأیید کنید. نتیجهٔ نهایی هر ردیف از سرور دوباره خوانده می‌شود. تغییر انتخاب، تأیید پیش‌نمایش بازار قبلی را باطل می‌کند. انتخاب موقت کالاهای مشخص با بازخوانی صفحه پاک می‌شود؛ عضویت قانون بازار فعال در سرور محفوظ است و خودکار گسترش پیدا نمی‌کند.

در کارت طلا/ارز، «انتخاب کالاهای این قانون ↑» شما را به همین قسمت برمی‌گرداند. زمان‌بندی قدیمیِ قیمت خرید فرم جداگانه‌ای است و از فیلتر جدول استفاده می‌کند؛ با روش دسته/کالای مشخص جدید ثبت نمی‌شود تا محدودهٔ متفاوتی را ناخواسته تغییر ندهد.

این نسخه منوی کناری با آیکون در نمایشگر بزرگ، منوی افقی در عرض کمتر، شمارندهٔ محصولات/انتخاب‌ها/ویرایش‌های باز و نوار اقدام خواناتر دارد. آیکون SVG محلی برای فهرست آپدیت و بنر جزئیات افزونه اضافه شده‌اند. چون اطلاعات آپدیت را **کد نصب‌شده** می‌سازد، آیکونِ اعلان‌های بعدی پس از نصب ۱.۶ نمایش داده می‌شود؛ اعلان ارتقا از ۱.۵.۱ هنوز از کد قبلی می‌آید.

## اگر اعلان به‌روزرسانی نمایش داده نمی‌شود

در نسخه‌های ۱.۴ و ۱.۵، اطلاعات ریلیز تا شش ساعت کش می‌شد و دکمهٔ وضعیت، بررسی تازه یا ثبت دوبارهٔ اعلان وردپرس انجام نمی‌داد. انتشار فایل در گیت‌هاب به‌تنهایی کش نسخهٔ نصب‌شده را پاک نمی‌کند. این رفتار در **۱.۵.۱** اصلاح شده است؛ خطاهای اتصال هاست هم اکنون نمایش داده می‌شوند. این موضوع با فعال‌کردن نصب خودکار تفاوت دارد؛ افزونه خودسرانه نصب خودکار را روشن نمی‌کند.

- برای دریافت فوری اصلاح، ZIP نسخهٔ ۱.۵.۱ را از Releases بگیرید، در **افزونه‌ها ← افزودن ← بارگذاری افزونه** بارگذاری کنید و **جایگزینی نسخهٔ نصب‌شده** را بزنید. افزونه را حذف نکنید. این کار داده‌های محصول و تاریخچه را حذف نمی‌کند.
- بعد از نصب، در **قیمت و موجودی نیل ← تنظیمات ← بررسی تازهٔ به‌روزرسانی**، نسخهٔ نصب‌شده، آخرین نسخه و نتیجه یا خطای اتصال را ببینید. این دکمه با دسترسی به‌روزرسانی افزونه‌ها، کش موفق/ناموفق قبلی را دور می‌زند و فقط اطلاعات همین افزونه را در کش آپدیت وردپرس ادغام می‌کند؛ چیزی نصب نمی‌کند.
- **پیشخوان ← به‌روزرسانی‌ها ← بررسی مجدد** نیز در نسخهٔ ۱.۵.۱، بررسی تازهٔ گیت‌هاب را اجرا می‌کند. اگر آخرین نسخه را دارید، نبود اعلان طبیعی است. برای نصب خودکار نسخه‌های آینده، گزینهٔ خود وردپرس را در فهرست افزونه‌ها و طبق تنظیمات سرور فعال کنید.
- خطای HTTPS یا HTTP نشان‌دهندهٔ مشکل ارتباط/دسترسی سرور است؛ وضعیت این مرورگر یا اتصال کامپیوتر شما اثبات‌کنندهٔ اتصال هاست نیست. جزئیات محرمانه یا توکن در نتیجه نمایش داده نمی‌شود. توکن اختیاری نامعتبر، برای ریپوی عمومی با تلاش بدون توکن جایگزین می‌شود؛ اعتبارسنجی TLS و هش فایل فعال می‌ماند.

## مثال رندکردن

برای **۱۰٪ افزایش و پایان قیمت ۹۰۰۰**: عملیات درصدی، مقدار `10`، گام `10000`، پایان `9000` و جهت رو به بالا. مثال: **۴۹۵٬۰۰۰ → ۵۴۹٬۰۰۰**. تمام اعداد با واحد فعلی ووکامرس هستند؛ تبدیل ریال و تومان انجام نمی‌شود.

## قیمت‌گذاری خودکار با طلا، دلار و یورو — نسخهٔ ۱.۵

در «محصولات» محدوده یا ردیف‌ها را انتخاب کنید، سپس به **قیمت‌گذاری ← قیمت‌گذاری با طلا، دلار یا یورو** بروید. این قسمت برای مدیر سایت است.

1. شاخص را انتخاب کنید: طلای ۱۸ عیار، دلار یا یورو. دکمهٔ دریافت نرخ برای مشاهدهٔ آخرین نرخ و زمان منبع است.
2. نرخ مبنا را **به تومان** وارد کنید؛ مثلاً اگر قیمت‌های فعلی با دلار ۲۵۰ هزار تومانی تعیین شده‌اند، مقدار `250000` را بگذارید. واحد قیمت کالا همان واحد ووکامرس می‌ماند؛ نسبت نرخ‌ها واحد ندارد.
3. ستون قیمت اصلی، حراج موجود یا هر دو، روش رندکردن و فاصلهٔ اجرا از **۵ تا ۱۴۴۰ دقیقه** را انتخاب کنید. برای پایان ۹۰۰۰، گام `10000`، پایان `9000` و جهت «رو به بالا» بگذارید.
4. مشخص کنید **کاهش به زیر قیمت مبنا** مجاز باشد یا خیر. سپس مبنا و پیش‌نمایش را بسازید. نمونهٔ قبل/بعد و گزارش کامل همهٔ تغییرها را بررسی کنید و قانون را فعال کنید. پیش‌نمایش به‌تنهایی هیچ قیمت محصولی را عوض نمی‌کند.
5. از فهرست قوانین، وضعیت، آخرین نرخ/خطا و نوبت بعد را ببینید؛ تناوب را بدون تغییر قیمت‌های پایه عوض کنید، توقف/ادامه، نوبت فوری یا حذف را بزنید. برای ساخت قانون تازه با مبنای جدید، قانون قبلی را حذف و دوباره پیش‌نمایش بسازید.

فرمول هر نوبت: **قیمت اولیهٔ ثبت‌شده × نرخ تازه ÷ نرخ مبنا → رندکردن**. مثلاً دلار `250000 → 275000` یعنی ۱۰٪ رشد؛ قیمت اولیهٔ `495000` با رند رو به بالا و پایان ۹۰۰۰ به `549000` می‌رسد. محاسبهٔ بعدی باز هم از همان `495000` شروع می‌شود؛ قیمت جدید پایهٔ افزایش بعدی نیست.

اگر کاهش زیر مبنا خاموش باشد، قیمت از قیمت اولیه پایین‌تر نمی‌رود؛ رند رو به پایین هم این کف را نمی‌شکند. **افت از نرخ بالاتر به سمت مبنا همچنان می‌تواند قیمت را کاهش دهد**؛ این گزینه به معنای «قیمت هرگز کاهش نیابد» نیست. با روشن‌بودن آن، نسبت کمتر از یک هم اعمال می‌شود. قیمت حراج خالی، خالی می‌ماند.

قانون فقط برای محصولات/ورییشن‌هایی است که هنگام ساخت ثبت شده‌اند؛ محصول یا ترکیب رنگ/سایز جدید خودکار اضافه نمی‌شود. قیمت روی ورییشن ذخیره و والد با API ووکامرس همگام می‌شود. هر محصول فقط عضو یک قانون بازار است، حتی اگر آن قانون متوقف باشد. تغییر دستی قیمت با قانون بازنویسی نمی‌شود و آن ردیف با علت کنار گذاشته می‌شود؛ برای پذیرش قیمت دستی به‌عنوان مبنا قانون تازه بسازید. قوانین بازار را هم‌زمان با قوانین زمان‌بندی‌شده یا افزونه‌های دیگر روی همان قیمت‌ها اجرا نکنید.

زمان‌بندی با **WP-Cron** است: نوبت اول پس از فعال‌سازی در صف می‌رود؛ هر درخواست تا ۵۰ ردیف را آماده یا ۱۰ ردیف را ذخیره می‌کند، و فاصلهٔ انتخابی از پایان هر چرخه حساب می‌شود. در فروشگاه بزرگ یا کم‌بازدید اجرای چرخه طولانی‌تر می‌شود؛ برای زمان‌بندی منظم، cron واقعی سرور لازم است. نوبت فوری هم در صف cron قرار می‌گیرد. توقف/حذف قیمت فعلی را برنمی‌گرداند؛ برای بازگردانی ابتدا قانون را متوقف و سپس تاریخچه را باز کنید.

حداکثر ۲۰ قانون و ۵۰۰۰ محصول/ورییشن برای هر قانون؛ یک پیش‌نویس یک‌ساعته برای هر مدیر. منبع ثابت نرخ، [فید عمومی وال‌گلد](https://wallgold.ir/wp-content/uploads/wallgold/prices.json) است؛ توکن لازم ندارد و اطلاعات محصول به آن ارسال نمی‌شود. نرخ نامعتبر، قدیمی‌تر از یک ساعت یا قطع ارتباط، باعث انتظار و تلاش دوباره می‌شود، نه ذخیره با نرخ قدیمی. تغییر واحد پول/دقت یا از دست رفتن دسترسی مدیر قانون را متوقف می‌کند. این قابلیت قیمت خرید یا منبع COGS را تغییر نمی‌دهد.

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

### Dashboard updates from GitHub

The repository is public at the user's request. **Version 1.4+ checks and downloads public releases without a token.** An installed v1.3 or earlier still requires a token; if none is configured, install the v1.4 ZIP once through Plugins → Add New → Upload Plugin and replace the existing version. Future releases use the standard WordPress update screen. This repository release does not install itself on the store.

For a private repository, an optional fine-grained GitHub token needs only **Contents: read** for that repository. On the WordPress server, before the `wp-settings.php` include in `wp-config.php`, define:

```php
define( 'NIL_BULK_GITHUB_TOKEN', 'SERVER_SIDE_READ_ONLY_TOKEN' );
```

Replace the placeholder on the server; do not commit the token or send it in chat. Updates use WordPress's normal Plugins / Dashboard update UI. Release metadata is cached for six hours; failed authentication for five minutes. The updater never sends the token to the redirected release-asset host and checks release size and the GitHub SHA-256 digest when provided. Public releases do not require this constant. Public/private metadata caches are separate; redirect hosts never receive credentials. Site installation and credential configuration are separate from repository development.


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


## Version 1.4: help, pricing workflow and live branding

**مسیر تغییر قیمت:** در «محصولات» ردیف‌ها را انتخاب کنید ← «قیمت‌گذاری» ← مثال «۱۰٪ + پایان ۹۰۰۰» یا تنظیم دلخواه ← محدوده را بررسی کنید ← «ساخت پیش‌نمایش» ← بررسی قبل/بعد ← «تأیید و ذخیره همین پیش‌نمایش». برای تمام محصولات، ابتدا فیلترها را پاک و جدول را تازه کنید، سپس محدودهٔ «همهٔ محصولات فیلتر فعلی» را انتخاب کنید. واحد قیمت همان واحد ووکامرس است؛ تبدیل ریال/تومان انجام نمی‌شود.

- **راهنمای داخل افزونه:** از دکمهٔ بالای صفحه یا تب «راهنما» باز می‌شود؛ شروع سه‌مرحله‌ای، مثال‌های آماده، تفاوت محدوده‌ها، رندکردن، ورییشن/موجودی مشترک، COGS، اکسل، خطاها و بازگردانی را توضیح می‌دهد. مثال‌ها فقط فرم را پر می‌کنند و چیزی ذخیره نمی‌کنند.
- **قیمت‌گذاری مستقل:** دیگر زیر جدول طولانی پنهان نیست. موجودی گروهی، قوانین زمان‌بندی و تاریخچه هم در همین بخش‌اند. پیش‌نمایش ویرایش جدول و قیمت گروهی هر دو به این بخش هدایت می‌شوند. مبلغ نمونه، نتیجهٔ تنظیمات فعلی را به‌صورت راهنما نشان می‌دهد؛ پیش‌نمایش قطعی روی سرور همچنان لازم است.
- **برندینگ زنده:** تغییرات ذخیره‌نشده در کادر پیش‌نمایش دیده می‌شوند. پس از ذخیرهٔ موفق، عنوان، رنگ، لوگو و منوی مدیریت فوراً به‌روز می‌شوند و بعد از بارگذاری تازه می‌مانند. خطای ذخیره، هویت اعمال‌شده را عوض نمی‌کند. لوگوی HTTP به‌جای حذف خاموش رد می‌شود؛ کد رنگ سه‌رقمی به شش‌رقمی تبدیل می‌شود. این قابلیت هویت همین افزونه را تغییر می‌دهد، نه ظاهر فروشگاه/وودمارت.
- **رابط تازه:** هشت فضای کاری، تیتر و کنترل‌های واضح، فرم‌های گروه‌بندی‌شده، ناوبری قابل پیمایش در عرض کم و جدول با اسکرول افقی مستقل. فونت محلی وزیرمتن، بدون فریم‌ورک جدید یا فایل عمومی فروشگاه.

The browser DOM suites use the same static admin/help templates and source/minified scripts that WordPress receives. `python3 scripts/preview.py` creates a self-contained **mock-data** preview and a 390px iframe harness under ignored `dist/`; neither connects to a store or ships in the plugin ZIP. Browser visual review was not completed: Cloud Browser rejected local `file:` navigation. DOM fixtures are not visual rendering evidence.
