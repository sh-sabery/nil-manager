<?php
/** Public GitHub releases; optional private-repository token stays on the server. */
defined( 'ABSPATH' ) || exit;
final class NIL_Updater {
    const REPO = 'sh-sabery/nilwears-bulk-manager';
    public static function boot() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'updates' ) );
        add_filter( 'site_transient_update_plugins', array( __CLASS__, 'updates' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
        add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), 10, 4 );
        add_action( 'load-update-core.php', array( __CLASS__, 'dashboard_check' ), 1 );
    }
    private static function token() { return defined( 'NIL_BULK_GITHUB_TOKEN' ) ? (string) NIL_BULK_GITHUB_TOKEN : ''; }
    private static function headers( $accept, $auth = true ) {
        $headers = array( 'Accept' => $accept, 'X-GitHub-Api-Version' => '2022-11-28' );
        if ( $auth && self::token() ) { $headers['Authorization'] = 'Bearer ' . self::token(); }
        return $headers;
    }
    private static function failure( $code, $message, $http = 0 ) {
        set_site_transient( self::token() ? 'nil_release' : 'nil_release_public', 'none', 5 * MINUTE_IN_SECONDS );
        set_site_transient( 'nil_release_error', array( 'code' => $code, 'message' => $message, 'http_status' => $http, 'checked_at' => time() ), 5 * MINUTE_IN_SECONDS ); return false;
    }
    private static function release( $force = false ) {
        if ( $force ) { delete_site_transient( 'nil_release' ); delete_site_transient( 'nil_release_public' ); delete_site_transient( 'nil_release_error' ); }
        $cached = get_site_transient( self::token() ? 'nil_release' : 'nil_release_public' ); if ( is_array( $cached ) ) { return $cached; }
        if ( 'none' === $cached ) { return false; }
        $url = 'https://api.github.com/repos/' . self::REPO . '/releases/latest'; $auth = '' !== self::token();
        $args = array( 'reject_unsafe_urls' => true, 'timeout' => 15, 'redirection' => 0, 'limit_response_size' => 1048576, 'headers' => self::headers( 'application/vnd.github+json', $auth ) );
        $response = wp_remote_get( $url, $args );
        if ( $auth && ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 401,403,404 ), true ) ) {
            // An expired optional token must not hide a public release.
            $auth = false; $args['headers'] = self::headers( 'application/vnd.github+json', false ); $response = wp_remote_get( $url, $args );
        }
        if ( is_wp_error( $response ) ) { return self::failure( 'network', 'ارتباط HTTPS سرور با گیت‌هاب ناموفق بود؛ اتصال خروجی، DNS و TLS هاست را بررسی کنید.' ); }
        $http = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $http ) { return self::failure( 'http', 'گیت‌هاب پاسخ HTTP ' . $http . ' داد؛ دسترسی یا محدودیت درخواست سرور را بررسی کنید.', $http ); }
        $data = json_decode( wp_remote_retrieve_body( $response ), true ); $tag = $data['tag_name'] ?? '';
        if ( ! is_array( $data ) || ! is_string( $tag ) || ! preg_match( '/^v(\d+\.\d+\.\d+)$/', $tag, $m ) || ! empty( $data['prerelease'] ) || ! empty( $data['draft'] ) || ! is_array( $data['assets'] ?? null ) ) { return self::failure( 'metadata', 'اطلاعات نسخهٔ منتشرشده معتبر نیست.' ); }
        foreach ( $data['assets'] ?? array() as $asset ) {
            if ( is_array( $asset ) && ( $asset['name'] ?? '' ) === 'nilwears-bulk-manager-' . $tag . '.zip' && ! empty( $asset['id'] ) && ! empty( $asset['size'] ) ) {
                if ( ! is_string( $asset['digest'] ?? null ) || ! preg_match( '/^sha256:[a-f0-9]{64}$/D', $asset['digest'] ) || ! filter_var( $asset['size'], FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1, 'max_range' => 20 * 1024 * 1024 ) ) ) ) { return self::failure( 'integrity', 'هش SHA-256 یا اندازهٔ بستهٔ به‌روزرسانی معتبر نیست.' ); }
                $release = array( 'version' => $m[1], 'asset' => absint( $asset['id'] ), 'size' => absint( $asset['size'] ), 'digest' => $asset['digest'] ?? '', 'notes' => wp_strip_all_tags( $data['body'] ?? '' ), 'access' => $auth ? 'token' : 'public', 'checked_at' => time() );
                delete_site_transient( 'nil_release_error' ); set_site_transient( self::token() ? 'nil_release' : 'nil_release_public', $release, 6 * HOUR_IN_SECONDS ); return $release;
            }
        } return self::failure( 'asset', 'فایل ZIP نصب برای این نسخه در گیت‌هاب پیدا نشد.' );
    }
    public static function status() { $r = self::release(); return array( 'configured' => '' !== self::token(), 'current' => NIL_Bulk_Manager::VERSION, 'available' => $r ? $r['version'] : null, 'update_available' => $r && version_compare( $r['version'], NIL_Bulk_Manager::VERSION, '>' ), 'access' => $r['access'] ?? ( self::token() ? 'token' : 'public' ), 'checked_at' => $r['checked_at'] ?? null, 'error' => $r ? null : ( get_site_transient( 'nil_release_error' ) ?: null ) ); }
    public static function check() {
        if ( ! current_user_can( 'update_plugins' ) ) { throw new RuntimeException( 'بررسی تازهٔ به‌روزرسانی به دسترسی به‌روزرسانی افزونه‌ها نیاز دارد.' ); }
        self::release( true ); $t = self::updates( get_site_transient( 'update_plugins' ) ); set_site_transient( 'update_plugins', $t ); return self::status();
    }
    public static function dashboard_check() { if ( ! empty( $_GET['force-check'] ) && current_user_can( 'update_plugins' ) ) { self::check(); } }
    private static function package( $r ) { return 'https://api.github.com/repos/' . self::REPO . '/releases/assets/' . $r['asset']; }
    private static function icons() { return array( 'svg' => plugins_url( 'assets/icon.svg', __DIR__ . '/nilwears-bulk-manager.php' ) ); }
    public static function updates( $transient ) {
        if ( ! is_object( $transient ) ) { $transient = new stdClass(); }
        $r = self::release(); $plugin = plugin_basename( __DIR__ . '/nilwears-bulk-manager.php' );
        if ( $r ) {
            $transient->checked[$plugin] = NIL_Bulk_Manager::VERSION;
            unset( $transient->response[$plugin], $transient->no_update[$plugin] );
            $entry = (object) array( 'slug' => 'nilwears-bulk-manager', 'plugin' => $plugin, 'new_version' => $r['version'], 'url' => 'https://github.com/' . self::REPO, 'package' => self::package( $r ), 'requires' => '6.4', 'tested' => '7.1.3', 'requires_php' => '7.4', 'icons' => self::icons() );
            if ( version_compare( $r['version'], NIL_Bulk_Manager::VERSION, '>' ) ) { $transient->response[$plugin] = $entry; }
            else { $transient->no_update[$plugin] = $entry; }
        } return $transient;
    }
    public static function info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || 'nilwears-bulk-manager' !== ( $args->slug ?? '' ) ) { return $result; }
        $r = self::release(); if ( ! $r ) { return $result; }
        return (object) array( 'name' => NIL_Suite::title(), 'slug' => 'nilwears-bulk-manager', 'version' => $r['version'], 'author' => 'Nil Web', 'homepage' => 'https://github.com/' . self::REPO, 'requires' => '6.4', 'tested' => '7.1.3', 'requires_php' => '7.4', 'icons' => self::icons(), 'banners' => array( 'low' => plugins_url( 'assets/banner.svg', __DIR__ . '/nilwears-bulk-manager.php' ) ), 'sections' => array( 'description' => 'نیل منجر؛ مدیریت قیمت و موجودی ووکامرس، محصول نیل وب', 'changelog' => nl2br( esc_html( $r['notes'] ) ) ), 'download_link' => self::package( $r ) );
    }
    public static function download( $reply, $package, $upgrader, $hook_extra ) {
        if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, 'https://api.github.com/repos/' . self::REPO . '/releases/assets/' ) ) { return $reply; }
        $r = self::release(); if ( ! $r || $package !== self::package( $r ) ) { return new WP_Error( 'nil_update_auth', 'ریلیز مورد درخواست معتبر نیست.' ); }
        if ( ! function_exists( 'wp_tempnam' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
        $tmp = wp_tempnam( 'nil-update.zip' );
        $args = array( 'reject_unsafe_urls' => true, 'timeout' => 60, 'redirection' => 0, 'headers' => self::headers( 'application/octet-stream', 'public' !== ( $r['access'] ?? '' ) ), 'stream' => true, 'filename' => $tmp, 'limit_response_size' => 20 * 1024 * 1024 );
        $response = wp_remote_get( $package, $args );
        if ( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 301,302,303,307 ), true ) ) {
            $url = wp_remote_retrieve_header( $response, 'location' ); $parts = wp_parse_url( $url );
            if ( 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( $parts['host'] ?? '', array( 'release-assets.githubusercontent.com','objects.githubusercontent.com' ), true ) || isset( $parts['user'] ) || isset( $parts['port'] ) ) { @unlink( $tmp ); return new WP_Error( 'nil_update_host', 'میزبان دانلود معتبر نیست.' ); }
            // Credentials must never follow the redirect to the asset host.
            $args['headers'] = array(); $response = wp_remote_get( $url, $args );
        }
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_file( $tmp ) || filesize( $tmp ) !== $r['size'] ) { @unlink( $tmp ); return new WP_Error( 'nil_update_download', 'دانلود کامل نشد.' ); }
        if ( ! preg_match( '/^sha256:([a-f0-9]{64})$/D', $r['digest'], $m ) || ! hash_equals( $m[1], hash_file( 'sha256', $tmp ) ) ) { @unlink( $tmp ); return new WP_Error( 'nil_update_hash', 'هش SHA-256 فایل مطابقت ندارد.' ); }
        return $tmp;
    }
}
