const {chromium}=require('/opt/node-tools/node_modules/playwright');const fs=require('fs');
const dir=process.argv[2];const files=fs.readdirSync(dir).filter(f=>f.endsWith('.html'));
(async()=>{const b=await chromium.launch();const agg={};const sin=[];
const run=async f=>{const m=f.endsWith('-m.html');const p=await b.newPage({viewport:{width:m?375:1140,height:800}});
 await p.goto('file://'+dir+'/'+f);await p.evaluate(()=>document.fonts.ready);
 const r=await p.evaluate(()=>{
  const parse=c=>{const m=c.match(/rgba?\(([^)]+)\)/);if(!m)return null;const a=m[1].split(/[ ,\/]+/).filter(Boolean).map(Number);return {r:a[0],g:a[1],b:a[2],a:a[3]===undefined?1:a[3]}};
  const lum=c=>{const f=v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)};return .2126*f(c.r)+.7152*f(c.g)+.0722*f(c.b)};
  const blend=(fg,bg)=>({r:fg.r*fg.a+bg.r*(1-fg.a),g:fg.g*fg.a+bg.g*(1-fg.a),b:fg.b*fg.a+bg.b*(1-fg.a),a:1});
  const bgOf=el=>{let layers=[];for(let e=el;e;e=e.parentElement){const c=parse(getComputedStyle(e).backgroundColor);if(c&&c.a>0){layers.push(c);if(c.a>=1)break}}let base={r:255,g:255,b:255,a:1};for(let i=layers.length-1;i>=0;i--)base=blend(layers[i],base);return base};
  const out=[];const w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);let n;const seen=new Set();
  while(n=w.nextNode()){const t=n.textContent.trim();if(!t)continue;const el=n.parentElement;if(seen.has(el))continue;seen.add(el);const cs=getComputedStyle(el);if(cs.visibility==='hidden'||el.closest('[aria-hidden=true]')&&!el.closest('button'))continue;const rc=el.getBoundingClientRect();if(!rc.width)continue;
   const bg=bgOf(el);let fg=parse(cs.color);fg=blend(fg,bg);const L1=lum(fg),L2=lum(bg);const ratio=(Math.max(L1,L2)+.05)/(Math.min(L1,L2)+.05);
   const size=parseFloat(cs.fontSize),bold=parseInt(cs.fontWeight)>=700;const large=size>=24||(size>=18.66&&bold);const need=large?3:4.5;window.__n=(window.__n||0)+1;
   if(ratio<need)out.push({t:t.slice(0,28),ratio:+ratio.toFixed(2),need,size,c:cs.color,bg:`rgb(${bg.r|0},${bg.g|0},${bg.b|0})`})}
  window.__out=out;return {out,n:window.__n}});
 console.log(f,r.n);for(const x of r.out){const k=x.c+' sobre '+x.bg+' ('+x.ratio+':1, '+x.size+'px)';(agg[k]=agg[k]||{n:0,ej:new Set(),pag:new Set()});agg[k].n++;if(agg[k].ej.size<3)agg[k].ej.add(x.t);agg[k].pag.add(f.replace('.html',''))}
 await p.close()};
for(let i=0;i<files.length;i+=8)await Promise.all(files.slice(i,i+8).map(run));
for(const [k,v] of Object.entries(agg).sort((a,b)=>b[1].n-a[1].n))console.log(v.n+'×',k,'|',[...v.ej].join(' / '),'| en',v.pag.size,'pág.');
await b.close()})()
