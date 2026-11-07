// Optional visual regression: only the generated, disconnected local fixture is opened.
// Install Playwright separately or set NIL_PLAYWRIGHT_MODULE; no browser ships in the plugin.
const assert = require('node:assert/strict');
const path = require('node:path');
const {pathToFileURL} = require('node:url');
const {chromium} = require(process.env.NIL_PLAYWRIGHT_MODULE || 'playwright');
const out = path.resolve(__dirname, '../dist');
const fixture = pathToFileURL(path.join(out, 'ui-preview.html')).href;
const idle = page => page.waitForFunction(() => window.NIL_APP && !document.querySelector('#nil-bulk-app').hasAttribute('aria-busy'));
async function contained(page, label) {
    const metrics = await page.evaluate(() => ({width:innerWidth, scroll:document.documentElement.scrollWidth}));
    assert(metrics.scroll <= metrics.width + 1, label + ': document overflow ' + JSON.stringify(metrics));
}
(async () => {
    const browser = await chromium.launch({headless:true, executablePath:process.env.NIL_CHROMIUM_BIN || undefined,
        args:['--no-sandbox','--disable-dev-shm-usage','--no-zygote','--disable-gpu']});
    const errors = [];
    try {
        for (const [name,width,height] of [['desktop',1440,1000],['mobile',390,844]]) {
            const page = await browser.newPage({viewport:{width,height}, deviceScaleFactor:1});
            page.on('pageerror', error => errors.push(error.message));
            await page.route(/^https?:/, route => route.abort());
            await page.goto(fixture);
            await idle(page);
            await page.evaluate(() => document.fonts.ready);
            await page.locator('#nil-panel-home').waitFor({state:'visible'});
            assert(await page.locator('#nil-panel-products').isHidden());
            // WordPress styles headings independently of the body.
            await page.addStyleTag({content:'h1,h2,h3{font-family:serif}'});
            for (const selector of ['.nil-welcome h2','.nil-welcome h3','#nil-tab-home']) {
                assert.match(await page.locator(selector).first().evaluate(el=>getComputedStyle(el).fontFamily), /Nil Vazirmatn/);
            }
            if(name==='desktop')assert((await page.locator('.nil-tabs').boundingBox()).width>=205);
            await contained(page, name + ' home');
            await page.screenshot({path:path.join(out, `ux-${name}-home.png`), fullPage:true});
            await page.locator('#nil-panel-home [data-start-flow="manual"]').click();
            const style = await page.locator('#nil-value').evaluate(el => {
                const s=getComputedStyle(el);return {radius:s.borderRadius, height:s.minHeight};
            });
            assert.equal(style.radius, '14px', 'shipped CSS must style descendants, not the app root');
            assert.equal(style.height, '44px');
            await page.locator('#nil-scope').selectOption('category');
            await page.locator('#nil-price-category').selectOption('7');
            await page.locator('#nil-scope-review').click();
            await idle(page);
            await contained(page, name + ' selection report');
            await page.locator('#nil-bulk').click();
            await page.locator('#nil-preview').waitFor({state:'visible'});
            await idle(page);
            const backgrounds = await page.evaluate(() => ['nil-bulk','nil-apply'].map(id => getComputedStyle(document.getElementById(id)).backgroundColor));
            assert.equal(backgrounds[0], backgrounds[1], 'primary hover keeps its solid background');
            await contained(page, name + ' preview');
            await page.screenshot({path:path.join(out, `ux-${name}-preview.png`), fullPage:true});
            await page.locator('#nil-value').fill('25');
            assert(await page.locator('#nil-preview').isHidden());
            await page.locator('#nil-mode-stock').click();
            assert(await page.locator('#nil-bulk-tools').isVisible());
            assert(await page.locator('#nil-pricing').isHidden());
            await page.locator('#nil-mode-market').click();
            assert(await page.locator('#nil-market-panel').isVisible());
            assert(await page.locator('#nil-bulk-tools').isHidden());
            await page.locator('#nil-market-rates').click();
            await idle(page);
            await contained(page, name + ' rates');
            await page.locator('#nil-market-base').fill('250000');
            await page.locator('#nil-market-preview').click();
            await page.locator('#nil-market-activate').waitFor({state:'visible'});
            await idle(page);
            await contained(page, name + ' market preview');
            await page.screenshot({path:path.join(out, `ux-${name}-market.png`), fullPage:true});
            await page.locator('#nil-tab-media').click();
            const archive=page.locator('.nil-media-archive');
            assert.equal(await archive.evaluate(el=>getComputedStyle(el).flexDirection), 'row');
            await archive.click();
            assert(await page.locator('#nil-media-trash').isChecked());
            const [quality,maxWidth]=await Promise.all(['#nil-media-quality','#nil-media-width'].map(id=>page.locator(id).boundingBox()));
            assert.equal(quality.y,maxWidth.y);
            await contained(page, name + ' media');

            await page.locator('#nil-tab-insights').click();
            assert.equal(await page.locator('style').filter({hasText:'nil-calendar::backdrop'}).count(),0);
            const from=page.locator('#nil-insight-from'), to=page.locator('#nil-insight-to');
            const dialog=page.locator('#nil-calendar'), year=page.locator('#nil-calendar-year');
            await from.fill('۱۴۰۳/۱۲/۰۱');
            await page.locator('[data-calendar=from]').click();
            await dialog.waitFor({state:'visible'});
            await contained(page, name + ' calendar');
            assert.equal(await dialog.locator('#nil-calendar-days > span').count(),4, 'Esfand 1, 1403 was Wednesday');
            assert.equal(await dialog.locator('[data-day]').count(),30);
            await dialog.locator('[data-day="30"]').click();
            assert.equal(await from.inputValue(),'۱۴۰۳/۱۲/۳۰');
            await page.locator('[data-calendar=to]').click();
            await dialog.waitFor({state:'visible'});
            await year.fill('۱۴۰۴');
            await year.press('Tab');
            await page.locator('#nil-calendar-month').selectOption('12');
            assert.equal(await dialog.locator('[data-day]').count(),29);
            await page.locator('#nil-calendar-next').click();
            assert.equal(await year.inputValue(),'۱۴۰۵');
            assert.equal(await page.locator('#nil-calendar-month').inputValue(),'1');
            await dialog.locator('[data-day="1"]').click();
            assert.equal(await to.inputValue(),'۱۴۰۵/۰۱/۰۱');
            await page.locator('[data-calendar=to]').click();
            await dialog.waitFor({state:'visible'});
            await page.keyboard.press('Escape');
            await dialog.waitFor({state:'hidden'});
            assert.equal(await to.inputValue(),'۱۴۰۵/۰۱/۰۱');
            await page.locator('#nil-insight-load').click();
            await idle(page);
            assert.deepEqual(await page.evaluate(()=>insightQuery),{from:'۱۴۰۳/۱۲/۳۰',to:'۱۴۰۵/۰۱/۰۱',calendar:'persian'});
            await page.close();
        }
        assert.deepEqual(errors, [], 'no uncaught browser errors');
        console.log('PASS: Chromium desktop/mobile journeys, rounded controls, primary hover, preview invalidation, contained reports, font, media layout and Jalali range selection');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
