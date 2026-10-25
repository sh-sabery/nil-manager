<?php
// Deterministic HTTP/cache mocks: no network, site data or real credentials.
define('ABSPATH',__DIR__);if(!getenv('NIL_PUBLIC_UPDATES'))define('NIL_BULK_GITHUB_TOKEN','test-read-token');define('HOUR_IN_SECONDS',3600);define('MINUTE_IN_SECONDS',60);
class NIL_Bulk_Manager{const VERSION='1.2.0';}class NIL_Suite{public static function title(){return 'Nil';}}
class WP_Error{public $code;public function __construct($code,$message){$this->code=$code;}}
function plugins_url($p,$file){return 'https://nil.test/plugins/nilwears-bulk-manager/'.$p;}
function is_wp_error($v){return $v instanceof WP_Error;}function absint($v){return abs((int)$v);}function wp_strip_all_tags($v){return strip_tags($v);}function esc_html($v){return htmlspecialchars($v);}function plugin_basename($v){return 'nilwears-bulk-manager/nilwears-bulk-manager.php';}
$cache=array();$calls=array();$bad_host=false;$bad_bytes=false;$bad_size=false;$can_update=true;$mode='ok';$archive_prefix='nil-manager-v';$tag='1.2.3';$bytes="PK\x03\x04fixture-zip";$hooks=array();
function add_filter($h,$fn,$priority=10,$args=1){global $hooks;$hooks[$h][]=$fn;}function add_action($h,$fn,$priority=10,$args=1){add_filter($h,$fn,$priority,$args);}function current_user_can($c){global $can_update;return $can_update;}
function get_site_transient($k){global $cache;return $cache[$k]??false;}function set_site_transient($k,$v,$t=0){global $cache;$cache[$k]=$v;}function delete_site_transient($k){global $cache;unset($cache[$k]);}
function wp_tempnam($n){return tempnam(sys_get_temp_dir(),'nilup');}function wp_parse_url($u){return parse_url($u);}function wp_remote_retrieve_response_code($r){return $r['response']['code'];}function wp_remote_retrieve_body($r){return $r['body']??'';}function wp_remote_retrieve_header($r,$h){return $r['headers'][$h]??'';}
function wp_remote_get($url,$args){global $calls,$bytes,$bad_host,$bad_bytes,$bad_size,$mode,$tag,$archive_prefix;$calls[]=array('url'=>$url,'args'=>$args);
 if(strpos($url,'/releases/latest')!==false){
  if($mode==='network')return new WP_Error('http_request_failed','fake error with test-read-token must not be exposed');
  if(is_int($mode))return array('response'=>array('code'=>$mode));
  if($mode==='auth'&&!empty($args['headers']['Authorization']))return array('response'=>array('code'=>401));
  if($mode==='malformed')return array('response'=>array('code'=>200),'body'=>'[broken');
  return array('response'=>array('code'=>200),'body'=>json_encode(array('tag_name'=>'v'.$tag,'body'=>'test','assets'=>$mode==='missing'?array():array(array('id'=>123,'name'=>$archive_prefix.$tag.'.zip','size'=>$mode==='huge'?20971521:strlen($bytes),'digest'=>$mode==='no-digest'?'':'sha256:'.hash('sha256',$bytes))))));
 }
 if(strpos($url,'api.github.com')!==false)return array('response'=>array('code'=>302),'headers'=>array('location'=>$bad_host?'https://evil.example/file.zip':'https://release-assets.githubusercontent.com/example/file.zip'));
 file_put_contents($args['filename'],$bad_size?'x':($bad_bytes?str_repeat('x',strlen($bytes)):$bytes));return array('response'=>array('code'=>200));
}
require __DIR__.'/../nilwears-bulk-manager/includes-updater.php';
$n=0;function expect_update($ok,$label){global $n;$n++;if(!$ok)throw new Exception($label);}
$plugin=plugin_basename('');$s=NIL_Updater::status();expect_update($s['configured']===!getenv('NIL_PUBLIC_UPDATES')&&$s['available']==='1.2.3','release visible');expect_update(strpos(json_encode($s),'test-read-token')===false,'status never returns token');expect_update($s['checked_at']>0&&$s['error']===null&&$s['update_available']===true,'successful check time and no error');
$t=(object)array('checked'=>array($plugin=>'1.2.0'));$out=NIL_Updater::updates($t);$package=$out->response[$plugin]->package;expect_update(strpos($package,'test-read-token')===false,'package URL never contains token');
$info=NIL_Updater::info(false,'plugin_information',(object)array('slug'=>'nilwears-bulk-manager'));expect_update(strpos($out->response[$plugin]->icons['svg'],'/assets/icon.svg')!==false,'update row icon URL');expect_update($info->icons===$out->response[$plugin]->icons&&strpos($info->banners['low'],'/assets/banner.svg')!==false,'details icon and banner URLs');
$file=NIL_Updater::download(false,$package,null,array());expect_update(is_string($file)&&is_file($file),'asset downloaded');expect_update(file_get_contents($file)===$bytes,'asset bytes checked');unlink($file);
$last=$calls[count($calls)-1];expect_update(empty($last['args']['headers']['Authorization']),'redirect never receives authorization');expect_update(getenv('NIL_PUBLIC_UPDATES')?empty($calls[count($calls)-2]['args']['headers']['Authorization']):$calls[count($calls)-2]['args']['headers']['Authorization']==='Bearer test-read-token','API authentication mode');
$bad_host=true;$result=NIL_Updater::download(false,$package,null,array());expect_update(is_wp_error($result)&&$result->code==='nil_update_host','untrusted redirect blocked');$bad_host=false;$bad_bytes=true;$result=NIL_Updater::download(false,$package,null,array());expect_update(is_wp_error($result)&&$result->code==='nil_update_hash','same-size tampering blocked');$bad_bytes=false;$bad_size=true;$result=NIL_Updater::download(false,$package,null,array());expect_update(is_wp_error($result)&&$result->code==='nil_update_download','truncated download blocked');$bad_size=false;
expect_update(NIL_Updater::download(false,'https://example.org/other.zip',null,array())===false,'other plugin download unaffected');
NIL_Updater::boot();expect_update(isset($hooks['site_transient_update_plugins'],$hooks['pre_set_site_transient_update_plugins'],$hooks['load-update-core.php']),'cached-read and dashboard hooks registered');
$count=count($calls);NIL_Updater::status();NIL_Updater::updates($t);expect_update(count($calls)===$count,'ordinary checks reuse six-hour metadata cache');
$key=getenv('NIL_PUBLIC_UPDATES')?'nil_release_public':'nil_release';$cache[$key]['version']='1.2.1';$other=(object)array('new_version'=>'9.0');$cache['update_plugins']=(object)array('checked'=>array('other/main.php'=>'8.0'),'response'=>array('other/main.php'=>$other),'last_checked'=>time());
$count=count($calls);$s=NIL_Updater::check();expect_update(count($calls)===$count+1&&$s['available']==='1.2.3','manual check bypasses fresh old cache');expect_update($cache['update_plugins']->response[$plugin]->new_version==='1.2.3','manual check persists WordPress update entry');expect_update($cache['update_plugins']->response['other/main.php']===$other,'other plugin update preserved');
$count=count($calls);NIL_Updater::dashboard_check();expect_update(count($calls)===$count,'normal dashboard load does not force HTTP');$_GET['force-check']=1;NIL_Updater::dashboard_check();expect_update(count($calls)===$count+1,'dashboard Check Again bypasses own cache');unset($_GET['force-check']);
$can_update=false;$count=count($calls);try{NIL_Updater::check();$denied=false;}catch(RuntimeException $e){$denied=true;}expect_update($denied&&count($calls)===$count,'unauthorized refresh blocked before HTTP');$_GET['force-check']=1;NIL_Updater::dashboard_check();expect_update(count($calls)===$count,'unauthorized dashboard refresh ignored');unset($_GET['force-check']);$can_update=true;
$out=NIL_Updater::updates(false);expect_update($out->response[$plugin]->new_version==='1.2.3','empty WordPress cache still gets update');
$tag='1.2.0';$s=NIL_Updater::check();expect_update($s['update_available']===false&&!isset($cache['update_plugins']->response[$plugin])&&isset($cache['update_plugins']->no_update[$plugin]),'installed release clears stale update and supplies no_update');expect_update(isset($cache['update_plugins']->response['other/main.php']),'other update preserved while current');$tag='1.2.3';
$mode='network';$s=NIL_Updater::check();expect_update($s['available']===null&&$s['error']['code']==='network','network failure visible');expect_update(strpos(json_encode($s),'test-read-token')===false,'network details cannot leak credentials');$count=count($calls);NIL_Updater::status();expect_update(count($calls)===$count,'negative cache throttles retries');
$mode=403;$s=NIL_Updater::check();expect_update($s['error']['http_status']===403,'HTTP failure visible');$mode='malformed';$s=NIL_Updater::check();expect_update($s['error']['code']==='metadata','bad metadata visible');$mode='missing';$s=NIL_Updater::check();expect_update($s['error']['code']==='asset','missing ZIP visible');
$mode='ok';$s=NIL_Updater::check();expect_update($s['available']==='1.2.3'&&$s['error']===null,'forced recovery clears failure cache');
$mode='auth';$count=count($calls);$s=NIL_Updater::check();expect_update($s['available']==='1.2.3'&&$s['access']==='public','invalid optional token falls back to public');expect_update(count($calls)===$count+(getenv('NIL_PUBLIC_UPDATES')?1:2),'bounded public fallback');$file=NIL_Updater::download(false,$package,null,array());expect_update(is_string($file),'public fallback download works');if(is_string($file))unlink($file);expect_update(empty($calls[count($calls)-2]['args']['headers']['Authorization']),'fallback asset request has no invalid token');
$mode='no-digest';$s=NIL_Updater::check();expect_update($s['error']['code']==='integrity','missing SHA-256 rejected');$mode='huge';$s=NIL_Updater::check();expect_update($s['error']['code']==='integrity','oversized asset metadata rejected');$mode='ok';NIL_Updater::check();
foreach($calls as $c)expect_update(!empty($c['args']['reject_unsafe_urls']),'every HTTP hop rejects unsafe URLs');
echo "PASS: $n ".(getenv('NIL_PUBLIC_UPDATES')?'public':'private')." updater checks\n";

