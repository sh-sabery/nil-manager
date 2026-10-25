<?php
/** Bounded per-user views and reusable price rules. Nothing loads at storefront boot. */
defined( 'ABSPATH' ) || exit;
final class NIL_Presets {
    public static function endpoint( $r ) {
        $list = get_user_meta( get_current_user_id(), '_nil_presets', true ); if ( ! is_array( $list ) ) { $list = array(); }
        if ( 'GET' === $r->get_method() ) { return array_values( $list ); }
        $b = $r->get_json_params(); $id = sanitize_key( $b['id'] ?? '' );
        if ( ! empty( $b['delete'] ) ) { unset( $list[$id] ); update_user_meta( get_current_user_id(), '_nil_presets', $list ); return array_values( $list ); }
        $kind = $b['kind'] ?? ''; if ( ! in_array( $kind, array( 'view','rule' ), true ) ) { throw new InvalidArgumentException( 'نوع الگو نامعتبر است.' ); }
        $name = sanitize_text_field( $b['name'] ?? '' ); if ( ! $name || strlen( $name ) > 150 ) { throw new InvalidArgumentException( 'نام کوتاه وارد کنید.' ); }
        $d = $b['data'] ?? array(); if ( ! is_array( $d ) ) { throw new InvalidArgumentException( 'الگو نامعتبر است.' ); }
        if ( 'rule' === $kind ) {
            $data = array_intersect_key( $d, array_flip( array( 'source','target','operation','value','rounding','step','ending' ) ) );
            if ( ! in_array( $data['source'] ?? '', array( 'cost','current' ), true ) || ! in_array( $data['target'] ?? '', array( 'regular_price','sale_price','both' ), true ) ) { throw new InvalidArgumentException( 'قاعده قیمت نامعتبر است.' ); }
            NIL_Bulk_Price::calculate( '100000', $data, wc_get_price_decimals() ); $data['currency'] = get_woocommerce_currency(); $data['decimals'] = wc_get_price_decimals();
        } else {
            $filters = array_intersect_key( (array) ( $d['filters'] ?? array() ), array_flip( array( 'search','category','brand','type','status','stock','min_price','max_price','sort' ) ) );
            foreach ( $filters as &$v ) { if ( ! is_scalar( $v ) || strlen( (string) $v ) > 200 ) { throw new InvalidArgumentException( 'فیلتر نامعتبر است.' ); } $v = sanitize_text_field( $v ); } unset( $v );
            $columns = array( 'cost','profit','regular','sale','manage','quantity','stock' );
            $visible = array_values( array_intersect( $columns, (array) ( $d['columns'] ?? $columns ) ) );
            $order = array_values( array_intersect( (array) ( $d['order'] ?? $columns ), $columns ) ); $order = array_values( array_unique( array_merge( $order, $columns ) ) );
            $data = array( 'filters' => $filters, 'columns' => $visible, 'order' => $order );
        }
        if ( ! $id || ! isset( $list[$id] ) ) { if ( count( $list ) >= 20 ) { throw new RuntimeException( 'حداکثر ۲۰ نمای ذخیره‌شده و الگوی قیمت.' ); } $id = wp_generate_uuid4(); }
        $list[$id] = array( 'id' => $id, 'kind' => $kind, 'name' => $name, 'data' => $data );
        if ( strlen( wp_json_encode( $list ) ) > 32000 ) { throw new RuntimeException( 'حجم الگوها بیش از حد مجاز است.' ); }
        update_user_meta( get_current_user_id(), '_nil_presets', $list ); return array_values( $list );
    }
}
