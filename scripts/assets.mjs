import fs from 'node:fs';
import path from 'node:path';
import {minify} from 'terser';
const dir=path.resolve('nilwears-bulk-manager/assets'), check=process.argv.includes('--check');
const generated=[];
let checkoutBytes=0;
for(const name of ['admin','suite','advanced','market','payment-checkout']){
 const source=fs.readFileSync(path.join(dir,name+'.js'),'utf8');
 const result=await minify(source,{ecma:2020,compress:{passes:2},mangle:true,format:{comments:false,ascii_only:false}});
 if(name==='payment-checkout')checkoutBytes=Buffer.byteLength(result.code+'\n');
 generated.push([name+'.min.js',result.code+'\n']);
}
const css=fs.readFileSync(path.join(dir,'admin.css'),'utf8').replace(/\/\*[\s\S]*?\*\//g,'').replace(/\s+/g,' ').replace(/\s*([{};,])\s*/g,'$1').replace(/:\s+/g,':').trim()+'\n';
generated.push(['admin.min.css',css]);
for(const [file,content] of generated){const target=path.join(dir,file);if(check){if(!fs.existsSync(target)||fs.readFileSync(target,'utf8')!==content)throw new Error(file+' is stale; run npm run build:assets');}else fs.writeFileSync(target,content);}
const bytes=generated.filter(([name])=>name!=='payment-checkout.min.js').reduce((sum,[,content])=>sum+Buffer.byteLength(content),0);
if(bytes>60000)throw new Error('Admin CSS/JS exceeds the 60 KB uncompressed budget');
if(checkoutBytes>3000)throw new Error('Optional checkout JS exceeds 3 KB');
console.log(`PASS: admin CSS/JS ${bytes} bytes; optional checkout JS ${checkoutBytes} bytes (checkout only, enabled feature only); no storefront CSS.`);
