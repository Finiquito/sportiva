"""Sportiva · generador de pantallas.

Cada pantalla se escribe UNA vez (bloques HTML) y se renderiza en móvil y escritorio.
Los textos de las piezas llevan marcadores que build.py resuelve:
  {{i:nombre:peso}}  ícono Phosphor (bold|fill|duotone), ver ../iconos/phosphor-subset.js
  {{c:nombre}}       ícono propio (../iconos/propios/*.svg)
  {{LOGO}}  S limón   {{LOGOC}}  S del color del club   {{GRANO}}  data-URI del grano
Reglas de diseño del usuario (CLAUDE.md): nada inclinado, grano solo en cabecera, amarillo solo en logo,
chip Partido, tile del auto y UN resaltador por tarjeta; fechas/horas/detalles en mono; títulos chicos;
«asistencia» (no «voto»); sin cupos asignados en los turnos.
"""
import os
HERE=os.path.dirname(os.path.abspath(__file__))
def css(n): return open(os.path.join(HERE,'css',n)).read()

ROLES=[('acceso','Acceso'),('apoderado','Apoderado'),('entrenador','Entrenador y encargado'),('directiva','Directiva'),('jugador','Jugador (hijo)')]
TABS={
 'apoderado':[('agenda','Agenda','calendar-blank'),('feed','Feed','newspaper'),('actividad','Mi actividad','path'),('mas','Más','squares-four')],
 'entrenador':[('agenda','Agenda','calendar-blank'),('feed','Feed','newspaper'),('consultas','Consultas','chat-circle-dots'),('plantel','Plantel','users-three'),('mas','Más','squares-four')],
 'directiva':[('feed','Feed','newspaper'),('club','Club','house'),('directorio','Directorio','user-circle'),('mas','Más','squares-four')],
 'jugador':[('agenda','Agenda','calendar-blank'),('feed','Feed','newspaper'),('chat','Chat','chats-circle'),('mas','Más','squares-four')],
}
USUARIO={'apoderado':'Daniela','entrenador':'Camila','directiva':'Sylvie','jugador':'Lucas'}

SCREENS=[]   # se llena con S(...) y A(...)
def S(id,rol,nombre,tab,titulo,main,side=None,*,left=None,eyebrow=None,pills=None,back=None,sub=None,layout='g2',fab=None,dmain=None,dside=None,dleft=None):
    """Pantalla de app (con menú). rol: apoderado|entrenador|directiva|jugador.
    tab: pestaña activa. back: texto del botón volver (pantalla de detalle) o None.
    main/side/left: listas de bloques HTML. Móvil = left+main+side apilados.
    layout escritorio: 'g2' (main | side 340) · 'g2l' (left 340 | main) · 'g3' (left 230 | main | side 300) ·
                       'uno' (una columna 760) · 'cols2' (main en 2 columnas) · 'cols3'.
    dmain/dside/dleft: reemplazan main/side/left solo en escritorio."""
    SCREENS.append(dict(kind='app',id=id,rol=rol,nombre=nombre,tab=tab,titulo=titulo,main=main,side=side or [],left=left or [],eyebrow=eyebrow,pills=pills,back=back,sub=sub,layout=layout,fab=fab,dmain=dmain,dside=dside,dleft=dleft))
def A(id,nombre,titulo,body,*,wide=False,back=False,club=False):
    """Pantalla de acceso (sin menú). body: lista de bloques. wide: en escritorio usa tarjeta ancha."""
    SCREENS.append(dict(kind='auth',id=id,rol='acceso',nombre=nombre,titulo=titulo,main=body,wide=wide,back=back,club=club))

# ---------------- piezas ----------------
def card(*inner,cls='',style=''): return '<section class="card%s"%s>%s</section>'%(' '+cls if cls else '',' style="%s"'%style if style else '',''.join(inner))
def ch(t,right=''): return '<div class="ch"><h3>%s</h3>%s</div>'%(t,right)
def tag(t,ic=None,k=''):
    return '<span class="tag%s">%s%s</span>'%(' '+k if k else '',('{{i:%s:bold}}'%ic if ic and not ic.startswith('c:') else ('{{c:%s}}'%ic[2:] if ic else '')),t)
