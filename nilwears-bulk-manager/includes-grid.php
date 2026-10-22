<?php
/** Static product and pricing layout, rendered only in the admin screen. */
defined( 'ABSPATH' ) || exit;
?>
<template id="nil-grid-template">
        <header class="nil-header"><div class="nil-identity"><span class="nil-mark" aria-hidden="true">N</span><div><span class="nil-kicker">فضای مدیریت فروشگاه</span><h1>قیمت و موجودی نیل</h1><p>محصولات، قیمت و موجودی؛ با کنترل قبل از هر تغییر.</p></div></div><div class="nil-header-actions"><span class="nil-unit">واحد: <bdi id="nil-currency"></bdi></span><button class="button" data-go="help">راهنما و شروع سریع</button></div></header>
        <div id="nil-message" role="status" aria-live="polite"></div>
        <section class="nil-card" id="nil-products"><div class="nil-section-head"><div><span class="nil-kicker">فهرست کالاها</span><h2>محصولات فروشگاه</h2></div><button class="button button-primary" data-go="pricing">تغییر گروهی قیمت ←</button></div><form id="nil-filter" class="nil-toolbar"><label>جست‌وجو<input id="nil-search" placeholder="نام محصول، شناسه یا SKU دقیق"></label><label>دسته‌بندی<select id="nil-category"><option value="">همه دسته‌ها</option></select></label><details class="nil-advanced"><summary>فیلترهای بیشتر</summary><div class="nil-toolbar"><label>برند<select id="nil-brand"><option value="">همه برندها</option></select></label>
        <label>نوع<select id="nil-type"></select></label>
        <label>انتشار<select id="nil-status"></select></label>
        <label>موجودی<select id="nil-stock"></select></label>
        <label>از قیمت<input id="nil-min_price" inputmode="decimal"></label><label>تا قیمت<input id="nil-max_price" inputmode="decimal"></label>
        <label>ترتیب<select id="nil-sort"></select></label>
        </div></details><button class="button" type="submit">جست‌وجو / تازه‌سازی</button><span id="nil-total"></span></form>
        <p class="nil-hint">قیمت‌ها با واحد فعلی فروشگاه هستند. ردیف والد با زمینهٔ بنفش و هر ورییشن زیر آن نمایش داده می‌شود. خانهٔ خالیِ حراج یعنی حذف حراج؛ قیمت صفر یعنی رایگان.</p>
        <div class="nil-table-scroll"><table class="widefat nil-table"><thead><tr><td><input id="nil-select-all" type="checkbox" aria-label="انتخاب همه ردیف‌های این صفحه"></td><th>محصول / رنگ و سایز</th><th>قیمت خرید</th><th>سود / حاشیه</th><th>قیمت اصلی</th><th>قیمت حراج</th><th>مدیریت تعداد</th><th>موجودی</th><th>وضعیت</th></tr></thead><tbody id="nil-rows"></tbody></table></div>
        <footer class="nil-toolbar nil-table-footer"><button type="button" class="button" id="nil-prev">صفحه قبل</button><span id="nil-pagination"></span><button type="button" class="button" id="nil-next">صفحه بعد</button><span id="nil-dirty"></span><button type="button" class="button button-primary" id="nil-manual">پیش‌نمایش ویرایش‌های جدول</button><button type="button" class="button" id="nil-reset">پاک‌کردن ویرایش‌ها</button></footer></section>
        <section class="nil-card" id="nil-pricing"><div class="nil-section-head"><div><span class="nil-kicker">۱ · تنظیم تغییر</span><h2>قیمت‌گذاری، با پیش‌نمایش</h2></div><button class="button" data-go="help">چطور قیمت را تغییر بدهم؟</button></div><p id="nil-scope-summary" class="nil-callout" aria-live="polite"></p><div class="nil-recipes"><span>شروع با یک مثال:</span><button class="button" data-recipe="ending">۱۰٪ + پایان ۹۰۰۰</button><button class="button" data-recipe="percent">۱۰٪ افزایش</button><button class="button" data-recipe="fixed">افزایش مبلغی</button><button class="button" data-recipe="discount">۱۰٪ کاهش</button></div><fieldset><legend>کدام محصولات و کدام قیمت؟</legend><div class="nil-rule-grid">
        <label>محدوده<select id="nil-scope"></select></label>
        <label>مبنای قیمت<select id="nil-source"></select></label><label>ستون<select id="nil-target"></select></label></div></fieldset><fieldset><legend>چه تغییری اعمال شود؟</legend><div class="nil-rule-grid">
        <label>عملیات<select id="nil-operation"></select></label>
        <label><span id="nil-value-label">درصد تغییر</span><input id="nil-value" inputmode="decimal" value="10" aria-label="مقدار تغییر؛ برای کاهش منفی بنویسید"></label>
        </div></fieldset><fieldset><legend>پایان قیمت؛ اختیاری</legend><div class="nil-rule-grid"><label>رندکردن<select id="nil-rounding"></select></label>
        <label>گام قیمت<input id="nil-step" inputmode="decimal" value="10000"></label><label>پایان قیمت<input id="nil-ending" inputmode="decimal" value="9000"></label>
        </div></fieldset><div class="nil-example nil-simulator"><label>آزمایش روی یک مبلغ<input id="nil-demo-price" inputmode="decimal" value="495000"></label><div><span>نتیجهٔ نمونه با تنظیمات فعلی</span><strong id="nil-demo-result" aria-live="polite"></strong><small>نمونه است؛ پیش‌نمایش محصولات را هم بررسی کنید.</small></div></div>
        <p class="nil-hint">برای کاهش عدد منفی بنویسید. انتخاب والد، تمام ورییشن‌هایش را شامل می‌شود. گام و پایان قیمت را با واحد فعلی فروشگاه وارد کنید.</p>
        <button type="button" class="button button-primary" id="nil-bulk">ساخت پیش‌نمایش تغییر گروهی</button></section>
        <section class="nil-card" id="nil-preview" hidden><div class="nil-toolbar"><h2>۲ · بررسی و تأیید تغییرات</h2><span id="nil-job-state"></span></div><p id="nil-summary"></p><div id="nil-preview-table" class="nil-table-scroll"></div><div class="nil-toolbar"><button type="button" class="button" id="nil-job-prev">گزارش قبل</button><span id="nil-job-pagination"></span><button type="button" class="button" id="nil-job-next">گزارش بعد</button><button type="button" class="button" id="nil-export">دانلود گزارش CSV</button><button type="button" class="button button-primary" id="nil-apply">تأیید و ذخیره همین پیش‌نمایش</button><button type="button" class="button" id="nil-undo">بازگردانی این عملیات</button></div><p class="nil-hint">ردیف متداخل رد می‌شود. عملیات قطع‌شده را از تاریخچه ادامه دهید؛ تاریخ حراج و پیش‌خرید حفظ می‌شوند.</p></section>
        <section class="nil-card"><div class="nil-toolbar"><h2>۲۰ عملیات اخیر شما</h2><button type="button" class="button" id="nil-history-refresh">تازه‌سازی تاریخچه</button></div><div id="nil-history"></div></section>

</template>
