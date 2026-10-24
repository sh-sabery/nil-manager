<?php
// Shared only by the native, temporary-site fixture controller and its workers.
if (PHP_SAPI !== 'cli') throw new RuntimeException('CLI fixture only.');
$site = realpath((string)getenv('NIL_TEST_WP_ROOT'));
if (PHP_SAPI !== 'cli' || !$site || !preg_match('/^nil-manager-mysql\.[A-Za-z0-9]+$/', basename(dirname($site))) || !is_file(dirname($site).'/.nil-manager-test')) throw new RuntimeException('Refusing to bootstrap a non-fixture WordPress site.');
require getenv('NIL_TEST_WP_ROOT').'/wp-load.php';
require_once __DIR__.'/fixture.php';
nil_fixture_guard();
wp_set_current_user(1);
function nm_request($method, $path, $data = null, $allow_error = false) {
    $request = new WP_REST_Request($method, '/nil-bulk/v1/'.$path);
    if ($data !== null) { $request->set_header('Content-Type','application/json'); $request->set_body(wp_json_encode($data)); }
    $response = rest_do_request($request);
    $result = array('status'=>$response->get_status(),'body'=>$response->get_data());
    if (!$allow_error && $result['status'] >= 400) throw new RuntimeException(wp_json_encode($result));
    return $allow_error ? $result : $result['body'];
}
function nm_fresh($id) { NIL_Bulk_Manager::clear_cache($id); return wc_get_product($id); }
function nm_signal($dir, $name, $data = array()) {
    $path = $dir.'/'.$name.'.json'; $tmp = $path.'.tmp.'.getmypid();
    file_put_contents($tmp,wp_json_encode($data)); rename($tmp,$path);
}
function nm_wait($dir, $name, $timeout = 20) {
    $path = $dir.'/'.$name.'.json'; $end = microtime(true)+$timeout;
    do { if (is_file($path)) return json_decode(file_get_contents($path),true); usleep(10000); } while (microtime(true)<$end);
    throw new RuntimeException('Fixture barrier timeout: '.$name);
}
