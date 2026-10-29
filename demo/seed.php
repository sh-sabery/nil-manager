<?php
/** Seed only an explicitly disposable Playground. No production data or accounts. */
require '/wordpress/wp-load.php';
if (!defined('NIL_PLAYGROUND_DEMO') || NIL_PLAYGROUND_DEMO !== true || !defined('DB_ENGINE') || DB_ENGINE !== 'sqlite' || !class_exists('WooCommerce')) {
    throw new RuntimeException('Refusing to seed outside the disposable Playground.');
}
wp_set_current_user(1);
if (get_option('nil_demo_seeded')) return;
foreach (array(
    'blogname' => 'دموی نیل منجر | نیل وب', 'blogdescription' => 'محصولات آزمایشی برای تجربهٔ مدیریت قیمت و موجودی',
    'timezone_string' => 'Asia/Tehran', 'date_format' => 'Y/m/d', 'time_format' => 'H:i',
    'woocommerce_currency' => 'IRT', 'woocommerce_price_num_decimals' => 0,
    'woocommerce_price_thousand_sep' => ',', 'woocommerce_price_decimal_sep' => '.',
    'woocommerce_default_country' => 'IR', 'woocommerce_store_address' => '',
    'woocommerce_onboarding_profile' => array('completed' => true, 'skipped' => true),
    'woocommerce_task_list_hidden' => 'yes', 'woocommerce_show_marketplace_suggestions' => 'no',
    'woocommerce_allow_tracking' => 'no', 'woocommerce_coming_soon' => 'no',
    'woocommerce_custom_orders_table_enabled' => 'yes', 'woocommerce_custom_orders_table_data_sync_enabled' => 'no',
    'woocommerce_feature_cost_of_goods_sold_enabled' => 'yes',
    'woocommerce_bacs_settings' => array('enabled' => 'yes', 'title' => 'پرداخت نقدی آزمایشی'),
    'woocommerce_cod_settings' => array('enabled' => 'yes', 'title' => 'پرداخت هنگام دریافت آزمایشی'),
    'nil_bulk_branding' => array('title' => 'نیل منجر', 'accent' => '#6941c6', 'logo' => '')
) as $key => $value) update_option($key, $value);
delete_transient('_wc_activation_redirect');
NIL_Bulk_Manager::install();
$cats = array();
foreach (array('پوشاک', 'اکسسوری', 'کالای دیجیتال') as $name) {
    $term = wp_insert_term($name, 'product_cat');
    if (is_wp_error($term)) throw new RuntimeException('Demo category creation failed.');
    $cats[$name] = $term['term_id'];
}
$items = array(
    array('تیشرت ساده', 'پوشاک', 495000, 12), array('هودی دورس', 'پوشاک', 1290000, 8),
    array('شلوار جین', 'پوشاک', 1890000, 5), array('شال نخی', 'اکسسوری', 395000, 20),
    array('کیف روزمره', 'اکسسوری', 2490000, 4), array('کمربند', 'اکسسوری', 590000, 0),
    array('هدفون بی‌سیم', 'کالای دیجیتال', 3290000, 7), array('پاوربانک', 'کالای دیجیتال', 1590000, 10)
);
foreach ($items as $i => $item) {
    $p = new WC_Product_Simple(); $p->set_name($item[0]); $p->set_status('publish');
    $p->set_sku('DEMO-S-' . ($i + 1)); $p->set_category_ids(array($cats[$item[1]]));
    $p->set_regular_price((string) $item[2]);
    if ($i === 1) $p->set_sale_price('1190000');
    $p->set_manage_stock(true); $p->set_stock_quantity($item[3]);
    $p->set_description('این کالا فقط برای تمرین در دموی نیل منجر ساخته شده است.');
    NIL_Cost::set($p, (string) floor($item[2] * .6)); $p->save();
}
foreach (array(array('بارانی', 1495000, false), array('کت پاییزه', 2495000, true)) as $i => $item) {
    $parent = new WC_Product_Variable(); $parent->set_name($item[0]); $parent->set_status('publish');
    $parent->set_sku('DEMO-V-' . ($i + 1)); $parent->set_category_ids(array($cats['پوشاک']));
    if ($item[2]) { $parent->set_manage_stock(true); $parent->set_stock_quantity(18); }
    $attrs = array();
    foreach (array('رنگ' => array('مشکی', 'کرم'), 'سایز' => array('M', 'L')) as $name => $options) {
        $a = new WC_Product_Attribute(); $a->set_name($name); $a->set_options($options);
        $a->set_visible(true); $a->set_variation(true); $attrs[] = $a;
    }
    $parent->set_attributes($attrs); $parent_id = $parent->save();
    $n = 0;
    foreach (array('مشکی', 'کرم') as $color) foreach (array('M', 'L') as $size) {
        $v = new WC_Product_Variation(); $v->set_parent_id($parent_id); $v->set_status('publish');
        $v->set_sku('DEMO-V-' . ($i + 1) . '-' . (++$n));
        $v->set_attributes(array(sanitize_title('رنگ') => $color, sanitize_title('سایز') => $size));
        $v->set_regular_price((string) ($item[1] + ($size === 'L' ? 100000 : 0)));
        if (!$item[2]) { $v->set_manage_stock(true); $v->set_stock_quantity($n + 2); }
        NIL_Cost::set($v, (string) floor($item[1] * .6)); $v->save();
    }
    WC_Product_Variable::sync($parent_id);
}
wp_update_user(array('ID' => 1, 'display_name' => 'بازدیدکنندهٔ دمو', 'locale' => 'fa_IR'));
update_option('nil_demo_seeded', true, false);
echo 'Nil Manager demo ready: 10 products, 8 variations; synthetic data only.';
