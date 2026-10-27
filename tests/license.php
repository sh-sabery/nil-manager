<?php
// Isolated fake keys, HTTP queue and option/cron spies; never WordPress/store credentials.
define('ABSPATH', __DIR__); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600);
$channel = getenv('NIL_TEST_LICENSE_CHANNEL') ?: 'zhaket';
$dir = sys_get_temp_dir().'/nil-guard-'.bin2hex(random_bytes(8)); mkdir($dir);
foreach (array('license','license-view','dates') as $file) copy(__DIR__.'/../nil-manager/includes-'.$file.'.php',$dir.'/includes-'.$file.'.php');
file_put_contents($dir.'/includes-channel.php', "<?php return '".$channel."';");
file_put_contents($dir.'/includes-license-product.php', "<?php return 'fixture-public-product';");
$options=array(); $autoload=array(); $calls=array(); $responses=array(); $events=array(); $hooks=array(); $allowed=true; $network=false; $salt='fixture-salt'; $home='https://www.fixture.test'; $reads=0; $during=null;
class WP_Error { public $code; public function __construct($c,$m,$data=array()){$this->code=$c;} }
class NIL_Bulk_Manager { const VERSION='1.11.0'; public static function permission(){global $allowed;return $allowed;} }
class Fixture_DB { public $options='wp_options'; public function prepare($sql,...$params){return $params;} public function query($params){global $options;if(($options[$params[0]]??null)===$params[1])unset($options[$params[0]]);return 1;} }
$wpdb=new Fixture_DB();
function get_option($k,$d=false){global $options,$reads;$reads++;return $options[$k]??$d;}
function add_option($k,$v,$deprecated='',$a=null){global $options,$autoload;if(array_key_exists($k,$options))return false;$options[$k]=$v;$autoload[$k]=$a;return true;}
function update_option($k,$v,$a=null){global $options,$autoload;$options[$k]=$v;$autoload[$k]=$a;return true;}
function delete_option($k){global $options;unset($options[$k]);return true;}
function wp_cache_delete($k,$g){} function wp_salt($scheme){global $salt;return $salt;}
function home_url(){global $home;return $home;}function wp_parse_url($s,$component=-1){return parse_url($s,$component);}
function current_user_can($cap){global $allowed;return $allowed;}function is_multisite(){global $network;return $network;}
function wp_next_scheduled($k){global $events;return $events[$k]??false;}function wp_schedule_single_event($t,$k){global $events;$events[$k]=$t;return true;}function wp_clear_scheduled_hook($k){global $events;unset($events[$k]);}
function add_action($h,$fn,$p=10,$args=1){global $hooks;$hooks[$h][]=$fn;}
function wp_remote_get($url,$args){global $calls,$responses,$during;$calls[]=array($url,$args);if($during){$fn=$during;$during=null;$fn();}if(!$responses)throw new Exception('Unexpected HTTP');return array_shift($responses);}
function is_wp_error($v){return $v instanceof WP_Error;}function wp_remote_retrieve_response_code($r){return $r['response']['code'];}function wp_remote_retrieve_body($r){return $r['body']??'';}
function wp_verify_nonce($n,$action){return $n==='fixture-nonce';}function wp_unslash($v){return $v;}function wp_nonce_field($action){echo '<input name="_wpnonce" value="fixture-nonce">';}
function esc_html($s){return htmlspecialchars($s,ENT_QUOTES,'UTF-8');}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}function admin_url($s){return 'https://fixture.test/wp-admin/'.$s;}function disabled($b){if($b)echo 'disabled';}function wp_timezone(){return new DateTimeZone('Asia/Tehran');}
class Fixture_Request { private $path;private $params;private $method;public function __construct($p,$v=array(),$m='POST'){$this->path=$p;$this->params=$v;$this->method=$m;}public function get_route(){return $this->path;}public function get_method(){return $this->method;}public function get_param($k){return $this->params[$k]??null;} }
$n=0;function guard_check($ok,$label){global $n;++$n;if(!$ok)throw new Exception($label);}function guard_bad($fn){try{$fn();return false;}catch(RuntimeException $e){return true;}catch(InvalidArgumentException $e){return true;}}
function reply($status='successful',$http=200){return array('response'=>array('code'=>$http),'body'=>json_encode(array('status'=>$status,'message'=>'fixture-token-123 <script>untrusted</script>')));}
function age_guard(){global $options;$options[NIL_License::OPTION]['checked']=time()-120;$options[NIL_License::OPTION]['attempt']=time()-120;$options[NIL_License::OPTION]['next']=time()-1;unset($GLOBALS['events'][NIL_License::EVENT]);}
try {
 require $dir.'/includes-license.php';require $dir.'/includes-dates.php';
 NIL_License::boot();
 if($channel==='github') {
  NIL_License::assert_write();NIL_License::refresh();NIL_License::ensure_event();
  guard_check(NIL_License::status()['state']==='community','GitHub remains usable');guard_check(!$reads&&!$calls&&!$hooks&&!$events,'GitHub license path has zero option reads HTTP hooks or cron');
 } else {
  guard_check(!$reads&&!$calls&&!$events,'marketplace boot adds no SQL HTTP or scheduled event');
  guard_check(isset($hooks['admin_menu'],$hooks[NIL_License::EVENT]),'admin and background hooks registered');
  guard_check(!NIL_License::status()['allowed'],'unactivated gated');
  guard_check(guard_bad(function(){NIL_License::assert_write();}),'new writes denied without HTTP');
  $allowed=false;guard_check(guard_bad(function(){NIL_License::activate('fixture-token-123');}),'activation capability enforced');$allowed=true;
  foreach(array('',array('bad'),'bad key','https://evil.test/key',str_repeat('x',513)) as $token)guard_check(guard_bad(function()use($token){NIL_License::activate($token);}),'invalid token rejected');
  guard_check(!$calls,'invalid/unauthorized activation has no network');
  $responses=array(reply());$during=function(){guard_check(guard_bad(function(){NIL_License::activate('fixture-other-key');}),'concurrent activation blocked');};NIL_License::activate('fixture-token-123');
  $s=NIL_License::status();$record=$options[NIL_License::OPTION];
  guard_check($s['allowed']&&$s['state']==='valid','activation grants same-domain access');guard_check($autoload[NIL_License::OPTION]===false&&$autoload[NIL_License::LOCK]===false,'non-autoload credential and lock');
  guard_check(strpos(json_encode($options),'fixture-token-123')===false,'encrypted record contains no raw license');guard_check(strpos(json_encode($s),'fixture-token-123')===false,'public status redacts license');
  guard_check(!isset($options[NIL_License::LOCK]),'operation releases lock');
  guard_check(abs($events[NIL_License::EVENT]-time()-86400)<=1,'one daily event after activation');
  $count=count($calls);NIL_License::status();NIL_License::assert_write();NIL_License::refresh();guard_check(count($calls)===$count,'cached checks no network');
  parse_str(parse_url($calls[0][0],PHP_URL_QUERY),$params);guard_check($params===array('token'=>'fixture-token-123','domain'=>'www.fixture.test','product_token'=>'fixture-public-product'),'official activation parameters and exact configured home host');
  $_SERVER['HTTP_HOST']='evil.test';$_SERVER['HTTP_X_FORWARDED_HOST']='evil.test';guard_check(NIL_License::domain()==='www.fixture.test','spoofed Host headers ignored');
  $home='https://other.test';guard_check(!NIL_License::status()['allowed']&&NIL_License::status()['state']==='changed','domain migration invalidates cached approval');NIL_License::refresh(true);guard_check(count($calls)===$count,'changed domain never automatically installs or validates key');$home='https://www.fixture.test';
  $salt='rotated-salt';guard_check(!NIL_License::status()['allowed'],'salt change cannot use cached approval');$salt='fixture-salt';
  $network=true;guard_check(!NIL_License::status()['allowed']&&guard_bad(function(){NIL_License::activate('fixture-token-123');}),'multisite not silently licensed');$network=false;
  age_guard();$responses=array(new WP_Error('network','fixture-token-123'),reply('successful'));NIL_License::refresh();
  guard_check(NIL_License::status()['allowed']&&count($calls)===$count+2,'HTTPS backup recovers transport failure');guard_check(strpos($calls[count($calls)-1][0],'https://guard.zhaket.org/')===0,'only official HTTPS backup');
  $count=count($calls);age_guard();$responses=array(reply('error'));NIL_License::refresh();
  guard_check(!NIL_License::status()['allowed']&&NIL_License::status()['state']==='invalid','explicit rejection blocks new writes');guard_check(count($calls)===$count+1,'rejection never bypassed through backup');
  age_guard();$responses=array(new WP_Error('network','raw secret'),reply('error',503));NIL_License::refresh();guard_check(!NIL_License::status()['allowed'],'outage after rejection never restores approval');
  age_guard();$responses=array(reply());NIL_License::refresh();guard_check(NIL_License::status()['allowed'],'later confirmed valid recovers');
  foreach(array(reply('error',429),reply('error',500),array('response'=>array('code'=>200),'body'=>'broken'),reply('unknown'),reply('error',302),reply('error',403)) as $failure){age_guard();$responses=array($failure,$failure);NIL_License::refresh();guard_check(NIL_License::status()['allowed']&&NIL_License::status()['state']==='offline','outage/HTML/redirect/HTTP error keeps prior approval');guard_check(abs($events[NIL_License::EVENT]-time()-3600)<=1,'outage retry limited to hourly');}
  age_guard();$previous=$options[NIL_License::OPTION]['sealed'];$responses=array(reply('error'));guard_check(guard_bad(function(){NIL_License::activate('fixture-replacement');}),'rejected replacement reported');guard_check($options[NIL_License::OPTION]['sealed']===$previous&&NIL_License::status()['allowed'],'rejected replacement keeps old key');
  age_guard();$responses=array(reply('error'));NIL_License::refresh();
  foreach(array('/nil-bulk/v1/create','/nil-bulk/v1/variations/apply','/nil-bulk/v1/schedules','/nil-bulk/v1/market/rules','/nil-bulk/v1/job/12345678-1234-1234-1234-123456789012/run') as $path)guard_check(NIL_License::rest(null,null,new Fixture_Request($path)) instanceof WP_Error,'mutation gated: '.$path);
  foreach(array(array('/nil-bulk/v1/products',array(),'GET'),array('/nil-bulk/v1/preview',array(),'POST'),array('/nil-bulk/v1/sheet/export',array(),'POST'),array('/nil-bulk/v1/job/12345678-1234-1234-1234-123456789012/run',array('mode'=>'undo'),'POST'),array('/nil-bulk/v1/schedules',array('delete'=>true),'POST'),array('/nil-bulk/v1/market/rules',array('action'=>'pause'),'POST'),array('/nil-bulk/v1/market/rules',array('action'=>'delete'),'POST')) as $r)guard_check(NIL_License::rest(null,null,new Fixture_Request(...$r))===null,'read/export/recovery stays available');
  $earlier=new WP_Error('earlier','earlier');guard_check(NIL_License::rest($earlier,null,new Fixture_Request('/nil-bulk/v1/create'))===$earlier,'other REST failure preserved');
  $_SERVER['REQUEST_METHOD']='POST';$_POST=array('_wpnonce'=>'forged','license_action'=>'forget');ob_start();NIL_License::page();$html=ob_get_clean();guard_check(isset($options[NIL_License::OPTION])&&strpos($html,'role="alert"')!==false,'invalid nonce cannot forget license');guard_check(strpos($html,'fixture-token-123')===false&&strpos($html,'<script>')===false,'view never echoes saved license or remote message');
  $_POST=array('_wpnonce'=>'fixture-nonce','license_action'=>'forget');ob_start();NIL_License::page();$html=ob_get_clean();guard_check(!isset($options[NIL_License::OPTION])&&!isset($events[NIL_License::EVENT]),'local forget removes only key and own event');
  $options[NIL_License::LOCK]=(time()-121).':abandoned';$responses=array(reply());NIL_License::activate('fixture-token-123');guard_check(NIL_License::status()['allowed'],'expired worker lock recovered atomically');
  $options[NIL_License::OPTION]['sealed']='tampered';guard_check(!NIL_License::status()['allowed'],'tampered ciphertext denied');
  foreach($calls as $call){$args=$call[1];guard_check(strpos($call[0],'https://guard.zhaket.')===0&&$args['sslverify']&&$args['reject_unsafe_urls']&&$args['redirection']===0&&$args['timeout']<=8&&$args['limit_response_size']===16384,'bounded HTTPS-only HTTP without redirects');}
 }
 echo "PASS: $n $channel licensing checks; fake HTTP/keys only\n";
} finally {foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