def tags(*t): return '<div class="tags">%s</div>'%''.join(t)
def tipo_tag(t):
    return {'partido':tag('Partido','c:balon','lim'),'entreno':tag('Entrenamiento','c:silbato','pol'),'especial':tag('Evento especial','sparkle'),'stage':tag('Stage','star')}[t]
def chipm(t): return '<span class="tag m">%s</span>'%t
def meta(ic,t,style=''): return '<p class="meta"%s>{{i:%s:bold}}%s</p>'%(' style="%s"'%style if style else '',ic,t)
def h2(t,style=''): return '<h2%s>%s</h2>'%(' style="%s"'%style if style else '',t)
def dia(t): return '<div class="dia">%s</div>'%t
def est(ic,t): return '<span class="est">{{i:%s:bold}}%s</span>'%(ic,t)
def bt(t,ic=None,k='',style=''):
    return '<button type="button" class="bt%s"%s>%s%s</button>'%(' '+k if k else '',' style="%s"'%style if style else '',('{{i:%s:bold}}'%ic if ic else ''),t)
def acciones(*b): return '<div class="acciones">%s</div>'%''.join(b)
def avatar(ini,lim=False): return '<span class="avatar%s">%s</span>'%(' lim' if lim else '',ini)
def fi(ic,k=''):
    inner='{{c:%s}}'%ic[2:] if ic.startswith('c:') else '{{i:%s:bold}}'%ic
    return '<span class="fi%s">%s</span>'%(' '+k if k else '',inner)
def pers(ini,name,sub='',right='',lim=False): return '<div class="pers">%s<div><b>%s</b><small>%s</small></div>%s</div>'%(avatar(ini,lim),name,sub,right)
def pers_l(*rows): return ''.join(rows)
def lk(ic,t,sub='',right=None,k=''):
    """fila de navegación (Más, listas). right=None → chevrón."""
    r=right if right is not None else '{{i:arrow-right:bold}}'.replace('{{i:arrow-right:bold}}','<span class="chev">{{i:arrow-right:bold}}</span>')
    lead=fi(ic,k) if ic else ''
    cols='auto minmax(0,1fr) auto' if ic else 'minmax(0,1fr) auto'
    return '<li><button type="button" class="lk" style="grid-template-columns:%s">%s<span><b>%s</b>%s</span>%s</button></li>'%(cols,lead,t,('<small>%s</small>'%sub if sub else ''),r)
def lkl(*rows): return '<ul class="lkl">%s</ul>'%''.join(rows)
def fila(lead,t,sub,right='',sel=False):
    return '<li><div class="fila%s" style="grid-template-columns:auto minmax(0,1fr) auto">%s<span><b>%s</b><small>%s</small></span>%s</div></li>'%(' sel' if sel else '',lead,t,sub,right)
def lista(*f): return '<ul class="lista">%s</ul>'%''.join(f)
def campo(label,value='',ph=False,ic=None,fin=None,ta=False,hint=None):
    return '<label class="campo"><span>%s</span><div class="in%s%s">%s%s%s</div>%s</label>'%(label,' ph' if ph else '',' ta' if ta else '',('{{i:%s:bold}}'%ic if ic else ''),value,('<i class="fin">{{i:%s:bold}}</i>'%fin if fin else ''),('<small>%s</small>'%hint if hint else ''))
