<?php
/** Automatic, virtual WooCommerce coupons; never negative fees or product-price writes. */
defined( 'ABSPATH' ) || exit;

final class NIL_Payments {
    const OPTION = 'nil_payment_discounts';
    const CODE = 'nil-manager-payment-discount';
    private static $quote = array();

    public static function settings() {
        $value = get_option( self::OPTION, array() );
        return is_array( $value ) ? wp_parse_args( $value, array( 'enabled' => false, 'currency' => '', 'rules' => array() ) ) : array( 'enabled' => false, 'currency' => '', 'rules' => array() );
    }
    public static function validate( $input, $gateways ) {
        if ( ! is_array( $input ) || ! isset( $input['currency'] ) || get_woocommerce_currency() !== $input['currency'] || ! isset( $input['rules'] ) || ! is_array( $input['rules'] ) || count( $input['rules'] ) > 100 ) { throw new InvalidArgumentException( 'واحد پول یا فهرست روش‌ها تغییر کرده است؛ صفحه را تازه کنید.' ); }
        $out = array( 'enabled' => ! empty( $input['enabled'] ), 'currency' => get_woocommerce_currency(), 'rules' => array() );
        foreach ( $input['rules'] as $id => $rule ) {
            if ( ! isset( $gateways[$id] ) || ! is_array( $rule ) ) { throw new InvalidArgumentException( 'روش پرداخت ناشناخته است؛ صفحه را تازه کنید.' ); }
            $percent = NIL_Bulk_Price::number( $rule['percent'] ?? '' );
            $cap = $rule['cap'] ?? '';
            $cap = '' === $cap ? '' : NIL_Bulk_Price::number( $cap );
            if ( $percent < 0 || $percent > 100 || round( $percent, 2 ) !== $percent || ( '' !== $cap && ( $cap <= 0 || round( $cap, wc_get_price_decimals() ) !== $cap ) ) ) { throw new InvalidArgumentException( 'درصد باید بین صفر و ۱۰۰ با حداکثر دو رقم اعشار باشد؛ سقف مثبت یا خالی وارد کنید.' ); }
            $out['rules'][$id] = array( 'percent' => wc_format_decimal( $percent, 2 ), 'cap' => '' === $cap ? '' : wc_format_decimal( $cap, wc_get_price_decimals() ) );
        }
        return $out;
    }
    private static function active( $settings ) { return ! empty( $settings['enabled'] ) && get_woocommerce_currency() === $settings['currency']; }
    public static function amount( $base, $percent, $cap, $decimals ) {
        if ( ! is_numeric( $base ) || ! is_finite( (float) $base ) || $base <= 0 ) { return 0.0; }
        $amount = round( (float) $base * (float) $percent / 100, $decimals );
        return (float) max( 0.0, min( $base, $amount, '' === $cap ? $amount : (float) $cap ) );
    }
    /** Rebuild from current product prices every time, including quantity/variation/sale changes. */
    public static function sync( $cart ) {
        self::$quote = array();
        $settings = self::settings();
        // Never shadow a merchant's real coupon using the reserved code, including after activation.
        if ( ( self::active( $settings ) || $cart->has_discount( self::CODE ) ) && wc_get_coupon_id_by_code( self::CODE ) ) { return; }
        $codes = array_values( array_diff( $cart->get_applied_coupons(), array( self::CODE ) ) );
        $cart->set_applied_coupons( $codes );
        if ( ! self::active( $settings ) || ! WC()->session || $cart->is_empty() ) { return; }
        $id = WC()->session->get( 'chosen_payment_method', '' );
        $gateways = WC()->payment_gateways()->payment_gateways();
        $rule = is_string( $id ) ? ( $settings['rules'][$id] ?? null ) : null;
        if ( ! $rule || (float) $rule['percent'] <= 0 || ! isset( $gateways[$id] ) || 'yes' !== $gateways[$id]->enabled ) { return; }
        foreach ( $codes as $code ) { if ( ( new WC_Coupon( $code ) )->get_individual_use() ) { return; } }
        $base = 0.0;
        $inclusive = wc_prices_include_tax() && ! ( $cart->get_customer() && $cart->get_customer()->get_is_vat_exempt() );
        foreach ( $cart->get_cart() as $item ) {
            $args = array( 'qty' => $item['quantity'] );
            $base += $inclusive ? wc_get_price_including_tax( $item['data'], $args ) : wc_get_price_excluding_tax( $item['data'], $args );
        }
        $amount = self::amount( $base, $rule['percent'], $rule['cap'], wc_get_price_decimals() );
        if ( $amount <= 0 ) { return; }
        self::$quote = array_merge( $rule, array( 'gateway' => $id, 'currency' => $settings['currency'], 'amount' => $amount ) );
        $codes[] = self::CODE;
        $cart->set_applied_coupons( $codes );
    }
    public static function coupon( $data, $code ) {
        if ( self::CODE !== $code || wc_get_coupon_id_by_code( self::CODE ) ) { return $data; }
        return array( 'discount_type' => 'fixed_cart', 'amount' => self::$quote['amount'] ?? 0, 'individual_use' => false, 'free_shipping' => false );
    }
    public static function valid( $valid, $coupon ) {
        if ( self::CODE !== $coupon->get_code() || $coupon->get_id() ) { return $valid; }
        return $valid && ! empty( self::$quote ) && WC()->session && self::$quote['gateway'] === WC()->session->get( 'chosen_payment_method' ) && self::active( self::settings() );
    }
    public static function sort( $sort, $coupon ) { return self::CODE === $coupon->get_code() && ! $coupon->get_id() ? PHP_INT_MAX : $sort; }
    public static function label( $label, $coupon ) { return self::CODE === $coupon->get_code() && ! $coupon->get_id() ? 'تخفیف روش پرداخت' : $label; }
    public static function coupon_html( $html, $coupon, $discount_html ) { return self::CODE === $coupon->get_code() && ! $coupon->get_id() ? $discount_html : $html; }

