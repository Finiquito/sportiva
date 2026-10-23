// uso: node shots.js DIR id [id...]  → DIR/<id>-m.png (390 completo) y DIR/<id>-d.png (1140); imprime desbordes a 320/375/414/768/1140
const {chromium}=require('/opt/node-tools/node_modules/playwright');
const [dir,...ids]=process.argv.slice(2);
(async()=>{const b=await chromium.launch();
for(const id of ids){
 for(const suf of ['m','d']){
  const ws=suf=='m'?[320,375,414,768]:[1140,768,420];
  for(const w of ws){const p=await b.newPage({viewport:{width:w,height:suf=='m'?844:800}});const er=[];p.on('pageerror',x=>er.push(x.message));
   await p.goto('file://'+dir+'/'+id+'-'+suf+'.html');await p.evaluate(()=>document.fonts.ready);await p.waitForTimeout(150);
   const o=await p.evaluate(()=>document.documentElement.scrollWidth-innerWidth);
   if(o>0||er.length)console.log(id,suf,w,'DESBORDE',o,er.join('|'));
   if((suf=='m'&&w==375)||(suf=='d'&&w==1140))await p.screenshot({path:dir+'/'+id+'-'+suf+'.png',fullPage:true});
   await p.close()}}}
await b.close()})()