def select(label,value,hint=None): return campo(label,value,fin='arrow-right',hint=hint).replace('class="fin"','class="fin" style="transform:rotate(90deg)"')
def tog(t,sub='',on=True): return '<div class="tog"><div><b>%s</b>%s</div><span class="sw%s"><i></i>%s</span></div>'%(t,('<small>%s</small>'%sub if sub else ''),' on' if on else '','Sí' if on else 'No')
def pills(items,on=0,cls='grupo-pills'): return '<div class="%s" role="group">%s</div>'%(cls,''.join('<button type="button" class="pill%s">%s</button>'%(' on' if i==on else '',t) for i,t in enumerate(items)))
def stats(*pairs): return '<div class="stats">%s</div>'%''.join('<div class="stat"><b>%s</b><span>%s</span></div>'%p for p in pairs)
def pasos(n,total,txt=None): return '<div class="pasos" role="img" aria-label="Paso %d de %d">%s</div><span class="paso-t">%s</span>'%(n,total,''.join('<i class="on"></i>' if i<n else '<i></i>' for i in range(total)),txt or 'Paso %d de %d'%(n,total))
def escudo(t,k=''): return '<span class="escudo%s">%s</span>'%(' '+k if k else '',t)
def vacio(ic,t,txt,btn=''): return '<div class="vacio">%s<b>%s</b><p>%s</p>%s</div>'%(fi(ic),t,txt,btn)
def aviso_box(ic,t,txt=''): return '<div class="aviso">%s<div><b>%s</b>%s</div></div>'%(fi(ic),t,('<p>%s</p>'%txt if txt else ''))
def barra(p): return '<span class="barra"><i style="width:%d%%"></i></span>'%p
def mini(t): return '<span class="mini-t">%s</span>'%t
def hr(): return '<hr class="sep-h">'
def burbuja(ini,name,txt,oculto=False):
    if oculto: txt='Mensaje oculto por un entrenador'
    return '<div class="burb%s">%s<div class="tx"><b>%s</b><span>%s</span></div></div>'%(' oculto' if oculto else '',avatar(ini),name,txt)
def stickers(*t): return '<div class="stickers">%s</div>'%''.join('<span>%s</span>'%x for x in t)
def evt(hora,autor,txt): return '<div class="evt"><time>%s</time><div><b>%s</b><span>%s</span></div></div>'%(hora,autor,txt)
def fotos(n=3,cols=3): return '<div class="fotos" role="img" aria-label="%d fotos">%s</div>'%(n,''.join('<span>{{i:images:bold}}</span>' for _ in range(n)))
def marcador(l,v,nl='U12',nv='Niort',sub='En vivo · 2.º tiempo'): return '<div class="marc"><div><b>%s</b><small>%s</small></div><i>–</i><div><b>%s</b><small>%s</small></div></div><p class="meta" style="justify-content:center;margin-top:14px">{{i:timer:bold}}%s</p>'%(l,nl,v,nv,sub)
def mapa(): return '<div class="mapa" aria-hidden="true"><svg class="ruta" viewBox="0 0 400 120" preserveAspectRatio="none"><path d="M-10 96 C 90 40, 170 110, 250 62 S 380 30, 420 50" fill="none" stroke="var(--club)" stroke-width="2.5" stroke-dasharray="3 7" stroke-linecap="round" vector-effect="non-scaling-stroke"/></svg><span class="pin">{{i:map-pin:fill}}</span></div>'
def mapas_btns(): return '<div class="nav"><button type="button" class="bt">{{i:navigation-arrow:bold}}Google Maps</button><button type="button" class="bt">{{i:navigation-arrow:bold}}Waze</button><button type="button" class="bt">{{i:share-network:bold}}WhatsApp</button></div>'
def horas(a,b,*,hl=True,ia=None,ib=None):
    """a,b = (etiqueta, hora, icono). Resaltador limón en la primera."""
    (la,ha,ica),(lb,hb,icb)=a,b
    ico=lambda i:('{{c:%s}}'%i[2:] if i.startswith('c:') else '{{i:%s:bold}}'%i)
    return '<div class="horas"><div><small>%s%s</small><span class="hora">%s</span></div><span class="sep"></span><div><small>%s%s</small><span class="hora">%s</span></div></div>'%(ico(ica),la,('<span class="hl">%s</span>'%ha if hl else ha),ico(icb),lb,hb)
