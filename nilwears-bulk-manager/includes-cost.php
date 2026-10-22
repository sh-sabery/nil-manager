<?php
/** One active product-cost backend. No settings reads or migration at boot. */
defined( 'ABSPATH' ) || exit;
final class NIL_Cost {
    public static function native() {
        return class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) && method_exists( 'WC_Product', 'get_cogs_total_value' ) && \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled( 'cost_of_goods_sold' );
    }
    public static function read( $p ) {
        return self::native() ? number_format( $p->get_cogs_total_value(), wc_get_price_decimals(), '.', '' ) : (string) get_post_meta( $p->get_id(), '_nil_cost_price', true );
    }
    public static function state( $p ) {
        if ( ! self::native() ) { return array( 'cost_backend' => 'legacy' ); }
        return array( 'cost_backend' => 'native', 'cost_defined' => $p->get_cogs_value(), 'cost_additive' => $p->is_type( 'variation' ) && $p->get_cogs_value_is_additive(), 'cost_parent' => $p->is_type( 'variation' ) ? $p->get_parent_cogs_effective_value() : 0 );
    }
    public static function propose( $p, $after, $value ) {
        if ( ! self::native() ) { $after['cost_price'] = $value; return $after; }
        $v = '' === $value ? null : (float) $value;
        $after['cost_defined'] = ! $p->is_type( 'variation' ) && 0.0 === $v ? null : $v;
        $after['cost_additive'] = false;
        $total = null === $v && $p->is_type( 'variation' ) ? $p->get_parent_cogs_effective_value() : ( $v ?? 0 );
        $after['cost_price'] = number_format( $total, wc_get_price_decimals(), '.', '' ); return $after;
    }
    public static function set( $p, $value ) {
        if ( self::native() ) { $p->set_cogs_value( '' === $value ? null : (float) $value ); if ( $p->is_type( 'variation' ) ) { $p->set_cogs_value_is_additive( false ); } }
        else { $p->update_meta_data( '_nil_cost_price', $value ); }
    }
    public static function restore( $p, $desired ) {
        $expected = $desired['cost_backend'] ?? 'legacy';
        if ( $expected !== ( self::native() ? 'native' : 'legacy' ) ) { throw new RuntimeException( 'منبع قیمت خرید تغییر کرده؛ پیش‌نمایش تازه بسازید.' ); }
        if ( 'native' === $expected ) { $p->set_cogs_value( $desired['cost_defined'] ); if ( $p->is_type( 'variation' ) ) { $p->set_cogs_value_is_additive( (bool) $desired['cost_additive'] ); } }
        else { self::set( $p, $desired['cost_price'] ); }
    }
    public static function migration( $r ) {
        if ( ! self::native() ) { throw new RuntimeException( 'ابتدا COGS ووکامرس را فعال کنید.' ); }
        $b = $r->get_json_params(); $ids = $b['ids'] ?? array(); if ( ! is_array( $ids ) || count( $ids ) > 5000 ) { throw new InvalidArgumentException( 'حداکثر ۵۰۰۰ محصول.' ); }
        $updates = array(); $conflicts = array();
        foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
            $p = NIL_Bulk_Manager::editable( wc_get_product( $id ) ); $legacy = (string) get_post_meta( $id, '_nil_cost_price', true ); if ( '' === $legacy ) { continue; }
            $current = self::read( $p ); if ( (float) $current === (float) $legacy ) { continue; }
            if ( null !== $p->get_cogs_value() || ( $p->is_type( 'variation' ) && $p->get_parent_cogs_effective_value() != 0 ) ) { $conflicts[] = array( 'id' => $id, 'legacy' => $legacy, 'native' => $current ); continue; }
            $row = NIL_Bulk_Manager::row( $p ); $updates[] = array( 'id' => $id, 'version' => $row['version'], 'changes' => array( 'cost_price' => $legacy ), 'parent' => $p->is_type( 'variable' ) );
        }
        usort( $updates, function( $a, $b ) { return (int) $a['parent'] <=> (int) $b['parent']; } ); foreach ( $updates as &$row ) { unset( $row['parent'] ); } unset( $row );
        return array( 'updates' => $updates, 'conflicts' => $conflicts );
    }
}
