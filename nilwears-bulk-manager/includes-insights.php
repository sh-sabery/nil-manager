<?php
/** HPOS-safe order reads and immutable unit-cost snapshots for future order lines. */
defined( 'ABSPATH' ) || exit;
final class NIL_Insights {
    public static function boot() {
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'checkout_cost' ), 10, 4 );
        add_action( 'woocommerce_new_order_item', array( __CLASS__, 'new_item_cost' ), 10, 3 );
    }
    public static function checkout_cost( $item, $cart_key, $values, $order ) { self::capture( $item, $order ); }
    public static function new_item_cost( $id, $item, $order_id ) {
        if ( ! NIL_Cost::native() && $item instanceof WC_Order_Item_Product && '' === $item->get_meta( '_nil_unit_cost', true ) ) {
            self::capture( $item, wc_get_order( $order_id ) ); $item->save_meta_data();
        }
    }
    private static function capture( $item, $order ) {
        if ( NIL_Cost::native() || ! $order || '' !== $item->get_meta( '_nil_unit_cost', true ) ) { return; }
        $product = $item->get_product(); if ( ! $product ) { return; }
        $cost = NIL_Cost::read( $product );
        if ( '' === $cost || $order->get_currency() !== get_woocommerce_currency() ) { return; }
        $item->add_meta_data( '_nil_unit_cost', (string) $cost, true ); $item->add_meta_data( '_nil_cost_currency', $order->get_currency(), true );
    }
    public static function report( $r ) {
        if ( ! current_user_can( 'view_woocommerce_reports' ) ) { throw new RuntimeException( 'اجازه مشاهده گزارش فروش ندارید.' ); }
        $id = sanitize_key( $r->get_param( 'id' ) ?: '' ); $key = 'nil_insight_' . get_current_user_id();
        if ( $id ) { $state = get_transient( $key ); if ( ! $state || ! hash_equals( $state['id'], $id ) ) { throw new RuntimeException( 'گزارش منقضی شده؛ دوباره محاسبه کنید.' ); } }
        else {
            $calendar = $r->get_param( 'calendar' ) ?: 'gregorian';
            $from = NIL_Dates::parse( $r->get_param( 'from' ), $calendar ); $to = NIL_Dates::parse( $r->get_param( 'to' ), $calendar );
            if ( $from > $to ) { throw new InvalidArgumentException( 'تاریخ پایان نباید پیش از شروع باشد.' ); }
            $id = wp_generate_uuid4(); $key = 'nil_insight_' . get_current_user_id();
            $state = array( 'id' => $id, 'from' => $from, 'to' => $to, 'page' => 1, 'done' => false, 'customers' => array(), 'products' => array(), 'orders' => 0, 'lines' => 0, 'pairs' => 0, 'other_currency_orders' => 0, 'currency' => get_woocommerce_currency(), 'net_revenue' => 0.0, 'known_revenue' => 0.0, 'known_cost' => 0.0, 'unknown_cost_lines' => 0 );
        }
        if ( ! isset( $state['lines'], $state['pairs'] ) ) { delete_transient( $key ); throw new RuntimeException( 'گزارش قدیمی است؛ دوباره محاسبه کنید.' ); }
        if ( ! $state['done'] ) {
            $result = wc_get_orders( array( 'type' => 'shop_order', 'status' => array_unique( array_merge( wc_get_is_paid_statuses(), array( 'refunded' ) ) ), 'date_created' => $state['from'] . '...' . $state['to'], 'limit' => 50, 'page' => $state['page'], 'paginate' => true, 'orderby' => 'ID', 'order' => 'ASC' ) );
            foreach ( $result->orders as $order ) {
                if ( $order->get_currency() !== $state['currency'] ) { $state['other_currency_orders']++; continue; }
                $state['orders']++; $net = (float) $order->get_total() - (float) $order->get_total_refunded(); $state['net_revenue'] += $net;
                $email = strtolower( trim( $order->get_billing_email() ) ); $customer_id = $order->get_customer_id();
                $customer_key = $customer_id ? 'user:' . $customer_id : ( $email ? 'email:' . $email : 'unknown:' . $order->get_id() );
                if ( ! isset( $state['customers'][$customer_key] ) ) { $state['customers'][$customer_key] = array( 'id' => $customer_id, 'name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ), 'email' => $email, 'orders' => 0, 'spend' => 0.0, 'last' => 0, 'products' => array() ); }
                $c =& $state['customers'][$customer_key]; $c['orders']++; $c['spend'] += $net; $date = $order->get_date_created(); $c['last'] = max( $c['last'], $date ? $date->getTimestamp() : 0 );
                foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
                    if ( ++$state['lines'] > 50000 ) { delete_transient( $key ); throw new RuntimeException( 'بیش از ۵۰۰۰۰ ردیف سفارش؛ بازه گزارش را کوتاه کنید.' ); }
                    $pid = $item->get_variation_id() ?: $item->get_product_id(); $qty = max( 0, (float) $item->get_quantity() + (float) $order->get_qty_refunded_for_item( $item_id ) );
                    $revenue = (float) $item->get_total() - abs( (float) $order->get_total_refunded_for_item( $item_id ) );
                    if ( ! isset( $state['products'][$pid] ) ) { $state['products'][$pid] = array( 'id' => $pid, 'name' => $item->get_name(), 'quantity' => 0, 'revenue' => 0.0, 'known_revenue' => 0.0, 'cost' => 0.0, 'unknown_lines' => 0 ); }
                    $p =& $state['products'][$pid]; $p['quantity'] += $qty; $p['revenue'] += $revenue;
                    if ( ! isset( $c['products'][(string) $pid] ) && ++$state['pairs'] > 20000 ) { delete_transient( $key ); throw new RuntimeException( 'بیش از ۲۰۰۰۰ ارتباط مشتری/کالا؛ بازه را کوتاه کنید.' ); }
                    $c['products'][(string) $pid] = ( $c['products'][(string) $pid] ?? 0 ) + $qty;
                    $native_data = NIL_Cost::native() ? $item->get_data() : array();
                    $native_cost = array_key_exists( 'cogs_value', $native_data ) && null !== $native_data['cogs_value'] ? $item->get_cogs_value( 'edit' ) : null;
                    $cost = null !== $native_cost && $item->get_quantity() > 0 ? (string) ( $native_cost / $item->get_quantity() ) : $item->get_meta( '_nil_unit_cost', true );
                    if ( '' !== $cost && ( null !== $native_cost || $item->get_meta( '_nil_cost_currency', true ) === $state['currency'] ) ) {
                        $total_cost = (float) $cost * $qty; $p['cost'] += $total_cost; $p['known_revenue'] += $revenue; $state['known_cost'] += $total_cost; $state['known_revenue'] += $revenue;
                    } else { $p['unknown_lines']++; $state['unknown_cost_lines']++; }
                    unset( $p );
                } unset( $c );
            }
            $state['done'] = $state['page'] >= $result->max_num_pages; $state['page']++;
            if ( count( $state['customers'] ) > 10000 || count( $state['products'] ) > 10000 ) { delete_transient( $key ); throw new RuntimeException( 'بازه گزارش را کوتاه‌تر کنید؛ بیش از ۱۰۰۰۰ مشتری یا محصول.' ); }
            if ( strlen( wp_json_encode( $state ) ) > 4 * 1024 * 1024 ) { delete_transient( $key ); throw new RuntimeException( 'گزارش بیش از ۴ مگابایت است؛ بازه را کوتاه کنید.' ); }
            set_transient( $key, $state, HOUR_IN_SECONDS );
        }
        if ( ! $state['done'] ) { return array( 'id' => $id, 'done' => false, 'orders' => $state['orders'], 'page' => $state['page'] ); }
        foreach ( $state['customers'] as &$c ) { $c['days_since_last'] = max( 0, (int) floor( ( time() - $c['last'] ) / DAY_IN_SECONDS ) ); $c['last_date'] = NIL_Dates::format( $c['last'] ); } unset( $c );
        foreach ( $state['products'] as &$p ) { $p['profit'] = $p['known_revenue'] - $p['cost']; $p['margin'] = $p['known_revenue'] > 0 ? round( 100 * $p['profit'] / $p['known_revenue'], 2 ) : null; } unset( $p );
        $state['customers'] = array_values( $state['customers'] ); $state['products'] = array_values( $state['products'] ); $state['profit'] = $state['known_revenue'] - $state['known_cost'];
        return array_merge( $state, array( 'id' => $id ) );
    }
}
