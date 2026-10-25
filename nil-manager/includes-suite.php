<?php
/** Product operations shared by the administration, imports and scheduler. */
defined( 'ABSPATH' ) || exit;
// Optional tools are parsed only when an endpoint or update hook needs them.
spl_autoload_register( function( $class ) {
    if ( 0 !== strpos( $class, 'NIL_' ) ) { return; }
    static $map = array( 'NIL_Payments' => 'payments', 'NIL_Dates' => 'dates', 'NIL_Sheet' => 'sheet', 'NIL_Media' => 'media', 'NIL_Insights' => 'insights', 'NIL_Updater' => 'updater', 'NIL_Maintenance' => 'maintenance', 'NIL_Variations' => 'variations', 'NIL_Presets' => 'presets', 'NIL_Market' => 'market', 'NIL_Selection' => 'selection' );
    if ( isset( $map[$class] ) ) { require_once __DIR__ . '/includes-' . $map[$class] . '.php'; }
} );
final class NIL_Suite {
    public static function boot() {
        add_action( 'admin_menu', function() { add_submenu_page( 'woocommerce', 'نیل منجر · تخفیف روش پرداخت', 'تخفیف روش پرداخت', 'manage_options', 'nil-payment-discounts', array( 'NIL_Payments', 'page' ) ); } );
        add_action( 'admin_enqueue_scripts', function( $hook ) { if ( 'woocommerce_page_nil-payment-discounts' === $hook ) { wp_enqueue_style( 'nil-bulk', plugins_url( 'assets/admin.min.css', __FILE__ ), array(), NIL_Bulk_Manager::VERSION ); } } );
        add_action( 'wp_enqueue_scripts', function() { if ( is_checkout() ) { NIL_Payments::assets(); } } );
        add_action( 'woocommerce_before_calculate_totals', array( 'NIL_Payments', 'sync' ), 1000 );
        add_filter( 'woocommerce_get_shop_coupon_data', array( 'NIL_Payments', 'coupon' ), 10, 2 );
        add_filter( 'woocommerce_coupon_is_valid', array( 'NIL_Payments', 'valid' ), 10, 2 );
        add_filter( 'woocommerce_coupon_sort', array( 'NIL_Payments', 'sort' ), 1000, 2 );
        add_filter( 'woocommerce_cart_totals_coupon_label', array( 'NIL_Payments', 'label' ), 10, 2 );
        add_filter( 'woocommerce_cart_totals_coupon_html', array( 'NIL_Payments', 'coupon_html' ), 10, 3 );
        add_filter( 'woocommerce_available_payment_gateways', array( 'NIL_Payments', 'pay_gateways' ), 1000 );
        add_action( 'woocommerce_checkout_create_order', array( 'NIL_Payments', 'capture' ), 1000, 2 );
        add_filter( 'woocommerce_checkout_posted_data', array( 'NIL_Payments', 'posted_data' ), 1000 );
        add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( 'NIL_Payments', 'block_request' ), 10, 2 );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( 'NIL_Payments', 'capture' ), 1000, 2 );
        add_action( 'rest_api_init', function() { if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) { woocommerce_store_api_register_update_callback( array( 'namespace' => 'nil-manager-payment', 'callback' => array( 'NIL_Payments', 'block_update' ) ) ); } } );
        add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
        add_filter( 'rest_pre_dispatch', function( $reply, $server, $r ) {
            if ( 0 === strpos( $r->get_route(), '/nil-bulk/v1/' ) ) {
                $limit = '/nil-bulk/v1/sheet/read' === $r->get_route() ? 12 : ( '/nil-bulk/v1/sheet/export' === $r->get_route() ? 4 : 1 );
                if ( strlen( $r->get_body() ) > $limit * 1024 * 1024 ) { return new WP_Error( 'nil_body_size', 'درخواست بزرگ است؛ فایل یا محدوده را کوچک کنید.', array( 'status' => 413 ) ); }
            }
            return $reply;
        }, 10, 3 );
        add_action( 'nil_bulk_market', array( 'NIL_Market', 'tick' ), 10, 1 );
        add_action( 'nil_bulk_before_job_run', array( 'NIL_Market', 'guard' ), 10, 2 );
        add_action( 'nil_bulk_schedule', array( __CLASS__, 'tick' ), 10, 1 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( 'NIL_Insights', 'checkout_cost' ), 10, 4 );
        add_action( 'woocommerce_new_order_item', array( 'NIL_Insights', 'new_item_cost' ), 10, 3 );
        add_filter( 'all_plugins', function( $plugins ) {
            $key = plugin_basename( NIL_Bulk_Manager::FILE );
            if ( isset( $plugins[$key] ) ) { $plugins[$key]['Name'] = self::title(); }
            return $plugins;
        } );
    }
    public static function branding() {
        $b = wp_parse_args( get_option( 'nil_bulk_branding', array() ), array( 'title' => 'نیل منجر', 'accent' => '#6941c6', 'logo' => '' ) );
        // Resolve the former stock title without rewriting saved customer branding.
        if ( '' === trim( (string) $b['title'] ) || 'مدیریت محصولات نیل' === $b['title'] ) { $b['title'] = 'نیل منجر'; }
        $b['accent'] = self::accent( $b['accent'] ); return $b;
    }
    private static function accent( $color ) {
        $color = sanitize_hex_color( $color ) ?: '#6941c6';
        return 4 === strlen( $color ) ? '#' . $color[1] . $color[1] . $color[2] . $color[2] . $color[3] . $color[3] : $color;
    }
    public static function title() { return self::branding()['title']; }
    public static function brand_taxonomy() {
        foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand' ) as $tax ) { if ( taxonomy_exists( $tax ) ) { return $tax; } }
        return '';
    }
    public static function brands() {
        $tax = self::brand_taxonomy(); if ( ! $tax ) { return array(); }
        $terms = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
        return is_wp_error( $terms ) ? array() : array_map( function( $t ) { return array( 'id' => $t->term_id, 'name' => $t->name ); }, $terms );
    }
    public static function product_brands( $p ) {
        $tax = self::brand_taxonomy(); if ( ! $tax || $p->is_type( 'variation' ) ) { return array(); }
        $terms = get_the_terms( $p->get_id(), $tax );
        $ids = ! $terms || is_wp_error( $terms ) ? array() : array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ); sort( $ids ); return $ids;
    }
    public static function validate_field( $p, $key, $value ) {
        if ( in_array( $key, array( 'category_ids','brand_ids' ), true ) ) {
            if ( $p->is_type( 'variation' ) ) { throw new InvalidArgumentException( 'دسته و برند روی والد ویرایش می‌شود.' ); }
            if ( ! is_array( $value ) ) { throw new InvalidArgumentException( 'شناسه‌های دسته و برند باید فهرست باشند.' ); }
            $tax = 'category_ids' === $key ? 'product_cat' : self::brand_taxonomy();
            if ( ! $tax && $value ) { throw new InvalidArgumentException( 'تاکسونومی برند فعال نیست.' ); }
            if ( $tax && ! current_user_can( get_taxonomy( $tax )->cap->assign_terms ) ) { throw new RuntimeException( 'اجازه تغییر دسته یا برند را ندارید.' ); }
            $ids = array_values( array_unique( array_map( 'absint', $value ) ) ); sort( $ids );
            foreach ( $ids as $id ) { if ( ! term_exists( $id, $tax ) ) { throw new InvalidArgumentException( 'دسته یا برند پیدا نشد.' ); } }
            return $ids;
        }
        if ( ! is_scalar( $value ) || is_bool( $value ) ) { throw new InvalidArgumentException( 'مقدار فیلد نامعتبر است.' ); }
        if ( 'cost_price' === $key ) {
            if ( '' === $value ) { return ''; }
            $n = NIL_Bulk_Price::number( $value ); if ( $n < 0 ) { throw new InvalidArgumentException( 'قیمت خرید منفی مجاز نیست.' ); }
            return number_format( $n, wc_get_price_decimals(), '.', '' );
        }
        if ( in_array( $key, array( 'description','short_description' ), true ) ) { return wp_kses_post( $value ); }
        $text = sanitize_text_field( $value );
        if ( 'name' === $key && '' === trim( $text ) ) { throw new InvalidArgumentException( 'نام محصول الزامی است.' ); }
        if ( 'sku' === $key && '' !== $text && ! wc_product_has_unique_sku( $p->get_id(), $text ) ) { throw new InvalidArgumentException( 'SKU تکراری است.' ); }
        return $text;
    }
    public static function filter_args( $args, $r ) {
        $type = $r->get_param( 'type' );
        if ( in_array( $type, array( 'simple','variable' ), true ) ) { $args['tax_query'][0]['terms'] = array( $type ); }
        if ( $r->get_param( 'brand' ) && self::brand_taxonomy() ) { $args['tax_query'][] = array( 'taxonomy' => self::brand_taxonomy(), 'field' => 'term_id', 'terms' => absint( $r->get_param( 'brand' ) ) ); }
        $status = $r->get_param( 'status' );
        if ( in_array( $status, array( 'publish','private','draft','pending','future' ), true ) ) { $args['post_status'] = $status; }
        $stock = $r->get_param( 'stock' );
        if ( in_array( $stock, array( 'instock','outofstock','onbackorder' ), true ) ) { $args['meta_query'][] = array( 'key' => '_stock_status', 'value' => $stock ); }
        foreach ( array( 'min_price' => '>=', 'max_price' => '<=' ) as $key => $operator ) {
            $value = $r->get_param( $key ); if ( null !== $value && '' !== $value ) {
                $value = NIL_Bulk_Price::number( $value ); if ( $value < 0 ) { throw new InvalidArgumentException( 'فیلتر قیمت منفی است.' ); }
                $args['meta_query'][] = array( 'key' => '_price', 'value' => $value, 'type' => 'DECIMAL(20,4)', 'compare' => $operator );
            }
        }
        $sort = $r->get_param( 'sort' );
        if ( in_array( $sort, array( 'modified','title','ID' ), true ) ) { $args['orderby'] = array( $sort => 'modified' === $sort ? 'DESC' : 'ASC', 'ID' => 'ASC' ); }
        return $args;
    }
    public static function routes() {
        $routes = array(
            'pricing/selection' => array( 'POST', 'NIL_Selection', 'rows' ),
            'market/rates' => array( 'GET', 'NIL_Market', 'rates' ),
            'market/rules' => array( 'GET,POST', 'NIL_Market', 'endpoint' ),
            'market/capture' => array( 'POST', 'NIL_Market', 'capture' ),
            'market/preview' => array( 'POST', 'NIL_Market', 'preview' ),
            'variation-parent' => array( 'GET', 'NIL_Variations', 'parent_info' ),
            'variations/preview' => array( 'POST', 'NIL_Variations', 'preview' ),
            'variations/apply' => array( 'POST', 'NIL_Variations', 'apply' ),
            'presets' => array( 'GET,POST', 'NIL_Presets', 'endpoint' ),
            'cogs/migration' => array( 'POST', 'NIL_Cost', 'migration' ),
            'create' => array( 'POST', __CLASS__, 'create' ),
            'branding' => array( 'POST', __CLASS__, 'save_branding' ),
            'schedules' => array( 'GET,POST', __CLASS__, 'schedules' ),
            'sheet/export' => array( 'POST', 'NIL_Sheet', 'export' ),
            'sheet/read' => array( 'POST', 'NIL_Sheet', 'read_request' ),
            'sheet/prepare' => array( 'POST', 'NIL_Sheet', 'prepare' ),
            'media' => array( 'GET', 'NIL_Media', 'listing' ),
            'media/scan' => array( 'POST', 'NIL_Media', 'scan' ),
            'maintenance' => array( 'POST', 'NIL_Maintenance', 'clean' ),
            'media/preview' => array( 'POST', 'NIL_Media', 'preview' ),
            'media/apply' => array( 'POST', 'NIL_Media', 'apply' ),
            'insights' => array( 'GET', 'NIL_Insights', 'report' ),
            'updater' => array( 'GET', 'NIL_Updater', 'status' ),
            'updater/check' => array( 'POST', 'NIL_Updater', 'check' ),
        );
        foreach ( $routes as $path => $def ) {
            register_rest_route( 'nil-bulk/v1', '/' . $path, array( 'methods' => $def[0], 'permission_callback' => array( 'NIL_Bulk_Manager', 'permission' ), 'callback' => function( $r ) use ( $def ) {
                try { return call_user_func( array( $def[1], $def[2] ), $r ); }
                catch ( Throwable $e ) { return new WP_Error( 'nil_suite', $e->getMessage(), array( 'status' => 400 ) ); }
            } ) );
        }
    }
    public static function save_branding( $r ) {
        if ( ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'تنظیم برندینگ به مدیر سایت نیاز دارد.' ); }
        $b = $r->get_json_params(); $title = sanitize_text_field( $b['title'] ?? '' ); $accent = sanitize_hex_color( $b['accent'] ?? '' );
        if ( ! $title || ! $accent ) { throw new InvalidArgumentException( 'عنوان و رنگ معتبر وارد کنید.' ); }
        $raw_logo = trim( (string) ( $b['logo'] ?? '' ) ); $logo = esc_url_raw( $raw_logo, array( 'https' ) );
        if ( $raw_logo && ( ! $logo || 'https' !== wp_parse_url( $logo, PHP_URL_SCHEME ) || ! wp_parse_url( $logo, PHP_URL_HOST ) ) ) { throw new InvalidArgumentException( 'لوگو باید نشانی HTTPS مستقیم تصویر باشد.' ); }
        update_option( 'nil_bulk_branding', array( 'title' => $title, 'accent' => self::accent( $accent ), 'logo' => $logo ), false );
        return self::branding();
    }
    public static function create( $r ) {
        if ( ! current_user_can( 'publish_products' ) ) { throw new RuntimeException( 'اجازه ایجاد محصول ندارید.' ); }
        $b = $r->get_json_params(); $token = sanitize_key( $b['token'] ?? '' );
        if ( ! preg_match( '/^[a-f0-9-]{32,36}$/', $token ) ) { throw new InvalidArgumentException( 'کلید ایجاد نامعتبر است.' ); }
        $lock = NIL_Bulk_Manager::lock(); $created = array();
        try {
            // Store one receipt on its product, never an unbounded collection of options.
            $matches = get_posts( array( 'post_type' => 'product', 'post_status' => array( 'publish','private','draft','pending','future' ), 'meta_key' => '_nil_creation_token', 'meta_value' => $token, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => true ) );
            if ( $matches ) { $old = wc_get_product( $matches[0] ); NIL_Bulk_Manager::editable( $old ); return array( 'id' => $old->get_id(), 'created' => array_merge( array( $old->get_id() ), $old->is_type( 'variable' ) ? $old->get_children() : array() ) ); }
            $type = $b['type'] ?? 'simple'; if ( ! in_array( $type, array( 'simple','variable' ), true ) ) { throw new InvalidArgumentException( 'نوع محصول معتبر نیست.' ); }
            $p = 'variable' === $type ? new WC_Product_Variable() : new WC_Product_Simple();
            $p->set_name( self::validate_field( $p, 'name', $b['name'] ?? '' ) );
            $status = $b['status'] ?? 'draft'; if ( ! in_array( $status, array( 'draft','publish' ), true ) ) { throw new InvalidArgumentException( 'وضعیت معتبر نیست.' ); } $p->set_status( $status );
            foreach ( array( 'sku','description','short_description','category_ids' ) as $key ) { if ( isset( $b[$key] ) ) { $setter = 'set_' . $key; $p->$setter( self::validate_field( $p, $key, $b[$key] ) ); } }
            if ( isset( $b['cost_price'] ) ) { NIL_Cost::set( $p, self::validate_field( $p, 'cost_price', $b['cost_price'] ) ); }
            $regular = self::validate_field( $p, 'cost_price', $b['regular_price'] ?? '' );
            $sale = self::validate_field( $p, 'cost_price', $b['sale_price'] ?? '' );
            if ( '' !== $sale && ( '' === $regular || (float) $sale >= (float) $regular ) ) { throw new InvalidArgumentException( 'حراج باید کمتر از قیمت اصلی باشد.' ); }
            $quantity = $b['stock_quantity'] ?? null;
            if ( null !== $quantity && '' !== $quantity ) { $quantity = NIL_Bulk_Price::number( $quantity ); if ( $quantity < 0 || floor( $quantity ) !== $quantity || $quantity > 100000000 ) { throw new InvalidArgumentException( 'تعداد نامعتبر است.' ); } }
            else { $quantity = null; }
            $combinations = array( array() ); $attributes = array();
            if ( 'variable' === $type ) {
                $input = $b['attributes'] ?? array(); if ( ! is_array( $input ) || ! $input || count( $input ) > 5 ) { throw new InvalidArgumentException( '۱ تا ۵ ویژگی وارد کنید.' ); }
                $names = array();
                foreach ( $input as $name => $options ) {
                    if ( ! is_array( $options ) || count( $options ) > 100 ) { throw new InvalidArgumentException( 'حداکثر ۱۰۰ گزینه برای هر ویژگی.' ); }
                    $name = sanitize_text_field( $name ); $key = sanitize_title( $name );
                    if ( ! $key || isset( $names[$key] ) || ! is_array( $options ) ) { throw new InvalidArgumentException( 'ویژگی نامعتبر یا تکراری است.' ); } $names[$key] = true;
                    $options = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $options ), 'strlen' ) ) );
                    if ( ! $options ) { throw new InvalidArgumentException( 'گزینه‌های ویژگی خالی است.' ); }
                    $a = new WC_Product_Attribute(); $a->set_name( $name ); $a->set_options( $options ); $a->set_visible( true ); $a->set_variation( true ); $attributes[] = $a;
                    $next = array(); foreach ( $combinations as $c ) { foreach ( $options as $o ) { $next[] = $c + array( $key => $o ); if ( count( $next ) > 100 ) { throw new InvalidArgumentException( 'حداکثر ۱۰۰ ترکیب در هر محصول.' ); } } } $combinations = $next;
                }
                $p->set_attributes( $attributes );
            } else { $p->set_regular_price( $regular ); $p->set_sale_price( $sale ); if ( null !== $quantity ) { $p->set_manage_stock( true ); $p->set_stock_quantity( $quantity ); } }
            // Save draft first. Incomplete creations are removed on any exception.
            $brand_ids = isset( $b['brand_ids'] ) ? self::validate_field( $p, 'brand_ids', $b['brand_ids'] ) : array();
            $p->update_meta_data( '_nil_creation_token', $token );
            $p->set_status( 'draft' ); $p->save(); $created[] = $p->get_id();
            if ( $brand_ids ) { $term_result = wp_set_object_terms( $p->get_id(), $brand_ids, self::brand_taxonomy() ); if ( is_wp_error( $term_result ) ) { throw new RuntimeException( $term_result->get_error_message() ); } }
            if ( 'variable' === $type ) {
                foreach ( $combinations as $i => $attrs ) {
                    $v = new WC_Product_Variation(); $v->set_parent_id( $p->get_id() ); $v->set_attributes( $attrs ); $v->set_status( 'publish' );
                    $v->set_regular_price( $regular ); $v->set_sale_price( $sale ); if ( null !== $quantity ) { $v->set_manage_stock( true ); $v->set_stock_quantity( $quantity ); }
                    if ( '' !== $p->get_sku() ) { $sku = $p->get_sku() . '-' . ( $i + 1 ); if ( wc_get_product_id_by_sku( $sku ) ) { throw new RuntimeException( 'SKU ورییشن تکراری است: ' . $sku ); } $v->set_sku( $sku ); }
                    if ( isset( $b['cost_price'] ) ) { NIL_Cost::set( $v, self::validate_field( $v, 'cost_price', $b['cost_price'] ) ); }
                    $v->save(); $created[] = $v->get_id();
                }
                WC_Product_Variable::sync( $p->get_id() );
            }
            $p = wc_get_product( $p->get_id() ); $p->set_status( $status ); $p->save();
            $result = array( 'id' => $p->get_id(), 'created' => $created );
            return $result;
        } catch ( Throwable $e ) {
            foreach ( array_reverse( $created ) as $id ) { $p = wc_get_product( $id ); if ( $p ) { $p->delete( true ); } }
            throw $e;
        } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
    public static function schedules( $r ) {
        $list = get_option( 'nil_bulk_schedules', array() );
        if ( 'GET' === $r->get_method() ) { return array_values( $list ); }
        if ( ! current_user_can( 'manage_options' ) ) { throw new RuntimeException( 'زمان‌بندی به مدیر سایت نیاز دارد.' ); }
        $b = $r->get_json_params(); $id = sanitize_key( $b['id'] ?? '' );
        if ( ! empty( $b['delete'] ) ) { unset( $list[$id] ); wp_clear_scheduled_hook( 'nil_bulk_schedule', array( $id ) ); update_option( 'nil_bulk_schedules', $list, false ); return array_values( $list ); }
        if ( count( $list ) >= 20 ) { throw new RuntimeException( 'حداکثر ۲۰ قانون.' ); }
        $rule = $b['rule'] ?? array();
        if ( ! is_array( $rule ) || strlen( wp_json_encode( $rule ) ) > 1024 ) { throw new InvalidArgumentException( 'قانون زمان‌بندی بزرگ یا نامعتبر است.' ); }
        $rule = array_intersect_key( $rule, array_flip( array( 'source','target','operation','value','rounding','step','ending' ) ) );
        $filters = array_intersect_key( (array) ( $b['filters'] ?? array() ), array_flip( array( 'category','brand','search','type','status','stock','min_price','max_price' ) ) );
        foreach ( $filters as &$value ) { if ( ! is_scalar( $value ) || strlen( (string) $value ) > 200 ) { throw new InvalidArgumentException( 'فیلتر زمان‌بندی نامعتبر است.' ); } $value = sanitize_text_field( $value ); } unset( $value );
        $name = sanitize_text_field( $b['name'] ?? 'قانون قیمت' ); if ( ! $name || strlen( $name ) > 150 ) { throw new InvalidArgumentException( 'نام کوتاه وارد کنید.' ); }
        if ( ( $rule['source'] ?? '' ) !== 'cost' || ! in_array( $rule['operation'] ?? '', array( 'percent','fixed','set','round' ), true ) ) { throw new InvalidArgumentException( 'قانون خودکار باید بر پایه قیمت خرید باشد تا هر بار افزایش مرکب نشود.' ); }
        NIL_Bulk_Price::calculate( '100000', $rule, wc_get_price_decimals() );
        if ( ! in_array( $rule['target'] ?? '', array( 'regular_price','sale_price' ), true ) ) { throw new InvalidArgumentException( 'ستون قیمت نامعتبر است.' ); }
        $time = NIL_Dates::parse( $b['start'] ?? '', $b['calendar'] ?? 'gregorian', true ); if ( $time <= time() ) { throw new InvalidArgumentException( 'زمان شروع باید در آینده و بر اساس منطقهٔ زمانی وردپرس باشد.' ); }
        $frequency = $b['frequency'] ?? 'once'; if ( ! in_array( $frequency, array( 'once','daily','weekly' ), true ) ) { throw new InvalidArgumentException( 'تناوب نامعتبر است.' ); }
        $id = wp_generate_uuid4();
        $list[$id] = array( 'id' => $id, 'name' => $name, 'owner' => get_current_user_id(), 'rule' => $rule, 'filters' => $filters, 'frequency' => $frequency, 'state' => 'scheduled', 'job' => '', 'page' => 1, 'added' => 0, 'next' => $time, 'error' => '', 'currency' => get_woocommerce_currency(), 'decimals' => wc_get_price_decimals() );
        update_option( 'nil_bulk_schedules', $list, false ); wp_schedule_single_event( $time, 'nil_bulk_schedule', array( $id ) ); return array_values( $list );
    }
    public static function tick( $id ) {
        // One page / ten writes per cron request. A crashed request fails closed.
        $list = get_option( 'nil_bulk_schedules', array() ); if ( empty( $list[$id] ) ) { return; }
        if ( get_transient( 'nil_cron_' . $id ) ) { return; } set_transient( 'nil_cron_' . $id, 1, 300 );
        $s = $list[$id]; $previous_user = get_current_user_id(); wp_set_current_user( $s['owner'] );
        try {
            if ( ! NIL_Bulk_Manager::permission() || $s['currency'] !== get_woocommerce_currency() || (int) $s['decimals'] !== wc_get_price_decimals() ) { throw new RuntimeException( 'مجوز یا واحد پول تغییر کرده است.' ); }
            $r = new WP_REST_Request( 'GET' ); foreach ( $s['filters'] as $k => $v ) { $r->set_param( $k, $v ); } $r->set_param( 'page', $s['page'] );
            if ( in_array( $s['state'], array( 'scheduled','previewing' ), true ) ) {
                $rows = NIL_Bulk_Manager::products( $r ); $input = array();
                foreach ( $rows['rows'] as $row ) { if ( 'variable' !== $row['type'] ) { $input[] = array( 'id' => $row['id'], 'version' => $row['version'], 'changes' => array() ); } }
                foreach ( array_chunk( $input, 50 ) as $batch ) {
                    $p = new WP_REST_Request( 'POST' ); $p->set_body( wp_json_encode( array( 'rows' => $batch, 'rule' => $s['rule'], 'job_id' => $s['job'] ) ) ); $p->set_header( 'content-type', 'application/json' );
                    $out = NIL_Bulk_Manager::preview( $p ); $s['job'] = $out['job_id']; $s['added'] += $out['added']; if ( $out['errors'] ) { throw new RuntimeException( $out['errors'][0]['error'] ); }
                }
                $s['page']++; $s['state'] = 'previewing';
                if ( $s['page'] > $rows['pages'] ) {
                    if ( $s['added'] ) { $p = new WP_REST_Request( 'POST' ); $p['id'] = $s['job']; $p->set_param( 'count', $s['added'] ); NIL_Bulk_Manager::seal( $p ); $s['state'] = 'running'; }
                    else { $s['state'] = 'done'; }
                }
            } elseif ( 'running' === $s['state'] ) {
                $p = new WP_REST_Request( 'POST' ); $p['id'] = $s['job']; $p->set_param( 'mode', 'apply' ); $out = NIL_Bulk_Manager::run( $p ); if ( $out['done'] ) { $s['state'] = 'done'; }
            }
            if ( 'done' === $s['state'] ) {
                $s['last_job'] = $s['job']; $s['last_run'] = gmdate( 'c' );
                if ( 'once' !== $s['frequency'] ) { $s['next'] = time() + ( 'daily' === $s['frequency'] ? DAY_IN_SECONDS : WEEK_IN_SECONDS ); $s['state'] = 'scheduled'; $s['job'] = ''; $s['page'] = 1; $s['added'] = 0; }
            } else { $s['next'] = time() + 10; }
            if ( 'done' !== $s['state'] ) { wp_schedule_single_event( $s['next'], 'nil_bulk_schedule', array( $id ) ); }
        } catch ( Throwable $e ) { $s['state'] = 'failed'; $s['error'] = $e->getMessage(); }
        finally {
            $list = get_option( 'nil_bulk_schedules', array() ); if ( isset( $list[$id] ) ) { $list[$id] = $s; update_option( 'nil_bulk_schedules', $list, false ); }
            delete_transient( 'nil_cron_' . $id ); wp_set_current_user( $previous_user );
        }
    }
}
