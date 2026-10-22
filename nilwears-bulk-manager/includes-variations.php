<?php
/** Preview and append combinations to existing variable products, through WooCommerce CRUD. */
defined( 'ABSPATH' ) || exit;
final class NIL_Variations {
    private static function parent_product( $id ) {
        $p = NIL_Bulk_Manager::editable( wc_get_product( absint( $id ) ) );
        if ( ! $p->is_type( 'variable' ) || ! current_user_can( 'publish_products' ) ) { throw new RuntimeException( 'والد باید محصول متغیر قابل‌ویرایش باشد.' ); } return $p;
    }
    private static function children( $id ) {
        $q = new WP_Query( array( 'post_type' => 'product_variation', 'post_parent' => $id, 'post_status' => array( 'publish','private','draft','pending','future' ), 'posts_per_page' => -1, 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC' ) );
        return array_values( array_filter( array_map( function( $post ) { return wc_get_product( $post->ID ); }, $q->posts ) ) );
    }
    private static function definitions( $p ) {
        $out = array(); foreach ( $p->get_attributes() as $key => $a ) {
            if ( ! $a->get_variation() ) { continue; }
            $values = $a->is_taxonomy() ? wc_get_product_terms( $p->get_id(), $a->get_name(), array( 'fields' => 'names' ) ) : $a->get_options();
            $out[$key] = array( 'key' => $key, 'name' => $a->get_name(), 'label' => wc_attribute_label( $a->get_name() ), 'taxonomy' => $a->is_taxonomy(), 'options' => $values );
        } return $out;
    }
    private static function version( $p, $children ) {
        $attrs = array(); foreach ( $p->get_attributes() as $key => $a ) { $attrs[$key] = array( $a->get_id(), $a->get_name(), $a->get_options(), $a->get_variation(), $a->get_visible() ); }
        return NIL_Bulk_Manager::fingerprint( array( NIL_Bulk_Manager::snapshot( $p ), $attrs, array_map( function( $v ) { return array( $v->get_id(), $v->get_status( 'edit' ), $v->get_attributes() ); }, $children ) ) );
    }
    public static function parent_info( $r ) {
        $p = self::parent_product( $r->get_param( 'id' ) ); $children = self::children( $p->get_id() );
        return array( 'id' => $p->get_id(), 'name' => $p->get_name(), 'version' => self::version( $p, $children ), 'attributes' => array_values( self::definitions( $p ) ), 'count' => count( $children ) );
    }
    private static function normalize( $p, $raw, $defs ) {
        if ( ! is_array( $raw ) || ! $raw || count( $raw ) > 5 ) { throw new InvalidArgumentException( 'ویژگی‌های کامل ورییشن را وارد کنید.' ); }
        $out = array(); foreach ( $raw as $name => $value ) {
            $key = sanitize_title( preg_replace( '/^attribute_/', '', (string) $name ) );
            if ( ! isset( $defs[$key] ) ) { foreach ( $defs as $k => $def ) { if ( $name === $def['label'] ) { $key = $k; break; } } }
            if ( $defs && ! isset( $defs[$key] ) ) { throw new InvalidArgumentException( 'افزودن بُعد ویژگی جدید به ورییشن‌های موجود پشتیبانی نمی‌شود؛ گزینهٔ رنگ/سایز موجود را گسترش دهید.' ); }
            if ( ! is_scalar( $value ) || is_bool( $value ) || '' === trim( (string) $value ) ) { throw new InvalidArgumentException( 'مقدار هر ویژگی الزامی است؛ «هر رنگ/سایز» برای ورییشن جدید مجاز نیست.' ); }
            $text = sanitize_text_field( $value );
            if ( isset( $out[$key] ) ) { throw new InvalidArgumentException( 'ویژگی تکراری است.' ); }
            if ( ! empty( $defs[$key]['taxonomy'] ) ) {
                $tax = $defs[$key]['name']; $term = get_term_by( 'slug', $text, $tax ) ?: get_term_by( 'name', $text, $tax );
                $out[$key] = $term ? $term->slug : sanitize_title( $text );
                $obj = get_taxonomy( $tax ); if ( ! current_user_can( $obj->cap->assign_terms ) || ( ! $term && ! current_user_can( $obj->cap->manage_terms ) ) ) { throw new RuntimeException( 'اجازه افزودن گزینهٔ ویژگی عمومی را ندارید.' ); }
            } else { $out[$key] = $text; }
        }
        if ( $defs && array_diff( array_keys( $defs ), array_keys( $out ) ) ) { throw new InvalidArgumentException( 'تمام ویژگی‌های والد باید در هر ورییشن مشخص شوند.' ); }
        ksort( $out ); return $out;
    }
    public static function preview( $r ) {
        $b = $r->get_json_params(); $input = $b['rows'] ?? null;
        if ( null === $input ) {
            $options = $b['options'] ?? array(); if ( ! is_array( $options ) || ! $options || count( $options ) > 5 ) { throw new InvalidArgumentException( '۱ تا ۵ ویژگی.' ); }
            $combos = array( array() ); foreach ( $options as $key => $values ) {
                if ( ! is_array( $values ) || ! $values ) { throw new InvalidArgumentException( 'گزینهٔ ویژگی خالی است.' ); }
                $next = array(); foreach ( $combos as $combo ) { foreach ( $values as $v ) { $next[] = $combo + array( $key => $v ); if ( count( $next ) > 100 ) { throw new InvalidArgumentException( 'حداکثر ۱۰۰ ترکیب در یک پیش‌نمایش.' ); } } } $combos = $next;
            }
            $input = array(); foreach ( $combos as $combo ) { $input[] = array_merge( (array) ( $b['defaults'] ?? array() ), array( 'parent_id' => absint( $b['parent_id'] ?? 0 ), 'attributes' => $combo ) ); }
        }
        if ( ! is_array( $input ) || ! $input || count( $input ) > 100 ) { throw new InvalidArgumentException( '۱ تا ۱۰۰ ورییشن در هر پیش‌نمایش.' ); }
        $groups = array(); $rows = array(); $skipped = array(); $seen = array(); $skus = array();
        foreach ( $input as $line => $raw ) {
            $id = absint( $raw['parent_id'] ?? 0 );
            if ( ! isset( $groups[$id] ) ) {
                if ( count( $groups ) >= 20 ) { throw new InvalidArgumentException( 'حداکثر ۲۰ والد در هر پیش‌نمایش.' ); }
                $p = self::parent_product( $id ); $children = self::children( $id ); $defs = self::definitions( $p );
                $version = self::version( $p, $children ); if ( ! empty( $b['version'] ) && ! hash_equals( $version, (string) $b['version'] ) ) { throw new RuntimeException( 'والد تغییر کرده؛ ویژگی‌ها را دوباره بارگذاری کنید.' ); }
                $groups[$id] = array( 'product' => $p, 'children' => $children, 'defs' => $defs, 'version' => $version );
            }
            $g = $groups[$id]; $p = $g['product']; $attrs = self::normalize( $p, $raw['attributes'] ?? array(), $g['defs'] );
            $keys = array_keys( $attrs ); if ( isset( $groups[$id]['keys'] ) && $groups[$id]['keys'] !== $keys ) { throw new InvalidArgumentException( 'ویژگی‌های تمام ورییشن‌های یک والد باید یکسان باشد.' ); } $groups[$id]['keys'] = $keys;
            $signature = $id . ':' . wp_json_encode( $attrs );
            $duplicate = isset( $seen[$signature] ); foreach ( $g['children'] as $v ) { $old = $v->get_attributes(); $matches = true; foreach ( $attrs as $key => $value ) { if ( isset( $old[$key] ) && '' !== $old[$key] && $old[$key] !== $value ) { $matches = false; break; } } if ( $matches ) { $duplicate = true; break; } }
            if ( $duplicate ) { $skipped[] = array( 'parent_id' => $id, 'attributes' => $attrs, 'reason' => 'ترکیب موجود/تکراری یا پوشش‌داده‌شده با «هر گزینه»' ); continue; } $seen[$signature] = true;
            $regular = NIL_Suite::validate_field( $p, 'cost_price', $raw['regular_price'] ?? '' ); $sale = NIL_Suite::validate_field( $p, 'cost_price', $raw['sale_price'] ?? '' );
            if ( '' !== $sale && ( '' === $regular || (float) $sale >= (float) $regular ) ) { throw new InvalidArgumentException( 'قیمت حراج باید کمتر از قیمت اصلی باشد.' ); }
            $sku = sanitize_text_field( $raw['sku'] ?? '' ); if ( '' === $sku ) { $prefix = sanitize_text_field( $b['sku_prefix'] ?? ( $p->get_sku() ?: 'NIL-' . $id ) ); $sku = $prefix . '-' . substr( hash( 'sha256', $signature ), 0, 10 ); }
            if ( isset( $skus[$sku] ) || ! wc_product_has_unique_sku( 0, $sku ) ) { throw new InvalidArgumentException( 'SKU تکراری: ' . $sku ); } $skus[$sku] = true;
            $quantity = $raw['stock_quantity'] ?? ''; $manage = isset( $raw['manage_stock'] ) ? $raw['manage_stock'] : '' !== $quantity;
            if ( ! is_bool( $manage ) ) { throw new InvalidArgumentException( 'مدیریت موجودی باید boolean باشد.' ); }
            if ( $manage ) { $quantity = NIL_Bulk_Price::number( $quantity ); if ( $quantity < 0 || $quantity > 100000000 || floor( $quantity ) !== $quantity ) { throw new InvalidArgumentException( 'موجودی باید عدد صحیح نامنفی باشد.' ); } } else { $quantity = null; }
            $row = array( 'parent_id' => $id, 'attributes' => $attrs, 'sku' => $sku, 'regular_price' => $regular, 'sale_price' => $sale, 'manage_stock' => $manage, 'stock_quantity' => $quantity );
            if ( array_key_exists( 'cost_price', $raw ) ) { $row['cost_price'] = NIL_Suite::validate_field( $p, 'cost_price', $raw['cost_price'] ); }
            $labels = array(); foreach ( $g['defs'] as $key => $def ) { if ( $def['taxonomy'] ) { $labels[$key] = sanitize_text_field( $raw['attributes'][$key] ?? $raw['attributes'][$def['name']] ?? $raw['attributes'][$def['label']] ?? $attrs[$key] ); } } $row['term_names'] = $labels;
            $rows[] = $row;
        }
        $token = wp_generate_uuid4(); $parents = array(); foreach ( $groups as $id => $g ) { $parents[$id] = $g['version']; }
        $plan = array( 'token' => $token, 'rows' => $rows, 'parents' => $parents, 'currency' => get_woocommerce_currency(), 'decimals' => wc_get_price_decimals(), 'backend' => NIL_Cost::native() ? 'native' : 'legacy' );
        set_transient( 'nil_variants_' . get_current_user_id(), $plan, HOUR_IN_SECONDS ); return array( 'token' => $token, 'rows' => $rows, 'skipped' => $skipped );
    }
    public static function apply( $r ) {
        global $wpdb; $b = $r->get_json_params(); $key = 'nil_variants_' . get_current_user_id(); $plan = get_transient( $key );
        if ( ! $plan || ! hash_equals( $plan['token'], (string) ( $b['token'] ?? '' ) ) ) { throw new RuntimeException( 'پیش‌نمایش ورییشن منقضی/جایگزین شده است.' ); }
        foreach ( $plan['parents'] as $id => $v ) { self::parent_product( $id ); }
        if ( isset( $plan['result'] ) ) { return $plan['result']; }
        if ( ! $plan['rows'] ) { return array( 'created' => array() ); }
        if ( $plan['currency'] !== get_woocommerce_currency() || $plan['decimals'] !== wc_get_price_decimals() || $plan['backend'] !== ( NIL_Cost::native() ? 'native' : 'legacy' ) ) { throw new RuntimeException( 'واحد پول، دقت یا منبع قیمت خرید تغییر کرده است.' ); }
        $lock = NIL_Bulk_Manager::lock(); $created = array(); $terms = array(); $parents = array_keys( $plan['parents'] );
        try {
            $old = get_posts( array( 'post_type' => 'product_variation', 'post_parent__in' => $parents, 'post_status' => array( 'publish','private','draft' ), 'meta_key' => '_nil_variation_batch', 'meta_value' => $plan['token'], 'posts_per_page' => 100, 'fields' => 'ids', 'no_found_rows' => true, 'orderby' => 'ID', 'order' => 'ASC' ) );
            if ( count( $old ) === count( $plan['rows'] ) ) { return array( 'created' => $old ); }
            NIL_Bulk_Manager::require_transactions(); if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'شروع تراکنش ناموفق بود.' ); }
            sort( $parents ); $ids = implode( ',', array_map( 'absint', $parents ) ); if ( false === $wpdb->query( "SELECT ID FROM $wpdb->posts WHERE ID IN ($ids) ORDER BY ID FOR UPDATE" ) || false === $wpdb->query( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id IN ($ids) ORDER BY post_id,meta_id FOR UPDATE" ) ) { throw new RuntimeException( 'قفل والد انجام نشد.' ); }
            $products = array(); foreach ( $parents as $id ) { NIL_Bulk_Manager::clear_cache( $id ); $p = self::parent_product( $id ); if ( ! hash_equals( $plan['parents'][$id], self::version( $p, self::children( $id ) ) ) ) { throw new RuntimeException( 'والد یا ترکیب‌های آن تغییر کرده؛ پیش‌نمایش جدید بسازید.' ); } $products[$id] = $p; }
            foreach ( $plan['rows'] as $row ) {
                $p = $products[$row['parent_id']]; $attrs = $p->get_attributes();
                foreach ( $row['attributes'] as $name => $value ) {
                    $a = isset( $attrs[$name] ) ? clone $attrs[$name] : new WC_Product_Attribute(); if ( ! isset( $attrs[$name] ) ) { $a->set_name( rawurldecode( $name ) ); $a->set_visible( true ); $a->set_variation( true ); }
                    $options = $a->get_options(); $option = $value;
                    if ( $a->is_taxonomy() ) {
                        $tax = $a->get_name(); if ( ! current_user_can( get_taxonomy( $tax )->cap->assign_terms ) ) { throw new RuntimeException( 'مجوز تخصیص ویژگی ندارید.' ); } $term = get_term_by( 'slug', $value, $tax );
                        if ( ! $term ) { $obj = get_taxonomy( $tax ); if ( ! current_user_can( $obj->cap->manage_terms ) ) { throw new RuntimeException( 'مجوز ایجاد گزینه ندارید.' ); } $result = wp_insert_term( $row['term_names'][$name] ?? rawurldecode( $value ), $tax, array( 'slug' => $value ) ); if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); } $option = $result['term_id']; $terms[$tax][] = $option; }
                        else { $option = $term->term_id; }
                    }
                    if ( ! in_array( $option, $options, true ) ) { $options[] = $option; } $a->set_options( $options ); $a->set_variation( true ); $attrs[$name] = $a;
                } $p->set_attributes( $attrs );
                if ( ! wc_product_has_unique_sku( 0, $row['sku'] ) ) { throw new RuntimeException( 'SKU اکنون تکراری است: ' . $row['sku'] ); }
                $v = new WC_Product_Variation(); $v->set_parent_id( $p->get_id() ); $v->set_status( 'publish' ); $v->set_attributes( $row['attributes'] ); $v->set_sku( $row['sku'] ); $v->set_regular_price( $row['regular_price'] ); $v->set_sale_price( $row['sale_price'] ); $v->set_manage_stock( $row['manage_stock'] ); if ( $row['manage_stock'] ) { $v->set_stock_quantity( $row['stock_quantity'] ); }
                if ( array_key_exists( 'cost_price', $row ) ) { NIL_Cost::set( $v, $row['cost_price'] ); } $v->update_meta_data( '_nil_variation_batch', $plan['token'] ); $v->save(); $created[] = $v->get_id();
            }
            foreach ( $products as $p ) { $p->save(); WC_Product_Variable::sync( $p->get_id() ); }
            if ( false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'ثبت نهایی انجام نشد.' ); }
            $plan['result'] = array( 'created' => $created ); set_transient( $key, $plan, HOUR_IN_SECONDS ); return $plan['result'];
        } catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); foreach ( $created as $id ) { clean_post_cache( $id ); wp_cache_delete( $id, 'post_meta' ); } throw $e; }
        finally { foreach ( $parents as $id ) { NIL_Bulk_Manager::clear_cache( $id ); clean_object_term_cache( $id, 'product' ); } foreach ( $terms as $tax => $ids ) { clean_term_cache( $ids, $tax ); } NIL_Bulk_Manager::unlock( $lock ); }
    }
}
