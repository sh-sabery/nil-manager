<?php
/** Explicit, bounded cleanup. Never runs on storefront requests. */
defined( 'ABSPATH' ) || exit;
final class NIL_Maintenance {
    public static function clean( $r ) {
        global $wpdb; $b = $r->get_json_params(); $history = ! empty( $b['history'] );
        $days = $history ? max( 30, absint( $b['days'] ?? 90 ) ) : 7;
        $jobs = $wpdb->prefix . 'nil_bulk_jobs'; $items = $wpdb->prefix . 'nil_bulk_items';
        $lock = NIL_Bulk_Manager::lock();
        try {
            NIL_Bulk_Manager::require_transactions();
            $states = $history ? "('done','undone')" : "('draft')";
            $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $jobs WHERE user_id=%d AND state IN $states AND created_at<%s ORDER BY created_at LIMIT 100", get_current_user_id(), gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'خواندن سابقه ناموفق بود.' ); }
            if ( ! $ids ) { return array( 'deleted' => 0, 'more' => false ); }
            $placeholders = implode( ',', array_fill( 0, count( $ids ), '%s' ) );
            if ( false === $wpdb->query( 'START TRANSACTION' ) ) { throw new RuntimeException( 'شروع تراکنش ناموفق بود.' ); }
            if ( false === $wpdb->query( $wpdb->prepare( "DELETE FROM $items WHERE job_id IN ($placeholders)", $ids ) ) || false === $wpdb->query( $wpdb->prepare( "DELETE FROM $jobs WHERE id IN ($placeholders)", $ids ) ) || false === $wpdb->query( 'COMMIT' ) ) { throw new RuntimeException( 'پاکسازی سابقه انجام نشد.' ); }
            return array( 'deleted' => count( $ids ), 'more' => 100 === count( $ids ) );
        } catch ( Throwable $e ) { $wpdb->query( 'ROLLBACK' ); throw $e; }
        finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
}
