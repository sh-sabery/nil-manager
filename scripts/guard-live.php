<?php
/** Explicit, local-only Guard smoke test: real adapter/HTTPS with isolated option stubs, NOT a WordPress store. */
if(PHP_SAPI!=='cli'||getenv('CI')||$argc!==4){fwrite(STDERR,"Usage outside CI: php guard-live.php PRODUCT_FILE LICENSE_FILE REPORT_FILE\n");exit(2);}
if(!extension_loaded('curl')||!extension_loaded('openssl')){fwrite(STDERR,"cURL and OpenSSL required\n");exit(2);}
$product=trim(file_get_contents($argv[1]));$license=trim(file_get_contents($argv[2]));
foreach(array($product,$license) as $key)if(!preg_match('/^[A-Za-z0-9_-]{8,512}$/D',$key)){fwrite(STDERR,"Invalid key file format\n");exit(2);}
$dir=dirname(realpath($argv[1])).'/adapter-'.bin2hex(random_bytes(6));mkdir($dir,0700);
define('ABSPATH',$dir.'/');define('DAY_IN_SECONDS',86400);define('HOUR_IN_SECONDS',3600);
foreach(array('license')as$part)copy(__DIR__.'/../nil-manager/includes-'.$part.'.php',$dir.'/includes-'.$part.'.php');
file_put_contents($dir.'/includes-channel.php',"<?php return 'zhaket';");file_put_contents($dir.'/includes-license-product.php',"<?php return '".$product."';");chmod($dir.'/includes-license-product.php',0600);
$options=array();$salt=bin2hex(random_bytes(32));$calls=array();
class WP_Error{} class Live_Guard_DB{public $options='isolated_options';public function prepare($q,...$p){return $p;}public function query($p){global $options;if(($options[$p[0]]??null)===$p[1])unset($options[$p[0]]);}}
$wpdb=new Live_Guard_DB();
function current_user_can($cap){return true;}function is_multisite(){return false;}function home_url(){return 'https://nil-manager-guard.test';}function wp_parse_url($u,$part){return parse_url($u,$part);}function wp_salt($scheme){global $salt;return $salt;}
function get_option($k,$d=false){global $options;return $options[$k]??$d;}function update_option($k,$v,$a=null){global $options;if($a!==false)throw new RuntimeException('autoload forbidden');$options[$k]=$v;return true;}function add_option($k,$v,$unused='',$a=null){global $options;if(isset($options[$k]))return false;return update_option($k,$v,$a);}function delete_option($k){global $options;unset($options[$k]);}function wp_cache_delete($k,$g){}function wp_next_scheduled($e){return false;}function wp_schedule_single_event($t,$e){return true;}function wp_clear_scheduled_hook($e){}
function is_wp_error($v){return $v instanceof WP_Error;}function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
function wp_remote_get($url,$args){
 global $calls;$parts=parse_url($url);
 if($parts['scheme']!=='https'||!in_array($parts['host'],array('guard.zhaket.com','guard.zhaket.org'),true)||!in_array($parts['path'],array('/api/install-license','/api/validation-license'),true))throw new RuntimeException('Unexpected host or method');
 $body='';$ch=curl_init($url);curl_setopt_array($ch,array(CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>$args['timeout'],CURLOPT_TIMEOUT=>$args['timeout'],CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>array('Accept: application/json'),CURLOPT_WRITEFUNCTION=>function($h,$chunk)use(&$body,$args){if(strlen($body)+strlen($chunk)>$args['limit_response_size'])return 0;$body.=$chunk;return strlen($chunk);}));
 $proxy=getenv('HTTPS_PROXY')?:getenv('https_proxy');if($proxy)curl_setopt($ch,CURLOPT_PROXY,$proxy);$ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_errno($ch);curl_close($ch);
 $data=json_decode($body,true);$status=is_array($data)&&isset($data['status'])&&in_array($data['status'],array('successful','error'),true)?$data['status']:'unrecognized';
 $calls[]=array('host'=>$parts['host'],'method'=>basename($parts['path']),'http'=>$code,'transport_errno'=>$error,'api_status'=>$status);
 return false===$ok?new WP_Error():array('code'=>$code,'body'=>$body);
}
try{
 require $dir.'/includes-license.php';
 NIL_License::activate($license);$activation=NIL_License::status()['allowed'];
 $options[NIL_License::OPTION]['checked']=time()-61;$options[NIL_License::OPTION]['next']=time()-1;
 NIL_License::refresh(true);$validation=NIL_License::status()['state']==='valid';
 $version=trim(file_get_contents(__DIR__.'/../VERSION'));
 $report=array('plugin_version'=>$version,'scope'=>'adapter-real-https-with-isolated-option-stubs','domain'=>'nil-manager-guard.test','tested_at'=>gmdate('c'),'product_sha256'=>hash('sha256',$product),'activation'=>$activation,'validation'=>$validation,'requests'=>$calls,'wordpress_bootstrapped'=>false,'store_data_used'=>false);
 file_put_contents($argv[3],json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");chmod($argv[3],0600);
 echo json_encode(array('activation'=>$activation,'validation'=>$validation,'scope'=>$report['scope'],'requests'=>$calls))."\n";
 $exit_code=$activation&&$validation?0:1;
}catch(Throwable $e){fwrite(STDERR,"Guard live check did not complete; no raw response or credentials logged.\n");fwrite(STDERR,json_encode(array("error_type"=>get_class($e),"line"=>$e->getLine(),"file"=>basename($e->getFile()),"requests"=>$calls))."\n");$exit_code=1;}
finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}

exit($exit_code);
