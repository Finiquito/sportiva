"""Genera ../pantallas.html (y, con --out DIR, cada pantalla suelta DIR/<id>-m.html / -d.html)."""
import sys,os,re,json,base64,html,importlib
HERE=os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0,HERE)
import lib
MODS=['screens_acceso','screens_apoderado','screens_apoderado_a','screens_apoderado_b','screens_entrenador','screens_directiva','screens_jugador']
for m in MODS:
    if os.path.exists(os.path.join(HERE,m+'.py')): importlib.import_module(m)
ICON_DIR=os.path.join(HERE,'..','iconos')
_js=open(os.path.join(ICON_DIR,'phosphor-subset.js')).read()
PH=json.loads(_js[_js.index('window.PH=')+len('window.PH='):_js.rindex('}')+1])
PH.update({
 'arrows-left-right':{'bold':'<g fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"><path d="M40 104h176M184 72l32 32-32 32"/><path d="M216 152H40M72 120l-32 32 32 32"/></g>'},
 'phone':{'bold':'<g fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"><path d="M92 60l-20-8a16 16 0 0 0-21 14c-4 80 56 140 136 136a16 16 0 0 0 14-21l-8-20a16 16 0 0 0-17-10l-24 5a72 72 0 0 1-42-42l5-24a16 16 0 0 0-10-17z"/></g>'},
 'arrow-left':{'bold':'<g fill="none" stroke="currentColor" stroke-width="24" stroke-linecap="round" stroke-linejoin="round"><path d="M216 128H40M104 64l-64 64 64 64"/></g>'}})
BB=json.load(open(os.path.join(HERE,'bbox.json')))
GRANO='data:image/png;base64,'+base64.b64encode(open(os.path.join(HERE,'grano-s.png'),'rb').read()).decode()
LOGO_PATHS='<path d="M305 458 L322 459 C328 459 330 465 331 476 C340 560 400 620 480 684 C508 702 527 722 527 745 C527 800 480 850 430 850 C421 850 417 844 415 832 C405 760 385 700 325 652 C285 626 212 590 210 548 C215 500 260 458 305 458 Z"/><path d="M385 458 L415 457 C490 458 527 510 527 560 L527 636 C527 644 520 647 510 646 C440 622 372 555 369 488 C369 465 375 458 385 458 Z"/><path d="M226 657 C218 657 209 660 209 678 L209 712 C209 790 255 851 315 851 L352 852 C366 852 371 846 370 826 C362 750 320 690 245 660 C238 658 232 657 226 657 Z"/>'
LOGO='<svg viewBox="205 453 326 404" aria-hidden="true" style="fill:var(--limon)">%s</svg>'%LOGO_PATHS
LOGOC='<svg viewBox="205 453 326 404" aria-hidden="true" style="fill:var(--club)">%s</svg>'%LOGO_PATHS
_prop={}
def propio(n):
    if n not in _prop:
        s=open(os.path.join(ICON_DIR,'propios',n+'.svg')).read()
        _prop[n]=re.sub(r'<svg[^>]*>','<svg class="pi pw" viewBox="%s" fill="currentColor" aria-hidden="true">'%BB[n],s,count=1)
    return _prop[n]
from PIL import Image
import io,glob
FOTOS=sorted(glob.glob(os.path.join(HERE,'fotos','*.jpg')))
_fc={}
def foto_uri(i,large):
    if not FOTOS: return ''
    p=FOTOS[i%len(FOTOS)];k=(p,large)
    if k not in _fc:
        im=Image.open(p).convert('RGB');im.thumbnail((760,760) if large else (420,420));buf=io.BytesIO();im.save(buf,'JPEG',quality=68 if not large else 72,optimize=True,progressive=True)
        _fc[k]='data:image/jpeg;base64,'+base64.b64encode(buf.getvalue()).decode()
    return _fc[k]
AV=sorted(glob.glob(os.path.join(HERE,'avatares','*.jpg')))
def av_uri(i):
    return 'data:image/jpeg;base64,'+base64.b64encode(open(AV[i%len(AV)],'rb').read()).decode()
def resolve(t):
    t=re.sub(r'\{\{av:(\d+)\}\}',lambda m:av_uri(int(m.group(1))),t)
    t=re.sub(r'\{\{foto:(\d+)(L?)\}\}',lambda m:foto_uri(int(m.group(1)),bool(m.group(2))),t)
    t=re.sub(r'\{\{i:([a-z-]+):(\w+)\}\}',lambda m:'<svg class="pi" viewBox="0 0 256 256" aria-hidden="true">%s</svg>'%PH[m.group(1)][m.group(2)],t)
    t=re.sub(r'\{\{c:([a-z-]+)\}\}',lambda m:propio(m.group(1)),t)
    return t.replace('{{LOGOC}}',LOGOC).replace('{{LOGO}}',LOGO).replace('{{GRANO}}',GRANO)
def pages(s):
    return resolve(lib.render_mobile(s)),resolve(lib.render_desktop(s))
