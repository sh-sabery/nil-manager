<?php
/** Guard every product-writing fixture; never include in the installable plugin. */
function nil_fixture_guard() {
    global $wpdb;
    if (!defined('NIL_DISPOSABLE_FIXTURE') || NIL_DISPOSABLE_FIXTURE !== true) {
        throw new RuntimeException('Disposable-fixture opt-in is required; never run on a store.');
    }
    if (defined('NIL_TEST_REAL_MYSQL') && NIL_TEST_REAL_MYSQL === true) {
        $site = realpath((string)getenv('NIL_TEST_WP_ROOT'));
        $root = $site ? dirname($site) : '';
        $marker = $root . '/.nil-manager-test';
        if (PHP_SAPI !== 'cli' || !$site || realpath(ABSPATH) !== $site ||
            !preg_match('/^nil-manager-mysql\.[A-Za-z0-9]+$/', basename($root)) ||
            !preg_match('/^nil_manager_test_[a-f0-9]{12}$/D', DB_NAME) || $wpdb->prefix !== 'nil_test_' ||
            !is_file($marker) || trim(file_get_contents($marker)) !== DB_NAME ||
            defined('SQLITE_DB_DROPIN_VERSION')) {
            throw new RuntimeException('Native SQL fixture identity does not match its isolated site/database.');
        }
        $out = realpath((string)getenv('NIL_TEST_RESULTS_DIR'));
        if (!$out || dirname($out) !== $root) throw new RuntimeException('Invalid fixture result directory.');
    } elseif (!defined('SQLITE_DB_DROPIN_VERSION') && !(defined('DB_ENGINE') && DB_ENGINE === 'sqlite')) {
        throw new RuntimeException('Choose the guarded native SQL runner or a disposable SQLite Playground.');
    }
}
function nil_test_dir() {
    return defined('NIL_TEST_REAL_MYSQL') && NIL_TEST_REAL_MYSQL ? getenv('NIL_TEST_RESULTS_DIR') : '/nil-tests';
}
function nil_test_engine_note() {
    return defined('NIL_TEST_REAL_MYSQL') && NIL_TEST_REAL_MYSQL ? 'native SQL, no SQLite lock shim; concurrency is reported separately' : 'SQLite lock shim, no MySQL concurrency verification';
}
