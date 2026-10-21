<?php
// Run after suite-integration.php, only in the disposable fixture.
wp_set_current_user(1);
$start=$wpdb->num_queries;NIL_Bulk_Manager::assets('dashboard');check($wpdb->num_queries===$start,'unrelated admin page performs no plugin asset queries');
$start=$wpdb->num_queries;NIL_Bulk_Manager::boot();check($wpdb->num_queries===$start,'plugin boot itself executes zero SQL');
check(!wp_style_is('nil-bulk','enqueued')&&!wp_script_is('nil-bulk','enqueued')&&!wp_script_is('nil-suite','enqueued'),'no plugin assets on storefront/unrelated screen');
$seen=array();$observe=function($sql)use(&$seen){$seen[]=$sql;return $sql;};add_filter('query',$observe);
request_nil('GET','media');remove_filter('query',$observe);check(!array_filter($seen,function($sql){return stripos($sql,'REGEXP')!==false;}),'media listing runs zero reference-scan queries');
$ml=request_nil('GET','media');$ids=array_slice(wp_list_pluck($ml['rows'],'id'),0,2);$scan=request_nil('POST','media/scan',array('ids'=>$ids));check(count($scan)===count($ids),'reference scans are explicit and bounded');check(rejected_nil('POST','media/scan',array('ids'=>range(1,6))),'oversized reference scans rejected');
// Compare cold page query counts with two and forty variations.
$parents=array();foreach(array(2,40) as $count){$p=new WC_Product_Variable();$p->set_name('Query fixture '.$count);$p->set_status('publish');$pid=$p->save();for($i=0;$i<$count;$i++){$v=new WC_Product_Variation();$v->set_parent_id($pid);$v->set_status('publish');$v->set_regular_price('10000');$v->save();}WC_Product_Variable::sync($pid);$parents[]=$pid;}
$profiles=array();$counts=array();foreach($parents as $pid){wp_cache_flush();$profile=array();$trace=function($sql)use(&$profile){$profile[]=$sql;return $sql;};add_filter('query',$trace);$before=$wpdb->num_queries;$rows=request_nil('GET','products?search='.$pid);$counts[]=$wpdb->num_queries-$before;remove_filter('query',$trace);$profiles[]=$profile;check(count($rows['rows'])===($pid===$parents[0]?3:41),'all variations retained after batch loading');}
file_put_contents('/nil-tests/query-profile.json',wp_json_encode($profiles));
check($counts[1]<=$counts[0]+6,'variation queries do not grow linearly: '.wp_json_encode($counts));
// One active temporary file per user, old tokens fail rather than accumulating.
$csv=base64_encode("id,regular_price\n".$simple.",12345\n");$old=request_nil('POST','sheet/read',array('format'=>'csv','data'=>$csv));$next=request_nil('POST','sheet/read',array('format'=>'csv','data'=>$csv));check(rejected_nil('POST','sheet/prepare',array('id'=>$old['id'],'mapping'=>array('id','regular_price'))),'replaced file token rejected');
$slot=get_transient('nil_sheet_'.get_current_user_id());check($slot['id']===$next['id'],'single active sheet cache slot');
// Cleanup respects age, state and ownership; current undo history remains intact.
$jobs=$wpdb->prefix.'nil_bulk_jobs';$items=$wpdb->prefix.'nil_bulk_items';$oldid=wp_generate_uuid4();$recentid=wp_generate_uuid4();$doneid=wp_generate_uuid4();$foreignid=wp_generate_uuid4();
foreach(array(array($oldid,1,'draft',10),array($recentid,1,'draft',0),array($doneid,1,'done',10),array($foreignid,999,'draft',10)) as $j){$wpdb->insert($jobs,array('id'=>$j[0],'user_id'=>$j[1],'state'=>$j[2],'created_at'=>gmdate('Y-m-d H:i:s',time()-$j[3]*DAY_IN_SECONDS),'config'=>'{}'));}
$wpdb->insert($items,array('job_id'=>$oldid,'product_id'=>$simple,'label'=>'fixture','before_json'=>'{}','after_json'=>'{}','fields_json'=>'[]','state'=>'pending','error'=>''));
$out=request_nil('POST','maintenance',array('history'=>false));check($out['deleted']===1,'only old own draft deleted');check(!(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $items WHERE job_id=%s",$oldid)),'draft items removed together');foreach(array($recentid,$doneid,$foreignid) as $id)check((bool)$wpdb->get_var($wpdb->prepare("SELECT id FROM $jobs WHERE id=%s",$id)),'recent/undo/foreign history preserved');
$result='PASS: '.$checks.' combined assertions; cold variation queries 2=>'.$counts[0].', 40=>'.$counts[1].'; WP '.get_bloginfo('version').'; WC '.WC_VERSION.'; HPOS '.get_option('woocommerce_custom_orders_table_enabled').'; SQLite shim, no MySQL concurrency verification.'."\n";file_put_contents('/nil-tests/performance-result.txt',$result);echo $result;
