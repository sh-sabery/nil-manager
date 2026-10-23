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
            await page.close();
        }
        assert.deepEqual(errors, [], 'no uncaught browser errors');
        console.log('PASS: Chromium desktop/mobile journeys, rounded controls, primary hover, preview invalidation and contained reports');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exit(1); });
