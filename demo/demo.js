'use strict';
const frame=document.getElementById('playground');
const workspace=document.getElementById('workspace');
const status=document.getElementById('status');
const url=new URL('https://playground.wordpress.net/');
url.searchParams.set('blueprint-url',new URL('blueprint.json',location.href).href);
url.searchParams.set('mode','seamless');
url.searchParams.set('storage','temp');
document.getElementById('direct').href=url.href;
function start(){
 document.getElementById('intro').hidden=true;workspace.hidden=false;
 status.textContent='وردپرس و ووکامرس در حال بارگذاری‌اند؛ پیشرفت آماده‌سازی داخل کادر نمایش داده می‌شود.';
 frame.src=url.href;workspace.scrollIntoView({behavior:'smooth',block:'start'});
}
document.getElementById('start').addEventListener('click',start);
document.getElementById('reset').addEventListener('click',()=>{
 if(!confirm('محیط تازه باز شود؟ تغییرات این دموی آزمایشی کنار گذاشته می‌شوند.'))return;
 frame.removeAttribute('src');
 requestAnimationFrame(start);
});
document.getElementById('expand').addEventListener('click',async()=>{
 try{if(document.fullscreenElement)await document.exitFullscreen();else await workspace.requestFullscreen();}
 catch(_){status.textContent='تمام‌صفحه در این مرورگر در دسترس نیست؛ از لینک پنجرهٔ جدا استفاده کنید.';}
});
