import fs from 'node:fs';
import crypto from 'node:crypto';
const source=fs.readFileSync('CHANGELOG.md','utf8');
const esc=s=>s.replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;');
const inline=s=>esc(s).replace(/`([^`]+)`/g,'<code>$1</code>').replace(/\*\*([^*]+)\*\*/g,'<strong>$1</strong>');
const digits=s=>s.replace(/[0-9]/g,d=>'۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
const date=new Intl.DateTimeFormat('fa-IR-u-ca-persian',{year:'numeric',month:'long',day:'numeric',timeZone:'UTC'});
// Split explicitly to include the last version without depending on multiline end anchors.
const versions=source.split(/^## \[/m).slice(1).map(part=>{
 const m=part.match(/^([\d.]+)\] - (\d{4}-\d{2}-\d{2})\n([\s\S]*)$/);
 if(!m)throw Error('Malformed release section');
 return `<h3>نسخهٔ ${digits(m[1])} — ${date.format(new Date(m[2]+'T12:00:00Z'))}</h3>\n<ul>\n`+m[3].split('\n').filter(line=>line.startsWith('- ')).map(line=>'<li>'+inline(line.slice(2))+'</li>').join('\n')+'\n</ul>';
});
const html='<!-- source-sha256: '+crypto.createHash('sha256').update(source).digest('hex')+' -->\n<div dir="rtl" lang="fa">\n'+versions.join('\n')+'\n</div>\n';
const path='nil-manager/changelog-fa.html';
if(process.argv.includes('--check')){if(fs.readFileSync(path,'utf8')!==html)throw Error('Run npm run build:descriptions');}else fs.writeFileSync(path,html);
console.log(`PASS: ${versions.length} Persian release descriptions with Persian-calendar dates`);
