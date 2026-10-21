<?php
// Deterministic HTTP mocks: no network and no real credentials.
define('ABSPATH',__DIR__);define('NIL_BULK_GITHUB_TOKEN','test-read-token');define('HOUR_IN_SECONDS',3600);define('MINUTE_IN_SECONDS',60);
class NIL_Bulk_Manager{const VERSION='1.1.0';}class NIL_Suite{public static function title(){return 'Nil';}}
class WP_Error{public $code;public function __construct($code,$message){$this->code=$code;}}
function is_wp_error($v){return $v instanceof WP_Error;}function absint($v){return abs((int)$v);}function wp_strip_all_tags($v){return strip_tags($v);}function esc_html($v){return htmlspecialchars($v);}function plugin_basename($v){return 'nilwears-bulk-manager/nilwears-bulk-manager.php';}
$cache=false;$calls=array();$bad_host=false;$bad_bytes=false;$bytes="PK\x03\x04fixture-zip";
function get_site_transient($k){global $cache;return $cache;}function set_site_transient($k,$v,$t){global $cache;$cache=$v;}
function wp_tempnam($n){return tempnam(sys_get_temp_dir(),'nilup');}function wp_parse_url($u){return parse_url($u);}function wp_remote_retrieve_response_code($r){return $r['response']['code'];}function wp_remote_retrieve_body($r){return $r['body']??'';}function wp_remote_retrieve_header($r,$h){return $r['headers'][$h]??'';}
function wp_remote_get($url,$args){global $calls,$bytes,$bad_host,$bad_bytes;$calls[]=array('url'=>$url,'args'=>$args);
 if(strpos($url,'/releases/latest')!==false)return array('response'=>array('code'=>200),'body'=>json_encode(array('tag_name'=>'v1.2.3','body'=>'test','assets'=>array(array('id'=>123,'name'=>'nilwears-bulk-manager-v1.2.3.zip','size'=>strlen($bytes),'digest'=>'sha256:'.hash('sha256',$bytes))))));
 if(strpos($url,'api.github.com')!==false)return array('response'=>array('code'=>302),'headers'=>array('location'=>$bad_host?'https://evil.example/file.zip':'https://release-assets.githubusercontent.com/example/file.zip'));
 file_put_contents($args['filename'],$bad_bytes?str_repeat('x',strlen($bytes)):$bytes);return array('response'=>array('code'=>200));
}
require __DIR__.'/../nilwears-bulk-manager/includes-updater.php';
$n=0;function expect_update($ok,$label){global $n;$n++;if(!$ok)throw new Exception($label);}
$s=NIL_Updater::status();expect_update($s['configured']&&$s['available']==='1.2.3','private release visible');expect_update(strpos(json_encode($s),'test-read-token')===false,'status never returns token');
$t=(object)array('checked'=>array('nilwears-bulk-manager/nilwears-bulk-manager.php'=>'1.1.0'));$out=NIL_Updater::updates($t);$package=$out->response['nilwears-bulk-manager/nilwears-bulk-manager.php']->package;expect_update(strpos($package,'test-read-token')===false,'package URL never contains token');
$file=NIL_Updater::download(false,$package,null,array());expect_update(is_string($file)&&is_file($file),'authenticated asset downloaded');expect_update(file_get_contents($file)===$bytes,'asset bytes checked');unlink($file);
$last=$calls[count($calls)-1];expect_update(empty($last['args']['headers']['Authorization']),'redirect never receives authorization');expect_update($calls[count($calls)-2]['args']['headers']['Authorization']==='Bearer test-read-token','API request authenticated');
$bad_host=true;$result=NIL_Updater::download(false,$package,null,array());expect_update(is_wp_error($result)&&$result->code==='nil_update_host','untrusted redirect blocked');$bad_host=false;$bad_bytes=true;$result=NIL_Updater::download(false,$package,null,array());expect_update(is_wp_error($result)&&$result->code==='nil_update_hash','same-size tampered payload blocked');
expect_update(NIL_Updater::download(false,'https://example.org/other.zip',null,array())===false,'other plugin downloads unaffected');
echo "PASS: $n private updater checks\n";
