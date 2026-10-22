const fs=require('fs'), vm=require('vm'), assert=require('assert/strict');
const {parseHTML}=require('linkedom');
(async()=>{
const templates=['grid','view','help'].map(name=>fs.readFileSync('nilwears-bulk-manager/includes-'+name+'.php','utf8').replace(/<\?php[\s\S]*?\?>/,'' )).join('');
const {window}=parseHTML('<html lang="fa" dir="rtl"><body><div id="toplevel_page_woocommerce"><a href="admin.php?page=nil-bulk">نیل</a></div><div id="nil-bulk-app"></div>'+templates+'</body></html>');
const document=window.document;window.HTMLElement.prototype.scrollIntoView=function(){};window.confirm=()=>true;
const base={cost_price:'100000',name:'تست',sku:'',category_ids:[],brand_ids:[],regular_price:'495000',sale_price:'',manage_stock:false,stock_quantity:null,stock_status:'instock',backorders:'no',sale_from:null,sale_to:null,parent_stock:false,type:'simple',parent_id:0,status:'publish'};
const rows=[{id:10,parent:0,type:'variable',name:'بارونی نیل',values:{...base,type:'variable',manage_stock:true,stock_quantity:12},version:'p'},
{id:11,parent:10,type:'variation',name:'بارونی مشکی M',values:{...base,type:'variation',parent_id:10,parent_stock:true,manage_stock:true,stock_quantity:5},version:'a'},
{id:12,parent:10,type:'variation',name:'بارونی سفید L',values:{...base,type:'variation',parent_id:10,parent_stock:true,regular_price:'695000'},version:'b'},
{id:20,parent:0,type:'simple',name:'تیشرت ساده',values:{...base,regular_price:'200000'},version:'s'}];
rows.forEach(r=>{r.sku='sku'+r.id;r.attributes='رنگ و سایز';r.url='https://nil.test/edit/'+r.id;});
let job=null,payload=null,presetList=[],brandFailure=false,marketRules=[],marketCaptured=[],marketPreviews=0;const requests=[];
async function fetchMock(url,options={}){
const parsed=new URL(url),path=parsed.pathname.replace('/nil/',''),body=options.body?JSON.parse(options.body):null;requests.push({path,body});if(path==='branding'&&brandFailure)return{ok:false,json:async()=>({message:'ذخیره انجام نشد'})};let result;
if(path==='products')result={rows:structuredClone(rows),page:1,pages:1,total:2};
else if(path==='jobs')result=job?[{id:job.id,state:job.state,created_at:'2026-10-21 12:00:00'}]:[];
else if(path==='preview'){
 payload=body;job={id:'12345678-0000-0000-0000-000000000000',state:'draft'};
 job.items=body.rows.map(r=>{const row=rows.find(x=>x.id===r.id);const after={...row.values,...r.changes};if(body.rule)after.regular_price=String(Math.ceil((Number(row.values.regular_price)*1.1-9000)/10000)*10000+9000);return{product_id:r.id,label:row.name,before:{...row.values},after,fields:Object.keys(after).filter(k=>after[k]!==row.values[k]),state:'pending',error:''};});
 result={job_id:job.id,added:job.items.length,unchanged:0,errors:[]};
}else if(path.endsWith('/seal')){job.state='ready';result={count:job.items.length};}
else if(path.endsWith('/run')){job.state=body.mode==='undo'?'undone':'done';job.items.forEach(i=>{i.state=body.mode==='undo'?'undone':'applied';rows.find(r=>r.id===i.product_id).values={...(body.mode==='undo'?i.before:i.after)};});result={done:true,processed:job.items.length};}
else if(path.startsWith('job/')){const counts={};job.items.forEach(i=>counts[i.state]=(counts[i.state]||0)+1);result={market:path==='job/market-job'?{id:'market-law',name:'Market',status:'previewed'}:null,job:{id:path==='job/market-job'?'market-job':job.id,state:path==='job/market-job'?'ready':job.state},items:job.items,counts:Object.entries(counts).map(([state,n])=>({state,n}))};}
else if(path==='market/rates')result={rates:{price_dollar_rl:{price:275000,updated_at:1792699055},price_eur:{price:305360,updated_at:1792699055},gold18k:{price:26811000,updated_at:1792699527}},errors:{}};
else if(path==='market/rules'){payload=body;if(!body)result=marketRules;else if(body.action==='create'){marketCaptured=[];marketPreviews=0;result={...body,id:'market-law',owner:1,count:0,status:'draft'};marketRules=[result];}else{const rule=marketRules.find(s=>s.id===body.id);if(body.action==='activate'){rule.status='active';rule.next=1792699999;}if(body.action==='pause')rule.status='paused';if(body.action==='resume')rule.status='active';if(body.action==='interval')rule.interval=Number(body.interval);if(body.action==='delete')marketRules=marketRules.filter(s=>s.id!==body.id);result=marketRules;}}
else if(path==='market/capture'){payload=body;marketCaptured.push(...body.rows);result={count:marketCaptured.length};}
else if(path==='market/preview'){const rule=marketRules[0];Object.assign(rule,{count:marketCaptured.length,added:marketCaptured.length,status:'previewed',job:'market-job',quote:{price:275000,updated_at:1792699055},sample:[{id:11,name:'Market sample',before:{regular_price:'495000'},after:{regular_price:'544500'}}]});result={done:++marketPreviews>1,rule};}
else if(path==='presets'){if(body){payload=body;if(body.delete)presetList=presetList.filter(p=>p.id!==body.id);else presetList.push({id:'p'+presetList.length,...body});}result=presetList;}
else if(path==='variation-parent')result={id:10,name:'والد',version:'v',count:2,attributes:[{name:'color',options:['black']},{name:'size',options:['M']}]};
else if(path==='variations/preview'){payload=body;result={token:'v-token',rows:[{parent_id:10,attributes:{color:'blue',size:'M'},sku:'new',regular_price:'300000'}],skipped:[]};}
else if(path==='variations/apply'){payload=body;result={created:[40]};}
else if(path==='create'){payload=body;result={id:30,created:[30,31,32,33,34]};}
else if(path==='branding'){payload=body;result=body;}
else if(path==='updater')result={configured:false,current:'1.5.0',available:null};
else if(path==='sheet/read')result={id:'file-token',headers:['id','regular_price'],sample:[['11','600000']],count:1};
else if(path==='sheet/prepare'){payload=body;result={updates:[],creates:[],errors:[]};}
else throw new Error('Unknown endpoint: '+path);
return{ok:true,json:async()=>structuredClone(result)};
}
const context=vm.createContext({window,document,Event:window.Event,NIL_BULK:{api:'https://nil.test/nil/',nonce:'test',currency:'تومان',currencyCode:'IRT',decimals:0,categories:[],canMarket:true,userId:1},fetch:fetchMock,Intl,Map,Set,URLSearchParams,URL,Blob,setTimeout,console,crypto:require('crypto').webcrypto,atob,Uint8Array,FileReader:class{readAsDataURL(){this.result='data:text/csv;base64,aWQscHJpY2U=';this.onload();}}});
vm.runInContext(fs.readFileSync('nilwears-bulk-manager/assets/admin'+(process.env.NIL_MINIFIED?'.min':'')+'.js','utf8'),context);
const $=s=>document.querySelector(s),change=el=>el.dispatchEvent(new window.Event('change',{bubbles:true}));
async function idle(){for(let i=0;i<200;i++){await new Promise(r=>setTimeout(r,1));if(!$('#nil-bulk-app').hasAttribute('aria-busy'))return;}throw new Error('busy timeout: '+$('#nil-message').textContent);}
await idle();assert.equal(document.querySelectorAll('#nil-rows tr').length,4);
assert($('[data-id="12"][data-field="stock_quantity"]').disabled);
const parent=$('[data-select="10"]');parent.checked=true;change(parent);
$('#nil-bulk').click();await idle();
assert.deepEqual(payload.rows.map(r=>r.id),[11,12]);assert.equal($('#nil-job-state').textContent,'آماده ذخیره');assert($('#nil-preview-table').textContent.includes('۵۴۹٬۰۰۰'));
$('#nil-apply').click();await idle();assert.equal($('[data-id="11"][data-field="regular_price"]').value,'549000');
$('#nil-undo').click();await idle();assert.equal($('[data-id="11"][data-field="regular_price"]').value,'495000');
let qty=$('[data-id="11"][data-field="stock_quantity"]');qty.value='3';change(qty);assert(!$('#nil-manual').disabled);
$('#nil-manual').click();await idle();assert.equal(payload.rows[0].changes.stock_quantity,'3');
// An edit made after preview must survive saving the older preview.
qty=$('[data-id="11"][data-field="stock_quantity"]');qty.value='4';change(qty);
$('#nil-apply').click();await idle();assert.equal($('[data-id="11"][data-field="stock_quantity"]').value,'4');assert(!$('#nil-manual').disabled);
$('#nil-reset').click();assert.equal($('[data-id="11"][data-field="stock_quantity"]').value,'3');assert($('#nil-manual').disabled);
vm.runInContext(fs.readFileSync('nilwears-bulk-manager/assets/suite'+(process.env.NIL_MINIFIED?'.min':'')+'.js','utf8'),context);
assert.equal(document.querySelectorAll('[role=tab]').length,8);assert(!$('#nil-panel-products').hidden);$('#nil-tab-media').click();assert(!$('#nil-panel-media').hidden);assert($('#nil-panel-products').hidden);$('#nil-tab-products').click();assert(!$('#nil-panel-products').hidden);
assert($('#nil-panel-pricing').contains($('#nil-bulk')));assert(!$('#nil-panel-products').contains($('#nil-bulk')));
$('#nil-tab-help').click();assert(!$('#nil-panel-help').hidden);assert($('#nil-panel-help').textContent.includes('قیمت تازه، در سه قدم.'));
const beforeRecipe=requests.length;$('[data-recipe="ending"]').click();assert(!$('#nil-panel-pricing').hidden);assert.equal(requests.length,beforeRecipe,'recipe only fills controls');assert.equal($('#nil-value').value,'10');assert.equal($('#nil-ending').value,'9000');assert($('#nil-demo-result').textContent.includes('۵۴۹٬۰۰۰'));
$('[data-recipe="fixed"]').click();assert.equal($('#nil-operation').value,'fixed');assert($('#nil-demo-result').textContent.includes('۵۴۵٬۰۰۰'));assert($('#nil-step').disabled);
$('[data-recipe="discount"]').click();assert($('#nil-demo-result').textContent.includes('۴۴۵٬۵۰۰'));
$('[data-recipe="percent"]').click();$('#nil-value').value='۲۰';$('#nil-value').dispatchEvent(new window.Event('input',{bubbles:true}));assert($('#nil-demo-result').textContent.includes('۵۹۴٬۰۰۰'));
$('[data-recipe="ending"]').click();$('#nil-step').value='0';$('#nil-step').dispatchEvent(new window.Event('input',{bubbles:true}));assert.equal($('#nil-demo-result').className,'nil-error-text');$('[data-recipe="ending"]').click();
$('#nil-tab-products').click();
assert($('#nil-create-form'));assert($('#nil-media-action'));assert($('#nil-mapping'));assert($('#nil-rows').textContent.includes('۳۹۵'));
$('#nil-create-name').value='محصول جدید';$('#nil-create-attrs').value='رنگ: مشکی، سفید\nسایز: M، L';
$('#nil-create-form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await idle();assert.equal(payload.name,'محصول جدید');assert.deepEqual(Object.keys(payload.attributes),['رنگ','سایز']);assert($('#nil-create-result').textContent.includes('#30'));
const fileInput=$('#nil-import-file');Object.defineProperty(fileInput,'files',{value:[{name:'test.csv',size:20}]});$('#nil-file-read').click();await idle();assert(!$('#nil-file-prepare').disabled,'mapping action becomes enabled after async load');assert.equal(document.querySelectorAll('[data-map]').length,2);$('#nil-file-prepare').click();await idle();assert.equal(payload.mapping[0],'id');assert.equal(payload.mapping[1],'regular_price');
$('#nil-brand-title').value='فروشگاه من';$('#nil-brand-accent').value='#146b5a';$('#nil-brand-logo').value='https://example.test/logo.png';$('#nil-brand-title').dispatchEvent(new window.Event('input'));assert.equal($('#nil-brand-preview strong').textContent,'فروشگاه من');assert.notEqual($('h1').textContent,'فروشگاه من','unsaved branding stays in preview');$('#nil-brand-save').click();await idle();assert.equal(payload.title,'فروشگاه من');assert.equal($('h1').textContent,'فروشگاه من');assert.equal($('#toplevel_page_woocommerce a').textContent,'فروشگاه من');assert.equal($('#nil-bulk-app').style.getPropertyValue('--nil-accent'),'#146b5a');assert.equal(document.querySelectorAll('.nil-header .nil-mark img').length,1);$('#nil-brand-save').click();await idle();assert.equal(document.querySelectorAll('.nil-header .nil-mark img').length,1,'logo not duplicated');
brandFailure=true;$('#nil-brand-title').value='ذخیره نشده';$('#nil-brand-save').click();await idle();assert.equal($('h1').textContent,'فروشگاه من','failed save leaves applied identity intact');assert.equal($('#nil-message').getAttribute('role'),'alert');brandFailure=false;
$('#nil-brand-title').value='فروشگاه من';$('#nil-brand-logo').value='';$('#nil-brand-save').click();await idle();assert.equal(document.querySelectorAll('.nil-header .nil-mark img').length,0,'clearing logo applied immediately');$('#nil-update-status').click();await idle();assert($('#nil-update-result').textContent.includes('1.5.0'));
vm.runInContext(fs.readFileSync('nilwears-bulk-manager/assets/advanced'+(process.env.NIL_MINIFIED?'.min':'')+'.js','utf8'),context);
const unrelated=document.createElement('table');unrelated.className='nil-table';unrelated.innerHTML='<tr>'+Array.from({length:9},(_,i)=>'<td>'+i+'</td>').join('')+'</tr>';document.getElementById('nil-bulk-app').append(unrelated);
const costColumn=$('#nil-columns [data-col="cost"]');costColumn.checked=false;change(costColumn);assert($('th[data-column="cost"]').hidden,'hidden cost column');assert(!unrelated.querySelector('[data-column]'),'column layout scoped to product grid');
$('#nil-p-name').value='نمای من';$('#nil-p-view').click();await idle();assert.equal(payload.kind,'view');assert(!payload.data.columns.includes('cost'));$('#nil-p-apply').click();await idle();assert($('th[data-column="cost"]').hidden,'saved columns survive reload');
$('#nil-v-parent').value='10';$('#nil-v-load').click();await idle();assert($('#nil-v-options').value.includes('color: black'));$('#nil-v-options').value='color: blue\nsize: M';$('#nil-v-price').value='300000';$('#nil-v-preview').click();await idle();assert.equal(payload.options.color[0],'blue');assert(!$('#nil-v-apply').hidden);$('#nil-v-apply').click();await idle();assert.equal(payload.token,'v-token');assert($('#nil-v-result').textContent.includes('#40'));
const beforeMarketBoot=requests.length;vm.runInContext(fs.readFileSync('nilwears-bulk-manager/assets/market'+(process.env.NIL_MINIFIED?'.min':'')+'.js','utf8'),context);assert.equal(requests.length,beforeMarketBoot,'market screen has no background rate or report calls');
$('#nil-market-rates').click();await idle();assert($('#nil-market-rates-result').textContent.includes('۲۷۵٬۰۰۰'));
const selectMarketParent=$('[data-select="10"]');selectMarketParent.checked=true;change(selectMarketParent);$('#nil-market-base').value='250000';$('#nil-market-preview').click();await idle();assert.deepEqual(marketCaptured.map(r=>r.id),[11,12],'capture variations, not parent price');assert.equal(marketRules[0].base_rate,'250000');assert(!$('#nil-market-activate').hidden);assert($('#nil-market-preview-result').textContent.includes('۵۴۴٬۵۰۰'));$('#nil-market-details').click();await idle();assert($('#nil-apply').hidden,'market full preview cannot be applied through regular history controls');
$('#nil-market-interval').value='15';$('#nil-market-interval').dispatchEvent(new window.Event('input',{bubbles:true}));assert($('#nil-market-activate').hidden,'editing settings invalidates confirmation');$('#nil-market-preview').click();await idle();$('#nil-market-activate').click();await idle();assert.equal(payload.action,'activate');assert.equal(marketRules[0].status,'active');assert.equal(document.querySelectorAll('#nil-market-controls').length,1);
$('[data-market-action="pause"]').click();await idle();assert.equal(marketRules[0].status,'paused');assert.equal(document.querySelectorAll('#nil-market-controls').length,1,'no duplicate rule action controls');$('[data-market-action="resume"]').click();await idle();assert.equal(marketRules[0].status,'active');$('#nil-market-controls input[type=number]').value='30';$('[data-market-action="interval"]').click();await idle();assert.equal(payload.action,'interval');assert.equal(marketRules[0].interval,30,'interval changed without recapturing product bases');
// Fresh document: saved identity is applied at startup; manager-only disable survives async loading.
const reloadWindow=parseHTML('<html lang="fa" dir="rtl"><body><div id="nil-bulk-app"></div>'+templates+'</body></html>').window;reloadWindow.HTMLElement.prototype.scrollIntoView=function(){};
const reloadContext=vm.createContext({...context,window:reloadWindow,document:reloadWindow.document,Event:reloadWindow.Event,NIL_BULK:{...context.NIL_BULK,canBrand:false,canMarket:false}});
for(const script of ['admin','suite','advanced','market'])vm.runInContext(fs.readFileSync('nilwears-bulk-manager/assets/'+script+(process.env.NIL_MINIFIED?'.min':'')+'.js','utf8'),reloadContext);
await new Promise(resolve=>setTimeout(resolve,10));assert.equal(reloadWindow.document.querySelector('h1').textContent,'فروشگاه من','saved branding shown after reload');assert.equal(reloadWindow.document.getElementById('nil-brand-accent').value,'#146b5a');assert(reloadWindow.document.getElementById('nil-brand-save').disabled,'manager branding permission survives initial async load');assert(reloadWindow.document.getElementById('nil-market-preview').disabled,'manager market permission survives initial async load');
console.log('PASS: suite forms, attribute parsing, import column mapping, async button state, live branding success/failure/logo clearing, help recipes, example calculator, pricing workspace, market rate/capture/preview/activation/pause/resume, updater, saved views/columns and append-variation preview/apply; DOM table, inherited stock, parent selection, preview, apply, undo, manual stock, preservation of post-preview edits, reset');
})().catch(e=>{console.error(e);process.exit(1);});
