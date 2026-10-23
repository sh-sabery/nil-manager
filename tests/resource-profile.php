<?php
// Disposable fixtures only. Counts PHP heap and request wall time, not host RAM/CPU%.
wp_set_current_user(1);
function nil_resource_measure($fn){global $wpdb;wp_cache_flush();gc_collect_cycles();if(function_exists('memory_reset_peak_usage'))memory_reset_peak_usage();$mem=memory_get_usage();$sql=$wpdb->num_queries;$start=hrtime(true);$result=$fn();$elapsed=(hrtime(true)-$start)/1e6;return array('queries'=>$wpdb->num_queries-$sql,'wall_ms'=>round($elapsed,2),'php_peak_delta_bytes'=>max(0,memory_get_peak_usage()-$mem),'rows'=>isset($result['rows'])?count($result['rows']):null);}
$profiles=array();
foreach(array(2,40) as $n){$ids=array();$rows=array();for($i=0;$i<$n;$i++){$p=new WC_Product_Simple();$p->set_name('Resource fixture '.$n.'-'.$i);$p->set_status('publish');$p->set_regular_price('500000');$p->update_meta_data('_nil_cost_price','300000');$id=$p->save();$ids[]=$id;$row=NIL_Bulk_Manager::row($p);$rows[]=array('id'=>$id,'version'=>$row['version'],'changes'=>array('regular_price'=>'550000'));}
 $profiles[$n]=array('preview'=>nil_resource_measure(function()use($rows){return preview_nil($rows);}), 'export'=>nil_resource_measure(function()use($ids){return request_nil('POST','sheet/export',array('ids'=>$ids,'format'=>'csv'));}));}
if(version_compare(NIL_Bulk_Manager::VERSION,'1.8.0','>=')){check($profiles[40]['preview']['queries']<=$profiles[2]['preview']['queries']+3,'preview query growth is bounded');check($profiles[40]['export']['queries']<=$profiles[2]['export']['queries']+3,'export query growth is bounded');}
$out=array('version'=>NIL_Bulk_Manager::VERSION,'php'=>PHP_VERSION,'wp'=>get_bloginfo('version'),'wc'=>WC_VERSION,'profiles'=>$profiles,'note'=>'Single cold-cache samples in disposable PHP.wasm / SQLite, PHP heap only. Wall time includes emulation and is not production latency or host CPU/RAM.');
file_put_contents('/nil-tests/resources-'.NIL_Bulk_Manager::VERSION.'.json',wp_json_encode($out,JSON_PRETTY_PRINT));echo wp_json_encode($out);
