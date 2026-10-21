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
echo "PASS: $checks price checks\n";
