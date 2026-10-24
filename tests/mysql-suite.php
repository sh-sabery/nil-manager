<?php
$site = realpath((string)getenv('NIL_TEST_WP_ROOT'));
if (PHP_SAPI !== 'cli' || !$site || !preg_match('/^nil-manager-mysql\.[A-Za-z0-9]+$/', basename(dirname($site))) || !is_file(dirname($site).'/.nil-manager-test')) throw new RuntimeException('Refusing to bootstrap a non-fixture WordPress site.');
require getenv('NIL_TEST_WP_ROOT') . '/wp-load.php';
require_once __DIR__ . '/fixture.php';
nil_fixture_guard();
try {
    foreach (array('frontend','integration','suite-integration','performance','advanced','market','selection','bulk-tools','audit','resource-profile') as $file) require __DIR__ . '/' . $file . '.php';
    $result = array('plugin'=>NIL_Bulk_Manager::VERSION,'php'=>PHP_VERSION,'wordpress'=>get_bloginfo('version'),'woocommerce'=>WC_VERSION,'database'=>$wpdb->get_var('SELECT VERSION()'),'hpos'=>get_option('woocommerce_custom_orders_table_enabled'),'assertions'=>$checks,'sqlite_lock_shim'=>false);
    file_put_contents(nil_test_dir().'/mysql-functional.json', wp_json_encode($result, JSON_PRETTY_PRINT));
    echo "\nPASS native functional suite: " . wp_json_encode($result) . "\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage().' at '.$e->getFile().':'.$e->getLine()."\n".$e->getTraceAsString()."\n");
    exit(1);
}
