<?php
require __DIR__.'/mysql-helpers.php';
$nm_checks = 0; $nm_processes = array(); $nm_cases = array();
function nm_check($condition,$message) { global $nm_checks; $nm_checks++; if (!$condition) throw new RuntimeException($message); }
function nm_plan($pid,$changes) {
    $row = NIL_Bulk_Manager::row(nm_fresh($pid));
    $p = nm_request('POST','preview',array('rows'=>array(array('id'=>$pid,'version'=>$row['version'],'changes'=>$changes))));
    nm_check(!$p['errors'] && $p['added']===1,'one complete preview');
    nm_request('POST','job/'.$p['job_id'].'/seal',array('count'=>1)); return $p['job_id'];
}
function nm_product($stock = 10) {
    $p = new WC_Product_Simple(); $p->set_name('Native concurrency fixture'); $p->set_status('publish');
    $p->set_regular_price('100000'); $p->set_manage_stock(true); $p->set_stock_quantity($stock); return $p->save();
}
function nm_order($pid) {
    $order = wc_create_order(); $order->set_billing_email('fixture@example.test');
    $order->add_product(nm_fresh($pid),1); $order->calculate_totals(); $order->save(); return $order->get_id();
}
function nm_case($name) { $dir = nil_test_dir().'/'.$name; if (!mkdir($dir)) throw new RuntimeException('Case already exists'); return $dir; }
function nm_spawn($mode,$subject,$pid,$dir,$label) {
    global $nm_processes;
    $out = $dir.'/'.$label.'.out'; $err = $dir.'/'.$label.'.err';
    $handle = proc_open(array(PHP_BINARY,__DIR__.'/mysql-worker.php',$mode,(string)$subject,(string)$pid,$dir),array(0=>array('pipe','r'),1=>array('file',$out,'w'),2=>array('file',$err,'w')),$pipes);
    if (!is_resource($handle)) throw new RuntimeException('Worker did not start');
    fclose($pipes[0]); $process = array('handle'=>$handle,'out'=>$out,'err'=>$err); $nm_processes[]=$handle; return $process;
}
function nm_finish($process) {
    $end = microtime(true)+25;
    do { $status = proc_get_status($process['handle']); if (!$status['running']) break; usleep(10000); } while (microtime(true)<$end);
    if ($status['running']) { proc_terminate($process['handle']); throw new RuntimeException('Worker timeout'); }
    $exit = $status['exitcode']; proc_close($process['handle']);
    if ($exit!==0) throw new RuntimeException('Worker failed: '.file_get_contents($process['err']));
    $result = json_decode(file_get_contents($process['out']),true);
    if (!is_array($result)) throw new RuntimeException('Worker did not return JSON: '.file_get_contents($process['out']));
    return $result;
}
function nm_wait_for_lock($connection) {
    global $wpdb;
    $maria = stripos($wpdb->get_var('SELECT VERSION()'),'MariaDB')!==false;
    $sql = $maria ? 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON w.requesting_trx_id=t.trx_id WHERE t.trx_mysql_thread_id=%d' : 'SELECT COUNT(*) FROM performance_schema.data_lock_waits w JOIN performance_schema.threads t ON w.REQUESTING_THREAD_ID=t.THREAD_ID WHERE t.PROCESSLIST_ID=%d';
    $end = microtime(true)+8;
    do { if ((int)$wpdb->get_var($wpdb->prepare($sql,$connection))>0) return true; usleep(20000); } while (microtime(true)<$end);
    return false;
}
function nm_overlap($job,$pid,$order,$dir,$undo = false) {
    $a = nm_spawn($undo?'undo-held':'apply-held',$job,$pid,$dir,'price');
    $held = nm_wait($dir,'held');
    $b = nm_spawn('reduce-order',$order,$pid,$dir,'order');
    $attempt = nm_wait($dir,'stock-attempt');
    nm_check($held['connection']!==$attempt['connection'],'separate database connections');
    nm_check(nm_wait_for_lock($attempt['connection']),'server reports a real InnoDB row-lock wait for order stock');
    nm_signal($dir,'release');
    nm_check(nm_finish($a)['response']['status']===200,'held product operation completed');
    nm_check(nm_finish($b)['response']['status']===200,'waiting order stock reduction completed');
}
try {
    $server = $wpdb->get_var('SELECT VERSION()');
    nm_check(!defined('SQLITE_DB_DROPIN_VERSION'),'native database, no SQLite drop-in');
    nm_check((bool)$wpdb->get_var('SELECT CONNECTION_ID()'),'real server connection');
    // Verify rollback itself before relying on plugin transaction behaviour.
    $table=$wpdb->prefix.'nil_transaction_probe'; $wpdb->query("CREATE TABLE $table (id INT PRIMARY KEY) ENGINE=InnoDB");
    $wpdb->query('START TRANSACTION'); $wpdb->query("INSERT INTO $table VALUES (1)"); $wpdb->query('ROLLBACK');
    nm_check((int)$wpdb->get_var("SELECT COUNT(*) FROM $table")===0,'native rollback removes uncommitted data'); $wpdb->query("DROP TABLE $table");

    $pid=nm_product(); $job=nm_plan($pid,array('regular_price'=>'110000')); $order=nm_order($pid); $dir=nm_case('price-and-lock');
    $a=nm_spawn('apply-held',$job,$pid,$dir,'price'); $held=nm_wait($dir,'held');
    $contender=nm_finish(nm_spawn('apply',$job,$pid,$dir,'contender'));
    nm_check($contender['connection']!==$held['connection'],'advisory-lock contender has another connection');
    nm_check($contender['response']['status']>=400,'real GET_LOCK excludes a simultaneous plugin execution');
    $checkout=nm_spawn('reduce-order',$order,$pid,$dir,'order'); $attempt=nm_wait($dir,'stock-attempt');
    nm_check(nm_wait_for_lock($attempt['connection']),'server observes order UPDATE blocked by price transaction');
    nm_signal($dir,'release'); nm_check(nm_finish($a)['response']['status']===200,'price operation finishes'); nm_check(nm_finish($checkout)['response']['status']===200,'order reduction resumes');
    nm_check(nm_fresh($pid)->get_regular_price('edit')==='110000','price committed'); nm_check((int)nm_fresh($pid)->get_stock_quantity('edit')===9,'order stock decrement survives price apply');
    nm_check(nm_finish(nm_spawn('apply',$job,$pid,$dir,'retry'))['response']['status']===200,'same job can be retried after lock release');
    nm_finish(nm_spawn('reduce-order',$order,$pid,$dir,'order-retry'));
    nm_check((int)nm_fresh($pid)->get_stock_quantity('edit')===9,'repeated order reduction is idempotent'); $nm_cases[]='price apply, advisory exclusion, real row-lock wait, order/job retries';

    $dir=nm_case('undo-and-order'); nm_overlap($job,$pid,nm_order($pid),$dir,true);
    nm_check(nm_fresh($pid)->get_regular_price('edit')==='100000','price undo restores the previous price'); nm_check((int)nm_fresh($pid)->get_stock_quantity('edit')===8,'order decrement survives simultaneous price undo'); $nm_cases[]='price undo versus order stock reduction';

    $job=nm_plan($pid,array('stock_quantity'=>'15')); $dir=nm_case('stock-and-order'); nm_overlap($job,$pid,nm_order($pid),$dir);
    nm_check((int)nm_fresh($pid)->get_stock_quantity('edit')===14,'order decrement is applied after explicit stock set');
    nm_finish(nm_spawn('undo',$job,$pid,$dir,'stock-undo'));
    nm_check(nm_request('GET','job/'.$job)['items'][0]['state']==='undo_failed','stock undo rejects newer order stock'); nm_check((int)nm_fresh($pid)->get_stock_quantity('edit')===14,'stock undo preserves the sale'); $nm_cases[]='stock set versus order reduction and guarded undo';

    $job=nm_plan($pid,array('regular_price'=>'120000')); $dir=nm_case('order-before-apply'); nm_finish(nm_spawn('reduce-order',nm_order($pid),$pid,$dir,'order')); nm_finish(nm_spawn('apply',$job,$pid,$dir,'apply'));
    nm_check(nm_request('GET','job/'.$job)['items'][0]['state']==='skipped','preview made before a committed order is rejected as stale'); nm_check(nm_fresh($pid)->get_regular_price('edit')==='100000' && (int)nm_fresh($pid)->get_stock_quantity('edit')===13,'stale application preserves price and order stock'); $nm_cases[]='committed order versus stale preview';

    $rollback=nm_product(6); $job=nm_plan($rollback,array('regular_price'=>'130000')); $dir=nm_case('log-rollback'); nm_finish(nm_spawn('fail-log',$job,$rollback,$dir,'failure'));
    $detail=nm_request('GET','job/'.$job); nm_check($detail['items'][0]['state']==='skipped' && strpos($detail['items'][0]['error'],'Fixture forced log write failure')!==false,'forced post-save history failure is logged');
    nm_check(nm_fresh($rollback)->get_regular_price('edit')==='100000','product metadata rolls back with the failed log');
    nm_check((float)$wpdb->get_var($wpdb->prepare("SELECT min_price FROM {$wpdb->wc_product_meta_lookup} WHERE product_id=%d",$rollback))===100000.0,'WooCommerce price lookup rolls back too'); $nm_cases[]='transaction rollback after product writes';

    $parent=new WC_Product_Variable(); $parent->set_name('Shared stock fixture'); $parent->set_status('publish'); $parent->set_manage_stock(true); $parent->set_stock_quantity(10); $parent_id=$parent->save();
    $child=new WC_Product_Variation(); $child->set_parent_id($parent_id); $child->set_status('publish'); $child->set_regular_price('100000'); $child_id=$child->save(); WC_Product_Variable::sync($parent_id);
    $job=nm_plan($child_id,array('regular_price'=>'110000')); $dir=nm_case('inherited-stock'); nm_overlap($job,$child_id,nm_order($child_id),$dir);
    nm_check((int)nm_fresh($parent_id)->get_stock_quantity('edit')===9,'variation order reduces shared parent stock once'); nm_check(nm_fresh($child_id)->get_regular_price('edit')==='110000','variation price committed during shared-stock sale'); $nm_cases[]='variation pricing versus shared-parent order stock';

    $result=array('plugin'=>NIL_Bulk_Manager::VERSION,'php'=>PHP_VERSION,'database'=>$server,'wordpress'=>get_bloginfo('version'),'woocommerce'=>WC_VERSION,'hpos'=>get_option('woocommerce_custom_orders_table_enabled'),'assertions'=>$nm_checks,'cases'=>$nm_cases,'sqlite_lock_shim'=>false,'scope'=>'Separate native PHP/database connections, observed server lock waits and WooCommerce order-stock reduction. Not an HTTP checkout/payment or production load test.');
    file_put_contents(nil_test_dir().'/mysql-concurrency.json',wp_json_encode($result,JSON_PRETTY_PRINT)); echo 'PASS native concurrency: '.wp_json_encode($result)."\n";
} catch (Throwable $e) {
    fwrite(STDERR,'FAIL native concurrency: '.$e->getMessage()."\n".$e->getTraceAsString()."\n"); exit(1);
} finally {
    foreach($nm_processes as $handle) if(is_resource($handle)){ $status=proc_get_status($handle); if($status['running']) proc_terminate($handle); proc_close($handle); }
}
