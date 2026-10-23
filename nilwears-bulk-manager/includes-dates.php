<?php
/** Persian presentation; UTC timestamps/Gregorian storage remain machine-readable.
 * Year-start arithmetic adapted from jalaali-js 2.0.1 (MIT), Borkowski algorithm.
 * See JALAALI-LICENSE.txt. Supported UI input: Persian years 1200..1499.
 */
defined( 'ABSPATH' ) || exit;
final class NIL_Dates {
    private static function start( $year ) {
        $breaks = array( -61,9,38,199,426,686,756,818,1111,1181,1210,1635,2060,2097,2192,2262,2324,2394,2456,3178 );
        $gy = $year + 621; $leap = -14; $previous = $breaks[0];
        foreach ( array_slice( $breaks, 1 ) as $next ) {
            $jump = $next - $previous; if ( $year < $next ) { break; }
            $leap += intdiv( $jump, 33 ) * 8 + intdiv( $jump % 33, 4 ); $previous = $next;
        }
        $n = $year - $previous; $leap += intdiv( $n, 33 ) * 8 + intdiv( $n % 33 + 3, 4 );
        if ( $jump % 33 === 4 && $jump - $n === 4 ) { $leap++; }
        $march = 20 + $leap - ( intdiv( $gy, 4 ) - intdiv( ( intdiv( $gy, 100 ) + 1 ) * 3, 4 ) - 150 );
        return new DateTimeImmutable( sprintf( '%04d-03-%02d', $gy, $march ), new DateTimeZone( 'UTC' ) );
    }
    public static function normalize( $value ) {
        if ( ! is_string( $value ) || strlen( $value ) > 64 ) { throw new InvalidArgumentException( 'تاریخ معتبر وارد کنید.' ); }
        $value = str_replace( array( "\u{200e}", "\u{200f}", "\u{061c}" ), '', $value );
        return strtr( trim( $value ), array_combine( preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY ), str_split( '01234567890123456789' ) ) );
    }
    public static function parse( $value, $calendar = 'persian', $clock = false ) {
        $text = self::normalize( $value );
        if ( 'persian' === $calendar ) {
            if ( ! preg_match( '#^(1[2-4][0-9]{2})[/-](\d{1,2})[/-](\d{1,2})(?:[ T](\d{2}):(\d{2}))?$#D', $text, $m ) || $clock !== isset( $m[4] ) ) { throw new InvalidArgumentException( 'تاریخ شمسی با قالب ۱۴۰۵/۰۸/۰۱ و ساعت ۱۵:۳۰ وارد کنید؛ سال ۱۲۰۰ تا ۱۴۹۹.' ); }
            $y = (int) $m[1]; $month = (int) $m[2]; $day = (int) $m[3]; $start = self::start( $y );
            $length = $month <= 6 ? 31 : ( $month <= 11 ? 30 : (int) $start->diff( self::start( $y + 1 ) )->days - 336 );
            if ( $month < 1 || $month > 12 || $day < 1 || $day > $length ) { throw new InvalidArgumentException( 'روز یا ماه شمسی معتبر نیست؛ کبیسه را بررسی کنید.' ); }
            $offset = $month <= 6 ? ( $month - 1 ) * 31 : 186 + ( $month - 7 ) * 30;
            $date = $start->modify( '+' . ( $offset + $day - 1 ) . ' days' )->format( 'Y-m-d' );
            if ( ! $clock ) { return $date; }
            if ( (int) $m[4] > 23 || (int) $m[5] > 59 ) { throw new InvalidArgumentException( 'ساعت باید بین ۰۰:۰۰ و ۲۳:۵۹ باشد.' ); }
            $local = $date . ' ' . $m[4] . ':' . $m[5]; $time = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $local, wp_timezone() );
            if ( ! $time || $time->format( 'Y-m-d H:i' ) !== $local ) { throw new InvalidArgumentException( 'این ساعت در منطقهٔ زمانی وردپرس وجود ندارد.' ); }
            return $time->getTimestamp();
        }
        // Existing REST clients may still send strict Gregorian dates / ISO instants.
        if ( 'gregorian' !== $calendar ) { throw new InvalidArgumentException( 'تقویم نامعتبر است.' ); }
        if ( ! $clock ) {
            if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $text, $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) { throw new InvalidArgumentException( 'تاریخ معتبر نیست.' ); }
            return $text;
        }
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,3})?(?:Z|[+-]\d{2}:\d{2})$/D', $text ) ) { throw new InvalidArgumentException( 'زمان ISO معتبر نیست.' ); }
        $parts = date_parse( $text ); if ( $parts['warning_count'] || $parts['error_count'] ) { throw new InvalidArgumentException( 'زمان معتبر نیست.' ); }
        return ( new DateTimeImmutable( $text ) )->getTimestamp();
    }
    public static function format( $timestamp, $clock = false, $digits = true ) {
        if ( ! $timestamp ) { return '—'; }
        $date = ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() ); $jy = (int) $date->format( 'Y' ) - 621;
        if ( $jy < 1200 || $jy > 1500 ) { return 'خارج از بازهٔ تقویم'; }
        $day = new DateTimeImmutable( $date->format( 'Y-m-d' ), new DateTimeZone( 'UTC' ) ); $start = self::start( $jy );
        if ( $day < $start ) { $jy--; $start = self::start( $jy ); }
        if ( $jy < 1200 || $jy > 1499 ) { return 'خارج از بازهٔ تقویم'; }
        $offset = (int) $start->diff( $day )->days; $month = $offset < 186 ? intdiv( $offset, 31 ) + 1 : intdiv( $offset - 186, 30 ) + 7; $dom = $offset < 186 ? $offset % 31 + 1 : ( $offset - 186 ) % 30 + 1;
        $out = sprintf( '%04d/%02d/%02d', $jy, $month, $dom ) . ( $clock ? ' ' . $date->format( 'H:i' ) : '' );
        return $digits ? strtr( $out, array_combine( str_split( '0123456789' ), preg_split( '//u', '۰۱۲۳۴۵۶۷۸۹', -1, PREG_SPLIT_NO_EMPTY ) ) ) : $out;
    }
}
