const fs=require('fs'), vm=require('vm'), assert=require('assert/strict');
const {parseHTML}=require('linkedom');
(async()=>{
const {window}=parseHTML('<html lang="fa" dir="rtl"><body><div id="nil-bulk-app"></div></body></html>');
const document=window.document;window.HTMLElement.prototype.scrollIntoView=function(){};window.confirm=()=>true;
const base={regular_price:'495000',sale_price:'',manage_stock:false,stock_quantity:null,stock_status:'instock',backorders:'no',sale_from:null,sale_to:null,parent_stock:false,type:'simple',parent_id:0,status:'publish'};
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
else throw new Error('Unknown endpoint: '+path);
return{ok:true,json:async()=>structuredClone(result)};
}
const context=vm.createContext({window,document,NIL_BULK:{api:'https://nil.test/nil/',nonce:'test',currency:'تومان',currencyCode:'IRT',decimals:0,categories:[]},fetch:fetchMock,Intl,Map,Set,URLSearchParams,URL,Blob,setTimeout,console});
vm.runInContext(fs.readFileSync('nilwears-bulk-manager/assets/admin.js','utf8'),context);
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
console.log('PASS: DOM table, inherited stock, parent selection, preview, apply, undo, manual stock, preservation of post-preview edits, reset');
})().catch(e=>{console.error(e);process.exit(1);});