expect_update($info->author==='نیل وب'&&strpos($info->sections['description'],'نیل منجر')!==false,'Persian author and description');
expect_update(strpos($info->sections['changelog'],'نسخهٔ ۱.۰.۰')!==false&&strpos($info->sections['changelog'],'آبان')!==false,'complete local Persian history and Persian dates');
expect_update($info->homepage==='https://github.com/sh-sabery/nil-manager','new public product homepage');
$mode='network';NIL_Updater::check();$offline=NIL_Updater::info(false,'plugin_information',(object)array('slug'=>'nilwears-bulk-manager'));expect_update($offline->sections['description']===$info->sections['description']&&$offline->sections['changelog']===$info->sections['changelog']&&$offline->download_link==='','Persian details available while metadata is offline');
$mode='ok';NIL_Updater::check();$old_package='https://api.github.com/repos/sh-sabery/nilwears-bulk-manager/releases/assets/123';$old_file=NIL_Updater::download(false,$old_package,null,array());expect_update(is_string($old_file),'verified legacy cached asset URL normalizes to new repository');if(is_string($old_file))unlink($old_file);
echo "PASS: Persian branding/history, offline details and legacy package normalization\n";

$archive_prefix='nilwears-bulk-manager-v';expect_update(NIL_Updater::check()['available']==='1.2.3','legacy archive name stays compatible');
