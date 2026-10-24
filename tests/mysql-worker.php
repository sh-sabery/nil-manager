<?php
ob_start();
try {
    require __DIR__.'/mysql-helpers.php';
    $mode = $argv[1] ?? ''; $subject = $argv[2] ?? ''; $pid = (int)($argv[3] ?? 0); $dir = realpath($argv[4] ?? '');
    if (!$dir || dirname($dir) !== realpath(nil_test_dir())) throw new RuntimeException('Invalid worker barrier path.');
    $connection = (int)$wpdb->get_var('SELECT CONNECTION_ID()');
    if (in_array($mode,array('apply-held','undo-held'),true)) {
        add_action('woocommerce_before_product_object_save',function($product) use ($pid,$dir,$connection) {
            static $held = false;
            if (!$held && $product->get_id() === $pid) { $held = true; nm_signal($dir,'held',array('connection'=>$connection)); nm_wait($dir,'release'); }
        });
    }
    if ($mode === 'reduce-order') {
        add_filter('query',function($sql) use ($dir,$connection) {
            global $wpdb;
            if (stripos(ltrim($sql),'UPDATE ')===0 && strpos($sql,$wpdb->postmeta)!==false && strpos($sql,"'_stock'")!==false) {
                nm_signal($dir,'stock-attempt',array('connection'=>$connection));
            }
            return $sql; // Observe the real stock UPDATE; never rewrite SQL.
        });
        wc_reduce_stock_levels((int)$subject);
        $result = array('status'=>200,'body'=>array('order'=>(int)$subject));
    } else {
        if ($mode === 'fail-log') {
            add_filter('query',function($sql) {
                global $wpdb; static $failed = false;
                if (!$failed && stripos(ltrim($sql),'UPDATE ')===0 && strpos($sql,$wpdb->prefix.'nil_bulk_items')!==false && strpos($sql,"'applied'")!==false) {
                    $failed = true; throw new RuntimeException('Fixture forced log write failure');
                }
                return $sql;
            });
        }
        if (!in_array($mode,array('apply-held','undo-held','apply','undo','fail-log'),true)) throw new RuntimeException('Unknown worker mode.');
        $result = nm_request('POST','job/'.$subject.'/run',array('mode'=>strpos($mode,'undo')===0?'undo':'apply'),true);
    }
    $noise = ob_get_clean(); if (trim($noise)) fwrite(STDERR,$noise);
    echo wp_json_encode(array('connection'=>$connection,'response'=>$result));
} catch (Throwable $e) {
    if (ob_get_level()) ob_end_clean();
    fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n"); exit(1);
}
