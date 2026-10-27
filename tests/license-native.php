<?php
// A separate PHP process with the real WordPress option/REST/SQL APIs and fake Guard HTTP.
$site=realpath((string)getenv('NIL_TEST_WP_ROOT'));
if(PHP_SAPI!=='cli'||!$site||!preg_match('/^nil-manager-mysql\.[A-Za-z0-9]+$/',basename(dirname($site)))||!is_file(dirname($site).'/.nil-manager-test'))throw new RuntimeException('Not an isolated SQL fixture.');
$dir=dirname($site).'/guard-adapter';mkdir($dir);
foreach(array('license','license-view') as $part)copy(__DIR__.'/../nil-manager/includes-'.$part.'.php',$dir.'/includes-'.$part.'.php');
file_put_contents($dir.'/includes-channel.php',"<?php return 'zhaket';");
file_put_contents($dir.'/includes-license-product.php',"<?php return 'fixture-public-product';");
define('ABSPATH',$site.'/');require $dir.'/includes-license.php';require $site.'/wp-load.php';require_once __DIR__.'/fixture.php';nil_fixture_guard();
wp_set_current_user(1);NIL_License::boot();$count=0;$calls=0;$mode='successful';
function license_native_check($ok,$label){global $count;$count++;if(!$ok)throw new RuntimeException($label);}
add_filter('pre_http_request',function($pre,$args,$url)use(&$calls,&$mode){
 if(strpos($url,'https://guard.zhaket.com/api/')!==0&&strpos($url,'https://guard.zhaket.org/api/')!==0)return new WP_Error('fixture_block','External fixture HTTP blocked');
 $calls++;return array('response'=>array('code'=>200),'body'=>wp_json_encode(array('status'=>$mode,'message'=>'fixture only')));
},PHP_INT_MAX,3);
try {
 $p=new WC_Product_Simple();$p->set_name('Guard fixture');$p->set_status('publish');$p->set_regular_price('100');$p->save();clean_post_cache($p->get_id());wc_delete_product_transients($p->get_id());$p=wc_get_product($p->get_id());
 $preview=new WP_REST_Request('POST');$preview->set_header('content-type','application/json');$preview->set_body(wp_json_encode(array('rows'=>array(array('id'=>$p->get_id(),'version'=>NIL_Bulk_Manager::fingerprint(NIL_Bulk_Manager::snapshot($p)),'changes'=>array('regular_price'=>'110'))))));
 $out=NIL_Bulk_Manager::preview($preview);license_native_check(!$out['errors']&&$out['added']===1,'fresh preview: '.wp_json_encode($out));$seal=new WP_REST_Request('POST');$seal['id']=$out['job_id'];$seal->set_param('count',1);NIL_Bulk_Manager::seal($seal);
 $run=new WP_REST_Request('POST','/nil-bulk/v1/job/'.$out['job_id'].'/run');$run->set_param('mode','apply');$denied=rest_do_request($run);license_native_check($denied->get_status()===403,'unactivated REST write rejected');
 try {NIL_Bulk_Manager::run($run);$blocked=false;}catch(RuntimeException $e){$blocked=true;}license_native_check($blocked&&wc_get_product($p->get_id())->get_regular_price()==='100','internal/cron job entry blocked before changes');
 NIL_License::activate('fixture-license-123');license_native_check(NIL_License::status()['allowed']&&$calls===1,'real WP HTTP interception activates');
 global $wpdb;$autoload=$wpdb->get_var($wpdb->prepare("SELECT autoload FROM $wpdb->options WHERE option_name=%s",NIL_License::OPTION));license_native_check(in_array($autoload,array('no','off','auto-off'),true),'real DB credential is not autoloaded');
 license_native_check(strpos(wp_json_encode(get_option(NIL_License::OPTION)),'fixture-license-123')===false,'real DB value is encrypted');
 $run['id']=$out['job_id'];$applied=NIL_Bulk_Manager::run($run);license_native_check($applied['done']&&wc_get_product($p->get_id())->get_regular_price()==='110','licensed real SQL job writes expected price');
 $s=get_option(NIL_License::OPTION);$s['checked']=time()-120;$s['next']=time()-1;update_option(NIL_License::OPTION,$s,false);$mode='error';NIL_License::refresh();license_native_check(!NIL_License::status()['allowed'],'real option rejection persisted');
 $run->set_param('mode','undo');$undo=rest_do_request($run);license_native_check($undo->get_status()===200&&wc_get_product($p->get_id())->get_regular_price()==='100','REST undo recovers despite rejected license');
 $before=$wpdb->num_queries;$requests=$calls;NIL_Bulk_Manager::boot();license_native_check($wpdb->num_queries===$before&&$calls===$requests,'marketplace storefront boot adds zero SQL and Guard HTTP');
 $_SERVER['REQUEST_METHOD']='POST';$_POST=array('_wpnonce'=>'forged','license_action'=>'forget');ob_start();NIL_License::page();$html=ob_get_clean();license_native_check(get_option(NIL_License::OPTION)!==false&&strpos($html,'role="alert"')!==false,'real WordPress nonce rejects forged form');
 $_SERVER['REQUEST_METHOD']='GET';$_POST=array();ob_start();NIL_License::page();$html=ob_get_clean();file_put_contents(nil_test_dir().'/license-ui-result.html',$html);license_native_check(strpos($html,'fixture-license-123')===false&&strpos($html,'type="password"')!==false,'server-rendered view contains no stored license');
 add_option(NIL_License::LOCK,time().':fixture-worker','',false);$requests=$calls;try{NIL_License::activate('fixture-another-license');$blocked=false;}catch(RuntimeException $e){$blocked=true;}license_native_check($blocked&&$calls===$requests,'real SQL atomic lock rejects overlapping activation');delete_option(NIL_License::LOCK);
 NIL_License::forget();license_native_check(get_option(NIL_License::OPTION)===false&&!wp_next_scheduled(NIL_License::EVENT),'local forget removes record and scheduled check');
 $p->delete(true);echo "PASS: $count real WordPress/SQL licensing checks; only fake Guard HTTP, no live license\n";
} finally {foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);}
