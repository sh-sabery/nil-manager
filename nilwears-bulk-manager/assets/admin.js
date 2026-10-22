/* global NIL_BULK */
(() => {
    'use strict';
    const cfg = NIL_BULK, root = document.getElementById('nil-bulk-app');
    const state = { rows: [], selected: new Set(), changes: new Map(), page: 1, pages: 1, busy: false, job: null, jobPage: 1, detail: null, previewChanges: null, columns:['cost','profit','regular','sale','manage','quantity','stock'], order:['cost','profit','regular','sale','manage','quantity','stock'] };
    const labels = {cost_defined:'هزینه تعریف‌شده COGS',cost_additive:'جمع‌شونده با والد',name:'نام',sku:'SKU',description:'توضیحات',short_description:'توضیح کوتاه',cost_price:'قیمت خرید',category_ids:'دسته‌ها',brand_ids:'برندها',regular_price:'قیمت اصلی', sale_price:'قیمت حراج', manage_stock:'مدیریت موجودی', stock_quantity:'تعداد', stock_status:'وضعیت موجودی'};
    const publication = {publish:'منتشرشده',private:'خصوصی',draft:'پیش‌نویس',pending:'در انتظار بررسی',future:'زمان‌بندی‌شده'};
    const statuses = {pending:'در انتظار', applied:'ذخیره‌شده', skipped:'ردشده', undone:'بازگردانده‌شده', undo_failed:'بازگردانی ناموفق'};
    const jobs = {ready:'آماده ذخیره', running:'در حال ذخیره', done:'ذخیره پایان یافته', undoing:'در حال بازگردانی', undone:'بازگردانی پایان یافته'};
    const esc = value => String(value ?? '').replace(/[&<>"']/g, x => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
    const fmt = value => value === '' || value == null ? '—' : new Intl.NumberFormat('fa-IR', {maximumFractionDigits:cfg.decimals}).format(Number(value));
    const select = (id, options, value) => `<select id="${id}">${options.map(([v,t]) => `<option value="${v}" ${String(v)===String(value)?'selected':''}>${esc(t)}</option>`).join('')}</select>`;
    root.innerHTML = `
        <header class="nil-header"><div class="nil-identity"><span class="nil-mark" aria-hidden="true">N</span><div><span class="nil-kicker">فضای مدیریت فروشگاه</span><h1>قیمت و موجودی نیل</h1><p>محصولات، قیمت و موجودی؛ با کنترل قبل از هر تغییر.</p></div></div><div class="nil-header-actions"><span class="nil-unit">واحد: ${esc(cfg.currency)} (${esc(cfg.currencyCode)})</span><button class="button" data-go="help">راهنما و شروع سریع</button></div></header>
        <div id="nil-message" role="status" aria-live="polite"></div>
        <section class="nil-card" id="nil-products"><div class="nil-section-head"><div><span class="nil-kicker">فهرست کالاها</span><h2>محصولات فروشگاه</h2></div><button class="button button-primary" data-go="pricing">تغییر گروهی قیمت ←</button></div><form id="nil-filter" class="nil-toolbar"><label>جست‌وجو<input id="nil-search" placeholder="نام محصول، شناسه یا SKU دقیق"></label><label>دسته‌بندی<select id="nil-category"><option value="">همه دسته‌ها</option>${cfg.categories.map(c => `<option value="${Number(c.id)}">${esc(c.name)}</option>`).join('')}</select></label><details class="nil-advanced"><summary>فیلترهای بیشتر</summary><div class="nil-toolbar"><label>برند<select id="nil-brand"><option value="">همه برندها</option>${(cfg.brands||[]).map(c=>`<option value="${Number(c.id)}">${esc(c.name)}</option>`).join('')}</select></label>
        <label>نوع${select('nil-type',[['','همه'],['simple','ساده'],['variable','متغیر']],'')}</label>
        <label>انتشار${select('nil-status',[['','همه'],...Object.entries(publication)],'')}</label>
        <label>موجودی${select('nil-stock',[['','همه'],['instock','موجود'],['outofstock','ناموجود'],['onbackorder','پیش‌خرید']],'')}</label>
        <label>از قیمت<input id="nil-min_price" inputmode="decimal"></label><label>تا قیمت<input id="nil-max_price" inputmode="decimal"></label>
        <label>ترتیب${select('nil-sort',[['ID','شناسه'],['modified','آخرین تغییر'],['title','نام']],'modified')}</label>
        </div></details><button class="button" type="submit">جست‌وجو / تازه‌سازی</button><span id="nil-total"></span></form>
        <p class="nil-hint">قیمت‌ها با واحد فعلی فروشگاه هستند. ردیف والد با زمینهٔ بنفش و هر ورییشن زیر آن نمایش داده می‌شود. خانهٔ خالیِ حراج یعنی حذف حراج؛ قیمت صفر یعنی رایگان.</p>
        <div class="nil-table-scroll"><table class="widefat nil-table"><thead><tr><td><input id="nil-select-all" type="checkbox" aria-label="انتخاب همه ردیف‌های این صفحه"></td><th>محصول / رنگ و سایز</th><th>قیمت خرید</th><th>سود / حاشیه</th><th>قیمت اصلی</th><th>قیمت حراج</th><th>مدیریت تعداد</th><th>موجودی</th><th>وضعیت</th></tr></thead><tbody id="nil-rows"></tbody></table></div>
        <footer class="nil-toolbar nil-table-footer"><button type="button" class="button" id="nil-prev">صفحه قبل</button><span id="nil-pagination"></span><button type="button" class="button" id="nil-next">صفحه بعد</button><span id="nil-dirty"></span><button type="button" class="button button-primary" id="nil-manual">پیش‌نمایش ویرایش‌های جدول</button><button type="button" class="button" id="nil-reset">پاک‌کردن ویرایش‌ها</button></footer></section>
        <section class="nil-card" id="nil-pricing"><div class="nil-section-head"><div><span class="nil-kicker">۱ · تنظیم تغییر</span><h2>قیمت‌گذاری، با پیش‌نمایش</h2></div><button class="button" data-go="help">چطور قیمت را تغییر بدهم؟</button></div><p id="nil-scope-summary" class="nil-callout" aria-live="polite"></p><div class="nil-recipes"><span>شروع با یک مثال:</span><button class="button" data-recipe="ending">۱۰٪ + پایان ۹۰۰۰</button><button class="button" data-recipe="percent">۱۰٪ افزایش</button><button class="button" data-recipe="fixed">افزایش مبلغی</button><button class="button" data-recipe="discount">۱۰٪ کاهش</button></div><fieldset><legend>کدام محصولات و کدام قیمت؟</legend><div class="nil-rule-grid">
        <label>محدوده${select('nil-scope', [['selected','ردیف‌های انتخاب‌شدهٔ همین صفحه'],['page','همهٔ ورییشن‌ها و ساده‌های همین صفحه'],['all','همهٔ محصولاتِ فیلتر فعلی']], 'selected')}</label>
        <label>مبنای قیمت${select('nil-source',[['current','قیمت فعلی'],['cost','قیمت خرید + درصد یا مبلغ']],'current')}</label><label>ستون${select('nil-target', [['regular_price','قیمت اصلی'],['sale_price','قیمت حراج موجود'],['both','اصلی و حراج موجود']], 'regular_price')}</label></div></fieldset><fieldset><legend>چه تغییری اعمال شود؟</legend><div class="nil-rule-grid">
        <label>عملیات${select('nil-operation', [['percent','تغییر درصدی'],['fixed','افزودن مبلغ ثابت'],['set','تعیین قیمت ثابت'],['round','فقط رندکردن']], 'percent')}</label>
        <label><span id="nil-value-label">درصد تغییر</span><input id="nil-value" inputmode="decimal" value="10" aria-label="مقدار تغییر؛ برای کاهش منفی بنویسید"></label>
        </div></fieldset><fieldset><legend>پایان قیمت؛ اختیاری</legend><div class="nil-rule-grid"><label>رندکردن${select('nil-rounding', [['up','رو به بالا'],['nearest','نزدیک‌ترین؛ تساوی رو به بالا'],['down','رو به پایین'],['none','بدون رندکردن']], 'up')}</label>
        <label>گام قیمت<input id="nil-step" inputmode="decimal" value="10000"></label><label>پایان قیمت<input id="nil-ending" inputmode="decimal" value="9000"></label>
        </div></fieldset><div class="nil-example nil-simulator"><label>آزمایش روی یک مبلغ<input id="nil-demo-price" inputmode="decimal" value="495000"></label><div><span>نتیجهٔ نمونه با تنظیمات فعلی</span><strong id="nil-demo-result" aria-live="polite"></strong><small>نمونه است؛ پیش‌نمایش محصولات را هم بررسی کنید.</small></div></div>
        <p class="nil-hint">برای کاهش عدد منفی بنویسید. انتخاب والد، تمام ورییشن‌هایش را شامل می‌شود. گام و پایان قیمت را با واحد فعلی فروشگاه وارد کنید.</p>
        <button type="button" class="button button-primary" id="nil-bulk">ساخت پیش‌نمایش تغییر گروهی</button></section>
        <section class="nil-card" id="nil-preview" hidden><div class="nil-toolbar"><h2>۲ · بررسی و تأیید تغییرات</h2><span id="nil-job-state"></span></div><p id="nil-summary"></p><div id="nil-preview-table" class="nil-table-scroll"></div><div class="nil-toolbar"><button type="button" class="button" id="nil-job-prev">گزارش قبل</button><span id="nil-job-pagination"></span><button type="button" class="button" id="nil-job-next">گزارش بعد</button><button type="button" class="button" id="nil-export">دانلود گزارش CSV</button><button type="button" class="button button-primary" id="nil-apply">تأیید و ذخیره همین پیش‌نمایش</button><button type="button" class="button" id="nil-undo">بازگردانی این عملیات</button></div><p class="nil-hint">ردیف متداخل رد می‌شود. عملیات قطع‌شده را از تاریخچه ادامه دهید؛ تاریخ حراج و پیش‌خرید حفظ می‌شوند.</p></section>
        <section class="nil-card"><div class="nil-toolbar"><h2>۲۰ عملیات اخیر شما</h2><button type="button" class="button" id="nil-history-refresh">تازه‌سازی تاریخچه</button></div><div id="nil-history"></div></section>
    `;
    const $ = id => document.getElementById(`nil-${id}`);
    function demo(){
        const mode=$('rounding').value,op=$('operation').value,out=$('demo-result');
        $('step').disabled=$('ending').disabled=mode==='none';$('value').disabled=op==='round';
        $('value-label').textContent=op==='percent'?'درصد تغییر':op==='set'?'قیمت ثابت':op==='fixed'?'مبلغ افزوده':'مقدار (لازم نیست)';
        try{
            const number=id=>{const text=$(id).value.trim().replace(/[۰-۹٠-٩]/g,c=>c.charCodeAt(0)%16).replace(/[,٬]/g,'').replace('٫','.');if(!/^-?\d+(?:\.\d{1,6})?$/.test(text)||Math.abs(Number(text))>1e11)throw Error();return Number(text);};
            let n=number('demo-price'),v=number('value'),scale=10**cfg.decimals;
            if(op==='percent')n*=1+v/100;else if(op==='fixed')n+=v;else if(op==='set')n=v;
            if(n<0||n>1e11)throw Error();n=Math.round(n*scale);
            if(mode!=='none'){const step=Math.round(number('step')*scale),end=Math.round(number('ending')*scale);if(step<=0||end<0||end>=step)throw Error();const pos=(n-end)/step;n=(mode==='up'?Math.ceil(pos):mode==='down'?Math.floor(pos):Math.floor(pos+.5))*step+end;if(n<0&&mode==='down')throw Error();n=Math.max(end,n);}
            if(n/scale>1e11)throw Error();out.textContent=fmt(n/scale)+' '+cfg.currency;out.className='';
        }catch(_){out.textContent='عدد، گام و پایان قیمت را بررسی کنید.';out.className='nil-error-text';}
    }
    const ruleInputs=['operation','value','rounding','step','ending','demo-price'];
    root.addEventListener('input',e=>{if(ruleInputs.includes(e.target.id.replace('nil-','')))demo();});
    root.addEventListener('change',e=>{if(ruleInputs.includes(e.target.id.replace('nil-','')))demo();if(e.target.id==='nil-scope')renderTableMeta();});
    document.addEventListener('nil-rule-ready',demo);demo();
    function message(text, error=false) { $('message').className = `nil-message ${error?'nil-error':''}`; $('message').textContent = text; $('message').setAttribute('role',error?'alert':'status');if(error)$('message').scrollIntoView({block:'nearest'}); }
    function progress(text) { message(text); }
    async function api(path, data) {
        const response = await fetch(cfg.api+path, {method:data ? 'POST':'GET', credentials:'same-origin', headers:{'X-WP-Nonce':cfg.nonce, ...(data?{'Content-Type':'application/json'}:{})}, ...(data?{body:JSON.stringify(data)}:{})});
        let result;
        try { result = await response.json(); } catch (_) { throw new Error('پاسخ سرور قابل خواندن نیست. تاریخچه را بررسی و در صورت نیاز ادامه دهید.'); }
        if (!response.ok) throw new Error(result.message || 'ارتباط با سرور برقرار نشد.');
        return result;
    }
    async function task(fn) {
        if (state.busy) return;
        state.busy = true; root.setAttribute('aria-busy','true');
        root.querySelectorAll('button, input, select, textarea').forEach(e => { e.dataset.nilWasDisabled = e.disabled ? '1':'0'; e.disabled = true; });
        try { await fn(); } catch (error) { message(error.message, true); }
        finally {
            state.busy = false; root.removeAttribute('aria-busy');
            root.querySelectorAll('button, input, select, textarea').forEach(e => { if(e.disabled&&e.dataset.nilWasDisabled!=null)e.disabled=e.dataset.nilWasDisabled==='1';delete e.dataset.nilWasDisabled; });
            renderRows(); updatePreviewButtons(); demo();
        }
    }
    function filters(page) { const params=new URLSearchParams({page:String(page)}); for(const key of ['search','category','brand','type','status','stock','min_price','max_price','sort']) params.set(key,($(key).value||'').trim()); return params; }
    async function load(page=state.page) {
        const result = await api('products?'+filters(page));
        state.page = result.page; state.pages = result.pages; state.rows = result.rows;
        state.selected.clear(); $('select-all').checked = false;
        $('total').textContent = `${fmt(result.total)} محصول والد یا ساده`;
        renderRows();
    }
    function renderRows() {
        $('rows').innerHTML = state.rows.length ? state.rows.map(row => {
            const values = {...row.values, ...(state.changes.get(row.id)?.changes || {})};
            const variable = row.type === 'variable', inherited = row.type === 'variation' && values.parent_stock && !values.manage_stock;
            const price = field => variable ? '<span class="nil-muted">از ورییشن‌ها</span>' : `<input data-field="${field}" data-id="${row.id}" aria-label="${labels[field]} ${esc(row.name)}" inputmode="decimal" value="${esc(values[field])}">`;
            const quantityDisabled = !values.manage_stock;
            const statusDisabled = values.manage_stock || inherited || variable;
            const now=Date.now()/1000, activeSale=(!values.sale_from||values.sale_from<=now)&&(!values.sale_to||values.sale_to>=now);
            const effective=activeSale&&values.sale_price!==''&&values.sale_price!=null&&Number(values.sale_price)<Number(values.regular_price)?values.sale_price:values.regular_price;
            const profit=values.cost_price!==''&&values.cost_price!=null&&effective!==''?Number(effective)-Number(values.cost_price):null;
            const editText=field=>`<label>${esc(labels[field])}<${field.includes('description')?'textarea':'input'} data-field="${field}" data-id="${row.id}" ${field.includes('description')?'':`value="${esc(values[field]??(field==='name'?row.name:field==='sku'?row.sku:''))}"`}>${field.includes('description')?esc(values[field]||'')+'</textarea>':''}</label>`;
            return `<tr class="${variable?'nil-parent':''} ${row.type==='variation'?'nil-variation':''} ${state.changes.has(row.id)?'nil-changed':''}"><td><input type="checkbox" data-select="${row.id}" ${state.selected.has(row.id)?'checked':''} aria-label="انتخاب ${esc(row.name)}"></td><td class="nil-name"><a href="${esc(row.url)}" target="_blank" rel="noopener">${esc(row.name)}</a><small>${row.type==='variation'?esc(row.attributes):variable?'محصول ورییشن‌دار':'محصول ساده'} · ${esc(publication[row.values.status]||row.values.status)} · #${row.id} ${row.sku?'· '+esc(row.sku):''}</small>${inherited?'<span class="nil-badge">موجودی مشترک؛ ویرایش روی والد</span>':''}<details><summary>ویرایش اطلاعات</summary>${['name','sku','description','short_description'].map(editText).join('')}${variable||row.type==='simple'?['category_ids','brand_ids'].map(field=>`<label>${esc(labels[field])} (شناسه‌ها با |)<input data-field="${field}" data-id="${row.id}" value="${esc((values[field]||[]).join('|'))}"></label>`).join(''):''}</details></td><td><input data-id="${row.id}" data-field="cost_price" inputmode="decimal" value="${esc(values.cost_price??'')}" aria-label="قیمت خرید ${esc(row.name)}"></td><td>${variable?'از ورییشن‌ها':profit==null?'هزینه نامشخص':`${fmt(profit)}<small>${Number(effective)>0?fmt(100*profit/Number(effective))+'٪':'—'}</small>`}</td><td>${price('regular_price')}</td><td>${price('sale_price')}</td><td><input data-id="${row.id}" data-field="manage_stock" type="checkbox" ${values.manage_stock?'checked':''} aria-label="مدیریت موجودی مستقل ${esc(row.name)}"></td><td><input data-id="${row.id}" data-field="stock_quantity" inputmode="numeric" value="${esc(values.stock_quantity)}" ${quantityDisabled?'disabled':''} aria-label="تعداد ${esc(row.name)}"></td><td><select data-id="${row.id}" data-field="stock_status" ${statusDisabled?'disabled':''} aria-label="وضعیت ${esc(row.name)}">${[['instock','موجود'],['outofstock','ناموجود'],['onbackorder','پیش‌خرید']].map(([v,t])=>`<option value="${v}" ${v===values.stock_status?'selected':''}>${t}</option>`).join('')}</select></td></tr>`;
        }).join('') : '<tr><td colspan="9">محصولی پیدا نشد.</td></tr>';
        decorateColumns(); renderTableMeta();
        if (state.busy) $('rows').querySelectorAll('input,select').forEach(e=>{e.disabled=true;});
    }
    function decorateColumns() {
        for(const tr of $('rows').closest('table').querySelectorAll('tr')){
            const cells=[...tr.children];if(cells.length!==9)continue;
            cells.forEach((cell,i)=>{if(!cell.dataset.column)cell.dataset.column=['select','name','cost','profit','regular','sale','manage','quantity','stock'][i];});
            for(const key of state.order){const cell=cells.find(c=>c.dataset.column===key);cell.hidden=!state.columns.includes(key);tr.append(cell);}
        }
    }
    function controls(data){for(const [key,value] of Object.entries(data)){const el=$(key);if(!el)continue;if(el.tagName==='SELECT'&&![...el.options].some(o=>o.value===String(value)))throw new Error('گزینهٔ ذخیره‌شده دیگر موجود نیست: '+key);}
        for(const [key,value] of Object.entries(data)){const el=$(key);if(el){if(el.tagName==='SELECT'){[...el.options].forEach(o=>{o.selected=false;});[...el.options].find(o=>o.value===String(value)).selected=true;}else el.value=String(value);}}
        document.dispatchEvent(new Event('nil-rule-ready'));
    }
    function renderTableMeta() {
        $('pagination').textContent = `صفحه ${fmt(state.page)} از ${fmt(Math.max(1,state.pages))}`;
        $('prev').disabled = state.busy || state.page <= 1; $('next').disabled = state.busy || state.page >= state.pages;
        $('dirty').textContent = `${fmt(state.changes.size)} ویرایش باز · ${fmt(state.selected.size)} انتخاب`;
        const scope=$('scope').value;$('scope-summary').textContent=scope==='selected'?`${fmt(state.selected.size)} ردیف انتخاب شده؛ انتخاب والد شامل ورییشن‌هاست.`:scope==='page'?'محدوده: همهٔ محصولات همین صفحه.':'محدوده: همهٔ صفحات فیلتر فعلی؛ جست‌وجو و فیلترها را بررسی کنید.';
        $('manual').disabled = state.busy || !state.changes.size;
    }
    root.addEventListener('change', event => {
        const el = event.target;
        if (el.dataset.select) {
            const id = Number(el.dataset.select); el.checked ? state.selected.add(id) : state.selected.delete(id); renderRows(); return;
        }
        if (!el.dataset.field) return;
        const row = state.rows.find(r=>r.id===Number(el.dataset.id));
        if (!row) return;
        const pending = state.changes.get(row.id) || {id:row.id, version:row.version, changes:{}};
        const value = el.dataset.field.endsWith('_ids') ? el.value.split('|').filter(Boolean).map(Number).sort((a,b)=>a-b) : el.type==='checkbox' ? el.checked : el.value;
        if (JSON.stringify(value)===JSON.stringify(row.values[el.dataset.field]??'') || (!Array.isArray(value)&&String(value)===String(row.values[el.dataset.field]??''))) delete pending.changes[el.dataset.field];
        else pending.changes[el.dataset.field] = value;
        if (Object.keys(pending.changes).length) state.changes.set(row.id,pending); else state.changes.delete(row.id);
        if (el.dataset.field==='manage_stock') renderRows();
        else { el.closest('tr').classList.toggle('nil-changed',state.changes.has(row.id)); renderTableMeta(); }
    });
    $('select-all').addEventListener('change', e=> { state.selected = new Set(e.target.checked?state.rows.map(r=>r.id):[]); renderRows(); });
    $('filter').addEventListener('submit', e=> {e.preventDefault(); task(()=>load(1));});
    $('prev').onclick=()=>task(()=>load(state.page-1)); $('next').onclick=()=>task(()=>load(state.page+1));
    $('reset').onclick=()=>{state.changes.clear(); renderRows(); message('ویرایش‌های ذخیره‌نشده پاک شد.');};
    async function buildPreview(rows, rule=null) {
        if (!rows.length) throw new Error('ابتدا ردیف موردنظر را انتخاب یا ویرایش کنید.');
        if(cfg.costBackend==='native'){rows=[...rows].sort((a,b)=>Number(state.rows.find(r=>r.id===a.id)?.type==='variable')-Number(state.rows.find(r=>r.id===b.id)?.type==='variable'));}
        let jobId=null, count=0, unchanged=0, errors=[];
        $('preview').hidden=true; state.job=null; state.detail=null;
        for(let i=0;i<rows.length;i+=50) {
            progress(`ساخت پیش‌نمایش: ${fmt(i)} از ${fmt(rows.length)} ردیف…`);
            const result=await api('preview',{job_id:jobId, rows:rows.slice(i,i+50), rule});
            jobId=result.job_id; count+=result.added; unchanged+=result.unchanged; errors.push(...result.errors);
        }
        if (errors.length) {
            // Do not seal a partial preview. No products have been changed.
            throw new Error(`پیش‌نمایش کامل نشد؛ هیچ محصولی ذخیره نشده است. ${errors.slice(0,8).map(e=>`#${e.id}: ${e.error}`).join(' / ')}${errors.length>8?` / و ${fmt(errors.length-8)} خطای دیگر`:''}`);
        }
        if (!count) { message(`هیچ تغییری لازم نبود؛ ${fmt(unchanged)} ردیف بدون تغییر.`); return; }
        await api(`job/${jobId}/seal`, {count});
        state.job=jobId; state.jobPage=1;
        state.previewChanges = rule ? null : new Map(rows.map(row=>[row.id, JSON.stringify(row)]));
        await showJob(); await history();
        message(`پیش‌نمایش آماده است: ${fmt(count)} ردیف تغییر و ${fmt(unchanged)} ردیف بدون تغییر. برای اعمال، دکمه تأیید و ذخیره را بزنید.`);
        window.NIL_APP?.activatePanel?.('pricing');
        $('preview').scrollIntoView({behavior:'smooth',block:'start'});
    }
    $('manual').onclick=()=>task(()=>buildPreview([...state.changes.values()]));
    $('bulk').onclick=()=>task(async()=>{
        if (state.changes.size) throw new Error('ابتدا ویرایش‌های جدول را ذخیره یا پاک کنید تا با عملیات گروهی ترکیب نشوند.');
        let rows=[...state.rows]; const scope=$('scope').value;
        if(scope==='all') {
            rows=[]; let pages=1;
            for(let page=1;page<=pages;page++) {progress(`دریافت محصولات: صفحه ${fmt(page)} از ${fmt(pages)}…`); const result=await api('products?'+filters(page)); pages=result.pages; rows.push(...result.rows);}
        } else if(scope==='selected') {
            rows=rows.filter(r=>state.selected.has(r.id) || (r.type==='variation' && state.selected.has(r.parent)));
        }
        rows=rows.filter(r=>r.type!=='variable');
        const rule={source:$('source').value, target:$('target').value, operation:$('operation').value, value:$('value').value, rounding:$('rounding').value, step:$('step').value, ending:$('ending').value};
        await buildPreview(rows.map(r=>({id:r.id,version:r.version,changes:{}})),rule);
    });
    function display(field,value) {
        if(field==='manage_stock'||field==='cost_additive') return value?'روشن':'خاموش';
        if(field==='stock_status') return ({instock:'موجود',outofstock:'ناموجود',onbackorder:'پیش‌خرید'})[value] || value;
        if (Array.isArray(value)) return value.join(' | ');
        if (['name','sku','description','short_description'].includes(field)) return value;
        return fmt(value);
    }
    async function showJob() {
        state.detail=await api(`job/${state.job}?page=${state.jobPage}`);
        const detail=state.detail;
        $('preview').hidden=false;
        $('job-state').textContent=jobs[detail.job.state]||detail.job.state;
        $('summary').textContent=detail.counts.map(c=>`${statuses[c.state]||c.state}: ${fmt(c.n)}`).join(' · ');
        $('preview-table').innerHTML=`<table class="widefat nil-report"><thead><tr><th>محصول</th><th>فیلد</th><th>قبل</th><th>بعد</th><th>نتیجه</th></tr></thead><tbody>${detail.items.map(item=>item.fields.map((field,i)=>`<tr><td>${i===0?`${esc(item.label)} <small>#${item.product_id}</small>`:''}</td><td>${esc(labels[field]||field)}</td><td>${esc(display(field,item.before[field]))}</td><td>${esc(display(field,item.after[field]))}</td><td>${i===0?`${esc(statuses[item.state]||item.state)}${item.error?'<br><span class="nil-error-text">'+esc(item.error)+'</span>':''}`:''}</td></tr>`).join('')).join('')}</tbody></table>`;
        const total=detail.counts.reduce((sum,c)=>sum+Number(c.n),0);
        $('job-pagination').textContent=`${fmt(state.jobPage)} / ${fmt(Math.max(1,Math.ceil(total/50)))}`;
        updatePreviewButtons();
    }
    function updatePreviewButtons() {
        const d=state.detail;
        if(!d) { $('apply').disabled=true; $('undo').disabled=true; return; }
        $('apply').hidden=!['ready','running'].includes(d.job.state);
        $('undo').hidden=!['done','undoing'].includes(d.job.state);
        $('apply').disabled=state.busy; $('undo').disabled=state.busy;
        const total=d.counts.reduce((sum,c)=>sum+Number(c.n),0);
        $('job-prev').disabled=state.busy || state.jobPage<=1;
        $('job-next').disabled=state.busy || state.jobPage>=Math.ceil(total/50);
    }
    $('job-prev').onclick=()=>task(async()=>{state.jobPage--;await showJob();});
    $('job-next').onclick=()=>task(async()=>{state.jobPage++;await showJob();});
    async function run(mode) {
        // The preview remains immutable on the server. Repeated requests resume safely.
        let done=false, processed=0;
        while(!done) {
            progress(`${mode==='undo'?'بازگردانی':'ذخیره'}: ${fmt(processed)} ردیف بررسی شده… صفحه را باز نگه دارید.`);
            const result=await api(`job/${state.job}/run`,{mode});
            done=result.done; processed+=result.processed||0;
        }
        if (mode!=='undo' && state.previewChanges) {
            for (const [id, snapshot] of state.previewChanges) {
                if (JSON.stringify(state.changes.get(id))===snapshot) state.changes.delete(id);
            }
        }
        await showJob(); await history(); await load();
        message(`عملیات پایان یافت. ${$('summary').textContent}؛ جزئیات ردیف‌های ردشده در گزارش است.`);
    }
    $('apply').onclick=()=>task(()=>run('apply'));
    $('undo').onclick=()=>{if(window.confirm('تغییرات همین عملیات بازگردانده شود؟ ردیف‌هایی که بعداً تغییر کرده‌اند رد خواهند شد.')) task(()=>run('undo'));};
    async function history() {
        const list=await api('jobs');
        $('history').innerHTML=list.length ? list.map(j=>`<button type="button" class="button nil-history-item" data-job="${esc(j.id)}">${esc(j.created_at)} UTC · ${esc(jobs[j.state]||j.state)} · ${esc(j.id.slice(0,8))}</button>`).join(''):'<p class="nil-muted">هنوز عملیاتی ثبت نشده است.</p>';
    }
    $('history').onclick=e=>{const button=e.target.closest('[data-job]'); if(button) task(async()=>{state.job=button.dataset.job;state.jobPage=1;state.previewChanges=null;await showJob();});};
    $('history-refresh').onclick=()=>task(history);
    $('export').onclick=()=>task(async()=>{
        if(!state.job) return;
        const lines=[['شناسه','محصول','فیلد','قبل','بعد','وضعیت','خطا']];
        let page=1,total=1;
        do {
            const d=await api(`job/${state.job}?page=${page}`); total=d.counts.reduce((s,c)=>s+Number(c.n),0);
            d.items.forEach(item=>item.fields.forEach(field=>lines.push([item.product_id,item.label,labels[field],item.before[field],item.after[field],statuses[item.state],item.error])));
            page++;
        } while((page-1)*50<total);
        const cell=value=>{let text=String(value??'');if(/^[=+@\-\t\r]/.test(text))text="'"+text;return '"'+text.replace(/"/g,'""')+'"';};
        const blob=new Blob(['\ufeff'+lines.map(row=>row.map(cell).join(',')).join('\r\n')],{type:'text/csv;charset=utf-8;'});
        const url=URL.createObjectURL(blob), a=document.createElement('a'); a.href=url; a.download=`nilwears-${state.job}.csv`; a.click(); setTimeout(()=>URL.revokeObjectURL(url),1000);
    });
    window.NIL_APP={refreshMeta:renderTableMeta,getView:()=>({filters:Object.fromEntries([...filters(1)].filter(([key])=>key!=='page')),columns:[...state.columns],order:[...state.order]}),setColumns:(columns,order)=>{state.columns=columns;state.order=order;decorateColumns();},applyView:async view=>{controls(view.filters);state.columns=view.columns;state.order=view.order;decorateColumns();await load(1);},applyRule:rule=>{if(rule.currency&&rule.currency!==cfg.currencyCode||rule.decimals!=null&&Number(rule.decimals)!==cfg.decimals)throw new Error('واحد پول یا دقت الگو تغییر کرده است.');controls(Object.fromEntries(Object.entries(rule).filter(([k])=>!['currency','decimals'].includes(k))));},api,task,load,buildPreview,filters,message,esc,fmt,getRows:()=>state.rows,getSelected:()=>new Set(state.selected),getRule:()=>({source:$('source').value,target:$('target').value,operation:$('operation').value,value:$('value').value,rounding:$('rounding').value,step:$('step').value,ending:$('ending').value})};
    window.addEventListener('beforeunload',e=>{if(state.changes.size||state.busy){e.preventDefault();e.returnValue='';}});
    task(async()=>{await load(1);await history();message('جدول آماده است. ابتدا تغییرات را پیش‌نمایش بگیرید.');});
})();
