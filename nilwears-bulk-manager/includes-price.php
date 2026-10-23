<?php
/** Price rules use integer minor units to avoid float-ending errors. */
defined( 'ABSPATH' ) || exit;

final class NIL_Bulk_Price {
    public static function discount( $price, array $rule, $decimals ) {
        $mode = $rule['operation'] ?? '';
        if ( 'remove' === $mode ) { return ''; }
        if ( ! in_array( $mode, array( 'percent', 'fixed' ), true ) ) { throw new InvalidArgumentException( 'نوع تخفیف نامعتبر است.' ); }
        if ( '' === $price || null === $price || self::number( $price ) <= 0 ) { throw new InvalidArgumentException( 'برای تخفیف، قیمت اصلی مثبت لازم است.' ); }
        $value = self::number( $rule['value'] ?? '' );
        if ( $value <= 0 || ( 'percent' === $mode && $value > 100 ) ) { throw new InvalidArgumentException( 'تخفیف باید مثبت و درصد حداکثر ۱۰۰ باشد.' ); }
        $rule['value'] = -$value;
        $sale = self::calculate( $price, $rule, $decimals );
        if ( (float) $sale >= (float) $price ) { throw new InvalidArgumentException( 'قیمت تخفیف پس از رندکردن باید کمتر از قیمت اصلی باشد.' ); }
        return $sale;
    }

    public static function stock( array $before, array $rule ) {
        if ( ( 'variation' === $before['type'] && $before['parent_stock'] && ! $before['manage_stock'] ) || ( 'variable' === $before['type'] && ! $before['manage_stock'] ) ) { throw new InvalidArgumentException( 'موجودی مشترک را روی والد و موجودی مستقل را روی ورییشن تغییر دهید.' ); }
        $mode = $rule['operation'] ?? '';
        if ( 'out' === $mode ) {
            if ( ! $before['manage_stock'] ) { return array( 'stock_status' => 'outofstock' ); }
            if ( 'no' !== $before['backorders'] ) { throw new InvalidArgumentException( 'پیش‌خرید فعال است؛ ابتدا در ووکامرس آن را غیرفعال کنید یا فقط تعداد را صفر کنید.' ); }
            return array( 'stock_quantity' => 0 );
        }
        if ( ! in_array( $mode, array( 'set','add','subtract' ), true ) ) { throw new InvalidArgumentException( 'عملیات موجودی نامعتبر است.' ); }
        $n = self::number( $rule['value'] ?? '' );
        if ( $n < 0 || floor( $n ) !== $n || $n > 100000000 ) { throw new InvalidArgumentException( 'تعداد باید عدد صحیح بین صفر و ۱۰۰ میلیون باشد.' ); }
        if ( 'set' !== $mode ) {
            if ( ! $before['manage_stock'] || null === $before['stock_quantity'] ) { throw new InvalidArgumentException( 'افزایش/کاهش به موجودی عددی فعال نیاز دارد؛ ابتدا تعیین تعداد را انجام دهید.' ); }
            $n = (float) $before['stock_quantity'] + ( 'add' === $mode ? $n : -$n );
        }
        if ( $n < 0 || $n > 100000000 || floor( $n ) !== $n ) { throw new InvalidArgumentException( 'موجودی حاصل خارج از بازه است؛ کاهش بیشتر از موجودی مجاز نیست.' ); }
        return array( 'manage_stock' => true, 'stock_quantity' => (int) $n );
    }

    public static function number( $value ) {
        if ( ! is_scalar( $value ) || is_bool( $value ) ) {
            throw new InvalidArgumentException( 'عدد نامعتبر است.' );
        }
        $value = strtr( trim( (string) $value ), array_combine(
            array( '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩','٫' ),
            array( '0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9','.' )
        ) );
        $value = str_replace( array( ',', '٬' ), '', $value );
        if ( ! preg_match( '/^-?\d+(?:\.\d{1,6})?$/D', $value ) || abs( (float) $value ) > 100000000000 ) {
            throw new InvalidArgumentException( 'عدد معتبر تا ۱۰۰ میلیارد وارد کنید؛ ممیز مجاز است.' );
        }
        return (float) $value;
    }

    public static function calculate( $price, array $rule, $decimals ) {
        if ( '' === $price || null === $price ) { return ''; }
        $scale = 10 ** $decimals;
        $base  = self::number( $price );
        $value = self::number( isset( $rule['value'] ) ? $rule['value'] : 0 );
        $op    = isset( $rule['operation'] ) ? $rule['operation'] : 'round';
        switch ( $op ) {
            case 'percent': $base *= ( 1 + $value / 100 ); break;
            case 'fixed': $base += $value; break;
            case 'set': $base = $value; break;
            case 'round': break;
            default: throw new InvalidArgumentException( 'نوع تغییر قیمت نامعتبر است.' );
        }
        if ( ! is_finite( $base ) || $base < 0 || $base > 100000000000 ) {
            throw new InvalidArgumentException( 'قیمت حاصل باید بین صفر و ۱۰۰ میلیارد باشد.' );
        }
        $minor = (int) round( round( $base, $decimals, PHP_ROUND_HALF_UP ) * $scale, 0, PHP_ROUND_HALF_UP );
        $mode  = isset( $rule['rounding'] ) ? $rule['rounding'] : 'none';
        if ( 'none' !== $mode ) {
            if ( ! in_array( $mode, array( 'up', 'down', 'nearest' ), true ) ) {
                throw new InvalidArgumentException( 'جهت رندکردن نامعتبر است.' );
            }
            $step   = (int) round( self::number( isset( $rule['step'] ) ? $rule['step'] : 10000 ) * $scale );
            $ending = (int) round( self::number( isset( $rule['ending'] ) ? $rule['ending'] : 9000 ) * $scale );
            if ( $step <= 0 || $ending < 0 || $ending >= $step ) {
                throw new InvalidArgumentException( 'گام باید مثبت و پایان قیمت باید از گام کوچک‌تر باشد.' );
            }
            $position = ( $minor - $ending ) / $step;
            $index = 'up' === $mode ? ceil( $position ) : ( 'down' === $mode ? floor( $position ) : floor( $position + 0.5 ) );
            $minor = (int) ( $index * $step + $ending );
            if ( $minor < 0 && 'down' === $mode ) {
                throw new InvalidArgumentException( 'پایان انتخابی برای این قیمت، خروجی غیرمنفی رو به پایین ندارد.' );
            }
            $minor = max( $ending, $minor );
        }
        if ( $minor / $scale > 100000000000 ) { throw new InvalidArgumentException( 'قیمت رندشده بیش از حد مجاز است.' ); }
        return number_format( $minor / $scale, $decimals, '.', '' );
    }
}
