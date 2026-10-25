const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
(async()=>{
const source=fs.readFileSync('nilwears-bulk-manager/assets/payment-checkout'+(process.env.NIL_MINIFIED?'.min':'')+'.js','utf8');
let handler,triggers=0;const body={};const jquery=arg=>({on:(event,selector,fn)=>{assert.equal(arg,body);assert.equal(selector,'input[name="payment_method"]');handler=fn;},trigger:event=>{assert.equal(event,'update_checkout');triggers++;}});
vm.runInNewContext(source,{NIL_PAYMENT:{blocks:false},document:{body},jQuery:jquery});handler();assert.equal(triggers,1,'classic change updates checkout');
let selected='bacs',ready=false,listener,filters,pending=[],requests=[],errors={};
const validation={setValidationErrors:values=>Object.assign(errors,values),clearValidationError:key=>delete errors[key]};
const context={NIL_PAYMENT:{blocks:true},window:{wp:{data:{select:()=>({paymentMethodsInitialized:()=>ready,getActivePaymentMethod:()=>selected}),dispatch:()=>validation,subscribe:fn=>{listener=fn;}}},wc:{wcBlocksData:{paymentStore:'payment',validationStore:'validation'},blocksCheckout:{registerCheckoutFilters:(id,f)=>{filters=f;},extensionCartUpdate:request=>{requests.push(request);return new Promise((resolve,reject)=>pending.push({resolve,reject}));}}}}};
vm.runInNewContext(source,context);assert.equal(requests.length,0,'wait for gateway registration');ready=true;listener();assert.equal(requests.length,1);assert(errors['nil-payment-sync'],'checkout blocked while amount is pending');
selected='cod';listener();selected='cheque';listener();assert.equal(requests.length,1,'no concurrent gateway writes');
pending.shift().resolve();await new Promise(setImmediate);assert.equal(requests.length,2);assert.equal(requests[1].data.payment_method,'cheque','latest selection wins');assert(errors['nil-payment-sync']);
pending.shift().resolve();await new Promise(setImmediate);assert.equal(Object.keys(errors).length,0);listener();assert.equal(requests.length,2,'no request loop on unchanged state');
selected='cod';listener();pending.shift().reject(new Error('network'));await new Promise(setImmediate);assert.equal(errors['nil-payment-sync'].hidden,false,'visible error and payment remains blocked');
selected='bacs';listener();pending.shift().resolve();await new Promise(setImmediate);assert.equal(Object.keys(errors).length,0,'recovery clears own validation only');
selected='cheque';listener();selected='cod';listener();pending.shift().reject(new Error('old request failed'));await new Promise(setImmediate);assert.equal(requests.at(-1).data.payment_method,'cod','queued new method still syncs when preceding request fails');errors.other={message:'Unrelated field',hidden:false};pending.shift().resolve();await new Promise(setImmediate);assert(!errors['nil-payment-sync']&&errors.other,'leaves unrelated checkout validation untouched');delete errors.other;
selected='';listener();assert.equal(requests.at(-1).data.payment_method,'');pending.shift().resolve();await new Promise(setImmediate);
const coupons=[{code:'nil-manager-payment-discount',label:'code',discount:'100'},{code:'other',label:'Keep'}];const mapped=filters.coupons(coupons);assert.equal(mapped[0].label,'تخفیف روش پرداخت');assert.equal(mapped[1],coupons[1]);assert.equal(coupons[0].label,'code','does not mutate store data');
console.log('PASS: classic refresh, Blocks serial/coalesced updates, submit guard, failure/recovery and coupon label');
})().catch(e=>{console.error(e);process.exit(1);});
