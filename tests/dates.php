<?php
// Independent Node/ICU Persian calendar vectors; no WP/intl extension dependency.
define('ABSPATH',__DIR__);$nil_date_zone='UTC';function wp_timezone(){global $nil_date_zone;return new DateTimeZone($nil_date_zone);}
require __DIR__.'/../nil-manager/includes-dates.php';
$n=0;function date_check($ok,$why){global $n;$n++;if(!$ok)throw new Exception($why);}function date_reject($fn){try{$fn();}catch(InvalidArgumentException $e){return true;}return false;}
foreach(json_decode(file_get_contents(__DIR__.'/date-vectors.json'),true) as $row){$t=strtotime($row[0].'T12:00:00Z');date_check(NIL_Dates::format($t,false,false)===$row[1],'ICU display '.$row[0]);date_check(NIL_Dates::parse($row[1])===$row[0],'ICU reverse '.$row[1]);}
date_check(NIL_Dates::parse("\u{200f}۱۴۰۵/۰۸/۰۱\u{200e}")==='2026-10-23','bidi date marks');
date_check(NIL_Dates::parse('۱۴۰۳/۱۲/۳۰')==='2025-03-20','Persian leap Esfand');date_check(NIL_Dates::parse('١٤٠٥/٠٨/٠١')==='2026-10-23','Arabic digits');date_check(NIL_Dates::parse('۱۴۰۵-۸-۱')==='2026-10-23','short month/day');
foreach(array('1404/12/30','1405/07/31','1405/00/01','1405/13/01','1405/01/00','1405/01/32','1199/12/01','1500/01/01','1405/1/1junk',true,array(),str_repeat('1',65)) as $bad)date_check(date_reject(function()use($bad){NIL_Dates::parse($bad);}), 'invalid Persian date');
foreach(array('2026-02-30','2026-13-01','2026-00-12','2026-10-23junk') as $bad)date_check(date_reject(function()use($bad){NIL_Dates::parse($bad,'gregorian');}), 'strict Gregorian compatibility');
$nil_date_zone='Asia/Tehran';date_check(NIL_Dates::parse('۱۴۰۵/۰۸/۰۱ ۱۵:۳۰','persian',true)===strtotime('2026-10-23T12:00:00Z'),'site time zone converts to UTC');date_check(NIL_Dates::format(strtotime('2026-10-22T21:00:00Z'),true)==='۱۴۰۵/۰۸/۰۱ ۰۰:۳۰','local midnight boundary');
foreach(array('1405/08/01 24:00','1405/08/01 12:60','1405/08/01','1405/08/01 1:30') as $bad)date_check(date_reject(function()use($bad){NIL_Dates::parse($bad,'persian',true);}), 'invalid clock');
date_check(date_reject(function(){NIL_Dates::parse('2026-02-30T12:00:00Z','gregorian',true);}), 'ISO date rollover rejected');date_check(date_reject(function(){NIL_Dates::parse('1405/1/1','other');}), 'unknown calendar');
$nil_date_zone='America/New_York';date_check(date_reject(function(){NIL_Dates::parse('1404/12/17 02:30','persian',true);}), 'DST nonexistent local time rejected');
echo "PASS: $n Persian date/time checks against independent ICU vectors\n";
