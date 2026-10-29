<?php
// Disposable, browser-equivalent SQLite setup only. Never run on a store.
require '/wordpress/wp-load.php';
if (!defined('NIL_PLAYGROUND_DEMO') || NIL_PLAYGROUND_DEMO !== true || !defined('DB_ENGINE') || DB_ENGINE !== 'sqlite' || ABSPATH !== '/wordpress/' || !get_option('nil_demo_seeded')) throw new RuntimeException('Not a disposable demo.');
wp_set_current_user(1);
$checks = 0;
function demo_check($ok, $why) { global $checks; $checks++; if (!$ok) throw new RuntimeException($why); }
function demo_request($method, $path, $body = null) {
    $r = new WP_REST_Request($method, '/nil-bulk/v1/' . $path);
    if ($body !== null) { $r->set_header('Content-Type', 'application/json'); $r->set_body(wp_json_encode($body)); }
    $res = rest_do_request($r);
    if ($res->get_status() >= 400) throw new RuntimeException(wp_json_encode($res->get_data()));
    return $res->get_data();
}
function demo_fresh($id) { clean_post_cache($id); wc_delete_product_transients($id); return wc_get_product($id); }
$rows = demo_request('GET', 'products');
demo_check($rows['total'] === 10 && count($rows['rows']) === 18, '10 products plus eight variation rows');
demo_check(get_woocommerce_currency() === 'IRT' && wc_get_price_decimals() === 0, 'Toman configuration');
demo_check(NIL_Cost::native(), 'Native Woo COGS active');
$id = wc_get_product_id_by_sku('DEMO-S-1'); $p = demo_fresh($id);
demo_check($p && NIL_Cost::read($p) === '297000', 'Native COGS sample retained');
$row = NIL_Bulk_Manager::row($p);
$preview = demo_request('POST', 'preview', array('rows' => array(array('id' => $id, 'version' => $row['version'], 'changes' => array())), 'rule' => array('operation' => 'percent', 'value' => '10', 'rounding' => 'up', 'step' => '10000', 'ending' => '9000', 'target' => 'both')));
demo_check(!$preview['errors'] && $preview['added'] === 1, 'Real REST preview created');
demo_check(demo_fresh($id)->get_regular_price() === '495000', 'Preview did not mutate price');
demo_request('POST', 'job/' . $preview['job_id'] . '/seal', array('count' => 1));
$applied = demo_request('POST', 'job/' . $preview['job_id'] . '/run', array('mode' => 'apply'));
demo_check($applied['done'] && demo_fresh($id)->get_regular_price() === '549000', 'Real apply rounds to 9000 ending');
demo_check((int) demo_fresh($id)->get_stock_quantity() === 12, 'Price edit preserved stock');
demo_request('POST', 'job/' . $preview['job_id'] . '/run', array('mode' => 'undo'));
demo_check(demo_fresh($id)->get_regular_price() === '495000', 'Real undo restored price');
$v = demo_fresh(wc_get_product_id_by_sku('DEMO-V-1-1'));
demo_check($v->is_type('variation') && $v->get_manage_stock('edit') === true && (int) $v->get_stock_quantity() === 3, 'Independent variation stock');
$v = demo_fresh(wc_get_product_id_by_sku('DEMO-V-2-1'));
demo_check($v->get_manage_stock() === 'parent' && (int) $v->get_stock_quantity() === 18, 'Shared variation stock');
demo_check(strpos($v->get_name(), 'مشکی') !== false && strpos($v->get_name(), 'M') !== false, 'Variation attributes match parent labels');
$rates = demo_request('GET', 'market/rates');
demo_check((float) $rates['rates']['price_dollar_rl']['price'] === 275000.0, 'Sample USD quote normalized');
demo_check(is_wp_error(wp_remote_get('https://example.com/')), 'External HTTP blocked in demo');
demo_check(wp_mail('nobody@example.test', 'Demo', 'Synthetic') === false, 'Email disabled');
demo_check(NIL_License::enabled() === false, 'No shared marketplace license');
demo_check(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'No misleading background scheduling');
echo "PASS: $checks real demo flow checks; WordPress " . get_bloginfo('version') . '; WooCommerce ' . WC_VERSION . "; SQLite only; no live store or marketplace license.\n";
