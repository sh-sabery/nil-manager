import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..');
const version=fs.readFileSync(path.join(root,'VERSION'),'utf8').trim();
const blueprint={
 '$schema':'https://playground.wordpress.net/blueprint-schema.json',
 meta:{title:'دموی نیل منجر',author:'نیل وب',description:'تجربهٔ واقعی مدیریت قیمت و موجودی با محصولات نمونه در مرورگر'},
 preferredVersions:{php:'8.3',wp:'7.1.3'},
 features:{networking:true},
 constants:{NIL_PLAYGROUND_DEMO:true,DISABLE_WP_CRON:true,WP_DEBUG:false,WP_AUTO_UPDATE_CORE:false},
 landingPage:'/wp-admin/admin.php?page=nil-bulk',
 steps:[
  {step:'mkdir',path:'/wordpress/wp-content/mu-plugins'},
  {step:'writeFile',path:'/wordpress/wp-content/mu-plugins/nil-demo.php',data:fs.readFileSync(path.join(root,'demo/sandbox.php'),'utf8')},
  {step:'setSiteOptions',options:{woocommerce_feature_cost_of_goods_sold_enabled:'yes'}},
  {step:'installPlugin',pluginData:{resource:'url',url:'https://downloads.wordpress.org/plugin/woocommerce.11.2.1.zip'},options:{activate:true},progress:{caption:'آماده‌سازی ووکامرس…'}},
  {step:'installPlugin',pluginData:{resource:'url',url:`https://github.com/sh-sabery/nil-manager/releases/download/v${version}/nil-manager-v${version}.zip`},options:{activate:true},progress:{caption:'نصب نیل منجر…'}},
  {step:'setSiteLanguage',language:'fa_IR',progress:{caption:'آماده‌سازی محیط فارسی…'}},
  {step:'runPHP',code:fs.readFileSync(path.join(root,'demo/seed.php'),'utf8'),progress:{caption:'ساخت محصولات و تنوع‌های نمونه…'}},
  {step:'login',username:'admin'}
 ]
};
const output=JSON.stringify(blueprint,null,2)+'\n';
const target=path.join(root,'demo/blueprint.json');
if(process.argv.includes('--check')){
 if(fs.readFileSync(target,'utf8')!==output)throw Error('Run node scripts/demo.mjs');
 console.log('PASS: demo Blueprint matches source and release version');
}else{
 fs.writeFileSync(target,output);
 const dest=path.join(root,'dist/demo');fs.mkdirSync(dest,{recursive:true});
 for(const f of ['index.html','demo.css','demo.js','blueprint.json'])fs.copyFileSync(path.join(root,'demo',f),path.join(dest,f));
 for(const f of ['icon.svg','vazirmatn-arabic-wght-normal.woff2','FONT-LICENSE.txt'])fs.copyFileSync(path.join(root,'nil-manager/assets',f),path.join(dest,f));
 console.log(`Built browser demo for Nil Manager ${version}; no backend or credentials`);
}
