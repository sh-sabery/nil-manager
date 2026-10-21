<?php
// Run only in disposable WordPress Playground. SQLite shims exercise CRUD and
// endpoint workflows, not MySQL locking or concurrent checkout behavior.
if ( ! defined('IS_PLAYGROUND_PREVIEW') && ! defined('SQLITE_DB_DROPIN_VERSION') && ! defined('DB_ENGINE') ) {
    // No web entry point. This file is excluded from the installable plugin.
}
wp_set_current_user(1);
update_option('woocommerce_price_num_decimals',0);
$checks=0;
function check($condition,$message){global $checks;$checks++;if(!$condition)throw new Exception('FAIL: '.$message);}
function request_nil($method,$path,$body=null){
    $parts=explode('?',$path,2);
    $request=new WP_REST_Request($method,'/nil-bulk/v1/'.$parts[0]);
    if(isset($parts[1])){parse_str($parts[1],$params);$request->set_query_params($params);}
    if($body!==null){$request->set_header('Content-Type','application/json');$request->set_body(wp_json_encode($body));}
    $response=rest_do_request($request);
    if($response->get_status()>=400)throw new Exception(wp_json_encode($response->get_data()));
    return $response->get_data();
}
// Explicitly emulates MySQL-only statements on the disposable SQLite fixture.
add_filter('query',function($sql){
    if(stripos($sql,'SELECT GET_LOCK(')!==false || stripos($sql,'SELECT RELEASE_LOCK(')!==false)return 'SELECT 1';
    if(stripos($sql,'SELECT ENGINE FROM information_schema.TABLES')!==false)return "SELECT 'InnoDB'";
    return preg_replace('/ FOR UPDATE\s*$/i','',$sql);
});
function fresh_nil($id){clean_post_cache($id);wc_delete_product_transients($id);return wc_get_product($id);}
function row_nil($id){$rows=request_nil('GET','products?search='.$id);foreach($rows['rows'] as $row)if($row['id']==$id)return $row;throw new Exception('missing row '.$id);}
function preview_nil($rows,$rule=null){return request_nil('POST','preview',array('rows'=>$rows,'rule'=>$rule));}
function run_nil($id,$mode='apply'){
    for($i=0;$i<20;$i++){$r=request_nil('POST','job/'.$id.'/run',array('mode'=>$mode));if($r['done'])return;}
    throw new Exception('batch did not finish');
}
function plan_nil($id,$changes,$rule=null){
    $row=row_nil($id);$preview=preview_nil(array(array('id'=>$id,'version'=>$row['version'],'changes'=>$changes)),$rule);
    check(!$preview['errors'],'preview no errors: '.wp_json_encode($preview));
    check($preview['added']===1,'one change planned');
    request_nil('POST','job/'.$preview['job_id'].'/seal',array('count'=>1));return $preview['job_id'];
}
$p=new WC_Product_Variable();$p->set_name('بارونی نیل');$p->set_status('publish');$p->set_manage_stock(true);$p->set_stock_quantity(12);$parent=$p->save();
$a=new WC_Product_Variation();$a->set_parent_id($parent);$a->set_status('publish');$a->set_attributes(array('color'=>'مشکی','size'=>'M'));$a->set_regular_price('495000');$a->set_sale_price('450000');$a->set_manage_stock(true);$a->set_stock_quantity(5);$a_id=$a->save();
$b=new WC_Product_Variation();$b->set_parent_id($parent);$b->set_status('publish');$b->set_attributes(array('color'=>'سفید','size'=>'L'));$b->set_regular_price('695000');$b_id=$b->save();
WC_Product_Variable::sync($parent);
$s=new WC_Product_Simple();$s->set_name('تیشرت ساده');$s->set_status('publish');$s->set_regular_price('200000');$simple=$s->save();
$rows=request_nil('GET','products');check(count($rows['rows'])===4,'parent, two variations, simple');
check(row_nil($b_id)['values']['parent_stock']===true,'shared stock flagged');
check(row_nil($a_id)['values']['manage_stock']===true,'independent stock flagged');
$rule=array('operation'=>'percent','value'=>'10','rounding'=>'up','step'=>'10000','ending'=>'9000','target'=>'both');
$job=plan_nil($a_id,array(),$rule);
check(fresh_nil($a_id)->get_regular_price('edit')==='495000','preview does not mutate');
run_nil($job);check(fresh_nil($a_id)->get_regular_price('edit')==='549000','variation price saved');
check(fresh_nil($a_id)->get_sale_price('edit')==='499000','sale transformed');
check((int)fresh_nil($a_id)->get_stock_quantity('edit')===5,'price-only preserves stock');
run_nil($job);check(fresh_nil($a_id)->get_regular_price('edit')==='549000','repeated commit idempotent');
run_nil($job,'undo');check(fresh_nil($a_id)->get_regular_price('edit')==='495000','undo price');
check(fresh_nil($a_id)->get_sale_price('edit')==='450000','undo sale');
$bad=row_nil($parent);$r=preview_nil(array(array('id'=>$parent,'version'=>$bad['version'],'changes'=>array('regular_price'=>'100'))));check(count($r['errors'])===1,'cannot price variable parent');
$bad=row_nil($b_id);$r=preview_nil(array(array('id'=>$b_id,'version'=>$bad['version'],'changes'=>array('stock_quantity'=>'9'))));check(count($r['errors'])===1,'cannot overwrite inherited quantity');
$bad=row_nil($a_id);$r=preview_nil(array(array('id'=>$a_id,'version'=>$bad['version'],'changes'=>array('regular_price'=>'400000'))));check(count($r['errors'])===1,'invalid sale blocked');
$job=plan_nil($a_id,array('stock_quantity'=>'0'));run_nil($job);check((int)fresh_nil($a_id)->get_stock_quantity('edit')===0,'zero stock saved');check(fresh_nil($a_id)->get_stock_status('edit')==='outofstock','zero stock status');
run_nil($job,'undo');check((int)fresh_nil($a_id)->get_stock_quantity('edit')===5,'stock undo');
$job=plan_nil($parent,array('stock_quantity'=>'7'));run_nil($job);check((int)fresh_nil($parent)->get_stock_quantity('edit')===7,'parent shared stock updated');check((int)fresh_nil($b_id)->get_stock_quantity()===7,'variation inherits updated parent');
$job=plan_nil($simple,array('regular_price'=>'250000'));$p=fresh_nil($simple);$p->set_regular_price('240000');$p->save();run_nil($job);check(fresh_nil($simple)->get_regular_price('edit')==='240000','stale preview rejected');
$detail=request_nil('GET','job/'.$job);check($detail['items'][0]['state']==='skipped','conflict logged');
$job=plan_nil($simple,array('regular_price'=>'260000'));run_nil($job);$p=fresh_nil($simple);$p->set_regular_price('270000');$p->save();run_nil($job,'undo');check(fresh_nil($simple)->get_regular_price('edit')==='270000','undo conflict does not overwrite');
$detail=request_nil('GET','job/'.$job);check($detail['items'][0]['state']==='undo_failed','undo failure logged');
$job=plan_nil($simple,array('regular_price'=>'0'));run_nil($job);check(fresh_nil($simple)->get_regular_price('edit')==='0','zero price persisted');
$job=plan_nil($simple,array('manage_stock'=>true,'stock_quantity'=>'6'));run_nil($job);check(fresh_nil($simple)->get_manage_stock('edit')===true,'enable stock management');check((int)fresh_nil($simple)->get_stock_quantity('edit')===6,'initial quantity');run_nil($job,'undo');check(fresh_nil($simple)->get_manage_stock('edit')===false,'undo enables flag restored');check(fresh_nil($simple)->get_stock_quantity('edit')===null,'undo null quantity restored');
$job=plan_nil($a_id,array('sale_price'=>''));run_nil($job);check(fresh_nil($a_id)->get_sale_price('edit')==='','sale deletion');run_nil($job,'undo');check(fresh_nil($a_id)->get_sale_price('edit')==='450000','sale deletion undo');
// A price undo must preserve a newer checkout-driven stock reduction.
$job=plan_nil($a_id,array('regular_price'=>'599000'));run_nil($job);wc_update_product_stock($a_id,1,'decrease');run_nil($job,'undo');check(fresh_nil($a_id)->get_regular_price('edit')==='495000','price undo after stock decrease');check((int)fresh_nil($a_id)->get_stock_quantity('edit')===4,'new stock survives price undo');
wp_set_current_user(0);$response=rest_do_request(new WP_REST_Request('GET','/nil-bulk/v1/products'));check($response->get_status()>=400,'unauthenticated denied');
$result="PASS: $checks WooCommerce integration checks; WP ".get_bloginfo('version').'; WC '.WC_VERSION."; SQLite lock shim (no concurrency verification).\n";
file_put_contents('/nil-tests/integration-result.txt',$result);echo $result;
