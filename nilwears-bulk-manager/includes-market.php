<?php
/** Bounded, fixed-baseline market pricing. No network, settings read or cron at boot. */
defined( 'ABSPATH' ) || exit;
class NIL_Market_Feed_Error extends RuntimeException {}
final class NIL_Market {
    const OPTION = 'nil_market_rules';
    const MAX_AGE = 3600;
    private static $executing = '';
    private static function table() { global $wpdb; return $wpdb->prefix . 'nil_market_bases'; }
    public static function install() {
        global $wpdb; $table = self::table(); $charset = $wpdb->get_charset_collate();
        dbDelta( "CREATE TABLE $table (
            product_id bigint(20) unsigned NOT NULL,
            rule_id varchar(36) NOT NULL,
            base_regular varchar(32) NOT NULL,
            base_sale varchar(32) NOT NULL,
            last_regular varchar(32) NOT NULL,
            last_sale varchar(32) NOT NULL,
            PRIMARY KEY  (product_id),
            KEY rule_product (rule_id,product_id)
        ) ENGINE=InnoDB $charset;" );
    }
    private static function db( $result ) { if ( false === $result ) { throw new RuntimeException( 'ثبت اطلاعات قانون بازار ناموفق بود.' ); } return $result; }
    private static function list_rules( $fresh = false ) { if ( $fresh ) { wp_cache_delete( self::OPTION, 'options' ); } $v = get_option( self::OPTION, array() ); return is_array( $v ) ? $v : array(); }
    private static function save( $s ) { $lock = self::lock( 'settings' ); try { $list = self::list_rules( true ); $list[$s['id']] = $s; self::write( $list ); } finally { NIL_Bulk_Manager::unlock( $lock ); } }
    private static function write( $list ) { if ( ! update_option( self::OPTION, $list, false ) && self::list_rules( true ) !== $list ) { throw new RuntimeException( 'ذخیرهٔ وضعیت قانون ناموفق بود.' ); } }
    private static function prime( $ids ) { _prime_post_caches( $ids, true, true ); $parents = array(); foreach ( $ids as $id ) { $post = get_post( $id ); if ( $post && $post->post_parent ) { $parents[] = (int) $post->post_parent; } } if ( $parents ) { _prime_post_caches( array_unique( $parents ), true, true ); } }
    private static function admin() { if ( ! current_user_can( 'manage_options' ) || ! NIL_Bulk_Manager::permission() ) { throw new RuntimeException( 'مدیریت قانون بازار به مدیر سایت نیاز دارد.' ); } }
    private static function lock( $id ) {
        global $wpdb; $name = 'nil_market_' . md5( DB_NAME . $wpdb->prefix . $id );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) ) { throw new RuntimeException( 'این قانون در حال پردازش است؛ دوباره تلاش کنید.' ); } return $name;
    }
    private static function rule( $id ) { $list = self::list_rules( true ); if ( ! isset( $list[$id] ) ) { throw new RuntimeException( 'قانون بازار پیدا نشد.' ); } return $list[$id]; }
    private static function currency( $s ) {
        if ( $s['currency'] !== get_woocommerce_currency() || $s['decimals'] !== wc_get_price_decimals() ) { throw new RuntimeException( 'واحد پول یا دقت فروشگاه تغییر کرده است؛ قانون تازه بسازید.' ); }
    }
    private static function fresh( $rate ) {
        return isset( $rate['updated_at'] ) && $rate['updated_at'] <= time() + 300 && $rate['updated_at'] >= time() - self::MAX_AGE;
    }
    public static function parse( $payload ) {
        if ( ! is_array( $payload ) || ! isset( $payload['indicators'] ) || ! is_array( $payload['indicators'] ) ) { throw new NIL_Market_Feed_Error( 'پاسخ منبع نرخ معتبر نیست.' ); }
        $out = array( 'rates' => array(), 'errors' => array(), 'fetched_at' => time() );
        foreach ( array( 'gold18k' => 'طلای ۱۸ عیار', 'price_dollar_rl' => 'دلار', 'price_eur' => 'یورو' ) as $key => $label ) {
            $r = $payload['indicators'][$key] ?? array();
            if ( ! isset( $r['price'], $r['updated_ms'], $r['divide10'], $r['unit'] ) || ! is_bool( $r['divide10'] ) || ! is_numeric( $r['price'] ) || ! is_numeric( $r['updated_ms'] ) || 'تومان' !== $r['unit'] ) { $out['errors'][$key] = 'ساختار یا واحد نرخ معتبر نیست.'; continue; }
            $value = (float) $r['price'] / ( $r['divide10'] ? 10 : 1 ); $rate = array( 'label' => $label, 'price' => $value, 'updated_at' => (int) floor( (float) $r['updated_ms'] / 1000 ) );
            if ( ! is_finite( $value ) || $value <= 0 || $value > 100000000000 || ! self::fresh( $rate ) ) { $out['errors'][$key] = 'نرخ نامعتبر یا بیش از یک ساعت قدیمی است.'; continue; }
            $out['rates'][$key] = $rate;
        }
        if ( ! $out['rates'] ) { throw new NIL_Market_Feed_Error( 'نرخ تازه و معتبر در منبع یافت نشد؛ قیمت‌ها تغییر نکردند.' ); } return $out;
    }
    public static function rates() {
        $cached = get_transient( 'nil_market_rates' ); if ( is_array( $cached ) ) { return $cached; }
        if ( get_transient( 'nil_market_rate_error' ) ) { throw new NIL_Market_Feed_Error( 'دریافت نرخ موقتاً ناموفق است؛ قیمت‌ها حفظ شدند.' ); }
        try {
            $response = wp_remote_get( 'https://wallgold.ir/wp-content/uploads/wallgold/prices.json?_=' . time(), array( 'reject_unsafe_urls' => true, 'timeout' => 8, 'redirection' => 0, 'limit_response_size' => 1048576, 'headers' => array( 'Accept' => 'application/json' ) ) );
            if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) { throw new NIL_Market_Feed_Error( 'ارتباط با منبع نرخ برقرار نشد؛ قیمت‌ها حفظ شدند.' ); }
            $out = self::parse( json_decode( wp_remote_retrieve_body( $response ), true ) ); set_transient( 'nil_market_rates', $out, 60 ); delete_transient( 'nil_market_rate_error' ); return $out;
        } catch ( Throwable $e ) { set_transient( 'nil_market_rate_error', 1, 15 ); throw new NIL_Market_Feed_Error( $e->getMessage() ); }
    }
    private static function quote( $asset ) {
        $out = self::rates(); $r = $out['rates'][$asset] ?? null;
        if ( ! $r || ! self::fresh( $r ) ) { throw new NIL_Market_Feed_Error( 'برای این شاخص نرخ تازه و معتبر نداریم؛ قیمت‌ها حفظ شدند.' ); } return $r;
    }
    public static function price( $base, $s, $rate ) {
        if ( '' === $base ) { return ''; }
        $ratio = $rate / $s['base_rate']; if ( ! $s['allow_decrease'] ) { $ratio = max( 1, $ratio ); }
        $raw = NIL_Bulk_Price::number( $base ) * $ratio;
        if ( ! is_finite( $raw ) || $raw < 0 || $raw > 100000000000 ) { throw new InvalidArgumentException( 'قیمت حاصل خارج از محدوده است.' ); }
        $rule = array( 'operation' => 'round', 'value' => 0, 'rounding' => $s['rounding'], 'step' => $s['step'], 'ending' => $s['ending'] );
        $out = NIL_Bulk_Price::calculate( number_format( $raw, $s['decimals'], '.', '' ), $rule, $s['decimals'] );
        if ( ! $s['allow_decrease'] && (float) $out < (float) $base ) { $rule['rounding'] = 'up'; $out = NIL_Bulk_Price::calculate( $base, $rule, $s['decimals'] ); }
        return $out;
    }
    private static function request( $body, $id = null ) { $r = new WP_REST_Request( 'POST' ); $r->set_header( 'content-type', 'application/json' ); $r->set_body( wp_json_encode( $body ) ); foreach ( $body as $key => $value ) { $r->set_param( $key, $value ); } if ( $id ) { $r['id'] = $id; } return $r; }
    private static function schedule( &$s, $delay ) {
        wp_clear_scheduled_hook( 'nil_bulk_market', array( $s['id'] ) ); $s['next'] = time() + $delay;
        $ok = wp_schedule_single_event( $s['next'], 'nil_bulk_market', array( $s['id'] ), true );
        if ( is_wp_error( $ok ) || ! $ok ) { throw new RuntimeException( 'ثبت نوبت اجرا ناموفق بود.' ); }
    }
    private static function begin( &$s ) {
        $s['quote'] = self::quote( $s['asset'] ); $s['job'] = ''; $s['cursor'] = 0; $s['record_cursor'] = 0; $s['added'] = 0; $s['skipped'] = 0; $s['conflicts'] = array(); $s['sample'] = array(); $s['result_counts'] = array(); $s['phase'] = 'previewing'; $s['error'] = '';
    }
    private static function plan_batch( &$s, $draft ) {
        global $wpdb; if ( ! self::fresh( $s['quote'] ) ) { throw new NIL_Market_Feed_Error( 'نرخ پیش‌نمایش منقضی شد؛ پیش‌نمایش تازه لازم است.' ); }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE rule_id=%s AND product_id>%d ORDER BY product_id LIMIT 50', $s['id'], $s['cursor'] ), ARRAY_A );
        if ( $rows ) {
            self::prime( array_map( 'intval', wp_list_pluck( $rows, 'product_id' ) ) ); $input = array();
            foreach ( $rows as $base ) {
                $id = (int) $base['product_id']; $s['cursor'] = $id;
                try {
                    $p = NIL_Bulk_Manager::editable( wc_get_product( $id ) ); if ( $p->is_type( 'variable' ) ) { throw new RuntimeException( 'نوع محصول تغییر کرده است.' ); }
                    $v = NIL_Bulk_Manager::snapshot( $p ); $changes = array();
                    foreach ( array( 'regular' => 'regular_price', 'sale' => 'sale_price' ) as $short => $field ) {
                        if ( 'both' !== $s['target'] && $field !== $s['target'] ) { continue; }
                        if ( (string) $v[$field] !== $base['last_' . $short] ) { throw new RuntimeException( 'قیمت خارج از این قانون تغییر کرده است.' ); }
                        $next = self::price( $base['base_' . $short], $s, $s['quote']['price'] );
                        if ( (string) $v[$field] !== $next ) { $changes[$field] = $next; }
                    }
                    if ( $changes ) { $input[] = array( 'id' => $id, 'version' => NIL_Bulk_Manager::fingerprint( $v ), 'changes' => $changes ); if ( count( $s['sample'] ) < 8 ) { $s['sample'][] = array( 'id' => $id, 'name' => $p->get_name(), 'before' => array_intersect_key( $v, $changes ), 'after' => $changes ); } }
                } catch ( Throwable $e ) {
                    if ( $draft ) { throw $e; } $s['skipped']++; if ( count( $s['conflicts'] ) < 10 ) { $s['conflicts'][] = array( 'id' => $id, 'error' => $e->getMessage() ); }
                }
            }
            if ( $input ) {
                $out = NIL_Bulk_Manager::preview( self::request( array( 'rows' => $input, 'job_id' => $s['job'] ) ) ); $s['job'] = $out['job_id']; $s['added'] += $out['added']; self::save( $s );
                if ( $out['errors'] ) { throw new RuntimeException( 'پیش‌نمایش قیمت نامعتبر است: #' . $out['errors'][0]['id'] . ' ' . $out['errors'][0]['error'] ); }
            }
        }
        if ( count( $rows ) < 50 ) { $s['phase'] = 'sealing'; }
    }
    private static function seal( &$s ) {
        global $wpdb; if ( $s['job'] && $s['added'] ) {
            $state = $wpdb->get_var( $wpdb->prepare( 'SELECT state FROM ' . $wpdb->prefix . 'nil_bulk_jobs WHERE id=%s AND user_id=%d', $s['job'], $s['owner'] ) );
            if ( 'draft' === $state ) { NIL_Bulk_Manager::seal( self::request( array( 'count' => $s['added'] ), $s['job'] ) ); }
            elseif ( ! in_array( $state, array( 'ready', 'running', 'done' ), true ) ) { throw new RuntimeException( 'پیش‌نمایش قانون در دسترس نیست.' ); }
        } $s['phase'] = 'previewed';
    }
    private static function sync( &$s ) {
        global $wpdb; if ( ! $s['job'] ) { return; }
        $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id,product_id,after_json FROM ' . $wpdb->prefix . 'nil_bulk_items WHERE job_id=%s AND state=%s AND id>%d ORDER BY id LIMIT 50', $s['job'], 'applied', $s['record_cursor'] ), ARRAY_A );
        foreach ( $rows as $row ) {
            $after = json_decode( $row['after_json'], true ); $data = array();
            if ( 'sale_price' !== $s['target'] ) { $data['last_regular'] = $after['regular_price']; }
            if ( 'regular_price' !== $s['target'] ) { $data['last_sale'] = $after['sale_price']; }
            self::db( $wpdb->update( self::table(), $data, array( 'rule_id' => $s['id'], 'product_id' => $row['product_id'] ) ) ); $s['record_cursor'] = (int) $row['id'];
        }
    }
    private static function cancel( &$s, $reason ) {
        global $wpdb; if ( ! $s['job'] ) { return; } $lock = NIL_Bulk_Manager::lock();
        try {
            self::sync( $s ); $jobs = $wpdb->prefix . 'nil_bulk_jobs'; $items = $wpdb->prefix . 'nil_bulk_items';
            self::db( $wpdb->query( $wpdb->prepare( "UPDATE $items SET state='skipped',error=%s WHERE job_id=%s AND state='pending'", $reason, $s['job'] ) ) );
            self::db( $wpdb->query( $wpdb->prepare( "UPDATE $jobs SET state='done' WHERE id=%s AND state IN ('draft','ready','running')", $s['job'] ) ) );
            $s['result_counts'] = array(); foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT state,COUNT(*) AS n FROM $items WHERE job_id=%s GROUP BY state", $s['job'] ), ARRAY_A ) as $row ) { $s['result_counts'][$row['state']] = (int) $row['n']; }
            $s['last_job_owner'] = $s['owner']; $s['last_job'] = $s['job']; $s['job'] = ''; $s['phase'] = 'idle';
        } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
    private static function remove( $s ) {
        global $wpdb; self::cancel( $s, 'قانون بازار حذف/جایگزین شد.' ); wp_clear_scheduled_hook( 'nil_bulk_market', array( $s['id'] ) ); self::db( $wpdb->delete( self::table(), array( 'rule_id' => $s['id'] ) ) );
        $lock = self::lock( 'settings' ); try { $list = self::list_rules( true ); unset( $list[$s['id']] ); self::write( $list ); } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
    private static function interval( $value ) { $n = filter_var( $value, FILTER_VALIDATE_INT ); if ( false === $n || $n < 5 || $n > 1440 ) { throw new InvalidArgumentException( 'فاصلهٔ اجرا عدد صحیح بین ۵ تا ۱۴۴۰ دقیقه است.' ); } return $n; }
    private static function make( $b ) {
        self::admin(); $lock = self::lock( 'create' );
        try {
            $list = self::list_rules( true );
            foreach ( $list as $old ) { if ( $old['owner'] === get_current_user_id() && in_array( $old['status'], array( 'draft','previewed','failed' ), true ) ) { $old_lock = self::lock( $old['id'] ); try { self::remove( $old ); } finally { NIL_Bulk_Manager::unlock( $old_lock ); } } }
            if ( count( self::list_rules( true ) ) >= 20 ) { throw new RuntimeException( 'حداکثر ۲۰ قانون بازار.' ); }
            $asset = $b['asset'] ?? ''; if ( ! in_array( $asset, array( 'gold18k','price_dollar_rl','price_eur' ), true ) ) { throw new InvalidArgumentException( 'شاخص نامعتبر است.' ); }
            $rate = NIL_Bulk_Price::number( $b['base_rate'] ?? '' ); if ( $rate <= 0 ) { throw new InvalidArgumentException( 'نرخ مبنا باید مثبت و به تومان باشد.' ); }
            $minutes = self::interval( $b['interval'] ?? null );
            if ( ! is_bool( $b['allow_decrease'] ?? null ) || ! in_array( $b['target'] ?? '', array( 'regular_price','sale_price','both' ), true ) ) { throw new InvalidArgumentException( 'گزینهٔ کاهش یا ستون قیمت نامعتبر است.' ); }
            $rounding = $b['rounding'] ?? 'none'; if ( ! in_array( $rounding, array( 'none','up','down','nearest' ), true ) ) { throw new InvalidArgumentException( 'رندکردن نامعتبر است.' ); }
            $step = 'none' === $rounding ? 10000 : NIL_Bulk_Price::number( $b['step'] ?? '10000' ); $ending = 'none' === $rounding ? 9000 : NIL_Bulk_Price::number( $b['ending'] ?? '9000' );
            NIL_Bulk_Price::calculate( number_format( max( 100000, $ending ), wc_get_price_decimals(), '.', '' ), array( 'operation' => 'round', 'value' => 0, 'rounding' => $rounding, 'step' => $step, 'ending' => $ending ), wc_get_price_decimals() );
            $name = sanitize_text_field( $b['name'] ?? 'قانون بازار' ); if ( ! $name || strlen( $name ) > 150 ) { throw new InvalidArgumentException( 'نام کوتاه و معتبر وارد کنید.' ); }
            $s = array( 'id' => wp_generate_uuid4(), 'name' => $name, 'owner' => get_current_user_id(), 'asset' => $asset, 'base_rate' => $rate, 'interval' => $minutes, 'allow_decrease' => $b['allow_decrease'], 'target' => $b['target'], 'rounding' => $rounding, 'step' => $step, 'ending' => $ending, 'currency' => get_woocommerce_currency(), 'decimals' => wc_get_price_decimals(), 'status' => 'draft', 'phase' => 'capturing', 'created' => time(), 'count' => 0, 'job' => '', 'last_job' => '', 'record_cursor' => 0, 'next' => 0, 'error' => '' );
            self::save( $s ); return $s;
        } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
    public static function endpoint( $r ) {
        self::admin(); if ( 'GET' === $r->get_method() ) { return array_values( self::list_rules() ); }
        $b = $r->get_json_params(); if ( 'create' === ( $b['action'] ?? '' ) ) { return self::make( $b ); }
        $id = sanitize_key( $b['id'] ?? '' ); $lock = self::lock( $id );
        try {
            $s = self::rule( $id ); $action = $b['action'] ?? '';
            if ( 'delete' === $action ) { self::remove( $s ); return array_values( self::list_rules() ); }
            if ( 'interval' === $action ) { if ( empty( $s['activated'] ) ) { throw new RuntimeException( 'تناوب پیش‌نویس را در فرم پیش‌نمایش تعیین کنید.' ); } $s['interval'] = self::interval( $b['interval'] ?? null ); if ( in_array( $s['status'], array( 'active','waiting' ), true ) && 'idle' === $s['phase'] ) { self::schedule( $s, $s['interval'] * 60 ); } }
            elseif ( 'pause' === $action ) { if ( ! in_array( $s['status'], array( 'active','waiting','paused' ), true ) ) { throw new RuntimeException( 'این قانون هنوز فعال نشده است.' ); } self::cancel( $s, 'قانون بازار متوقف شد.' ); $s['status'] = 'paused'; $s['phase'] = 'idle'; $s['next'] = 0; wp_clear_scheduled_hook( 'nil_bulk_market', array( $id ) ); }
            elseif ( 'activate' === $action ) {
                if ( 'previewed' !== $s['status'] || $s['created'] < time() - HOUR_IN_SECONDS ) { throw new RuntimeException( 'پیش‌نمایش کامل و تازه لازم است.' ); }
                self::currency( $s ); if ( ! self::fresh( $s['quote'] ) ) { throw new NIL_Market_Feed_Error( 'نرخ پیش‌نمایش منقضی شد.' ); } $s['activated'] = time(); $s['status'] = 'active'; $s['phase'] = $s['job'] ? 'running' : 'idle'; self::schedule( $s, 1 );
            } elseif ( 'resume' === $action ) {
                if ( 'paused' !== $s['status'] || empty( $s['activated'] ) || ! $s['count'] ) { throw new RuntimeException( 'این قانون متوقف نیست.' ); } self::currency( $s ); $s['status'] = 'active'; $s['phase'] = 'idle'; $s['owner'] = get_current_user_id(); self::schedule( $s, 1 );
            } elseif ( 'run' === $action ) {
                if ( ! in_array( $s['status'], array( 'active','waiting' ), true ) ) { throw new RuntimeException( 'ابتدا قانون را فعال کنید.' ); } self::schedule( $s, 1 );
            } else { throw new InvalidArgumentException( 'عملیات نامعتبر است.' ); }
            self::save( $s ); return array_values( self::list_rules() );
        } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
    public static function capture( $r ) {
        global $wpdb; self::admin(); $b = $r->get_json_params(); $id = sanitize_key( $b['id'] ?? '' ); $lock = self::lock( $id );
        try {
            $s = self::rule( $id ); self::currency( $s );
            if ( $s['owner'] !== get_current_user_id() || 'capturing' !== $s['phase'] || 'draft' !== $s['status'] || $s['created'] < time() - HOUR_IN_SECONDS ) { throw new RuntimeException( 'ثبت مبنای این پیش‌نویس مجاز نیست.' ); }
            $input = $b['rows'] ?? array(); if ( ! is_array( $input ) || ! $input || count( $input ) > 50 ) { throw new InvalidArgumentException( '۱ تا ۵۰ ردیف در هر بسته.' ); }
            $global = NIL_Bulk_Manager::lock();
            try {
                $ids = array_map( function( $row ) { return absint( $row['id'] ?? 0 ); }, $input ); if ( count( array_unique( $ids ) ) !== count( $ids ) ) { throw new InvalidArgumentException( 'ردیف تکراری در بسته.' ); } self::prime( $ids ); $prepared = array();
                $existing = $wpdb->get_results( 'SELECT product_id,rule_id FROM ' . self::table() . ' WHERE product_id IN (' . implode( ',', array_map( 'absint', $ids ) ) . ')', OBJECT_K );
                foreach ( $input as $row ) {
                    $p = NIL_Bulk_Manager::editable( wc_get_product( absint( $row['id'] ?? 0 ) ) ); if ( $p->is_type( 'variable' ) ) { throw new InvalidArgumentException( 'قیمت روی ورییشن ثبت می‌شود.' ); }
                    $v = NIL_Bulk_Manager::snapshot( $p ); if ( ! is_string( $row['version'] ?? null ) || ! hash_equals( NIL_Bulk_Manager::fingerprint( $v ), $row['version'] ) ) { throw new RuntimeException( 'محصول تغییر کرده؛ جدول را تازه کنید.' ); }
                    $old = isset( $existing[$p->get_id()] ) ? (array) $existing[$p->get_id()] : null;
                    if ( $old && $old['rule_id'] !== $id ) { throw new RuntimeException( 'محصول #' . $p->get_id() . ' در یک قانون بازار دیگر است؛ ابتدا آن قانون را حذف کنید.' ); }
                    if ( $old ) { continue; }
                    $prepared[] = array( 'product_id' => $p->get_id(), 'rule_id' => $id, 'base_regular' => $v['regular_price'], 'base_sale' => $v['sale_price'], 'last_regular' => $v['regular_price'], 'last_sale' => $v['sale_price'] );
                }
                $count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE rule_id=%s', $id ) );
                if ( $count + count( $prepared ) > 5000 ) { throw new RuntimeException( 'حداکثر ۵۰۰۰ محصول یا ورییشن در هر قانون.' ); }
                if ( $prepared ) { $values = array(); foreach ( $prepared as $row ) { $values = array_merge( $values, array_values( $row ) ); } $sql = 'INSERT INTO ' . self::table() . ' (product_id,rule_id,base_regular,base_sale,last_regular,last_sale) VALUES ' . implode( ',', array_fill( 0, count( $prepared ), '(%d,%s,%s,%s,%s,%s)' ) ); self::db( $wpdb->query( $wpdb->prepare( $sql, $values ) ) ); }
                $s['count'] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE rule_id=%s', $id ) ); self::save( $s ); return array( 'count' => $s['count'] );
            } finally { NIL_Bulk_Manager::unlock( $global ); }
        } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
    public static function preview( $r ) {
        self::admin(); $b = $r->get_json_params(); $id = sanitize_key( $b['id'] ?? '' ); $lock = self::lock( $id );
        try {
            $s = self::rule( $id ); self::currency( $s );
            if ( $s['owner'] !== get_current_user_id() || ! in_array( $s['status'], array( 'draft','previewed' ), true ) || ! $s['count'] || $s['created'] < time() - HOUR_IN_SECONDS ) { throw new RuntimeException( 'پیش‌نویس دارای محصول و تازه لازم است.' ); }
            if ( 'capturing' === $s['phase'] ) { self::begin( $s ); self::save( $s ); }
            if ( 'previewing' === $s['phase'] ) { self::plan_batch( $s, true ); self::save( $s ); }
            if ( 'sealing' === $s['phase'] ) { self::seal( $s ); $s['status'] = 'previewed'; }
            self::save( $s ); return array( 'done' => 'previewed' === $s['phase'], 'rule' => $s );
        } catch ( Throwable $e ) {
            if ( isset( $s ) && ! ( $e instanceof NIL_Market_Feed_Error ) ) { self::cancel( $s, 'پیش‌نمایش قانون نامعتبر بود.' ); $s['status'] = 'failed'; $s['error'] = $e->getMessage(); self::save( $s ); } throw $e;
        } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
    public static function job_info( $id ) {
        foreach ( self::list_rules() as $s ) { if ( $s['job'] === $id || $s['last_job'] === $id ) { return array( 'id' => $s['id'], 'name' => $s['name'], 'status' => $s['status'] ); } } return null;
    }
    public static function guard( $job, $undo ) {
        if ( $undo ) { return; }
        foreach ( self::list_rules( true ) as $s ) {
            if ( $s['job'] !== $job['id'] ) { continue; }
            if ( self::$executing !== $s['id'] || ! in_array( $s['status'], array( 'active','waiting' ), true ) || 'running' !== $s['phase'] || ! self::fresh( $s['quote'] ) ) { throw new RuntimeException( 'این پیش‌نمایش بازار فقط از قانون فعال و با نرخ تازه اجرا می‌شود؛ از بخش قانون بازار اقدام کنید.' ); }
        }
    }
    public static function tick( $id ) {
        $previous = get_current_user_id(); $lock = null; $s = null;
        try {
            $lock = self::lock( $id ); $s = self::rule( $id ); if ( ! in_array( $s['status'], array( 'active','waiting' ), true ) ) { return; }
            wp_set_current_user( $s['owner'] ); self::admin(); self::currency( $s ); $s['status'] = 'active';
            // One replacement event survives a worker crash; normal completion replaces it again.
            self::schedule( $s, max( 300, $s['interval'] * 60 ) ); self::save( $s );
            if ( 'idle' === $s['phase'] ) { self::begin( $s ); self::save( $s ); }
            if ( ! self::fresh( $s['quote'] ) ) { throw new NIL_Market_Feed_Error( 'نرخ این نوبت قدیمی شد؛ ادامهٔ تغییر قیمت متوقف شد.' ); }
            if ( 'previewing' === $s['phase'] ) { self::plan_batch( $s, false ); self::save( $s ); }
            elseif ( 'running' === $s['phase'] ) {
                self::sync( $s ); self::save( $s ); self::$executing = $id;
                try { $out = NIL_Bulk_Manager::run( self::request( array( 'mode' => 'apply' ), $s['job'] ) ); } finally { self::$executing = ''; }
                self::sync( $s ); if ( $out['done'] ) { $s['phase'] = 'finished'; }
            }
            if ( 'sealing' === $s['phase'] ) { self::seal( $s ); $s['phase'] = $s['job'] ? 'running' : 'finished'; }
            if ( 'finished' === $s['phase'] || ( 'previewed' === $s['phase'] && ! $s['job'] ) ) {
                global $wpdb; $s['result_counts'] = array(); if ( $s['job'] ) { foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT state,COUNT(*) AS n FROM ' . $wpdb->prefix . 'nil_bulk_items WHERE job_id=%s GROUP BY state', $s['job'] ), ARRAY_A ) as $row ) { $s['result_counts'][$row['state']] = (int) $row['n']; } }
                if ( $s['job'] ) { $s['last_job_owner'] = $s['owner']; } $s['last_job'] = $s['job'] ?: $s['last_job']; $s['job'] = ''; $s['phase'] = 'idle'; $s['last_run'] = time(); $s['last_rate'] = $s['quote']; self::schedule( $s, $s['interval'] * 60 );
            } else { self::schedule( $s, 10 ); }
            self::save( $s );
        } catch ( Throwable $e ) {
            if ( $lock && $s ) {
                try { self::cancel( $s, 'اجرای قانون متوقف شد: ' . $e->getMessage() ); } catch ( Throwable $ignored ) { $s['error'] = $ignored->getMessage(); }
                $s['error'] = $e->getMessage(); $s['phase'] = 'idle'; $s['next'] = 0; wp_clear_scheduled_hook( 'nil_bulk_market', array( $id ) );
                $s['status'] = $e instanceof NIL_Market_Feed_Error ? 'waiting' : 'paused';
                if ( 'waiting' === $s['status'] ) { try { self::schedule( $s, $s['interval'] * 60 ); } catch ( Throwable $ignored ) { $s['status'] = 'paused'; } } self::save( $s );
            }
        } finally { self::$executing = ''; wp_set_current_user( $previous ); if ( $lock ) { NIL_Bulk_Manager::unlock( $lock ); } }
    }
}
