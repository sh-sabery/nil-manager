<?php
/** Price rules use integer minor units to avoid float-ending errors. */
defined( 'ABSPATH' ) || exit;

final class NIL_Bulk_Price {
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
