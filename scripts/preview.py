#!/usr/bin/env python3
"""Render the actual shipped interface with disposable, mocked data; no store access."""
from pathlib import Path
import base64
import re
root = Path(__file__).resolve().parents[1]
plugin = root / 'nilwears-bulk-manager'
out = root / 'dist'
out.mkdir(exist_ok=True)
templates = ''.join(re.sub(r'<\?php[\s\S]*?\?>', '', (plugin / ('includes-'+name+'.php')).read_text()) for name in ['grid','view','help'])
css = (plugin / 'assets/admin.min.css').read_text().replace('url(vazirmatn-arabic-wght-normal.woff2)', 'url(data:font/woff2;base64,'+base64.b64encode((plugin / 'assets/vazirmatn-arabic-wght-normal.woff2').read_bytes()).decode()+')')
test = (root / 'tests/ui-dom.cjs').read_text()
mock = test[test.index('const base='):test.index('const context=')]
config = '''const NIL_BULK={api:'https://nil.test/nil/',nonce:'fixture',currency:'تومان',currencyCode:'IRT',decimals:0,categories:[],canBrand:true,canMarket:true,userId:1,branding:{title:'مدیریت محصولات نیل',accent:'#6941c6',logo:''}};window.fetch=fetchMock;'''
js = '\n'.join((plugin / ('assets/'+name+'.min.js')).read_text() for name in ['admin','suite','advanced','market'])
html = '''<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>پیش‌نمایش آزمایشی رابط نیل</title><style>body{margin:0;background:#f3f5f9}.fixture-note{padding:10px 24px;background:#172333;color:#fff;font:12px Tahoma}#toplevel_page_woocommerce{display:none}</style><style>'''+css+'''</style><body><div class="fixture-note">محیط آزمایشی · محصولات نمونه · هیچ فروشگاهی متصل نیست</div><div id="toplevel_page_woocommerce"><a href="admin.php?page=nil-bulk">نیل</a></div><main id="nil-bulk-app" dir="rtl"></main>'''+templates+'<script>'+mock+config+'</script><script>'+js+'</script></body></html>'
(out / 'ui-preview.html').write_text(html)
(out / 'ui-mobile.html').write_text('''<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>آزمون رابط نیل در عرض ۳۹۰ پیکسل</title><style>body{margin:0;background:#dfe4ed;display:grid;place-items:center}iframe{width:390px;height:900px;border:1px solid #ccd2df;background:white}</style><iframe title="نیل؛ عرض موبایل" src="ui-preview.html"></iframe></html>''')
print(out / 'ui-preview.html')
