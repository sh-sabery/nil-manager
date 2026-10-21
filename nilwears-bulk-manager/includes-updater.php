<?php
/** Private GitHub releases; the read-only token stays on the WordPress server. */
defined( 'ABSPATH' ) || exit;
final class NIL_Updater {
    const REPO = 'sh-sabery/nilwears-bulk-manager';
    public static function boot() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'updates' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
        add_filter( 'upgrader_pre_download', array( __CLASS__, 'download' ), 10, 4 );
    }
    private static function token() { return defined( 'NIL_BULK_GITHUB_TOKEN' ) ? (string) NIL_BULK_GITHUB_TOKEN : ''; }
    private static function release() {
        if ( ! self::token() ) { return false; }
        $cached = get_site_transient( 'nil_release' ); if ( is_array( $cached ) ) { return $cached; }
        if ( 'none' === $cached ) { return false; }
        $response = wp_remote_get( 'https://api.github.com/repos/' . self::REPO . '/releases/latest', array( 'timeout' => 15, 'redirection' => 0, 'headers' => array( 'Authorization' => 'Bearer ' . self::token(), 'Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28' ) ) );
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { set_site_transient( 'nil_release', 'none', 5 * MINUTE_IN_SECONDS ); return false; }
        $data = json_decode( wp_remote_retrieve_body( $response ), true ); $tag = $data['tag_name'] ?? '';
        if ( ! preg_match( '/^v(\d+\.\d+\.\d+)$/', $tag, $m ) || ! empty( $data['prerelease'] ) || ! empty( $data['draft'] ) ) { return false; }
        foreach ( $data['assets'] ?? array() as $asset ) {
            if ( $asset['name'] === 'nilwears-bulk-manager-' . $tag . '.zip' ) {
                $release = array( 'version' => $m[1], 'asset' => absint( $asset['id'] ), 'size' => absint( $asset['size'] ), 'digest' => $asset['digest'] ?? '', 'notes' => wp_strip_all_tags( $data['body'] ?? '' ) );
                set_site_transient( 'nil_release', $release, 6 * HOUR_IN_SECONDS ); return $release;
            }
        } return false;
    }
    public static function status() { $r = self::release(); return array( 'configured' => '' !== self::token(), 'current' => NIL_Bulk_Manager::VERSION, 'available' => $r ? $r['version'] : null ); }
    private static function package( $r ) { return 'https://api.github.com/repos/' . self::REPO . '/releases/assets/' . $r['asset']; }
    public static function updates( $transient ) {
        if ( ! is_object( $transient ) || empty( $transient->checked ) ) { return $transient; }
        $r = self::release(); $plugin = plugin_basename( __DIR__ . '/nilwears-bulk-manager.php' );
        if ( $r && version_compare( $r['version'], NIL_Bulk_Manager::VERSION, '>' ) ) {
            $transient->response[$plugin] = (object) array( 'slug' => 'nilwears-bulk-manager', 'plugin' => $plugin, 'new_version' => $r['version'], 'url' => 'https://github.com/' . self::REPO, 'package' => self::package( $r ), 'requires' => '6.4', 'requires_php' => '7.4' );
        } return $transient;
    }
    public static function info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || 'nilwears-bulk-manager' !== ( $args->slug ?? '' ) ) { return $result; }
        $r = self::release(); if ( ! $r ) { return $result; }
        return (object) array( 'name' => NIL_Suite::title(), 'slug' => 'nilwears-bulk-manager', 'version' => $r['version'], 'author' => 'Nilwears', 'homepage' => 'https://github.com/' . self::REPO, 'requires' => '6.4', 'requires_php' => '7.4', 'sections' => array( 'description' => 'مدیریت محصولات و قیمت نیل', 'changelog' => nl2br( esc_html( $r['notes'] ) ) ), 'download_link' => self::package( $r ) );
    }
    public static function download( $reply, $package, $upgrader, $hook_extra ) {
        if ( false !== $reply || ! is_string( $package ) || 0 !== strpos( $package, 'https://api.github.com/repos/' . self::REPO . '/releases/assets/' ) ) { return $reply; }
        $r = self::release(); if ( ! $r || $package !== self::package( $r ) || ! self::token() ) { return new WP_Error( 'nil_update_auth', 'دسترسی به ریلیز خصوصی معتبر نیست.' ); }
        if ( ! function_exists( 'wp_tempnam' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
        $tmp = wp_tempnam( 'nil-update.zip' );
        $args = array( 'timeout' => 60, 'redirection' => 0, 'headers' => array( 'Authorization' => 'Bearer ' . self::token(), 'Accept' => 'application/octet-stream', 'X-GitHub-Api-Version' => '2022-11-28' ), 'stream' => true, 'filename' => $tmp, 'limit_response_size' => 20 * 1024 * 1024 );
        $response = wp_remote_get( $package, $args );
        if ( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 301,302,303,307 ), true ) ) {
            $url = wp_remote_retrieve_header( $response, 'location' ); $parts = wp_parse_url( $url );
            if ( 'https' !== ( $parts['scheme'] ?? '' ) || ! in_array( $parts['host'] ?? '', array( 'release-assets.githubusercontent.com','objects.githubusercontent.com' ), true ) || isset( $parts['user'] ) || isset( $parts['port'] ) ) { @unlink( $tmp ); return new WP_Error( 'nil_update_host', 'میزبان دانلود معتبر نیست.' ); }
            // Credentials must never follow the redirect to the asset host.
            $args['headers'] = array(); $response = wp_remote_get( $url, $args );
        }
        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || ! is_file( $tmp ) || filesize( $tmp ) !== $r['size'] ) { @unlink( $tmp ); return new WP_Error( 'nil_update_download', 'دانلود کامل نشد.' ); }
        if ( preg_match( '/^sha256:([a-f0-9]{64})$/', $r['digest'], $m ) && ! hash_equals( $m[1], hash_file( 'sha256', $tmp ) ) ) { @unlink( $tmp ); return new WP_Error( 'nil_update_hash', 'امضای هش فایل مطابقت ندارد.' ); }
        return $tmp;
    }
}
