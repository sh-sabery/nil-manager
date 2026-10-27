<?php
/**
 * Plugin Name: نیل منجر — مدیریت قیمت و موجودی ووکامرس
 * Description: مدیریت گروهی قیمت و موجودی محصولات و ورییشن‌ها، رندکردن پیشرفته، تخفیف روش پرداخت، قیمت‌گذاری طلا و ارز، پیش‌نمایش و بازگردانی؛ محصول نیل وب.
 * Version: 1.11.0
 * Update URI: https://github.com/sh-sabery/nil-manager
 * Author: نیل وب
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.5
 * WC tested up to: 11.2.1
 * Text Domain: nil-manager
 * License: GPL-2.0-or-later
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/includes-price.php';
require_once __DIR__ . '/includes-cost.php';
require_once __DIR__ . '/includes-suite.php';
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) { \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true ); if ( method_exists( 'WC_Product', 'get_cogs_total_value' ) ) { \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cost_of_goods_sold', __FILE__, true ); } }
} );

final class NIL_Bulk_Manager {
    const VERSION = '1.11.0';
    const FILE = __FILE__;
    const VARIATION_LIMIT = 500;
    /** Prime a bounded batch; reuse WordPress caches instead of per-product reads. */
    public static function prime( $ids ) {
        $ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ); if ( ! $ids ) { return; }
        _prime_post_caches( $ids, true, true ); $parents = array();
        foreach ( $ids as $id ) { $post = get_post( $id ); if ( $post && $post->post_parent ) { $parents[] = $post->post_parent; } }
        if ( $parents ) { _prime_post_caches( array_unique( $parents ), true, true ); }
    }
    private static function table( $name ) { global $wpdb; return $wpdb->prefix . 'nil_bulk_' . $name; }

    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $jobs = self::table( 'jobs' ); $items = self::table( 'items' );
        dbDelta( "CREATE TABLE $jobs (
            id varchar(36) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'draft',
            config longtext NOT NULL,
            PRIMARY KEY  (id),
            KEY user_created (user_id,created_at)
        ) ENGINE=InnoDB $charset;" );
        dbDelta( "CREATE TABLE $items (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            job_id varchar(36) NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            label text NOT NULL,
            before_json longtext NOT NULL,
            after_json longtext NOT NULL,
            fields_json text NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'pending',
            error text NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY job_product (job_id,product_id),
            KEY job_state (job_id,state)
        ) ENGINE=InnoDB $charset;" );
        NIL_Market::install();
        update_option( 'nil_bulk_version', self::VERSION, false );
    }

    public static function boot() {
        if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) { NIL_Updater::boot(); NIL_License::boot(); }
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function() { echo '<div class="notice notice-warning"><p>نیل منجر به ووکامرس فعال نیاز دارد.</p></div>'; } );
            return;
        }
        add_action( 'admin_init', function() {
            if ( current_user_can( 'manage_woocommerce' ) && get_option( 'nil_bulk_version' ) !== self::VERSION ) { self::install(); }
        } );
        NIL_Suite::boot();
        add_action( 'admin_menu', function() {
            add_submenu_page( 'woocommerce', NIL_Suite::title(), NIL_Suite::title(), 'manage_woocommerce', 'nil-bulk', array( __CLASS__, 'page' ) );
        } );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
    }

    public static function page() {
        if ( current_user_can( 'manage_woocommerce' ) ) {
            echo '<div id="nil-bulk-app" class="wrap" dir="rtl"><p>در حال آماده‌سازی نیل منجر…</p></div>';
            require __DIR__ . '/includes-grid.php';
            require __DIR__ . '/includes-view.php';
            require __DIR__ . '/includes-help.php';
        }
    }
    private static function config() {
        $decimals = wc_get_price_decimals();
        if ( $decimals < 0 || $decimals > 4 || PHP_INT_SIZE < 8 ) { throw new RuntimeException( 'این نسخه به PHP ۶۴ بیتی و حداکثر ۴ رقم اعشار قیمت نیاز دارد.' ); }
        return array( 'currency' => get_woocommerce_currency(), 'decimals' => $decimals );
    }
    public static function assets( $hook ) {
        if ( 'woocommerce_page_nil-bulk' !== $hook ) { return; }
        wp_enqueue_style( 'nil-bulk', plugins_url( 'assets/admin.min.css', __FILE__ ), array(), self::VERSION );
        wp_enqueue_script( 'nil-bulk', plugins_url( 'assets/admin.min.js', __FILE__ ), array(), self::VERSION, true );
        wp_enqueue_script( 'nil-suite', plugins_url( 'assets/suite.min.js', __FILE__ ), array( 'nil-bulk' ), self::VERSION, true );
        wp_enqueue_script( 'nil-advanced', plugins_url( 'assets/advanced.min.js', __FILE__ ), array( 'nil-suite' ), self::VERSION, true );
        wp_enqueue_script( 'nil-market', plugins_url( 'assets/market.min.js', __FILE__ ), array( 'nil-advanced' ), self::VERSION, true );
        $categories = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
        wp_localize_script( 'nil-bulk', 'NIL_BULK', array(
            'api' => esc_url_raw( rest_url( 'nil-bulk/v1/' ) ), 'nonce' => wp_create_nonce( 'wp_rest' ),
            'currency' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
            'currencyCode' => get_woocommerce_currency(),
            'decimals' => wc_get_price_decimals(), 'timeZone' => wp_timezone_string(),
            'costBackend' => NIL_Cost::native() ? 'native' : 'legacy',
            'canBrand' => current_user_can( 'manage_options' ), 'canMarket' => current_user_can( 'manage_options' ), 'canUpdate' => current_user_can( 'update_plugins' ), 'userId' => get_current_user_id(),
            'branding' => NIL_Suite::branding(), 'brands' => NIL_Suite::brands(),
            'categories' => is_wp_error( $categories ) ? array() : array_map( function( $term ) {
                return array( 'id' => $term->term_id, 'name' => $term->name );
            }, $categories ),
        ) );
    }

    public static function permission() { return current_user_can( 'manage_woocommerce' ) && current_user_can( 'edit_products' ); }
    public static function routes() {
        $routes = array(
            'products' => array( 'GET', 'products' ), 'preview' => array( 'POST', 'preview' ),
            'jobs' => array( 'GET', 'jobs' ), 'job/(?P<id>[a-f0-9-]{36})' => array( 'GET', 'job_detail' ),
            'job/(?P<id>[a-f0-9-]{36})/seal' => array( 'POST', 'seal' ),
            'job/(?P<id>[a-f0-9-]{36})/run' => array( 'POST', 'run' ),
        );
        foreach ( $routes as $route => $definition ) {
            register_rest_route( 'nil-bulk/v1', '/' . $route, array(
                'methods' => $definition[0], 'permission_callback' => array( __CLASS__, 'permission' ),
                'callback' => function( $request ) use ( $definition ) {
                    try { return call_user_func( array( __CLASS__, $definition[1] ), $request ); }
                    catch ( Throwable $e ) { return new WP_Error( 'nil_error', $e->getMessage(), array( 'status' => 400 ) ); }
                },
            ) );
        }
    }

    public static function editable( $product ) {
        if ( ! $product || ! in_array( $product->get_type(), array( 'simple','variable','variation' ), true ) ||
            ! in_array( $product->get_status( 'edit' ), array( 'publish','private','draft','pending','future' ), true ) ||
            ! current_user_can( 'edit_post', $product->get_id() ) ||
            ( $product->get_parent_id() && ! current_user_can( 'edit_post', $product->get_parent_id() ) ) ) {
            throw new RuntimeException( 'محصول در دسترس نیست یا اجازه ویرایش آن را ندارید.' );
        }
        return $product;
    }
    public static function snapshot( $p ) {
        $from = $p->get_date_on_sale_from( 'edit' ); $to = $p->get_date_on_sale_to( 'edit' );
        $parent = $p->is_type( 'variation' ) ? wc_get_product( $p->get_parent_id() ) : false;
        return array_merge( array(
            'name' => $p->get_name( 'edit' ), 'sku' => $p->get_sku( 'edit' ),
            'description' => $p->get_description( 'edit' ), 'short_description' => $p->get_short_description( 'edit' ),
            'cost_price' => NIL_Cost::read( $p ),
            'category_ids' => $p->is_type( 'variation' ) ? array() : $p->get_category_ids( 'edit' ),
            'brand_ids' => NIL_Suite::product_brands( $p ), 'brand_taxonomy' => NIL_Suite::brand_taxonomy(),
            'regular_price' => $p->get_regular_price( 'edit' ), 'sale_price' => $p->get_sale_price( 'edit' ),
            'manage_stock' => (bool) $p->get_manage_stock( 'edit' ), 'stock_quantity' => $p->get_stock_quantity( 'edit' ),
            'stock_status' => $p->get_stock_status( 'edit' ), 'backorders' => $p->get_backorders( 'edit' ),
            'sale_from' => $from ? $from->getTimestamp() : null, 'sale_to' => $to ? $to->getTimestamp() : null,
            'parent_stock' => $parent ? (bool) $parent->get_manage_stock( 'edit' ) : false,
            'type' => $p->get_type(), 'parent_id' => $p->get_parent_id(), 'status' => $p->get_status( 'edit' ),
        ), NIL_Cost::state( $p ) );
    }
    public static function fingerprint( $data ) { return hash( 'sha256', wp_json_encode( $data ) ); }
    public static function row( $p ) {
        $s = self::snapshot( $p );
        return array( 'id' => $p->get_id(), 'parent' => $p->get_parent_id(), 'type' => $p->get_type(),
            'name' => $p->get_name(), 'sku' => $p->get_sku( 'edit' ), 'values' => $s,
            'version' => self::fingerprint( $s ), 'url' => get_edit_post_link( $p->get_parent_id() ?: $p->get_id(), 'raw' ),
            'attributes' => $p->is_type( 'variation' ) ? wp_strip_all_tags( wc_get_formatted_variation( $p, true, true, false ) ) : '',
        );
    }
    public static function products( $r ) {
        $page = max( 1, absint( $r->get_param( 'page' ) ) );
        $args = array( 'post_type' => 'product', 'post_status' => array( 'publish','private','draft','pending','future' ),
            'posts_per_page' => 15, 'paged' => $page, 'orderby' => 'ID', 'order' => 'ASC',
            'tax_query' => array( array( 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => array( 'simple','variable' ) ) ),
        );
        if ( $r->get_param( 'category' ) ) { $args['tax_query'][] = array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => absint( $r->get_param( 'category' ) ) ); }
        $args = NIL_Suite::filter_args( $args, $r );
        $search = sanitize_text_field( $r->get_param( 'search' ) ?: '' );
        if ( '' !== $search ) {
            $sku_id = wc_get_product_id_by_sku( $search );
            if ( $sku_id ) { $sku = wc_get_product( $sku_id ); $args['post__in'] = array( $sku->get_parent_id() ?: $sku_id ); }
            elseif ( ctype_digit( $search ) && wc_get_product( (int) $search ) ) {
                $found = wc_get_product( (int) $search ); $args['post__in'] = array( $found->get_parent_id() ?: $found->get_id() );
            } else { $args['s'] = $search; }
        }
        $query = new WP_Query( $args ); $rows = array();
        // Prime all variation posts/meta once for the page, rather than once per parent.
        $children = array(); $parent_ids = wp_list_pluck( $query->posts, 'ID' );
        if ( $parent_ids ) {
            $variations = new WP_Query( array( 'post_type' => 'product_variation', 'post_parent__in' => $parent_ids,
                'post_status' => array( 'publish','private','draft','pending','future' ), 'posts_per_page' => self::VARIATION_LIMIT + 1,
                'no_found_rows' => true, 'update_post_term_cache' => true, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) ) );
            if ( count( $variations->posts ) > self::VARIATION_LIMIT ) { throw new RuntimeException( 'بیش از ۵۰۰ ورییشن در یک صفحه؛ دسته یا جست‌وجو را محدود کنید.' ); }
            foreach ( $variations->posts as $variation_post ) { $children[$variation_post->post_parent][] = $variation_post->ID; }
        }
        foreach ( $query->posts as $post ) {
            $p = wc_get_product( $post->ID );
            if ( ! $p || ! current_user_can( 'edit_post', $post->ID ) ) { continue; }
            $rows[] = self::row( $p );
            if ( $p->is_type( 'variable' ) ) {
                foreach ( $children[$p->get_id()] ?? array() as $child ) {
                    $variation = wc_get_product( $child );
                    if ( $variation && current_user_can( 'edit_post', $child ) && 'trash' !== $variation->get_status( 'edit' ) ) { $rows[] = self::row( $variation ); }
                }
            }
        }
        return array( 'rows' => $rows, 'pages' => (int) $query->max_num_pages, 'total' => (int) $query->found_posts, 'page' => $page );
    }

    private static function same( $a, $b ) { return is_array( $a ) || is_array( $b ) ? $a === $b : (string) $a === (string) $b; }
    private static function bool_value( $value ) {
        if ( ! is_bool( $value ) ) { throw new InvalidArgumentException( 'مدیریت موجودی باید روشن یا خاموش باشد.' ); }
        return $value;
    }
    private static function proposal( $p, $before, $input, $rule ) {
        $after = $before;
        if ( ! is_array( $input ) ) { throw new InvalidArgumentException( 'تغییرات نامعتبر است.' ); }
        if ( is_array( $rule ) && isset( $rule['kind'] ) ) {
            if ( $input ) { throw new InvalidArgumentException( 'عملیات گروهی را با ویرایش دستی ترکیب نکنید.' ); }
            if ( 'discount' === $rule['kind'] ) {
                if ( $p->is_type( 'variable' ) ) { throw new InvalidArgumentException( 'تخفیف روی ورییشن اعمال می‌شود.' ); }
                $after['sale_price'] = NIL_Bulk_Price::discount( $before['regular_price'], $rule, wc_get_price_decimals() );
                $after['sale_from'] = $after['sale_to'] = null;
            } elseif ( 'stock' === $rule['kind'] ) { $input = NIL_Bulk_Price::stock( $before, $rule ); }
            else { throw new InvalidArgumentException( 'نوع عملیات گروهی نامعتبر است.' ); }
            $rule = null;
        }
        $allowed = array( 'regular_price','sale_price','manage_stock','stock_quantity','stock_status','name','sku','description','short_description','cost_price','category_ids','brand_ids' );
        foreach ( $input as $key => $value ) {
            if ( ! in_array( $key, $allowed, true ) ) { throw new InvalidArgumentException( 'فیلد غیرمجاز.' ); }
            if ( in_array( $key, array( 'name','sku','description','short_description','cost_price','category_ids','brand_ids' ), true ) ) {
                $after[$key] = NIL_Suite::validate_field( $p, $key, $value );
                if ( 'cost_price' === $key ) { $after = NIL_Cost::propose( $p, $after, $after[$key] ); }
            } elseif ( in_array( $key, array( 'regular_price','sale_price' ), true ) ) {
                if ( $p->is_type( 'variable' ) ) { throw new InvalidArgumentException( 'قیمت والد از ورییشن‌ها محاسبه می‌شود.' ); }
                if ( '' === $value ) { $after[$key] = ''; }
                else { $n = NIL_Bulk_Price::number( $value ); if ( $n < 0 ) { throw new InvalidArgumentException( 'قیمت منفی مجاز نیست.' ); } $after[$key] = number_format( $n, wc_get_price_decimals(), '.', '' ); }
            } elseif ( 'manage_stock' === $key ) { $after[$key] = self::bool_value( $value ); }
            elseif ( 'stock_quantity' === $key ) {
                $n = NIL_Bulk_Price::number( $value );
                if ( floor( $n ) !== $n || $n < 0 || $n > 100000000 ) { throw new InvalidArgumentException( 'موجودی باید عدد صحیح نامنفی تا ۱۰۰ میلیون باشد.' ); }
                $after[$key] = (int) $n;
            } else {
                if ( ! in_array( $value, array( 'instock','outofstock','onbackorder' ), true ) ) { throw new InvalidArgumentException( 'وضعیت موجودی نامعتبر است.' ); }
                $after[$key] = $value;
            }
        }
        if ( is_array( $rule ) && ! $p->is_type( 'variable' ) ) {
            $target = isset( $rule['target'] ) ? $rule['target'] : 'regular_price';
            if ( ! in_array( $target, array( 'regular_price','sale_price','both' ), true ) ) { throw new InvalidArgumentException( 'ستون قیمت نامعتبر است.' ); }
            foreach ( array( 'regular_price','sale_price' ) as $key ) {
                if ( $target === $key || 'both' === $target ) { $source = isset( $rule['source'] ) && 'cost' === $rule['source'] ? $after['cost_price'] : $after[$key]; if ( isset( $rule['source'] ) && 'cost' === $rule['source'] && '' === $source ) { throw new InvalidArgumentException( 'قیمت خرید ثبت نشده است.' ); } $after[$key] = NIL_Bulk_Price::calculate( $source, $rule, wc_get_price_decimals() ); }
            }
        }
        if ( $after['manage_stock'] && ( array_key_exists( 'manage_stock', $input ) || array_key_exists( 'stock_quantity', $input ) || array_key_exists( 'stock_status', $input ) ) ) {
            if ( null === $after['stock_quantity'] ) { throw new InvalidArgumentException( 'برای مدیریت موجودی، تعداد را وارد کنید.' ); }
            // Managed-stock status is derived by WooCommerce from quantity and backorders.
            $after['stock_status'] = $after['stock_quantity'] > (int) get_option( 'woocommerce_notify_no_stock_amount', 0 ) ? 'instock' : ( 'no' === $after['backorders'] ? 'outofstock' : 'onbackorder' );
        } elseif ( ! $after['manage_stock'] && array_key_exists( 'stock_quantity', $input ) ) { throw new InvalidArgumentException( 'ابتدا مدیریت موجودی را روشن کنید.' ); }
        if ( $p->is_type( 'variation' ) && $before['parent_stock'] && ! $after['manage_stock'] &&
            ( array_key_exists( 'stock_quantity', $input ) || array_key_exists( 'stock_status', $input ) ) ) {
            throw new InvalidArgumentException( 'موجودی این ورییشن مشترک است؛ آن را روی ردیف والد ویرایش کنید.' );
        }
        if ( ( $after['regular_price'] !== $before['regular_price'] || $after['sale_price'] !== $before['sale_price'] ) &&
            '' !== $after['sale_price'] && ( '' === $after['regular_price'] || (float) $after['sale_price'] >= (float) $after['regular_price'] ) ) {
            throw new InvalidArgumentException( 'قیمت حراج باید کمتر از قیمت اصلی باشد؛ هر دو ستون را تغییر دهید یا حراج را خالی کنید.' );
        }
        if ( $p->is_type( 'variable' ) && ! $after['manage_stock'] && $after['stock_status'] !== $before['stock_status'] ) {
            throw new InvalidArgumentException( 'وضعیت والد از ورییشن‌ها محاسبه می‌شود.' );
        }
        return $after;
    }

    private static function job( $id ) {
        global $wpdb;
        $job = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'jobs' ) . ' WHERE id=%s AND user_id=%d', $id, get_current_user_id() ), ARRAY_A );
        if ( ! $job ) { throw new RuntimeException( 'عملیات پیدا نشد.' ); }
        return $job;
    }
    public static function lock() {
        global $wpdb;
        $name = 'nil_bulk_' . md5( DB_NAME . $wpdb->prefix );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) ) { throw new RuntimeException( 'یک عملیات دیگر در حال اجراست؛ چند ثانیه بعد دوباره امتحان کنید.' ); }
        return $name;
    }
    public static function unlock( $name ) { global $wpdb; $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); }
    public static function preview( $r ) {
        global $wpdb;
        $body = $r->get_json_params();
        if ( empty( $body['rows'] ) || ! is_array( $body['rows'] ) || count( $body['rows'] ) > 50 ) { throw new InvalidArgumentException( 'هر بسته باید ۱ تا ۵۰ ردیف داشته باشد.' ); }
        $lock = self::lock();
        try {
            if ( empty( $body['job_id'] ) ) {
                $id = wp_generate_uuid4();
                self::db_ok( $wpdb->insert( self::table( 'jobs' ), array( 'id' => $id, 'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ), 'state' => 'draft', 'config' => wp_json_encode( self::config() ) ) ) );
            } else { $id = sanitize_text_field( $body['job_id'] ); }
            $job = self::job( $id );
            if ( 'draft' !== $job['state'] || json_decode( $job['config'], true ) !== self::config() ) { throw new RuntimeException( 'پیش‌نمایش بسته شده یا واحد پول تغییر کرده است؛ پیش‌نمایش تازه بسازید.' ); }
            $errors = array(); $added = 0; $unchanged = 0;
            self::prime( array_column( array_filter( $body['rows'], 'is_array' ), 'id' ) );
            foreach ( $body['rows'] as $row ) {
                try {
                    if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['version'] ) ) { throw new InvalidArgumentException( 'ردیف نامعتبر است.' ); }
                    $p = self::editable( wc_get_product( absint( $row['id'] ) ) ); $before = self::snapshot( $p );
                    if ( ! is_string( $row['version'] ) || ! hash_equals( self::fingerprint( $before ), $row['version'] ) ) { throw new RuntimeException( 'محصول بعد از بارگذاری تغییر کرده است؛ جدول را تازه کنید.' ); }
                    $after = self::proposal( $p, $before, isset( $row['changes'] ) ? $row['changes'] : array(), isset( $body['rule'] ) ? $body['rule'] : null );
                    $fields = array();
                    foreach ( $before as $key => $value ) { if ( ! self::same( $value, $after[$key] ) ) { $fields[] = $key; } }
                    if ( ! $fields ) { $unchanged++; continue; }
                    self::db_ok( $wpdb->insert( self::table( 'items' ), array( 'job_id' => $id, 'product_id' => $p->get_id(), 'label' => $p->get_name(), 'before_json' => wp_json_encode( $before ), 'after_json' => wp_json_encode( $after ), 'fields_json' => wp_json_encode( $fields ), 'state' => 'pending', 'error' => '' ) ) );
                    $added++;
                } catch ( Throwable $e ) { $errors[] = array( 'id' => isset( $row['id'] ) ? absint( $row['id'] ) : 0, 'error' => $e->getMessage() ); }
            }
            return array( 'job_id' => $id, 'added' => $added, 'unchanged' => $unchanged, 'errors' => $errors );
        } finally { self::unlock( $lock ); }
    }
    private static function db_ok( $result ) { if ( false === $result ) { throw new RuntimeException( 'ثبت اطلاعات در پایگاه داده انجام نشد.' ); } return $result; }
    public static function seal( $r ) {
        global $wpdb; $lock = self::lock();
        try {
            $job = self::job( $r['id'] );
            $count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'items' ) . ' WHERE job_id=%s', $job['id'] ) );
            if ( 'draft' !== $job['state'] || $count < 1 || $count !== (int) $r->get_param( 'count' ) ) { throw new RuntimeException( 'پیش‌نمایش ناقص است یا هیچ تغییری ندارد.' ); }
            self::db_ok( $wpdb->update( self::table( 'jobs' ), array( 'state' => 'ready' ), array( 'id' => $job['id'] ) ) );
            return array( 'count' => $count );
        } finally { self::unlock( $lock ); }
    }
    public static function jobs() {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 'SELECT id,created_at,state FROM ' . self::table( 'jobs' ) . ' WHERE user_id=%d AND state<>%s ORDER BY created_at DESC,id DESC LIMIT 20', get_current_user_id(), 'draft' ), ARRAY_A );
    }
    public static function job_detail( $r ) {
        global $wpdb; $job = self::job( $r['id'] ); $offset = max( 0, ( absint( $r->get_param( 'page' ) ?: 1 ) - 1 ) * 50 );
        $items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'items' ) . ' WHERE job_id=%s ORDER BY id LIMIT 50 OFFSET %d', $job['id'], $offset ), ARRAY_A );
        foreach ( $items as &$item ) {
            foreach ( array( 'before','after','fields' ) as $field ) { $item[$field] = json_decode( $item[$field . '_json'], true ); unset( $item[$field . '_json'] ); }
        } unset( $item );
        $counts = $wpdb->get_results( $wpdb->prepare( 'SELECT state,COUNT(*) AS n FROM ' . self::table( 'items' ) . ' WHERE job_id=%s GROUP BY state', $job['id'] ), ARRAY_A );
        return array( 'job' => $job, 'items' => $items, 'counts' => $counts, 'market' => NIL_Market::job_info( $job['id'] ) );
    }
    public static function require_transactions() {
        global $wpdb;
        foreach ( array( $wpdb->posts, $wpdb->postmeta, $wpdb->term_relationships, $wpdb->term_taxonomy, $wpdb->terms, self::table( 'items' ), self::table( 'jobs' ) ) as $table ) {
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
            if ( 'innodb' !== strtolower( (string) $engine ) ) { throw new RuntimeException( 'برای ذخیره امن، جدول‌های محصولات و گزارش باید InnoDB باشند.' ); }
        }
    }
    public static function clear_cache( $id ) { clean_post_cache( $id ); wp_cache_delete( $id, 'post_meta' ); wc_delete_product_transients( $id ); }
    private static function apply_item( $item, $undo ) {
        global $wpdb;
        $id = (int) $item['product_id']; $before = json_decode( $item['before_json'], true ); $after = json_decode( $item['after_json'], true );
        $fields = json_decode( $item['fields_json'], true );
        $expected = $undo ? $after : $before; $desired = $undo ? $before : $after;
        self::db_ok( $wpdb->query( 'START TRANSACTION' ) );
        try {
            $ids = array_filter( array( $id, (int) $before['parent_id'] ) ); sort( $ids );
            $ids_sql = implode( ',', array_map( 'absint', $ids ) );
            self::db_ok( $wpdb->query( "SELECT ID FROM $wpdb->posts WHERE ID IN ($ids_sql) ORDER BY ID FOR UPDATE" ) );
            self::db_ok( $wpdb->query( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id IN ($ids_sql) ORDER BY post_id,meta_id FOR UPDATE" ) );
            foreach ( $ids as $cache_id ) { self::clear_cache( $cache_id ); }
            $p = self::editable( wc_get_product( $id ) ); $current = self::snapshot( $p );
            // Undo compares only touched fields plus their semantic dependencies.
            $compare = $undo ? array_unique( array_merge( $fields, array( 'type','parent_id','parent_stock','manage_stock','backorders','sale_from','sale_to' ) ) ) : array_keys( $expected );
            $cost_fields = array( 'cost_price','cost_defined','cost_additive' );
            if ( array_intersect( $cost_fields, $fields ) ) {
                if ( ( $expected['cost_backend'] ?? 'legacy' ) !== $current['cost_backend'] ) { throw new RuntimeException( 'منبع قیمت خرید تغییر کرده است.' ); }
                if ( isset( $expected['cost_backend'] ) && 'native' === $expected['cost_backend'] ) { $compare = array_unique( array_merge( $compare, array( 'cost_backend','cost_defined','cost_additive','cost_parent' ) ) ); }
            }
            foreach ( $compare as $field ) {
                if ( ! self::same( $current[$field], $expected[$field] ) ) { throw new RuntimeException( 'تداخل با تغییر جدید (' . $field . ')؛ این ردیف رد شد.' ); }
            }
            // Keep the log aligned with actual WooCommerce values after save.
            if ( in_array( 'regular_price', $fields, true ) ) { $p->set_regular_price( $desired['regular_price'] ); }
            if ( in_array( 'sale_price', $fields, true ) ) { $p->set_sale_price( $desired['sale_price'] ); }
            if ( in_array( 'sale_from', $fields, true ) ) { $p->set_date_on_sale_from( $desired['sale_from'] ); }
            if ( in_array( 'sale_to', $fields, true ) ) { $p->set_date_on_sale_to( $desired['sale_to'] ); }
            $regular = $p->get_regular_price( 'edit' ); $sale = $p->get_sale_price( 'edit' );
            if ( ( in_array( 'regular_price', $fields, true ) || in_array( 'sale_price', $fields, true ) ) && '' !== $sale && ( '' === $regular || (float) $sale >= (float) $regular ) ) { throw new RuntimeException( 'قیمت حراج با قیمت اصلی فعلی سازگار نیست.' ); }
            if ( in_array( 'manage_stock', $fields, true ) ) { $p->set_manage_stock( $desired['manage_stock'] ); }
            if ( in_array( 'stock_quantity', $fields, true ) ) { $p->set_stock_quantity( $desired['stock_quantity'] ); }
            if ( in_array( 'stock_status', $fields, true ) ) { $p->set_stock_status( $desired['stock_status'] ); }
            foreach ( array( 'name','sku','description','short_description','category_ids' ) as $field ) {
                if ( in_array( $field, $fields, true ) ) { $setter = 'set_' . $field; $p->$setter( $desired[$field] ); }
            }
            if ( array_intersect( $cost_fields, $fields ) ) { NIL_Cost::restore( $p, $desired ); }
            $p->save();
            if ( in_array( 'brand_ids', $fields, true ) && NIL_Suite::brand_taxonomy() ) { $result = wp_set_object_terms( $id, $desired['brand_ids'], NIL_Suite::brand_taxonomy() ); if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); } }
            if ( $p->get_parent_id() ) { WC_Product_Variable::sync( $p->get_parent_id() ); }
            if ( $p->is_type( 'variable' ) ) { WC_Product_Variable::sync( $id ); }
            self::clear_cache( $id );
            $actual = self::snapshot( wc_get_product( $id ) );
            // Capture side effects (for example stock status when manage_stock changes).
            if ( ! $undo ) {
                foreach ( array( 'regular_price','sale_price','manage_stock','stock_quantity','stock_status' ) as $field ) {
                    if ( ! self::same( $current[$field], $actual[$field] ) && ! in_array( $field, $fields, true ) ) { $fields[] = $field; }
                }
            }
            $data = array( 'state' => $undo ? 'undone' : 'applied', 'error' => '' );
            if ( ! $undo ) { $data['after_json'] = wp_json_encode( $actual ); $data['fields_json'] = wp_json_encode( $fields ); }
            self::db_ok( $wpdb->update( self::table( 'items' ), $data, array( 'id' => $item['id'] ) ) );
            self::db_ok( $wpdb->query( 'COMMIT' ) );
            foreach ( $ids as $cache_id ) { self::clear_cache( $cache_id ); }
        } catch ( Throwable $e ) {
            $wpdb->query( 'ROLLBACK' );
            foreach ( $ids as $cache_id ) { self::clear_cache( $cache_id ); }
            self::db_ok( $wpdb->update( self::table( 'items' ), array( 'state' => $undo ? 'undo_failed' : 'skipped', 'error' => $e->getMessage() ), array( 'id' => $item['id'] ) ) );
        }
    }
    public static function run( $r ) {
        global $wpdb; $lock = self::lock();
        try {
            $job = self::job( $r['id'] );
            if ( ! in_array( $r->get_param( 'mode' ), array( 'apply', 'undo' ), true ) ) { throw new InvalidArgumentException( 'حالت اجرای عملیات نامعتبر است.' ); }
            $undo = 'undo' === $r->get_param( 'mode' );
            if ( ! $undo ) { NIL_License::assert_write(); }
            do_action( 'nil_bulk_before_job_run', $job, $undo );
            if ( json_decode( $job['config'], true ) !== self::config() ) { throw new RuntimeException( 'واحد پول یا دقت قیمت تغییر کرده است؛ عملیات متوقف شد.' ); }
            if ( ! in_array( $job['state'], $undo ? array( 'done','undoing','undone' ) : array( 'ready','running','done' ), true ) ) { throw new RuntimeException( 'وضعیت عملیات اجازه اجرا نمی‌دهد.' ); }
            if ( ( ! $undo && 'done' === $job['state'] ) || ( $undo && 'undone' === $job['state'] ) ) { return array( 'done' => true ); }
            self::require_transactions();
            self::db_ok( $wpdb->update( self::table( 'jobs' ), array( 'state' => $undo ? 'undoing' : 'running' ), array( 'id' => $job['id'] ) ) );
            $items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table( 'items' ) . ' WHERE job_id=%s AND state=%s ORDER BY id ' . ( $undo ? 'DESC' : 'ASC' ) . ' LIMIT 10', $job['id'], $undo ? 'applied' : 'pending' ), ARRAY_A );
            foreach ( $items as $item ) { self::apply_item( $item, $undo ); }
            $remaining = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table( 'items' ) . ' WHERE job_id=%s AND state=%s', $job['id'], $undo ? 'applied' : 'pending' ) );
            if ( ! $remaining ) { self::db_ok( $wpdb->update( self::table( 'jobs' ), array( 'state' => $undo ? 'undone' : 'done' ), array( 'id' => $job['id'] ) ) ); }
            return array( 'done' => 0 === $remaining, 'remaining' => $remaining, 'processed' => count( $items ) );
        } finally { self::unlock( $lock ); }
    }
}
register_activation_hook( __FILE__, array( 'NIL_Bulk_Manager', 'install' ) );
add_action( 'plugins_loaded', array( 'NIL_Bulk_Manager', 'boot' ) );

register_deactivation_hook( __FILE__, function() { wp_clear_scheduled_hook( 'nil_manager_guard_check' ); } );
