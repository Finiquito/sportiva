// uso: node audit.js DIR  → desbordes, textos recortados y objetivos táctiles pequeños, a 375 (móvil) y 1140 (escritorio)
const {chromium}=require('/opt/node-tools/node_modules/playwright');const fs=require('fs');
const dir=process.argv[2];const files=fs.readdirSync(dir).filter(f=>f.endsWith('.html'));
(async()=>{const b=await chromium.launch();let pend=[];
const run=async f=>{const m=f.endsWith('-m.html');const out=[];
 for(const w of (m?[320,375,414]:[1140,860])){const p=await b.newPage({viewport:{width:w,height:m?844:800}});
  const er=[];p.on('pageerror',e=>er.push(e.message));
  await p.goto('file://'+dir+'/'+f);await p.evaluate(()=>document.fonts.ready);
  const r=await p.evaluate(()=>{const o={};o.desb=document.documentElement.scrollWidth-innerWidth;
   const small=[];document.querySelectorAll('button,a,[role=button]').forEach(e=>{const r=e.getBoundingClientRect();if(r.width&&(r.height<34||r.width<34)&&!e.closest('.pill')&&getComputedStyle(e).visibility!=='hidden')small.push((e.textContent||'').trim().slice(0,20)+' '+Math.round(r.width)+'x'+Math.round(r.height))});
   o.peq=small;
   const rec=[];document.querySelectorAll('h1,h2,h3,b,span,small,p,button').forEach(e=>{if(e.children.length===0&&e.scrollWidth>e.clientWidth+2&&getComputedStyle(e).overflow!=='visible')rec.push((e.textContent||'').trim().slice(0,24))});o.rec=rec;return o});
  if(r.desb>0)out.push(w+' desborde '+r.desb);if(w==375||w==1140){if(r.peq.length)out.push(w+' chicos: '+[...new Set(r.peq)].slice(0,4).join('; '));if(r.rec.length)out.push(w+' recortes: '+r.rec.slice(0,3).join('; '))}
  if(er.length)out.push('ERR '+er[0]);await p.close()}
 if(out.length)console.log(f.replace('.html',''),'→',out.join(' | '))};
for(let i=0;i<files.length;i+=6)await Promise.all(files.slice(i,i+6).map(run));
await b.close()})()
