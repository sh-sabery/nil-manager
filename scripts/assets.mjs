import fs from 'node:fs';
import path from 'node:path';
import {minify} from 'terser';
const dir=path.resolve('nil-manager/assets'), check=process.argv.includes('--check');
const generated=[];
let checkoutBytes=0;
for(const name of ['admin','suite','advanced','market','payment-checkout','calendar']){
 const source=fs.readFileSync(path.join(dir,name+'.js'),'utf8');
 const result=await minify(source,{ecma:2020,module:name==='calendar',compress:{passes:2},mangle:true,format:{comments:false,ascii_only:false}});
 if(name==='payment-checkout')checkoutBytes=Buffer.byteLength(result.code+'\n');
 generated.push([name+'.min.js',result.code+'\n']);
}
const css=fs.readFileSync(path.join(dir,'admin.css'),'utf8').replace(/\/\*[\s\S]*?\*\//g,'').replace(/\s+/g,' ').replace(/\s*([{};,])\s*/g,'$1').replace(/:\s+/g,':').trim()+'\n';
generated.push(['admin.min.css',css]);
for(const [file,content] of generated){const target=path.join(dir,file);if(check){if(!fs.existsSync(target)||fs.readFileSync(target,'utf8')!==content)throw new Error(file+' is stale; run npm run build:assets');}else fs.writeFileSync(target,content);}
const bytes=generated.filter(([name])=>!['payment-checkout.min.js','calendar.min.js'].includes(name)).reduce((sum,[,content])=>sum+Buffer.byteLength(content),0);
if(bytes>60000)throw new Error('Admin CSS/JS exceeds the 60 KB uncompressed budget');
const calendarBytes=Buffer.byteLength(generated.find(([name])=>name==='calendar.min.js')[1]);
if(calendarBytes>3500)throw new Error('On-demand calendar JS/CSS exceeds 3.5 KB');
if(checkoutBytes>3000)throw new Error('Optional checkout JS exceeds 3 KB');
console.log(`PASS: admin CSS/JS ${bytes} bytes; on-demand calendar ${calendarBytes} bytes; optional checkout JS ${checkoutBytes} bytes (checkout only, enabled feature only); no storefront CSS.`);
