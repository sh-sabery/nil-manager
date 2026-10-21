<?php
// Bounded spreadsheet parser/writer tests without WordPress or WooCommerce.
define('ABSPATH',__DIR__);function wp_tempnam($name){return tempnam(sys_get_temp_dir(),'nil');}function sanitize_file_name($name){return $name;}
require __DIR__.'/../nilwears-bulk-manager/includes-sheet.php';
$n=0;function expect_sheet($ok,$label){global $n;$n++;if(!$ok)throw new Exception($label);}
$headers=array('sku','name','price','notes');$rows=array(array('000012','پیراهن نیل','9000','x,y'),array('x','=SUM(1,2)','0',"multi\nline"));
foreach(array('xlsx','csv') as $format){$file=NIL_Sheet::file($headers,$rows,$format,'nil');$out=NIL_Sheet::parse(base64_decode($file['data']),$format);expect_sheet($out['headers']===$headers,$format.' headers');expect_sheet($out['rows'][0]===$rows[0],$format.' Persian, leading zero SKU, commas');expect_sheet($out['rows'][1][2]==='0',$format.' zero price');expect_sheet($out['rows'][1][3]==="multi\nline",$format.' multiline');}
$xlsx=NIL_Sheet::file($headers,$rows,'xlsx','nil');expect_sheet(NIL_Sheet::parse(base64_decode($xlsx['data']),'xlsx')['rows'][1][1]==='=SUM(1,2)','formula is text');
$tmp=wp_tempnam('bad');file_put_contents($tmp,base64_decode($xlsx['data']));$z=new ZipArchive();$z->open($tmp);$xml=$z->getFromName('xl/worksheets/sheet1.xml');$z->addFromString('xl/worksheets/sheet1.xml',str_replace('<is><t xml:space="preserve">000012</t></is>','<f>1+1</f><v>2</v>',$xml));$z->close();try{NIL_Sheet::parse(file_get_contents($tmp),'xlsx');$rejected=false;}catch(Throwable $e){$rejected=true;}expect_sheet($rejected,'formula cells rejected');unlink($tmp);
$bad=array('exe'=>'unrecognized format','csv'=>str_repeat('a',8388609));foreach($bad as $format=>$bytes){try{NIL_Sheet::parse($bytes,$format);$rejected=false;}catch(Throwable $e){$rejected=true;}expect_sheet($rejected,$format.' invalid/oversize rejected');}
$typed=NIL_Sheet::file(array('sku','profit'),array(array('0007',-12.5)),'xlsx','typed');$tmp=wp_tempnam('typed');file_put_contents($tmp,base64_decode($typed['data']));$z=new ZipArchive();$z->open($tmp);$xml=$z->getFromName('xl/worksheets/sheet1.xml');expect_sheet(strpos($xml,'t="n"><v>-12.5</v>')!==false,'report profits are numeric Excel cells');expect_sheet(strpos($xml,'>0007</t>')!==false,'numeric-looking SKU remains text');$z->close();unlink($tmp);
echo "PASS: $n spreadsheet checks\n";