HORAS_ENC=lambda enc='12:30',cancha='13:15': horas(('Encuentro',enc,'users-three'),('En cancha',cancha,'c:cancha-futbol'))
HORAS_DIR=lambda cancha='13:15',part='14:00': horas(('En cancha',cancha,'c:cancha-futbol'),('Partido',part,'timer'))
def asistencia(on=None,ro=False):
    L=[('check','Voy'),('question','Duda'),('x','No voy')]
    return '<div class="voto">%s</div>'%''.join('<button type="button"%s>{{i:%s:bold}}%s</button>'%(' class="on" aria-pressed="true"' if i==on else '',ic,t) for i,(ic,t) in enumerate(L))
def donut(n,de,leyenda=True):
    return '<div class="donut" role="img" aria-label="%s de %s"><div><span>%s<small>de %s</small></span></div></div>'%(n,de,n,de)+('<div class="lg"><span>{{i:check:bold}}Voy 5</span><span>{{i:question:bold}}Duda 2</span><span>{{i:x:bold}}No voy 1</span><span>{{i:clock:bold}}Sin respuesta 2</span></div>' if leyenda else '')
def post(autor_ini,autor,sub,texto,*,titulo=None,foto=False,coment=None,lim=False,extra=''):
    h='<div class="post-h">%s<div><b>%s</b><small class="sub" style="margin-top:2px">%s</small></div></div>'%(avatar(autor_ini,lim),autor,sub)
    return '<article class="card post">%s%s<p class="tx">%s</p>%s%s%s</article>'%(h,('<h2 style="font-size:20px;margin:0 0 8px">%s</h2>'%titulo if titulo else ''),texto,(fotos(1) if foto else ''),extra,('<div class="post-f">%s</div>'%bt(coment,'chat-circle-dots') if coment else ''))
def avatars_row(*ini): return '<div class="chips">%s</div>'%''.join(avatar(i) for i in ini)

# jugadores / familias de ejemplo (iguales a los wireframes)
JUG=['Lucas','Tomás','Mateo','Gabriel','Louis','Raphaël','Adam','Arthur','Ethan','Paul']
def ini(n): return ''.join(w[0] for w in n.replace('·',' ').split()[:2]).upper()
PPFAM=[('Lucas','Daniela Pérez'),('Tomás','Marta Garcia'),('Mateo','Paul Fournier'),('Gabriel','Inés Garcia'),('Louis','Karim Dubois'),('Raphaël','Marta Roux'),('Adam','Luis Girard'),('Arthur','Sofía Bernard'),('Ethan','Julien Lefèvre'),('Paul','Ana Morel')]

# ---------------- shells ----------------
HEAD='''<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sportiva · %s</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap">
<style>
'''
def _css(dev):
    import glob
    extra=''.join(open(f).read() for f in sorted(glob.glob(os.path.join(HERE,'css','x_*.css'))))
    return css('tokens.css')+css('mobile.css' if dev=='m' else 'desktop.css')+css('shared.css')+css('components.css')+extra

def bell(): return '<button type="button" class="bell" aria-label="Notificaciones, 3 nuevas">{{i:bell:bold}}<b>3</b></button>'
def clubchip(): return '<span class="chip-club"><span class="mk">{{LOGO}}</span>Club Aigles</span>'

