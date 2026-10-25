<?php
define('ABSPATH', __DIR__);
require __DIR__.'/../nil-manager/includes-price.php';
require __DIR__.'/../nil-manager/includes-payments.php';
function get_woocommerce_currency(){return 'IRT';}
function wc_get_price_decimals(){return 0;}
function wc_format_decimal($n,$d){return number_format((float)$n,$d,'.','');}
$checks=0;
function assert_payment($v,$message){global $checks;if(!$v)throw new Exception($message);$checks++;}
$gateways=array('bacs'=>true,'installments'=>true,'cod'=>true);
$input=array('enabled'=>'1','currency'=>'IRT','rules'=>array('bacs'=>array('percent'=>'۱۰','cap'=>'۱٬۰۰۰٬۰۰۰'),'installments'=>array('percent'=>'۰','cap'=>'')));
$out=NIL_Payments::validate($input,$gateways);
assert_payment($out['rules']['bacs']===array('percent'=>'10.00','cap'=>'1000000'),'Persian values and separators');
assert_payment($out['rules']['installments']===array('percent'=>'0.00','cap'=>''),'zero discount/unlimited preserved');
foreach(array(-1,101,'1e2',true,array(),'<script>10</script>','NaN','10.001')as$value){$bad=$input;$bad['rules']['bacs']['percent']=$value;try{NIL_Payments::validate($bad,$gateways);assert_payment(false,'invalid percent accepted');}catch(InvalidArgumentException $e){$checks++;}}
foreach(array(0,-1,'0.1',array(),true,'100000000001','not a cap')as$value){$bad=$input;$bad['rules']['bacs']['cap']=$value;try{NIL_Payments::validate($bad,$gateways);assert_payment(false,'invalid cap accepted');}catch(InvalidArgumentException $e){$checks++;}}
foreach(array(array('currency'=>'IRR'),array('rules'=>array('forged'=>array('percent'=>10,'cap'=>''))),array('rules'=>null))as$change){try{NIL_Payments::validate(array_merge($input,$change),$gateways);assert_payment(false,'invalid config accepted');}catch(InvalidArgumentException $e){$checks++;}}
foreach(array(array(15000000,10,'',0,1500000.0),array(15000000,10,1000000,0,1000000.0),array(5000000,10,1000000,0,500000.0),array(15000000,0,'',0,0.0),array(0,10,'',0,0.0),array(10.01,10,'',2,1.0),array(15000000,100,'',0,15000000.0),array(INF,10,'',0,0.0))as$case){$expected=array_pop($case);assert_payment(NIL_Payments::amount(...$case)===$expected,'arithmetic '.json_encode($case));}
echo "PASS: $checks payment validation/arithmetic assertions\n";
