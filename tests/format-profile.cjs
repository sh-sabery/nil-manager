// Diagnostic microbenchmark, not a browser/production latency or speed threshold test.
const fs=require('fs'),assert=require('assert/strict');
const values=Array.from({length:2000},(_,i)=>495000+i*1000),cached=new Intl.NumberFormat('fa-IR',{maximumFractionDigits:0});
function measure(fn){const start=process.cpuUsage(),clock=performance.now();let checksum=0;for(const v of values)checksum+=fn(v).length;const cpu=process.cpuUsage(start);return{wall_ms:performance.now()-clock,cpu_ms:(cpu.user+cpu.system)/1000,checksum};}
const old=[],next=[];for(let i=0;i<5;i++){old.push(measure(v=>new Intl.NumberFormat('fa-IR',{maximumFractionDigits:0}).format(v)));next.push(measure(v=>cached.format(v)));assert.equal(old[i].checksum,next[i].checksum);}
const median=(xs,key)=>xs.map(x=>x[key]).sort((a,b)=>a-b)[2],out={node:process.version,icu:process.versions.icu,values:values.length,samples:5,per_cell:{wall_ms:median(old,'wall_ms'),cpu_ms:median(old,'cpu_ms')},cached:{wall_ms:median(next,'wall_ms'),cpu_ms:median(next,'cpu_ms')},note:'Node/ICU microbenchmark, not live browser, PHP or host CPU%. No performance threshold assertion.'};
console.log(JSON.stringify(out,null,2));fs.writeFileSync('tests/resources-format.json',JSON.stringify(out,null,2));