def render_mobile(s):
    title=s['nombre']
    if s['kind']=='auth':
        body=''.join(s['main'])
        top='<div class="top"><span class="lockup" style="display:inline-flex;align-items:center;gap:10px;font:800 24px/1 var(--font-display);letter-spacing:-.02em"><span style="width:22px;display:block">{{LOGO}}</span>sportiva</span></div>'
        back='<button type="button" class="volver">{{i:arrow-right:bold}}Atrás</button>' if s['back'] else ''
        return HEAD%title+_css('m')+'</style></head><body><div class="app"><header class="band">'+top+'</header><main class="sheet">'+back+('<h1 style="font-size:30px">%s</h1>'%s['titulo'] if s['titulo'] else '')+body+'</main></div></body></html>'
    tabs=TABS[s['rol']]
    blocks=s['left']+s['main']+s['side']
    head=''
    if s['back']: head+='<button type="button" class="volver">{{i:arrow-right:bold}}%s</button>'%s['back']
    if s['eyebrow']: head+='<p class="hola">%s</p>'%s['eyebrow']
    head+='<h1%s>%s</h1>'%(' style="font-size:34px;margin-bottom:6px"' if s['back'] else '',s['titulo'])
    if s['sub']: head+='<p class="sub" style="margin:0 2px 16px">%s</p>'%s['sub']
    if s['pills']: head+=pills(s['pills'][0],s['pills'][1],'pills')+'<div style="height:12px"></div>'
    elif not s['sub']: head+=''
    nav='<nav class="bar" aria-label="Principal" style="grid-template-columns:repeat(%d,minmax(0,1fr))">%s</nav>'%(len(tabs),''.join('<button type="button"%s>{{i:%s:%s}}%s</button>'%(' class="on" aria-current="page"' if k==s['tab'] else '',ic,'fill' if k==s['tab'] else 'bold',l) for k,l,ic in tabs))
    fab=s['fab'] or ''
    return HEAD%title+_css('m')+'</style></head><body><div class="app"><header class="band"><div class="top">'+clubchip()+bell()+'</div></header><main class="sheet">'+head+''.join(blocks)+fab+'</main>'+nav+'</div></body></html>'

def render_desktop(s):
    title=s['nombre']
    lock='<span class="lockup">{{LOGO}}sportiva</span>'
    if s['kind']=='auth':
        wide=' wide' if s['wide'] else ''
        return HEAD%title+_css('d')+'</style></head><body><div class="auth-d"><div class="auth-c%s"><div class="auth-lock">{{LOGOC}}sportiva</div>%s%s</div></div></body></html>'%(wide,('<h1 style="font-size:30px;margin:0 0 16px">%s</h1>'%s['titulo'] if s['titulo'] else ''),''.join(s['main']))
    tabs=TABS[s['rol']]
    rail='<nav class="rail" aria-label="Principal">%s</nav>'%''.join('<button type="button"%s>{{i:%s:%s}}%s</button>'%(' class="on" aria-current="page"' if k==s['tab'] else '',ic,'fill' if k==s['tab'] else 'bold',l) for k,l,ic in tabs)
    main=s['dmain'] if s['dmain'] is not None else s['main']; side=s['dside'] if s['dside'] is not None else s['side']; left=s['dleft'] if s['dleft'] is not None else s['left']
    lay=s['layout']
    J=lambda b:''.join(b)
    if lay=='g2': body='<div class="g2"><div class="col">%s</div><div class="col">%s</div></div>'%(J(main),J(side)) if side else '<div class="uno col">%s</div>'%J(main)
    elif lay=='g2l': body='<div class="g2l"><div class="col">%s</div><div class="col">%s</div></div>'%(J(left or side),J(main))
    elif lay=='g3': body='<div class="g3"><div class="col">%s</div><div class="col">%s</div><div class="col">%s</div></div>'%(J(left),J(main),J(side))
    elif lay=='cols2': body='<div class="cols2-d">%s</div>'%J(left+main+side)
    elif lay=='cols3': body='<div class="cols3-d">%s</div>'%J(left+main+side)
    else: body='<div class="uno col">%s</div>'%J(left+main+side)
    hdr='<div><p class="hola">%s</p><h1>%s</h1>%s</div>'%(s['eyebrow'] or (s['sub'] if s['back'] else 'Hola, %s'%USUARIO[s['rol']]),s['titulo'],('<p class="sub" style="margin:6px 2px 0">%s</p>'%s['sub'] if s['sub'] and not s['back'] else ''))
    right=''
    if s['back']: right='<button type="button" class="volver" style="margin:0">{{i:arrow-right:bold}}%s</button>'%s['back']
    elif s['pills']: right=pills(s['pills'][0],s['pills'][1],'pills')
    return HEAD%title+_css('d')+'</style></head><body><div class="page"><header class="band"><div class="top">'+lock+'<div class="right">'+clubchip()+bell()+'</div></div></header><div class="panel"><div class="panel-in">'+rail+'<main class="main"><div class="hd">'+hdr+right+'</div>'+body+(s['fab'] or '')+'</main></div></div></div></body></html>'
