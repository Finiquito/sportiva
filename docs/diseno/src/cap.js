const {chromium}=require('/opt/node-tools/node_modules/playwright');const fs=require('fs');
const dir=process.argv[2];const files=fs.readdirSync(dir).filter(f=>f.endsWith('.html'));
(async()=>{const b=await chromium.launch();
const run=async f=>{const m=f.endsWith('-m.html');const p=await b.newPage({viewport:{width:m?375:1140,height:m?844:800}});
 await p.goto('file://'+dir+'/'+f);await p.evaluate(()=>document.fonts.ready);await p.waitForTimeout(100);
 await p.addStyleTag({content:'.bar{position:static!important}'});
 await p.screenshot({path:dir+'/'+f.replace('.html','.png'),fullPage:true});await p.close()};
for(let i=0;i<files.length;i+=8)await Promise.all(files.slice(i,i+8).map(run));
await b.close()})()
