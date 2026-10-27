<?php
/** Zhaket Guard adapter. No HTTP or option reads at storefront boot/checkout. */
defined( 'ABSPATH' ) || exit;
final class NIL_License {
    const OPTION = 'nil_manager_guard';
    const LOCK = 'nil_manager_guard_lock';
    const EVENT = 'nil_manager_guard_check';
    public static function enabled() {
        static $enabled;
        if ( null === $enabled ) { $enabled = 'zhaket' === ( require __DIR__ . '/includes-channel.php' ); }
        return $enabled;
    }
    private static function product() { static $product; if ( null === $product ) { $product = require __DIR__ . '/includes-license-product.php'; } return $product; }
    public static function boot() {
        if ( ! self::enabled() ) { return; }
        add_action( self::EVENT, array( __CLASS__, 'refresh' ) );
        add_action( 'admin_menu', function() { add_submenu_page( 'woocommerce', 'لایسنس نیل منجر', 'لایسنس نیل منجر', 'manage_options', 'nil-manager-license', array( __CLASS__, 'page' ) ); } );
        add_action( 'admin_enqueue_scripts', function( $hook ) { if ( 'woocommerce_page_nil-manager-license' === $hook ) { wp_enqueue_style( 'nil-bulk', plugins_url( 'assets/admin.min.css', __FILE__ ), array(), NIL_Bulk_Manager::VERSION ); } } );
        add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
        add_action( 'admin_init', array( __CLASS__, 'ensure_event' ) );
    }
    public static function domain() {
        // Never trust request Host/X-Forwarded-Host. Match the official API's home host.
        $host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
        if ( ! $host || strlen( $host ) > 253 || ! preg_match( '/^[a-z0-9][a-z0-9.-]*[a-z0-9]$/D', $host ) ) { throw new RuntimeException( 'دامنهٔ اصلی وردپرس معتبر نیست؛ نشانی سایت را بررسی کنید.' ); }
        return $host;
    }
    private static function binding() { return hash( 'sha256', self::domain() . '|' . self::product() ); }
    private static function record() { $s = get_option( self::OPTION, array() ); return is_array( $s ) ? $s : array(); }
    private static function key() { return hash( 'sha256', wp_salt( 'auth' ) . '|nil-manager-guard', true ); }
    private static function seal( $token ) {
        if ( ! function_exists( 'openssl_encrypt' ) || ! in_array( 'aes-256-gcm', openssl_get_cipher_methods(), true ) ) { throw new RuntimeException( 'برای نگهداری امن لایسنس، افزونهٔ OpenSSL با AES-256-GCM روی هاست لازم است.' ); }
        $iv = random_bytes( 12 ); $tag = ''; $cipher = openssl_encrypt( $token, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, self::binding() );
        if ( false === $cipher || 16 !== strlen( $tag ) ) { throw new RuntimeException( 'نگهداری امن لایسنس ناموفق بود.' ); }
        return base64_encode( $iv . $tag . $cipher );
    }
    private static function open( $s ) {
        if ( ! function_exists( 'openssl_decrypt' ) || empty( $s['sealed'] ) || ! is_string( $s['sealed'] ) ) { return ''; }
        $raw = base64_decode( $s['sealed'], true ); if ( false === $raw || strlen( $raw ) < 29 || strlen( $raw ) > 2048 ) { return ''; }
        $token = openssl_decrypt( substr( $raw, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ), self::binding() );
        return is_string( $token ) ? $token : '';
    }
    public static function status() {
        if ( ! self::enabled() ) { return array( 'state' => 'community', 'allowed' => true, 'configured' => false, 'checked' => 0, 'verified' => 0 ); }
        $s = self::record(); $state = isset( $s['state'] ) && is_string( $s['state'] ) && in_array( $s['state'], array( 'valid','offline','invalid' ), true ) ? $s['state'] : 'missing';
        try {
            if ( ! self::product() ) { $state = 'configuration'; }
            elseif ( ! empty( $s ) && ( ( $s['binding'] ?? '' ) !== self::binding() || '' === self::open( $s ) ) ) { $state = 'changed'; }
        } catch ( Throwable $e ) { $state = 'configuration'; }
        return array( 'state' => $state, 'allowed' => ! is_multisite() && in_array( $state, array( 'valid','offline' ), true ) && ! empty( $s['verified'] ), 'configured' => (bool) self::product(), 'checked' => (int) ( $s['checked'] ?? 0 ), 'verified' => (int) ( $s['verified'] ?? 0 ) );
    }
    public static function assert_write() {
        if ( ! self::enabled() ) { return; }
        if ( ! self::status()['allowed'] ) { throw new RuntimeException( 'برای تغییرات تازه، لایسنس نیل منجر را از ووکامرس ← لایسنس نیل منجر فعال کنید. مشاهده، خروجی و بازگردانی در دسترس‌اند.' ); }
    }
    public static function rest( $reply, $server, $r ) {
        if ( null !== $reply || ! self::enabled() || ! NIL_Bulk_Manager::permission() ) { return $reply; }
        $path = $r->get_route();
        // Read/preview/export/recovery remain available. Gate new product/media/settings writes.
        $write = in_array( $path, array( '/nil-bulk/v1/create','/nil-bulk/v1/variations/apply','/nil-bulk/v1/cogs/migration' ), true );
        if ( preg_match( '#^/nil-bulk/v1/job/[a-f0-9-]{36}/run$#D', $path ) && 'undo' !== $r->get_param( 'mode' ) ) { $write = true; }
        if ( '/nil-bulk/v1/schedules' === $path && ! $r->get_param( 'delete' ) ) { $write = true; }
        if ( '/nil-bulk/v1/market/rules' === $path && ! in_array( $r->get_param( 'action' ), array( 'pause','delete' ), true ) ) { $write = true; }
        if ( ! in_array( $r->get_method(), array( 'GET','HEAD','OPTIONS' ), true ) && $write ) {
            try { self::assert_write(); } catch ( RuntimeException $e ) { return new WP_Error( 'nil_license_required', $e->getMessage(), array( 'status' => 403 ) ); }
        }
        return $reply;
    }
    private static function take_lock() {
        global $wpdb; $lock = time() . ':' . bin2hex( random_bytes( 12 ) );
        if ( add_option( self::LOCK, $lock, '', false ) ) { return $lock; }
        $old = get_option( self::LOCK );
        if ( is_string( $old ) && (int) $old < time() - 120 ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name=%s AND option_value=%s", self::LOCK, $old ) );
            wp_cache_delete( self::LOCK, 'options' );
            if ( add_option( self::LOCK, $lock, '', false ) ) { return $lock; }
        }
        throw new RuntimeException( 'بررسی لایسنس در حال انجام است؛ کمی بعد دوباره تلاش کنید.' );
    }
    private static function unlock( $lock ) {
        global $wpdb; $wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name=%s AND option_value=%s", self::LOCK, $lock ) ); wp_cache_delete( self::LOCK, 'options' );
    }
    private static function request( $method, $token ) {
        $params = array( 'token' => $token, 'domain' => self::domain() );
        if ( 'install-license' === $method ) { $params['product_token'] = self::product(); }
        foreach ( array( 'guard.zhaket.com', 'guard.zhaket.org' ) as $host ) {
            // The official protocol is GET. Never log/echo this URL, raw response or WP_Error.
            $r = wp_remote_get( 'https://' . $host . '/api/' . $method . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ), array( 'timeout' => 8, 'redirection' => 0, 'sslverify' => true, 'reject_unsafe_urls' => true, 'limit_response_size' => 16384, 'headers' => array( 'Accept' => 'application/json' ) ) );
            if ( is_wp_error( $r ) || in_array( wp_remote_retrieve_response_code( $r ), array( 429,500,502,503,504 ), true ) ) { continue; }
            if ( 200 !== wp_remote_retrieve_response_code( $r ) ) { return 'offline'; }
            $data = json_decode( wp_remote_retrieve_body( $r ), true );
            if ( ! is_array( $data ) || ! isset( $data['status'] ) || ! is_string( $data['status'] ) ) { return 'offline'; }
            if ( 'successful' === $data['status'] ) { return 'valid'; }
            // Only an explicit, well-formed API rejection is definitive; never infer it from HTTP/TLS errors.
            if ( 'error' === $data['status'] && isset( $data['message'] ) && ( is_string( $data['message'] ) || is_array( $data['message'] ) ) ) { return 'invalid'; }
            return 'offline';
        }
        return 'offline';
    }
    private static function persist( $s ) {
        update_option( self::OPTION, $s, false );
        if ( self::record() !== $s ) { throw new RuntimeException( 'ذخیرهٔ وضعیت لایسنس ناموفق بود؛ دوباره تلاش کنید.' ); }
        wp_clear_scheduled_hook( self::EVENT ); self::ensure_event();
    }
    public static function ensure_event() {
        if ( ! self::enabled() || wp_next_scheduled( self::EVENT ) ) { return; }
        $s = self::record();
        try { if ( empty( $s['sealed'] ) || ( $s['binding'] ?? '' ) !== self::binding() || '' === self::open( $s ) ) { return; } } catch ( Throwable $e ) { return; } wp_schedule_single_event( max( time() + 60, (int) ( $s['next'] ?? 0 ) ), self::EVENT );
    }
    public static function activate( $token ) {
        if ( ! current_user_can( 'manage_options' ) || ! self::enabled() || ! self::product() ) { throw new RuntimeException( 'تنظیم لایسنس این بسته در دسترس نیست.' ); }
        if ( is_multisite() ) { throw new RuntimeException( 'لایسنس چندسایتی هنوز تأیید و پشتیبانی نشده است؛ با پشتیبانی هماهنگ کنید.' ); }
        if ( ! is_string( $token ) || ! preg_match( '/^[A-Za-z0-9_-]{8,512}$/D', $token ) ) { throw new InvalidArgumentException( 'کد لایسنس را بدون فاصله از پنل ژاکت کپی کنید.' ); }
        $lock = self::take_lock();
        try {
            $old = self::record(); if ( (int) ( $old['attempt'] ?? 0 ) > time() - 60 ) { throw new RuntimeException( 'برای تلاش دوباره یک دقیقه صبر کنید.' ); }
            $sealed = self::seal( $token );
            // Keep a previously valid key if a replacement is rejected or the network fails.
            $old['attempt'] = time(); update_option( self::OPTION, $old, false );
            $state = self::request( 'install-license', $token );
            if ( 'valid' !== $state ) { self::ensure_event(); throw new RuntimeException( 'invalid' === $state ? 'ژاکت این لایسنس را نپذیرفت؛ کد، محصول و دامنهٔ ثبت‌شده را بررسی کنید.' : 'ارتباط معتبر با گارد برقرار نشد؛ لایسنس قبلی و داده‌های فروشگاه حفظ شدند.' ); }
            self::persist( array( 'sealed' => $sealed, 'binding' => self::binding(), 'state' => 'valid', 'attempt' => time(), 'checked' => time(), 'verified' => time(), 'next' => time() + DAY_IN_SECONDS ) );
        } finally { self::unlock( $lock ); }
    }
    public static function refresh( $manual = false ) {
        if ( ! self::enabled() || ( $manual && ! current_user_can( 'manage_options' ) ) ) { return; }
        try { $lock = self::take_lock(); } catch ( RuntimeException $e ) { return; }
        try {
            $s = self::record(); if ( ! $s || (int) ( $s['checked'] ?? 0 ) > time() - 60 || ( ! $manual && (int) ( $s['next'] ?? 0 ) > time() ) ) { return; }
            if ( ( $s['binding'] ?? '' ) !== self::binding() || '' === ( $token = self::open( $s ) ) ) { return; }
            $s['state'] = self::request( 'validation-license', $token ); $s['checked'] = time();
            if ( 'valid' === $s['state'] ) { $s['verified'] = time(); }
            elseif ( 'invalid' === $s['state'] ) { $s['verified'] = 0; }
            $s['next'] = time() + ( 'offline' === $s['state'] ? HOUR_IN_SECONDS : DAY_IN_SECONDS ); self::persist( $s );
        } finally { self::unlock( $lock ); self::ensure_event(); }
    }
    public static function forget() {
        if ( ! current_user_can( 'manage_options' ) || ! self::enabled() ) { throw new RuntimeException( 'دسترسی ندارید.' ); }
        $lock = self::take_lock();
        try { delete_option( self::OPTION ); wp_clear_scheduled_hook( self::EVENT ); } finally { self::unlock( $lock ); }
    }
    public static function notice() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
        $screen = get_current_screen(); if ( ! $screen || ! in_array( $screen->id, array( 'woocommerce_page_nil-bulk','woocommerce_page_nil-payment-discounts' ), true ) ) { return; }
        $s = self::status(); if ( 'valid' === $s['state'] ) { return; }
        echo '<div class="notice notice-warning"><p>' . esc_html( $s['allowed'] ? 'بررسی گارد موقتاً در دسترس نیست؛ تأیید قبلی معتبر مانده است.' : 'لایسنس نیل منجر به بررسی نیاز دارد؛ تغییرات تازه تا فعال‌سازی محدودند.' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=nil-manager-license' ) ) . '">مدیریت لایسنس</a></p></div>';
    }
    public static function page() {
        if ( ! current_user_can( 'manage_options' ) || ! self::enabled() ) { return; }
        $notice = ''; $error = false;
        if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            try {
                if ( ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'nil-manager-license' ) ) { throw new RuntimeException( 'اعتبار فرم تمام شده است؛ صفحه را تازه کنید.' ); }
                $action = isset( $_POST['license_action'] ) && is_string( $_POST['license_action'] ) ? $_POST['license_action'] : '';
                if ( 'activate' === $action ) { self::activate( isset( $_POST['license'] ) && is_string( $_POST['license'] ) ? trim( wp_unslash( $_POST['license'] ) ) : '' ); $notice = 'لایسنس برای دامنهٔ فعلی فعال شد.'; }
                elseif ( 'check' === $action ) { self::refresh( true ); $notice = 'وضعیت فعلی در بخش زیر نمایش داده می‌شود؛ بررسی دستی نیز محدودیت یک‌دقیقه‌ای دارد.'; }
                elseif ( 'forget' === $action ) { self::forget(); $notice = 'کد ذخیره‌شده از این سایت پاک شد. انتقال یا آزادسازی دامنه باید در ژاکت انجام شود.'; }
                else { throw new InvalidArgumentException( 'عملیات نامعتبر است.' ); }
            } catch ( Throwable $e ) { $notice = 'خطا در عملیات لایسنس؛ تنظیمات و ارتباط هاست را بررسی کنید.'; if ( $e instanceof RuntimeException || $e instanceof InvalidArgumentException ) { $notice = $e->getMessage(); } $error = true; }
        }
        $status = self::status(); require __DIR__ . '/includes-license-view.php';
    }
}
