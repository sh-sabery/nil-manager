<?php
define( 'ABSPATH', __DIR__ );
require __DIR__ . '/../nilwears-bulk-manager/includes-price.php';
$checks = 0;
function same( $actual, $expected, $message ) {
    global $checks; $checks++;
    if ( $actual !== $expected ) { throw new Exception( "$message: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) ); }
}
function rejects( $fn, $message ) {
    global $checks; $checks++;
    try { $fn(); } catch ( InvalidArgumentException $e ) { return; }
    throw new Exception( 'Should reject: ' . $message );
}
$rule = array( 'operation'=>'percent','value'=>'10','rounding'=>'up','step'=>'10000','ending'=>'9000' );
same( NIL_Bulk_Price::calculate( '495000', $rule, 0 ), '549000', 'User example' );
same( NIL_Bulk_Price::calculate( '', $rule, 0 ), '', 'Empty sale stays empty' );
same( NIL_Bulk_Price::calculate( '۴۹۵٬۰۰۰', $rule, 0 ), '549000', 'Persian input' );
$rule['rounding']='down'; same( NIL_Bulk_Price::calculate( '495000', $rule, 0 ), '539000', 'Down ending' );
$rule['rounding']='nearest'; same( NIL_Bulk_Price::calculate( '495000', $rule, 0 ), '549000', 'Nearest ending' );
$rule['operation']='round';
same( NIL_Bulk_Price::calculate( '549000', $rule, 0 ), '549000', 'Already rounded' );
same( NIL_Bulk_Price::calculate( '544000', $rule, 0 ), '549000', 'Tie up' );
same( NIL_Bulk_Price::calculate( '0', $rule, 0 ), '9000', 'Nearest nonnegative ending' );
$rule['rounding']='down'; rejects( function()use($rule){NIL_Bulk_Price::calculate('500',$rule,0);},'Impossible down ending' );
$rule['rounding']='none'; $rule['operation']='fixed'; $rule['value']='-5000';
same( NIL_Bulk_Price::calculate( '495000', $rule, 0 ), '490000', 'Fixed decrease' );
$rule['operation']='percent';$rule['value']='-10';
same( NIL_Bulk_Price::calculate( '495000', $rule, 0 ), '445500', 'Percent decrease' );
$rule['operation']='set';$rule['value']='0';
same( NIL_Bulk_Price::calculate( '495000', $rule, 0 ), '0', 'Free is different from empty' );
$rule['value']='9.995';
same( NIL_Bulk_Price::calculate( '1', $rule, 2 ), '10.00', 'Currency precision' );
same( NIL_Bulk_Price::number( '١٢٣٫٤٥' ), 123.45, 'Arabic digits' );
rejects( function(){NIL_Bulk_Price::number('1e9');}, 'Scientific notation' );
rejects( function(){NIL_Bulk_Price::number(array());}, 'Array input' );
rejects( function(){NIL_Bulk_Price::calculate('100',array('operation'=>'fixed','value'=>'-101'),0);}, 'Negative result' );
rejects( function(){NIL_Bulk_Price::calculate('100',array('rounding'=>'up','step'=>'100','ending'=>'100'),0);}, 'Ending >= step' );
rejects( function(){NIL_Bulk_Price::calculate('100',array('rounding'=>'up','step'=>'0','ending'=>'0'),0);}, 'Zero step' );
rejects( function(){NIL_Bulk_Price::calculate('100',array('operation'=>'evil'),0);}, 'Invalid operation' );
foreach (array(0,2) as $decimals) {
    foreach(array('up','down','nearest') as $mode) {
        for($value=10000;$value<2000000;$value+=7919) {
            $r=array('operation'=>'round','rounding'=>$mode,'step'=>'10000','ending'=>'9000');
            $result=(float)NIL_Bulk_Price::calculate((string)$value,$r,$decimals);
            same( (int)$result % 10000, 9000, 'Ending invariant' );
            if($mode==='up' && $result<$value)throw new Exception('up direction');
            if($mode==='down' && $result>$value)throw new Exception('down direction');
        }
    }
}
// Discounts always derive from regular price; zero is a valid sale.
$d=array('operation'=>'percent','value'=>'۱۵','rounding'=>'up','step'=>'10000','ending'=>'9000');
same(NIL_Bulk_Price::discount('495000',$d,0),'429000','discount rounding from regular');
$d['rounding']='none';same(NIL_Bulk_Price::discount('495000',$d,0),'420750','discount percent');
$d['operation']='fixed';$d['value']='50000';same(NIL_Bulk_Price::discount('495000',$d,0),'445000','fixed discount');
$d['operation']='percent';$d['value']='100';same(NIL_Bulk_Price::discount('495000',$d,0),'0','free sale');
same(NIL_Bulk_Price::discount('',array('operation'=>'remove'),0),'','remove empty sale');
same(NIL_Bulk_Price::discount('9.99',array('operation'=>'percent','value'=>'10'),2),'8.99','discount currency precision');
foreach(array('0','-1','101',true,array()) as $bad)rejects(function()use($bad){NIL_Bulk_Price::discount('100',array('operation'=>'percent','value'=>$bad),0);},'invalid discount');
foreach(array('',null,'0') as $bad)rejects(function()use($bad){NIL_Bulk_Price::discount($bad,array('operation'=>'percent','value'=>'10'),0);},'missing regular');
rejects(function(){NIL_Bulk_Price::discount('100',array('operation'=>'fixed','value'=>'101'),0);},'discount below zero');
rejects(function(){NIL_Bulk_Price::discount('100',array('operation'=>'percent','value'=>'1','rounding'=>'up','step'=>'100','ending'=>'0'),0);},'rounded discount must reduce');
rejects(function(){NIL_Bulk_Price::discount('100',array('operation'=>'set','value'=>'1'),0);},'invalid discount mode');
$stock=array('type'=>'simple','parent_stock'=>false,'manage_stock'=>true,'stock_quantity'=>5,'backorders'=>'no');
foreach(array('set'=>3,'add'=>8,'subtract'=>2) as $op=>$n)same(NIL_Bulk_Price::stock($stock,array('operation'=>$op,'value'=>'۳')),array('manage_stock'=>true,'stock_quantity'=>$n),'stock '.$op);
same(NIL_Bulk_Price::stock($stock,array('operation'=>'out')),array('stock_quantity'=>0),'managed unavailable');
$unmanaged=$stock;$unmanaged['manage_stock']=false;$unmanaged['stock_quantity']=null;
same(NIL_Bulk_Price::stock($unmanaged,array('operation'=>'out')),array('stock_status'=>'outofstock'),'status without enabling count');
same(NIL_Bulk_Price::stock($unmanaged,array('operation'=>'set','value'=>'0')),array('manage_stock'=>true,'stock_quantity'=>0),'enable zero count');
foreach(array('-1','1.5','100000001',true,array()) as $bad)rejects(function()use($stock,$bad){NIL_Bulk_Price::stock($stock,array('operation'=>'set','value'=>$bad));},'invalid stock quantity');
rejects(function()use($stock){NIL_Bulk_Price::stock($stock,array('operation'=>'subtract','value'=>'6'));},'no negative inventory');
rejects(function()use($unmanaged){NIL_Bulk_Price::stock($unmanaged,array('operation'=>'add','value'=>'1'));},'relative needs tracking');
$shared=$unmanaged;$shared['type']='variation';$shared['parent_stock']=true;
rejects(function()use($shared){NIL_Bulk_Price::stock($shared,array('operation'=>'set','value'=>'2'));},'shared child cannot become independent');
$variable=$unmanaged;$variable['type']='variable';rejects(function()use($variable){NIL_Bulk_Price::stock($variable,array('operation'=>'set','value'=>'2'));},'unmanaged parent cannot become shared');
$stock['backorders']='yes';rejects(function()use($stock){NIL_Bulk_Price::stock($stock,array('operation'=>'out'));},'do not silently disable backorders');
rejects(function()use($stock){NIL_Bulk_Price::stock($stock,array('operation'=>'evil','value'=>'1'));},'invalid stock operation');
echo "PASS: $checks price checks\n";
