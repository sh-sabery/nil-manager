<?php
/** Read-only, bounded resolution of explicit pricing selections. */
defined( 'ABSPATH' ) || exit;
final class NIL_Selection {
    public static function rows( $r ) {
        $ids = $r->get_json_params()['ids'] ?? null;
        if ( ! is_array( $ids ) || ! $ids || count( $ids ) > 50 ) { throw new InvalidArgumentException( '۱ تا ۵۰ شناسهٔ کالا در هر درخواست لازم است.' ); }
        foreach ( $ids as &$id ) { if ( is_bool( $id ) || ! filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) { throw new InvalidArgumentException( 'شناسهٔ کالا نامعتبر است.' ); } $id = (int) $id; } unset( $id );
        $ids = array_values( array_unique( $ids ) ); _prime_post_caches( $ids, true, true ); $parents = array(); $requested = array();
        foreach ( $ids as $id ) { $post = get_post( $id ); if ( $post && $post->post_parent ) { $parents[] = $post->post_parent; } }
        if ( $parents ) { _prime_post_caches( array_unique( $parents ), true, true ); }
        $variable = array();
        foreach ( $ids as $id ) { $p = NIL_Bulk_Manager::editable( wc_get_product( $id ) ); $requested[$id] = $p; if ( $p->is_type( 'variable' ) ) { $variable[] = $id; } }
        $children = array();
        if ( $variable ) {
            $q = new WP_Query( array( 'post_type' => 'product_variation', 'post_parent__in' => $variable, 'post_status' => array( 'publish','private','draft','pending','future' ), 'posts_per_page' => 5001, 'no_found_rows' => true, 'orderby' => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ) ) );
            if ( count( $q->posts ) > 5000 ) { throw new RuntimeException( 'بیش از ۵۰۰۰ ورییشن؛ انتخاب را محدود کنید.' ); }
            foreach ( $q->posts as $post ) { if ( current_user_can( 'edit_post', $post->ID ) ) { $children[$post->post_parent][] = wc_get_product( $post->ID ); } }
        }
        $out = array();
        foreach ( $requested as $p ) { $out[$p->get_id()] = NIL_Bulk_Manager::row( $p ); foreach ( $children[$p->get_id()] ?? array() as $child ) { if ( $child ) { $out[$child->get_id()] = NIL_Bulk_Manager::row( NIL_Bulk_Manager::editable( $child ) ); } } }
        if ( count( $out ) > 5000 ) { throw new RuntimeException( 'بیش از ۵۰۰۰ ردیف؛ انتخاب را محدود کنید.' ); }
        return array( 'rows' => array_values( $out ) );
    }
}
