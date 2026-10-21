const fs=require('fs'), vm=require('vm'), assert=require('assert/strict');
const {parseHTML}=require('linkedom');
(async()=>{
const {window}=parseHTML('<html lang="fa" dir="rtl"><body><div id="nil-bulk-app"></div></body></html>');
const document=window.document;window.HTMLElement.prototype.scrollIntoView=function(){};window.confirm=()=>true;
const base={cost_price:'100000',name:'تست',sku:'',category_ids:[],brand_ids:[],regular_price:'495000',sale_price:'',manage_stock:false,stock_quantity:null,stock_status:'instock',backorders:'no',sale_from:null,sale_to:null,parent_stock:false,type:'simple',parent_id:0,status:'publish'};
const rows=[{id:10,parent:0,type:'variable',name:'بارونی نیل',values:{...base,type:'variable',manage_stock:true,stock_quantity:12},version:'p'},
{id:11,parent:10,type:'variation',name:'بارونی مشکی M',values:{...base,type:'variation',parent_id:10,parent_stock:true,manage_stock:true,stock_quantity:5},version:'a'},
{id:12,parent:10,type:'variation',name:'بارونی سفید L',values:{...base,type:'variation',parent_id:10,parent_stock:true,regular_price:'695000'},version:'b'},
{id:20,parent:0,type:'simple',name:'تیشرت ساده',values:{...base,regular_price:'200000'},version:'s'}];
rows.forEach(r=>{r.sku='sku'+r.id;r.attributes='رنگ و سایز';r.url='https://nil.test/edit/'+r.id;});
let job=null,payload=null;
async function fetchMock(url,options={}){
const parsed=new URL(url),path=parsed.pathname.replace('/nil/',''),body=options.body?JSON.parse(options.body):null;let result;
if(path==='products')result={rows:structuredClone(rows),page:1,pages:1,total:2};
else if(path==='jobs')result=job?[{id:job.id,state:job.state,created_at:'2026-10-21 12:00:00'}]:[];
else if(path==='preview'){
 payload=body;job={id:'12345678-0000-0000-0000-000000000000',state:'draft'};
 job.items=body.rows.map(r=>{const row=rows.find(x=>x.id===r.id);const after={...row.values,...r.changes};if(body.rule)after.regular_price=String(Math.ceil((Number(row.values.regular_price)*1.1-9000)/10000)*10000+9000);return{product_id:r.id,label:row.name,before:{...row.values},after,fields:Object.keys(after).filter(k=>after[k]!==row.values[k]),state:'pending',error:''};});
 result={job_id:job.id,added:job.items.length,unchanged:0,errors:[]};
}else if(path.endsWith('/seal')){job.state='ready';result={count:job.items.length};}
else if(path.endsWith('/run')){job.state=body.mode==='undo'?'undone':'done';job.items.forEach(i=>{i.state=body.mode==='undo'?'undone':'applied';rows.find(r=>r.id===i.product_id).values={...(body.mode==='undo'?i.before:i.after)};});result={done:true,processed:job.items.length};}
else if(path.startsWith('job/')){const counts={};job.items.forEach(i=>counts[i.state]=(counts[i.state]||0)+1);result={job:{id:job.id,state:job.state},items:job.items,counts:Object.entries(counts).map(([state,n])=>({state,n}))};}
else if(path==='create'){payload=body;result={id:30,created:[30,31,32,33,34]};}
else if(path==='branding'){payload=body;result=body;}
else if(path==='updater')result={configured:false,current:'1.2.0',available:null};
else if(path==='sheet/read')result={id:'file-token',headers:['id','regular_price'],sample:[['11','600000']],count:1};
else if(path==='sheet/prepare'){payload=body;result={updates:[],creates:[],errors:[]};}
else throw new Error('Unknown endpoint: '+path);
return{ok:true,json:async()=>structuredClone(result)};
}
const context=vm.createContext({window,document,NIL_BULK:{api:'https://nil.test/nil/',nonce:'test',currency:'تومان',currencyCode:'IRT',decimals:0,categories:[]},fetch:fetchMock,Intl,Map,Set,URLSearchParams,URL,Blob,setTimeout,console,crypto:require('crypto').webcrypto,atob,Uint8Array,FileReader:class{readAsDataURL(){this.result='data:text/csv;base64,aWQscHJpY2U=';this.onload();}}});
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
assert.equal(document.querySelectorAll('[role=tab]').length,7);assert(!$('#nil-panel-products').hidden);$('#nil-tab-media').click();assert(!$('#nil-panel-media').hidden);assert($('#nil-panel-products').hidden);$('#nil-tab-products').click();assert(!$('#nil-panel-products').hidden);
assert($('#nil-create-form'));assert($('#nil-media-action'));assert($('#nil-mapping'));assert($('#nil-rows').textContent.includes('۳۹۵'));
$('#nil-create-name').value='محصول جدید';$('#nil-create-attrs').value='رنگ: مشکی، سفید\nسایز: M، L';
$('#nil-create-form').dispatchEvent(new window.Event('submit',{bubbles:true,cancelable:true}));await idle();assert.equal(payload.name,'محصول جدید');assert.deepEqual(Object.keys(payload.attributes),['رنگ','سایز']);assert($('#nil-create-result').textContent.includes('#30'));
const fileInput=$('#nil-import-file');Object.defineProperty(fileInput,'files',{value:[{name:'test.csv',size:20}]});$('#nil-file-read').click();await idle();assert(!$('#nil-file-prepare').disabled,'mapping action becomes enabled after async load');assert.equal(document.querySelectorAll('[data-map]').length,2);$('#nil-file-prepare').click();await idle();assert.equal(payload.mapping[0],'id');assert.equal(payload.mapping[1],'regular_price');
$('#nil-brand-title').value='فروشگاه من';$('#nil-brand-save').click();await idle();assert.equal(payload.title,'فروشگاه من');$('#nil-update-status').click();await idle();assert($('#nil-update-result').textContent.includes('1.2.0'));
console.log('PASS: suite forms, attribute parsing, import column mapping, async button state, branding, updater; DOM table, inherited stock, parent selection, preview, apply, undo, manual stock, preservation of post-preview edits, reset');
})().catch(e=>{console.error(e);process.exit(1);});
