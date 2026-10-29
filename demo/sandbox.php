<?php
/** Browser-only demo support. Never ship or install this file on a real store. */
defined('ABSPATH') || exit;
if (!defined('NIL_PLAYGROUND_DEMO') || NIL_PLAYGROUND_DEMO !== true || !defined('DB_ENGINE') || DB_ENGINE !== 'sqlite') {
    return;
}

// Each browser owns a disposable, single-worker SQLite database. These MySQL-only
// statements are emulated ONLY here; production locks and transactions stay intact.
add_filter('query', function ($sql) {
    if (preg_match('/^SELECT (GET_LOCK|RELEASE_LOCK)\(/i', trim($sql))) return 'SELECT 1';
    if (stripos($sql, 'SELECT ENGINE FROM information_schema.TABLES') !== false) return "SELECT 'InnoDB'";
    return preg_replace('/ FOR UPDATE\s*$/i', '', $sql);
});
add_filter('pre_wp_mail', '__return_false');
add_filter('woocommerce_allow_tracking', '__return_false');
add_filter('woocommerce_enable_setup_wizard', '__return_false');
add_filter('woocommerce_admin_disabled', '__return_true');
add_filter('pre_http_request', function ($pre, $args, $url) {
    // A clearly labelled sample quote, not a connection to WallGold or a bank.
    if (strpos($url, 'https://wallgold.ir/wp-content/uploads/wallgold/prices.json?_=') === 0) {
        $feed = array('indicators' => array());
        foreach (array('price_dollar_rl' => array(2750000, true), 'price_eur' => array(3300000, true), 'gold18k' => array(22000000, false)) as $key => $rate) {
            $feed['indicators'][$key] = array('price' => $rate[0], 'divide10' => $rate[1], 'unit' => 'تومان', 'updated_ms' => time() * 1000);
        }
        return array('response' => array('code' => 200), 'headers' => array(), 'body' => wp_json_encode($feed));
    }
    return new WP_Error('nil_demo_offline', 'ارتباط بیرونی در دموی آزمایشی غیرفعال است.');
}, 1, 3);

add_action('admin_notices', function () {
    echo '<div class="notice notice-info nil-demo-notice" dir="rtl"><p><strong>دموی شخصی نیل منجر</strong> — محصولات و نرخ‌های بازار نمونه‌اند. تغییرات فقط در مرورگر شما انجام می‌شوند؛ با بازکردن دوبارهٔ لینک، محیط تازه می‌گیرید. لایسنس، بروزرسان ژاکت، پرداخت واقعی و اجرای زمان‌بندی در پس‌زمینه در این دمو آزموده نمی‌شوند.</p><details><summary>تمرین سریع: ۱۰٪ افزایش با پایان ۹۰۰۰</summary><ol><li>در نیل منجر «تغییر قیمت» را باز کنید.</li><li>دستهٔ «پوشاک» یا چند کالای مشخص را انتخاب کنید.</li><li>الگوی «۱۰٪ افزایش و پایان ۹۰۰۰» را بزنید و کالاهای مشمول را ببینید.</li><li>پیش‌نمایش را تأیید و اعمال کنید؛ برای برگشت به «تاریخچه» بروید.</li></ol><p>نمونه: ۴۹۵٬۰۰۰ ← ۵۴۹٬۰۰۰ تومان. دلار نمونه ۲۷۵٬۰۰۰، یورو ۳۳۰٬۰۰۰ و طلای ۱۸ عیار ۲۲٬۰۰۰٬۰۰۰ تومان است؛ برای تمرین رشد ۱۰٪، نرخ مبنای دلار را ۲۵۰٬۰۰۰ قرار دهید.</p></details></div>';
});
add_action('admin_head', function () {
    $font = plugins_url('assets/vazirmatn-arabic-wght-normal.woff2', WP_PLUGIN_DIR . '/nilwears-bulk-manager/nilwears-bulk-manager.php');
    echo '<style>@font-face{font-family:NilDemo;src:url(' . esc_url($font) . ') format("woff2");font-display:swap}body.rtl,.nil-demo-notice{font-family:NilDemo,Tahoma,sans-serif}.nil-demo-notice{border-radius:12px;background:#f5f3ff;border-right-color:#6941c6;padding:8px 18px;line-height:1.9}.nil-demo-notice summary{cursor:pointer;color:#5b32a8;font-weight:600}.nil-demo-notice ol{margin-right:20px}</style>';
});