    /** Store API supplies only an identifier; all amounts stay server-controlled. */
    public static function block_update( $data ) {
        $id = is_array( $data ) ? ( $data['payment_method'] ?? null ) : null;
        if ( ! is_string( $id ) || strlen( $id ) > 200 || ! WC()->session || ! WC()->cart ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nil_payment_method', 'روش پرداخت نامعتبر است.', 400 ); }
        if ( '' === $id && ! WC()->cart->needs_payment() ) {
            WC()->cart->calculate_totals(); // Rebuild a cached zero-total cart in a fresh request.
            if ( ! WC()->cart->needs_payment() && WC()->cart->has_discount( self::CODE ) ) { return; }
        }
        if ( '' !== $id && ! isset( WC()->payment_gateways()->get_available_payment_gateways()[$id] ) ) { throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'nil_payment_method', 'این روش پرداخت برای سبد فعلی در دسترس نیست.', 400 ); }
        WC()->session->set( 'chosen_payment_method', $id );
    }
    /** Runs before Store API totals/order creation, including direct POST without our JS. */
    public static function block_request( $customer, $request ) {
        $id = $request->get_param( 'payment_method' );
        if ( null !== $id ) { self::block_update( array( 'payment_method' => $id ) ); }
    }
    public static function capture( $order, $data ) {
        if ( $data instanceof WP_REST_Request && 'POST' !== $data->get_method() ) { return; } // Draft edits do not authorize a payment.
        $id = $data instanceof WP_REST_Request ? $data->get_param( 'payment_method' ) : ( $data['payment_method'] ?? '' );
        $own = null;
        foreach ( $order->get_items( 'coupon' ) as $item ) { if ( self::CODE === $item->get_code() ) { $own = $item; break; } }
        if ( ! $own || wc_get_coupon_id_by_code( self::CODE ) ) { $order->delete_meta_data( '_nil_payment_discount' ); return; }
        if ( ! $id && ! $order->needs_payment() && self::$quote ) { $id = self::$quote['gateway']; }
        if ( ! self::valid( true, new WC_Coupon( self::CODE ) ) || self::$quote['gateway'] !== $id ) { throw new Exception( 'روش پرداخت یا تخفیف تغییر کرده است؛ سبد را تازه کنید و دوباره تأیید کنید.' ); }
        $order->update_meta_data( '_nil_payment_discount', array_merge( self::$quote, array( 'discount' => $own->get_discount(), 'discount_tax' => $own->get_discount_tax() ) ) );
    }
    public static function posted_data( $data ) {
        if ( empty( $data['payment_method'] ) && WC()->cart && ! WC()->cart->needs_payment() ) {
            WC()->cart->calculate_totals();
            if ( ! WC()->cart->needs_payment() && WC()->cart->has_discount( self::CODE ) && self::$quote ) { $data['payment_method'] = self::$quote['gateway']; }
        }
        return $data;
    }
    /** A discounted pending order may only be retried with its original gateway. */
    public static function pay_gateways( $gateways ) {
        if ( ! is_wc_endpoint_url( 'order-pay' ) ) { return $gateways; }
        $id = absint( get_query_var( 'order-pay' ) );
        $key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
        $order = $id ? wc_get_order( $id ) : false;
        if ( ! $order || ! $key || ! hash_equals( $order->get_order_key(), $key ) || ! in_array( self::CODE, $order->get_coupon_codes(), true ) ) { return $gateways; }
        $receipt = $order->get_meta( '_nil_payment_discount' );
        if ( ! is_array( $receipt ) || empty( $receipt['gateway'] ) ) { return $gateways; }
        $gateway = is_array( $receipt ) ? ( $receipt['gateway'] ?? '' ) : '';
        return isset( $gateways[$gateway] ) ? array( $gateway => $gateways[$gateway] ) : array();
    }
    public static function assets() {
        if ( ! is_checkout() || is_wc_endpoint_url( 'order-pay' ) || is_order_received_page() || ! self::active( self::settings() ) ) { return; }
        $blocks = has_block( 'woocommerce/checkout', get_post() );
        wp_enqueue_script( 'nil-payment-checkout', plugins_url( 'assets/payment-checkout.min.js', __FILE__ ), $blocks ? array( 'wp-data', 'wc-blocks-checkout', 'wc-blocks-data-store' ) : array( 'jquery', 'wc-checkout' ), NIL_Bulk_Manager::VERSION, true );
        wp_localize_script( 'nil-payment-checkout', 'NIL_PAYMENT', array( 'blocks' => $blocks ) );
    }
    public static function page() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $gateways = WC()->payment_gateways()->payment_gateways();
        $settings = self::settings(); $notice = ''; $error = false; $posted = null;
        if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
            try {
                if ( ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'nil-payment-settings' ) ) { throw new RuntimeException( 'اعتبار فرم تمام شده است؛ صفحه را تازه کنید.' ); }
                $posted = wp_unslash( $_POST );
                if ( ! isset( $posted['revision'] ) || ! is_string( $posted['revision'] ) || ! hash_equals( hash( 'sha256', wp_json_encode( $settings ) ), $posted['revision'] ) ) { throw new RuntimeException( 'تنظیمات در پنجرهٔ دیگری تغییر کرده است؛ صفحه را تازه کنید.' ); }
                $next = self::validate( $posted, $gateways );
                if ( $next['enabled'] && wc_get_coupon_id_by_code( self::CODE ) ) { throw new RuntimeException( 'کد nil-manager-payment-discount برای این قابلیت رزرو است؛ ابتدا کوپن هم‌نام موجود را تغییر نام دهید.' ); }
                update_option( self::OPTION, $next, false );
                $settings = $next; $notice = 'تنظیمات ذخیره شد؛ از محاسبهٔ بعدی سبد اعمال می‌شود.';
            } catch ( Throwable $e ) { $error = true; $notice = $e->getMessage(); }
        }
        require __DIR__ . '/includes-payment-view.php';
    }
}
