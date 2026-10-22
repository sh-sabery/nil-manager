import fs from 'node:fs';
import path from 'node:path';
import {minify} from 'terser';
const dir=path.resolve('nilwears-bulk-manager/assets'), check=process.argv.includes('--check');
const generated=[];
for(const name of ['admin','suite','advanced','market']){
 const source=fs.readFileSync(path.join(dir,name+'.js'),'utf8');
 const result=await minify(source,{ecma:2020,compress:{passes:2},mangle:true,format:{comments:false,ascii_only:false}});
 generated.push([name+'.min.js',result.code+'\n']);
}
const css=fs.readFileSync(path.join(dir,'admin.css'),'utf8').replace(/\/\*[\s\S]*?\*\//g,'').replace(/\s+/g,' ').replace(/\s*([{}:;,])\s*/g,'$1').trim()+'\n';
generated.push(['admin.min.css',css]);
for(const [file,content] of generated){const target=path.join(dir,file);if(check){if(!fs.existsSync(target)||fs.readFileSync(target,'utf8')!==content)throw new Error(file+' is stale; run npm run build:assets');}else fs.writeFileSync(target,content);}
const bytes=generated.reduce((sum,[,content])=>sum+Buffer.byteLength(content),0);
if(bytes>60000)throw new Error('Admin CSS/JS exceeds the 60 KB uncompressed budget');
console.log(`PASS: production CSS/JS ${bytes} bytes; storefront assets 0 (admin-page-only enqueue).`);
