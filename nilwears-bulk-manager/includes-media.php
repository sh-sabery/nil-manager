<?php
/** Media changes are previewed and checked again before each write. */
defined( 'ABSPATH' ) || exit;
final class NIL_Media {
    private static function check( $id ) {
        $post = get_post( $id );
        if ( ! current_user_can( 'upload_files' ) || ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $id ) || ! current_user_can( 'edit_post', $id ) ) { throw new RuntimeException( 'تصویر در دسترس نیست یا اجازه ندارید.' ); }
        return $post;
    }
    private static function version( $id ) {
        $file = get_attached_file( $id ); return hash( 'sha256', wp_json_encode( array( get_post( $id ), get_post_meta( $id ), $file, is_file( $file ) ? filemtime( $file ) : 0, is_file( $file ) ? filesize( $file ) : 0 ) ) );
    }
    public static function unused( $id ) {
        global $wpdb; $p = get_post( $id ); if ( ! $p || $p->post_parent ) { return false; }
        // Known reference encodings, not arbitrary digit fragments in colors or dates.
        $file = get_post_meta( $id, '_wp_attached_file', true ); if ( ! $file ) { return false; }
        $stem = pathinfo( basename( $file ), PATHINFO_FILENAME ); if ( strlen( $stem ) < 3 ) { return false; }
        $name_like = '%' . $wpdb->esc_like( $stem ) . '%';
        $n = absint( $id );
        $id_pattern = '(^|[,|]|\\[)' . $n . '([,|"\\]]|$)|i:' . $n . ';|s:[0-9]+:"' . $n . '";|:[[:space:]]*"?' . $n . '"?[[:space:]]*([,}\\]]|$)|wp-image-' . $n . '([^0-9]|$)|attachment[_-]' . $n . '([^0-9]|$)|=["\x27]' . $n . '["\x27]';
        $meta = $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM $wpdb->postmeta WHERE post_id<>%d AND (meta_value LIKE %s OR meta_value REGEXP %s) LIMIT 1", $id, $name_like, $id_pattern ) );
        if ( $meta || $wpdb->last_error ) { return false; }
        $content = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE ID<>%d AND (post_content LIKE %s OR post_excerpt LIKE %s OR post_content REGEXP %s) LIMIT 1", $id, $name_like, $name_like, $id_pattern ) );
        if ( $content || $wpdb->last_error ) { return false; }
        $option = $wpdb->get_var( $wpdb->prepare( "SELECT option_id FROM $wpdb->options WHERE option_name NOT LIKE %s AND option_name NOT LIKE %s AND (option_value LIKE %s OR option_value REGEXP %s) LIMIT 1", $wpdb->esc_like( '_transient_' ) . '%', $wpdb->esc_like( 'nil_media_last_' ) . '%', $name_like, $id_pattern ) );
        return ! $option && ! $wpdb->last_error;
    }
    public static function listing( $r ) {
        if ( ! current_user_can( 'upload_files' ) ) { throw new RuntimeException( 'اجازه مدیریت رسانه ندارید.' ); }
        $page = max( 1, absint( $r->get_param( 'page' ) ) ); $trash = '1' === (string) $r->get_param( 'trash' );
        $q = new WP_Query( array( 'post_type' => 'attachment', 'post_status' => $trash ? 'trash' : 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 20, 'paged' => $page, 's' => sanitize_text_field( $r->get_param( 'search' ) ?: '' ), 'orderby' => 'ID', 'order' => 'DESC' ) );
        $rows = array(); foreach ( $q->posts as $p ) {
            if ( ! current_user_can( 'edit_post', $p->ID ) ) { continue; } $file = get_attached_file( $p->ID );
            $rows[] = array( 'id' => $p->ID, 'title' => $p->post_title, 'alt' => get_post_meta( $p->ID, '_wp_attachment_image_alt', true ), 'file' => basename( $file ), 'url' => wp_get_attachment_image_url( $p->ID, 'thumbnail' ), 'bytes' => is_file( $file ) ? filesize( $file ) : 0, 'candidate' => null, 'version' => self::version( $p->ID ), 'trash' => $trash );
        }
        return array( 'rows' => $rows, 'page' => $page, 'pages' => (int) $q->max_num_pages );
    }
    public static function scan( $r ) {
        $b = $r->get_json_params(); $ids = $b['ids'] ?? array();
        if ( ! is_array( $ids ) || ! $ids || count( $ids ) > 5 ) { throw new InvalidArgumentException( 'هر بسته بررسی حداکثر ۵ تصویر دارد.' ); }
        $out = array(); foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
            self::check( $id ); $out[] = array( 'id' => $id, 'candidate' => self::unused( $id ) );
        } return $out;
    }
    public static function preview( $r ) {
        $b = $r->get_json_params(); $rows = $b['rows'] ?? array();
        if ( ! is_array( $rows ) || ! $rows || count( $rows ) > 20 ) { throw new InvalidArgumentException( '۱ تا ۲۰ تصویر انتخاب کنید.' ); }
        $action = $b['action'] ?? ''; if ( ! in_array( $action, array( 'alt','rename','optimize','trash','restore' ), true ) ) { throw new InvalidArgumentException( 'عملیات نامعتبر است.' ); }
        $out = array();
        foreach ( $rows as $row ) {
            $id = absint( $row['id'] ?? 0 ); $post = self::check( $id );
            if ( self::version( $id ) !== ( $row['version'] ?? '' ) ) { throw new RuntimeException( 'تصویر تغییر کرده است؛ تازه‌سازی کنید.' ); }
            if ( 'trash' === $action && ( ! current_user_can( 'delete_post', $id ) || ! self::unused( $id ) ) ) { throw new RuntimeException( 'تصویر #' . $id . ' بلااستفاده تشخیص داده نشد.' ); }
            $text = str_replace( array( '{id}','{title}','{filename}' ), array( $id, $post->post_title, pathinfo( get_attached_file( $id ), PATHINFO_FILENAME ) ), sanitize_text_field( $b['template'] ?? '' ) );
            if ( 'rename' === $action ) { $text = sanitize_file_name( $text ); if ( ! $text || strlen( $text ) > 150 || false !== strpos( $text, '.' ) ) { throw new InvalidArgumentException( 'نام جدید بدون پسوند، حداکثر ۱۵۰ بایت باشد.' ); } }
            $out[] = array( 'id' => $id, 'version' => $row['version'], 'before' => 'alt' === $action ? get_post_meta( $id, '_wp_attachment_image_alt', true ) : basename( get_attached_file( $id ) ), 'after' => in_array( $action, array( 'alt','rename' ), true ) ? $text : $action );
        }
        $quality = absint( $b['quality'] ?? 82 ); $width = absint( $b['width'] ?? 2400 );
        if ( $quality < 40 || $quality > 95 || $width < 320 || $width > 6000 ) { throw new InvalidArgumentException( 'کیفیت ۴۰ تا ۹۵ و عرض ۳۲۰ تا ۶۰۰۰.' ); }
        $token = wp_generate_uuid4(); set_transient( 'nil_media_' . get_current_user_id(), array( 'token' => $token, 'rows' => $out, 'action' => $action, 'quality' => $quality, 'width' => $width ), HOUR_IN_SECONDS );
        return array( 'token' => $token, 'rows' => $out, 'action' => $action );
    }
    private static function copy_file( $id, $basename, $optimize, $quality, $width ) {
        $old = get_attached_file( $id ); $uploads = wp_get_upload_dir(); $real = realpath( $old ); $base = realpath( $uploads['basedir'] );
        if ( ! $real || ! $base || 0 !== strpos( $real, $base . DIRECTORY_SEPARATOR ) || ! is_writable( dirname( $real ) ) ) { throw new RuntimeException( 'فایل محلی و قابل نوشتن نیست.' ); }
        $meta = wp_get_attachment_metadata( $id ); if ( ! is_array( $meta ) ) { throw new RuntimeException( 'متادیتای تصویر ناقص است.' ); }
        $ext = strtolower( pathinfo( $old, PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, array( 'jpg','jpeg','png','webp' ), true ) ) { throw new RuntimeException( 'فقط JPG، PNG و WebP.' ); }
        if ( ! $optimize && $basename === pathinfo( $real, PATHINFO_FILENAME ) ) { return array( 'file' => basename( $real ), 'saved_bytes' => 0 ); }
        $name = wp_unique_filename( dirname( $real ), $basename . '.' . $ext ); $new = dirname( $real ) . '/' . $name; $written = array();
        try {
            if ( count( $meta['sizes'] ?? array() ) > 50 ) { throw new RuntimeException( 'بیش از ۵۰ اندازهٔ تصویر؛ متادیتا را بررسی کنید.' ); }
            if ( $optimize ) {
                $dimensions = @getimagesize( $real );
                if ( ! $dimensions || filesize( $real ) > 8 * 1024 * 1024 || $dimensions[0] * $dimensions[1] > 8000000 ) { throw new RuntimeException( 'تصویر برای پردازش بزرگ است؛ حداکثر ۸ مگابایت و ۸ مگاپیکسل.' ); }
                $limit = trim( ini_get( 'memory_limit' ) );
                if ( preg_match( '/^(\d+)([KMG]?)$/i', $limit, $m ) ) {
                    $bytes = (int) $m[1] * ( 1024 ** ( $m[2] ? strpos( 'KMG', strtoupper( $m[2] ) ) + 1 : 0 ) );
                    if ( memory_get_usage( true ) + $dimensions[0] * $dimensions[1] * 8 + 16 * 1024 * 1024 > $bytes ) { throw new RuntimeException( 'رم آزاد برای پردازش این تصویر کافی نیست؛ تصویر را کوچک کنید.' ); }
                }
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $editor = wp_get_image_editor( $real ); if ( is_wp_error( $editor ) ) { throw new RuntimeException( $editor->get_error_message() ); }
                $size = $editor->get_size(); if ( $size['width'] > $width ) { $resized = $editor->resize( $width, null, false ); if ( is_wp_error( $resized ) ) { throw new RuntimeException( $resized->get_error_message() ); } }
                $q = $editor->set_quality( $quality ); if ( is_wp_error( $q ) ) { throw new RuntimeException( $q->get_error_message() ); }
                $saved = $editor->save( $new ); if ( is_wp_error( $saved ) ) { throw new RuntimeException( $saved->get_error_message() ); } $new = $saved['path']; $written[] = $new;
                if ( filesize( $new ) >= filesize( $real ) ) { unlink( $new ); return array( 'saved_bytes' => 0, 'message' => 'فایل بهینه کوچک‌تر نبود؛ نسخه قبلی حفظ شد.' ); }
                $meta['width'] = $saved['width']; $meta['height'] = $saved['height']; $meta['filesize'] = filesize( $new );
                // Existing size copies remain valid; they are never deleted automatically.
            } else { if ( ! copy( $real, $new ) ) { throw new RuntimeException( 'کپی فایل انجام نشد.' ); } $written[] = $new; }
            // Rename all generated sizes too. Keep old URLs valid for custom page builders.
            foreach ( $meta['sizes'] ?? array() as $size_key => $info ) {
                $source = realpath( dirname( $real ) . '/' . basename( $info['file'] ) ); if ( ! $source || ! is_file( $source ) ) { continue; }
                if ( 0 !== strpos( $source, $base . DIRECTORY_SEPARATOR ) || dirname( $source ) !== dirname( $real ) ) { throw new RuntimeException( 'مسیر اندازهٔ تصویر خارج از پوشهٔ مجاز است.' ); }
                $target_name = wp_unique_filename( dirname( $real ), pathinfo( basename( $new ), PATHINFO_FILENAME ) . '-' . $info['width'] . 'x' . $info['height'] . '.' . pathinfo( $source, PATHINFO_EXTENSION ) );
                $target = dirname( $real ) . '/' . $target_name; if ( ! copy( $source, $target ) ) { throw new RuntimeException( 'کپی اندازه‌های تصویر ناموفق بود.' ); } $written[] = $target; $meta['sizes'][$size_key]['file'] = $target_name;
            }
            $meta['file'] = _wp_relative_upload_path( $new );
            $history = get_post_meta( $id, '_nil_media_backups', true ); if ( ! is_array( $history ) ) { $history = array(); }
            $history[] = array( 'file' => $old, 'metadata' => wp_get_attachment_metadata( $id ), 'date' => gmdate( 'c' ) );
            update_post_meta( $id, '_nil_media_backups', array_slice( $history, -3 ) );
            update_attached_file( $id, $new ); wp_update_attachment_metadata( $id, $meta ); clean_post_cache( $id );
            return array( 'file' => basename( $new ), 'saved_bytes' => $optimize ? filesize( $real ) - filesize( $new ) : 0 );
        } catch ( Throwable $e ) { foreach ( $written as $file ) { @unlink( $file ); } throw $e; }
    }
    public static function apply( $r ) {
        $b = $r->get_json_params(); $key = 'nil_media_' . get_current_user_id(); $p = get_transient( $key );
        if ( ! $p || ! hash_equals( $p['token'], (string) ( $b['token'] ?? '' ) ) ) { throw new RuntimeException( 'پیش‌نمایش منقضی شده است یا پیش‌نمایش جدید جای آن را گرفته است.' ); }
        $lock = NIL_Bulk_Manager::lock(); $results = array();
        try {
            foreach ( $p['rows'] as $row ) {
                $id = $row['id'];
                try {
                    self::check( $id ); if ( self::version( $id ) !== $row['version'] ) { throw new RuntimeException( 'تصویر تغییر کرده؛ رد شد.' ); }
                    $action = $p['action']; $detail = array();
                    if ( 'alt' === $action ) { update_post_meta( $id, '_wp_attachment_image_alt', $row['after'] ); }
                    elseif ( in_array( $action, array( 'rename','optimize' ), true ) ) { $detail = self::copy_file( $id, 'rename' === $action ? $row['after'] : pathinfo( get_attached_file( $id ), PATHINFO_FILENAME ) . '-optimized', 'optimize' === $action, $p['quality'], $p['width'] ); }
                    elseif ( 'trash' === $action ) {
                        if ( ! current_user_can( 'delete_post', $id ) || ! self::unused( $id ) ) { throw new RuntimeException( 'تصویر ارجاع دارد یا مجوز حذف ندارید.' ); }
                        // Do not call wp_delete_attachment: MEDIA_TRASH=false would destroy files.
                        update_post_meta( $id, '_nil_media_trashed', time() ); $result = wp_update_post( array( 'ID' => $id, 'post_status' => 'trash' ), true ); if ( is_wp_error( $result ) ) { throw new RuntimeException( $result->get_error_message() ); }
                    } elseif ( 'restore' === $action ) { if ( ! get_post_meta( $id, '_nil_media_trashed', true ) ) { throw new RuntimeException( 'این تصویر توسط نیل بایگانی نشده است.' ); } wp_update_post( array( 'ID' => $id, 'post_status' => 'inherit' ) ); delete_post_meta( $id, '_nil_media_trashed' ); }
                    $results[] = array( 'id' => $id, 'state' => 'applied', 'detail' => $detail );
                } catch ( Throwable $e ) { $results[] = array( 'id' => $id, 'state' => 'skipped', 'error' => $e->getMessage() ); }
            }
            delete_transient( $key ); update_option( 'nil_media_last_' . get_current_user_id(), array( 'date' => gmdate( 'c' ), 'action' => $p['action'], 'results' => $results ), false );
            return $results;
        } finally { NIL_Bulk_Manager::unlock( $lock ); }
    }
}
