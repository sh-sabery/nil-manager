<?php
/** Bounded, values-only XLSX/CSV interchange. No formula execution. */
defined( 'ABSPATH' ) || exit;
final class NIL_Sheet {
    const LIMIT = 5000;
    public static function columns() {
        return array( 'id','sku','version','type','parent_id','name','regular_price','sale_price','cost_price','manage_stock','stock_quantity','stock_status','category_ids','brand_ids','description','short_description','attributes','parent_sku','profit','margin','currency' );
    }
    public static function file( $headers, $rows, $format, $name ) {
        if ( ! function_exists( 'wp_tempnam' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
        if ( count( $rows ) > self::LIMIT ) { throw new RuntimeException( 'حداکثر ۵۰۰۰ ردیف در هر فایل.' ); }
        $all = array_merge( array( $headers ), $rows );
        if ( 'xlsx' !== $format ) {
            $f = fopen( 'php://temp', 'w+' ); fwrite( $f, "\xEF\xBB\xBF" );
            foreach ( $all as $row ) { fputcsv( $f, array_map( function( $v ) { $s = (string) $v; if ( is_int( $v ) || is_float( $v ) ) { return $s; } return preg_match( '/^[=+@\-\t\r]/u', $s ) ? "'" . $s : $s; }, $row ), ',', '"', '' ); }
            rewind( $f ); $bytes = stream_get_contents( $f ); fclose( $f ); $format = 'csv';
        } else {
            if ( ! class_exists( 'ZipArchive' ) ) { throw new RuntimeException( 'برای XLSX افزونه PHP zip لازم است؛ CSV در دسترس است.' ); }
            $tmp = wp_tempnam( 'nil-sheet' ); $zip = new ZipArchive();
            try {
                if ( true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) { throw new RuntimeException( 'ساخت فایل اکسل ناموفق بود.' ); }
                $zip->addFromString( '[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>' );
                $zip->addFromString( '_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
                $zip->addFromString( 'xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Nilwears" sheetId="1" r:id="rId1"/></sheets></workbook>' );
                $zip->addFromString( 'xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>' );
                $xml = '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0" rightToLeft="1"/></sheetViews><sheetData>';
                foreach ( $all as $n => $row ) {
                    $xml .= '<row r="' . ( $n + 1 ) . '">';
                    foreach ( array_values( $row ) as $i => $value ) {
                        $column = ''; $number = $i + 1; while ( $number ) { $number--; $column = chr( 65 + $number % 26 ) . $column; $number = intdiv( $number, 26 ); }
                        $text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string) $value );
                        if ( ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) ) {
                            $xml .= '<c r="' . $column . ( $n + 1 ) . '" t="n"><v>' . (string) $value . '</v></c>';
                        } else { $xml .= '<c r="' . $column . ( $n + 1 ) . '" t="inlineStr"><is><t xml:space="preserve">' . htmlspecialchars( $text, ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</t></is></c>'; }
                    } $xml .= '</row>';
                }
                $zip->addFromString( 'xl/worksheets/sheet1.xml', $xml . '</sheetData></worksheet>' ); $zip->close(); $zip = null; $bytes = file_get_contents( $tmp );
            } finally { if ( $zip instanceof ZipArchive ) { $zip->close(); } @unlink( $tmp ); }
        }
        if ( strlen( $bytes ) > 8 * 1024 * 1024 ) { throw new RuntimeException( 'فایل از ۸ مگابایت بزرگ‌تر است؛ محدوده را کوچک کنید.' ); }
        return array( 'name' => sanitize_file_name( $name ) . '.' . $format, 'data' => base64_encode( $bytes ), 'mime' => 'xlsx' === $format ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv;charset=utf-8' );
    }
    public static function export( $r ) {
        $b = $r->get_json_params();
        if ( isset( $b['report'] ) ) {
            if ( ! is_array( $b['report'] ) || ! is_array( $b['headers'] ?? null ) || count( $b['headers'] ) > 40 ) { throw new InvalidArgumentException( 'گزارش نامعتبر است.' ); }
            foreach ( $b['report'] as $row ) { if ( ! is_array( $row ) || count( $row ) > 40 ) { throw new InvalidArgumentException( 'ردیف نامعتبر است.' ); } foreach ( $row as $v ) { if ( ! is_scalar( $v ) && null !== $v ) { throw new InvalidArgumentException( 'سلول نامعتبر است.' ); } } }
            return self::file( $b['headers'], $b['report'], $b['format'] ?? 'xlsx', 'nil-report-' . gmdate( 'Ymd' ) );
        }
        $ids = $b['ids'] ?? array(); if ( ! is_array( $ids ) || count( $ids ) > self::LIMIT ) { throw new InvalidArgumentException( 'حداکثر ۵۰۰۰ محصول در هر فایل.' ); }
        $rows = array(); $headers = self::columns();
        foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
            $p = NIL_Bulk_Manager::editable( wc_get_product( $id ) ); $row = NIL_Bulk_Manager::row( $p ); $data = $row['values'];
            $parent = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : false; $data['parent_sku'] = $parent ? $parent->get_sku( 'edit' ) : '';
            $data['id'] = $id; $data['version'] = $row['version']; $attrs = array();
            if ( $p->is_type( 'variable' ) ) { foreach ( $p->get_attributes() as $attr ) { $attrs[$attr->get_name()] = $attr->get_options(); } }
            elseif ( $p->is_type( 'variation' ) ) { $attrs = $p->get_attributes(); }
            $effective = $p->get_price( 'edit' ); $known = ! $p->is_type( 'variable' ) && '' !== $data['cost_price'] && '' !== $effective;
            $data['profit'] = $known ? (float) $effective - (float) $data['cost_price'] : '';
            $data['margin'] = $known && (float) $effective > 0 ? round( 100 * $data['profit'] / (float) $effective, 2 ) : '';
            $data['currency'] = get_woocommerce_currency();
            $data['attributes'] = wp_json_encode( $attrs, JSON_UNESCAPED_UNICODE ); $data['manage_stock'] = $data['manage_stock'] ? '1' : '0';
            $data['category_ids'] = implode( '|', $data['category_ids'] ); $data['brand_ids'] = implode( '|', $data['brand_ids'] );
            $line = array(); foreach ( $headers as $key ) { $value = $data[$key] ?? ''; if ( '' !== $value && null !== $value && in_array( $key, array( 'id','parent_id','regular_price','sale_price','cost_price','stock_quantity','manage_stock' ), true ) ) { $value = (float) $value; } $line[] = $value; } $rows[] = $line;
        }
        return self::file( $headers, $rows, $b['format'] ?? 'xlsx', 'nil-products-' . gmdate( 'Ymd' ) );
    }
    private static function xml( $text ) {
        if ( false !== stripos( $text, '<!DOCTYPE' ) || false !== stripos( $text, '<!ENTITY' ) || strlen( $text ) > 24 * 1024 * 1024 ) { throw new RuntimeException( 'ساختار XML مجاز نیست.' ); }
        $old = libxml_use_internal_errors( true );
        try { $xml = simplexml_load_string( $text, 'SimpleXMLElement', LIBXML_NONET ); if ( false === $xml ) { throw new RuntimeException( 'ساختار فایل اکسل خراب است.' ); } return $xml; }
        finally { libxml_clear_errors(); libxml_use_internal_errors( $old ); }
    }
    public static function parse( $bytes, $format ) {
        if ( ! function_exists( 'wp_tempnam' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
        if ( strlen( $bytes ) > 8 * 1024 * 1024 ) { throw new RuntimeException( 'حداکثر حجم فایل ۸ مگابایت.' ); }
        $rows = array();
        if ( 'xlsx' === $format ) {
            if ( ! class_exists( 'ZipArchive' ) || ! function_exists( 'simplexml_load_string' ) ) { throw new RuntimeException( 'برای XLSX افزونه‌های zip و SimpleXML لازم است.' ); }
            $tmp = wp_tempnam( 'nil-import' ); file_put_contents( $tmp, $bytes ); $z = new ZipArchive(); $opened = false;
            try {
                if ( true !== $z->open( $tmp ) ) { throw new RuntimeException( 'فایل اکسل باز نشد.' ); } $opened = true;
                $size = 0; if ( $z->numFiles > 1000 ) { throw new RuntimeException( 'تعداد اجزای فایل زیاد است.' ); }
                for ( $i = 0; $i < $z->numFiles; $i++ ) { $stat = $z->statIndex( $i ); $size += $stat['size']; } if ( $size > 32 * 1024 * 1024 ) { throw new RuntimeException( 'حجم بازشده فایل بیش از حد مجاز است.' ); }
                $shared = array(); $strings = $z->getFromName( 'xl/sharedStrings.xml' );
                if ( false !== $strings ) { foreach ( self::xml( $strings )->xpath( '//*[local-name()="si"]' ) as $si ) { $text = ''; foreach ( $si->xpath( './/*[local-name()="t"]' ) as $t ) { $text .= (string) $t; } $shared[] = $text; } }
                $workbook = self::xml( (string) $z->getFromName( 'xl/workbook.xml' ) );
                $sheets = $workbook->xpath( '//*[local-name()="sheet"]' ); if ( ! $sheets ) { throw new RuntimeException( 'برگه‌ای پیدا نشد.' ); }
                $relation = (string) $sheets[0]->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' )['id'];
                $rels = self::xml( (string) $z->getFromName( 'xl/_rels/workbook.xml.rels' ) ); $path = '';
                foreach ( $rels->xpath( '//*[local-name()="Relationship"]' ) as $rel ) { if ( (string) $rel['Id'] === $relation && 'External' !== (string) $rel['TargetMode'] ) { $path = (string) $rel['Target']; } }
                $path = '/' === substr( $path, 0, 1 ) ? ltrim( $path, '/' ) : 'xl/' . $path;
                if ( ! preg_match( '#^xl/worksheets/[A-Za-z0-9_/-]+\.xml$#', $path ) || false !== strpos( $path, '..' ) ) { throw new RuntimeException( 'مسیر برگه مجاز نیست.' ); }
                $xml = self::xml( (string) $z->getFromName( $path ) );
                foreach ( $xml->xpath( '//*[local-name()="sheetData"]/*[local-name()="row"]' ) as $row ) {
                    $line = array(); foreach ( $row->xpath( './*[local-name()="c"]' ) as $cell ) {
                        if ( $cell->xpath( './*[local-name()="f"]' ) ) { throw new RuntimeException( 'فایل شامل فرمول است؛ پیش از ورود، Paste Values کنید.' ); }
                        if ( ! preg_match( '/^([A-Z]+)[0-9]+$/', (string) $cell['r'], $m ) ) { throw new RuntimeException( 'نشانی سلول نامعتبر است.' ); }
                        $col = 0; foreach ( str_split( $m[1] ) as $c ) { $col = $col * 26 + ord( $c ) - 64; } if ( $col > 100 ) { throw new RuntimeException( 'حداکثر ۱۰۰ ستون.' ); }
                        $v = $cell->xpath( './*[local-name()="v"]' ); $value = $v ? (string) $v[0] : '';
                        if ( 's' === (string) $cell['t'] ) { $value = $shared[(int) $value] ?? ''; }
                        elseif ( 'inlineStr' === (string) $cell['t'] ) { $value = ''; foreach ( $cell->xpath( './/*[local-name()="t"]' ) as $t ) { $value .= (string) $t; } }
                        $line[$col - 1] = $value;
                    }
                    if ( $line ) { $out = array_fill( 0, max( array_keys( $line ) ) + 1, '' ); foreach ( $line as $key => $v ) { $out[$key] = $v; } $rows[] = $out; }
                    if ( count( $rows ) > self::LIMIT + 1 ) { throw new RuntimeException( 'حداکثر ۵۰۰۰ ردیف.' ); }
                }
            } finally { if ( $opened ) { $z->close(); } @unlink( $tmp ); }
        } elseif ( 'csv' === $format ) {
            $f = fopen( 'php://temp', 'w+' ); fwrite( $f, preg_replace( '/^\xEF\xBB\xBF/', '', $bytes ) ); rewind( $f );
            try {
                $sample = fgets( $f ); rewind( $f ); $delimiter = substr_count( (string) $sample, ';' ) > substr_count( (string) $sample, ',' ) ? ';' : ',';
                while ( false !== ( $line = fgetcsv( $f, 0, $delimiter, '"', '' ) ) ) {
                    if ( count( $line ) > 100 ) { throw new RuntimeException( 'حداکثر ۱۰۰ ستون.' ); }
                    if ( array( null ) !== $line ) { $rows[] = $line; } if ( count( $rows ) > self::LIMIT + 1 ) { throw new RuntimeException( 'حداکثر ۵۰۰۰ ردیف.' ); }
                }
            } finally { fclose( $f ); }
        } else { throw new InvalidArgumentException( 'فقط CSV یا XLSX.' ); }
        if ( count( $rows ) < 2 ) { throw new RuntimeException( 'فایل باید عنوان ستون و حداقل یک ردیف داشته باشد.' ); }
        $headers = array_shift( $rows ); return array( 'headers' => $headers, 'rows' => $rows );
    }
    public static function read_request( $r ) {
        $b = $r->get_json_params(); $bytes = base64_decode( $b['data'] ?? '', true ); if ( false === $bytes ) { throw new InvalidArgumentException( 'داده فایل نامعتبر است.' ); }
        $out = self::parse( $bytes, $b['format'] ?? '' ); $id = wp_generate_uuid4();
        set_transient( 'nil_sheet_' . get_current_user_id(), array_merge( $out, array( 'id' => $id ) ), HOUR_IN_SECONDS );
        return array( 'id' => $id, 'headers' => $out['headers'], 'sample' => array_slice( $out['rows'], 0, 3 ), 'count' => count( $out['rows'] ) );
    }
    public static function prepare( $r ) {
        $b = $r->get_json_params(); $file = get_transient( 'nil_sheet_' . get_current_user_id() );
        if ( ! $file || ! hash_equals( $file['id'], (string) ( $b['id'] ?? '' ) ) ) { throw new RuntimeException( 'فایل منقضی شده؛ دوباره انتخاب کنید.' ); }
        $mapping = $b['mapping'] ?? array(); $used = array();
        foreach ( $mapping as $i => $field ) { if ( '' === $field ) { continue; } if ( ! in_array( $field, self::columns(), true ) || isset( $used[$field] ) ) { throw new InvalidArgumentException( 'نگاشت تکراری یا نامعتبر است.' ); } $used[$field] = true; }
        if ( empty( $used['id'] ) && empty( $used['sku'] ) ) { throw new InvalidArgumentException( 'شناسه یا SKU را نگاشت کنید.' ); }
        $updates = array(); $creates = array(); $variations = array(); $errors = array(); $seen = array();
        foreach ( $file['rows'] as $i => $line ) {
            try {
                $data = array(); foreach ( $mapping as $index => $field ) { if ( '' !== $field ) { $data[$field] = (string) ( $line[(int) $index] ?? '' ); } }
                $id = absint( $data['id'] ?? 0 ); $sku = $data['sku'] ?? ''; $sku_id = '' === $sku ? 0 : wc_get_product_id_by_sku( $sku );
                if ( $id && $sku_id && $id !== $sku_id ) { throw new RuntimeException( 'شناسه و SKU به دو محصول متفاوت اشاره می‌کنند.' ); }
                $id = $id ?: $sku_id;
                if ( ! $id ) {
                    if ( empty( $b['allow_create'] ) ) { throw new RuntimeException( 'محصول پیدا نشد؛ ایجاد جدید خاموش است.' ); }
                    if ( ( $data['type'] ?? 'simple' ) === 'variation' ) {
                        $pid = absint( $data['parent_id'] ?? 0 ); $by_sku = empty( $data['parent_sku'] ) ? 0 : wc_get_product_id_by_sku( $data['parent_sku'] );
                        if ( $pid && $by_sku && $pid !== $by_sku ) { throw new RuntimeException( 'شناسه و SKU والد متفاوت‌اند.' ); }
                        $data['parent_id'] = $pid ?: $by_sku; $parent = NIL_Bulk_Manager::editable( wc_get_product( $data['parent_id'] ) );
                        if ( ! $parent->is_type( 'variable' ) ) { throw new RuntimeException( 'والد باید محصول متغیر موجود باشد.' ); }
                        $data['attributes'] = json_decode( $data['attributes'] ?? '', true ); if ( ! is_array( $data['attributes'] ) || ! $data['attributes'] ) { throw new RuntimeException( 'attributes باید شیء JSON از ویژگی به مقدار باشد.' ); }
                        if ( isset( $data['manage_stock'] ) ) { if ( ! in_array( $data['manage_stock'], array( '0','1','false','true' ), true ) ) { throw new RuntimeException( 'manage_stock فقط ۰/۱.' ); } $data['manage_stock'] = in_array( $data['manage_stock'], array( '1','true' ), true ); }
                        $variations[] = $data; if ( count( $variations ) > 100 ) { throw new RuntimeException( 'حداکثر ۱۰۰ ورییشن جدید در فایل.' ); } continue;
                    }
                    $data['token'] = substr( hash( 'sha256', $b['id'] . ':' . $i . ':' . get_current_user_id() ), 0, 32 );
                    $data['type'] = $data['type'] ?? 'simple'; $data['status'] = 'draft';
                    if ( isset( $data['attributes'] ) ) { $data['attributes'] = json_decode( $data['attributes'], true ); }
                    foreach ( array( 'category_ids','brand_ids' ) as $key ) { if ( isset( $data[$key] ) ) { $data[$key] = '' === $data[$key] ? array() : explode( '|', $data[$key] ); } }
                    if ( empty( $data['name'] ) ) { throw new RuntimeException( 'نام محصول جدید الزامی است.' ); }
                    if ( '' !== $sku && isset( $seen['sku:' . $sku] ) ) { throw new RuntimeException( 'SKU تکراری در فایل.' ); } $seen['sku:' . $sku] = true;
                    $creates[] = $data; continue;
                }
                if ( isset( $seen[$id] ) ) { throw new RuntimeException( 'محصول در فایل تکرار شده است.' ); } $seen[$id] = true;
                $p = NIL_Bulk_Manager::editable( wc_get_product( $id ) ); $row = NIL_Bulk_Manager::row( $p ); $changes = array();
                if ( ! empty( $data['version'] ) && $data['version'] !== $row['version'] ) { throw new RuntimeException( 'محصول پس از خروجی گرفتن تغییر کرده است.' ); }
                foreach ( $data as $key => $value ) {
                    if ( ! array_key_exists( $key, $row['values'] ) || in_array( $key, array( 'type','parent_id' ), true ) ) { continue; }
                    if ( ! empty( $data['version'] ) && is_string( $row['values'][$key] ) && $row['values'][$key] !== $value && preg_match( "/^'[=+@\\-\\t\\r]/", $value ) ) { $value = substr( $value, 1 ); }
                    if ( 'manage_stock' === $key ) { if ( ! in_array( strtolower( $value ), array( '1','0','true','false' ), true ) ) { throw new RuntimeException( 'manage_stock فقط ۰/۱.' ); } $value = in_array( strtolower( $value ), array( '1','true' ), true ); }
                    elseif ( in_array( $key, array( 'category_ids','brand_ids' ), true ) ) { $value = '' === $value ? array() : array_map( 'absint', explode( '|', $value ) ); sort( $value ); }
                    if ( $p->is_type( 'variable' ) && in_array( $key, array( 'regular_price','sale_price' ), true ) ) { continue; }
                    if ( is_array( $value ) ? $value !== $row['values'][$key] : (string) $value !== (string) $row['values'][$key] ) { $changes[$key] = $value; }
                }
                if ( $changes ) { $updates[] = array( 'id' => $id, 'version' => $row['version'], 'changes' => $changes, 'parent' => $p->is_type( 'variable' ) ); }
            } catch ( Throwable $e ) { $errors[] = array( 'line' => $i + 2, 'error' => $e->getMessage() ); }
        }
        usort( $updates, function( $a, $b ) { return (int) $a['parent'] <=> (int) $b['parent']; } ); foreach ( $updates as &$u ) { unset( $u['parent'] ); } unset( $u );
        return array( 'updates' => $updates, 'creates' => $creates, 'variations' => $variations, 'errors' => $errors );
    }
}
