<?php
// Isolated HTTP/transient spies; never load WordPress or use store data.
define('ABSPATH', __DIR__);
class NIL_Bulk_Manager { const VERSION = '1.9.2'; const FILE = __DIR__.'/../nil-manager/nil-manager.php'; }
class WP_Error { public $code; public function __construct($code, $message) { $this->code = $code; } }
$hooks = array(); $allowed = true;
function add_filter($hook, $callback, $priority = 10, $count = 1) { global $hooks; $hooks[$hook][] = $callback; }
function add_action($hook, $callback, $priority = 10, $count = 1) { add_filter($hook, $callback, $priority, $count); }
function plugin_basename($file) { return 'nilwears-bulk-manager/nilwears-bulk-manager.php'; }
function current_user_can($cap) { global $allowed; return $allowed; }
function wp_remote_get($url, $args) { throw new Exception('Unexpected outbound HTTP'); }
function get_site_transient($key) { throw new Exception('Unexpected cache read'); }
function set_site_transient($key, $value, $ttl = 0) { throw new Exception('Unexpected cache write'); }
function delete_site_transient($key) { throw new Exception('Unexpected cache delete'); }
$n = 0;
function channel_expect($ok, $label) { global $n; ++$n; if (!$ok) { throw new Exception($label); } }
$dir = sys_get_temp_dir() . '/nil-channel-' . bin2hex(random_bytes(8));
mkdir($dir);
try {
    copy(__DIR__ . '/../nil-manager/includes-updater.php', $dir . '/includes-updater.php');
    file_put_contents($dir . '/includes-channel.php', "<?php defined('ABSPATH') || exit; return 'zhaket';");
    require $dir . '/includes-updater.php';
    channel_expect(NIL_Updater::channel() === 'zhaket', 'build selects marketplace channel');
    NIL_Updater::boot();
    channel_expect(isset($hooks['pre_set_site_transient_update_plugins'], $hooks['site_transient_update_plugins'], $hooks['upgrader_pre_download']), 'stale-cache and download guards registered');
    channel_expect(!isset($hooks['plugins_api'], $hooks['load-update-core.php']), 'no GitHub information or forced dashboard HTTP hook');
    $status = NIL_Updater::status();
    channel_expect($status['access'] === 'zhaket' && $status['available'] === null && !$status['update_available'] && $status['error'] === null, 'external status without claiming current marketplace version');
    channel_expect(!empty($status['message']) && !$status['configured'], 'review notice without pretending license activation');
    channel_expect(NIL_Updater::check() === $status, 'manual status does not fetch or alter caches');
    $allowed = false;
    try { NIL_Updater::check(); $denied = false; } catch (RuntimeException $e) { $denied = true; }
    channel_expect($denied, 'manual checks retain update_plugins capability'); $allowed = true;
    $plugin = plugin_basename('');
    $own = (object) array('package' => 'https://api.github.com/repos/sh-sabery/nilwears-bulk-manager/releases/assets/123');
    $canonical = (object) array('package' => 'https://api.github.com/repos/sh-sabery/nil-manager/releases/assets/123');
    channel_expect(NIL_Updater::download(false, $canonical->package, null, array())->code === 'nil_update_channel', 'new repository package blocked in marketplace channel');
    $market = (object) array('package' => 'https://zhaket.test/update.zip', 'new_version' => '9.0');
    $other = (object) array('package' => $own->package);
    foreach (array('response', 'no_update') as $bucket) {
        $transient = (object) array($bucket => array($plugin => $own, 'other/plugin.php' => $other), 'checked' => array($plugin => '1.9.1'), 'last_checked' => 123);
        $out = NIL_Updater::updates($transient);
        channel_expect(!isset($out->$bucket[$plugin]), 'remove own GitHub cache from ' . $bucket);
        channel_expect($out->$bucket['other/plugin.php'] === $other && $out->checked[$plugin] === '1.9.1' && $out->last_checked === 123, 'preserve other plugins and cache metadata');
        $transient->$bucket[$plugin] = $market;
        channel_expect(NIL_Updater::updates($transient)->$bucket[$plugin] === $market, 'preserve marketplace metadata in ' . $bucket);
    }
    channel_expect(NIL_Updater::updates(false) === false, 'empty transient stays empty');
    channel_expect(NIL_Updater::updates((object) array()) == (object) array(), 'missing buckets are safe');
    $result = (object) array('name' => 'marketplace details');
    channel_expect(NIL_Updater::info($result, 'plugin_information', (object) array('slug' => 'nilwears-bulk-manager')) === $result, 'external details preserved');
    channel_expect(NIL_Updater::download(false, $own->package, null, array())->code === 'nil_update_channel', 'cached GitHub download cannot overwrite marketplace package');
    channel_expect(NIL_Updater::download('/already.zip', $own->package, null, array())->code === 'nil_update_channel', 'prior short circuit cannot bypass channel guard');
    channel_expect(NIL_Updater::download(false, $market->package, null, array()) === false, 'marketplace downloader untouched');
    channel_expect(NIL_Updater::download('/other.zip', $market->package, null, array()) === '/other.zip', 'other download short circuit untouched');
    channel_expect(NIL_Updater::download(false, null, null, array()) === false, 'non-string package safe');
    echo "PASS: $n marketplace distribution checks; no HTTP or cache calls\n";
} finally {
    foreach (array('includes-updater.php', 'includes-channel.php') as $file) { if (is_file($dir . '/' . $file)) { unlink($dir . '/' . $file); } }
    rmdir($dir);
}