def main():
    out=None
    if '--out' in sys.argv: out=sys.argv[sys.argv.index('--out')+1]; os.makedirs(out,exist_ok=True)
    sel=[a for a in sys.argv[1:] if not a.startswith('--') and a!=out]
    rows={r:[] for r,_ in lib.ROLES}
    for n,s in enumerate(lib.SCREENS,1):
        if sel and s['id'] not in sel: continue
        m,d=pages(s)
        if out: open(os.path.join(out,s['id']+'-m.html'),'w').write(m); open(os.path.join(out,s['id']+'-d.html'),'w').write(d)
        rows[s['rol']].append((s,m,d))
    if sel: return
    fr=lambda t,src,w,h:'<iframe title="%s" srcdoc="%s" loading="lazy" style="width:%s;height:%dpx"></iframe>'%(html.escape(t,quote=True),html.escape(src,quote=True),w,h)
    def panel(idx,w,h,pid,hid):
        o=[]
        for r,rn in lib.ROLES:
            if not rows[r]: continue
            figs=''.join('<figure><figcaption>%s</figcaption>%s</figure>'%(html.escape('%02d · %s'%(i+1,x[0]['nombre'])),fr(x[0]['nombre'],x[idx],w,h)) for i,x in enumerate(rows[r]))
            o.append('<section class="rol" id="%s-%s"><h2>%s <span>%d pantallas</span></h2><div class="fila">%s</div></section>'%(pid,r,rn,len(rows[r]),figs))
        return ''.join(o)
    jump=''.join('<a href="#%%s-%s">%s</a>'%(r,rn) for r,rn in lib.ROLES if rows[r])
    page='''<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sportiva · Pantallas</title>
<style>
:root{--fondo:#DAD8D0;--tinta:#10201F;--tinta-2:#4B5E5C;--teal:#215E5E;--papel:#FFFFFF;--linea:rgba(16,32,31,.22);--font-body:"Geist",ui-sans-serif,system-ui,sans-serif;--font-mono:"Geist Mono",ui-monospace,Menlo,monospace;--font-display:"Bricolage Grotesque",ui-sans-serif,system-ui,sans-serif}
*{box-sizing:border-box}html,body{overflow-x:clip}
body{margin:0;background:var(--fondo);color:var(--tinta);font:14px/1.4 var(--font-body)}
.bar{position:sticky;top:0;z-index:5;display:flex;gap:6px;align-items:center;flex-wrap:wrap;padding:12px 20px;background:var(--fondo)}
.tab{height:40px;padding:0 18px;border-radius:999px;border:1.5px solid var(--linea);background:transparent;font:700 14px var(--font-body);color:var(--tinta);cursor:pointer}
.tab[aria-selected=true]{background:var(--teal);border-color:var(--teal);color:var(--papel)}
.tab:focus-visible,.salto a:focus-visible{outline:2px solid var(--teal);outline-offset:2px}
.salto{display:flex;gap:4px;margin-left:12px;flex-wrap:wrap}
.salto a{height:34px;display:inline-flex;align-items:center;padding:0 12px;border-radius:999px;font:600 13px var(--font-body);color:var(--tinta);text-decoration:none}
.salto a:hover{background:rgba(16,32,31,.08)}
.panel[hidden]{display:none}
.rol{padding-top:8px;scroll-margin-top:64px}
.rol h2{margin:8px 20px 10px;font:800 24px/1.1 var(--font-display);letter-spacing:-.02em}
.rol h2 span{font:500 12px var(--font-mono);letter-spacing:.06em;text-transform:uppercase;color:var(--tinta-2);margin-left:10px}
.fila{display:flex;gap:24px;overflow-x:auto;padding:8px 20px 36px;align-items:flex-start}
.fila figure{flex:none}
figure{margin:0}
figcaption{font:500 12px/1 var(--font-mono);letter-spacing:.06em;text-transform:uppercase;color:var(--tinta-2);margin:0 0 10px 4px}
iframe{display:block;border:0;border-radius:28px;background:var(--papel);box-shadow:0 0 0 1px var(--linea)}
</style></head><body>
<div class="bar"><div role="tablist" aria-label="Vista" style="display:flex;gap:6px"><button class="tab" role="tab" id="t-m" aria-selected="true" aria-controls="p-m">Móvil</button><button class="tab" role="tab" id="t-d" aria-selected="false" aria-controls="p-d">Escritorio</button></div><nav class="salto" id="salto" aria-label="Ir a un rol">'''+jump.replace('%s','m')+'''</nav></div>
<section class="panel" id="p-m" role="tabpanel" aria-labelledby="t-m">'''+panel(1,'390px',844,'m','')+'''</section>
<section class="panel" id="p-d" role="tabpanel" aria-labelledby="t-d" hidden>'''+panel(2,'1140px',820,'d','')+'''</section>
<script>
const T=[['t-m','p-m','m'],['t-d','p-d','d']],salto=document.getElementById('salto');
T.forEach(([t,p,k])=>document.getElementById(t).addEventListener('click',()=>{T.forEach(([a,b])=>{const on=a===t;document.getElementById(a).setAttribute('aria-selected',on);document.getElementById(b).hidden=!on});salto.querySelectorAll('a').forEach(a=>a.href=a.getAttribute('href').replace(/#[md]-/,'#'+k+'-'));scrollTo(0,0)}));
</script></body></html>'''
    open(os.path.join(HERE,'..','pantallas.html'),'w').write(page)
    print('pantallas.html',len(page)//1024,'KB',sum(len(v) for v in rows.values()),'pantallas')
if __name__=='__main__': main()
