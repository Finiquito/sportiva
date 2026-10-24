"""Genera ../guia-colores.html (guía de color y marca de u330).   python3 docs/diseno/marca/guia.py"""
import json,re,os,sys
HERE=os.path.dirname(os.path.abspath(__file__)); sys.path.insert(0,HERE)
from contraste import cr
D=os.path.join(HERE,'..')+'/'
TOK=open(HERE+'/tokens-u330.css').read(); CSS=open(HERE+'/guia.css').read()
CSS+='''
.tel .aviso{border-radius:14px;padding:10px 12px;font:600 12.5px "Geist",sans-serif;display:flex;gap:10px;align-items:center;background:var(--aviso);color:var(--tinta);margin:8px 0}
.ia{width:24px;height:24px;border-radius:50%;display:grid;place-items:center;flex:none}
.ia svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:3;stroke-linecap:round;stroke-linejoin:round}
.ia.ok{color:var(--exito-txt)}.ia.err{color:var(--error)}.ia.dor{color:var(--dorado-txt)}
.ia.am{background:var(--amarillo);color:var(--tinta)}
.msgs{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr));gap:10px}
.msgs .aviso{margin:0;background:var(--aviso);border-radius:16px;padding:12px 14px;display:flex;gap:12px;align-items:center;font:600 14px "Geist",sans-serif}
.msgs .aviso small{display:block;font:500 11px "Geist Mono",monospace;color:var(--tinta-2);margin-top:2px}
.ig{margin:18px 0 8px;font:800 17px "Bricolage Grotesque",sans-serif;color:var(--verde)}
.iconos.ch{grid-template-columns:repeat(auto-fill,minmax(120px,1fr))}
.icn .ic.gr{width:64px;height:48px}
.mk2{width:28px;height:28px;border-radius:50%;background:var(--verde);display:grid;place-items:center;--u-v:var(--hueso)}
.mk2 .lg{height:15px;width:auto}
.lbox .chip{display:inline-flex;align-items:center;gap:10px;height:44px;padding:0 18px 0 6px;border-radius:999px;background:#fff;font:600 14px "Geist",sans-serif;color:var(--tinta)}
.lbox .chip .mk2{width:32px;height:32px}.lbox .chip .mk2 .lg{height:17px}
'''
bb=json.load(open(D+'iconos/bbox-marca.json'))
def icono(carpeta,n,cls=''):
    s=open(D+'iconos/%s/%s.svg'%(carpeta,n)).read()
    return re.sub(r'<svg[^>]*>','<svg class="ic %s" viewBox="%s" fill="currentColor" aria-hidden="true">'%(cls,bb[carpeta+'/'+n]),s,1)
def svgfile(n,cls='',alt='u330'):
    s=open(D+'marca/%s.svg'%n).read()
    s=re.sub(r'<\?xml.*?\?>\s*','',s);s=re.sub(r'<!--.*?-->\s*','',s,flags=re.S);s=re.sub(r'<defs>.*?</defs>','',s,flags=re.S)
    s=s.replace('class="st0"','class="u-b"').replace('class="st1"','class="u-a"').replace('class="st2"','class="u-v"')
    return re.sub(r'<svg [^>]*viewBox="([^"]+)"[^>]*>',r'<svg class="lg %s" viewBox="\1" role="img" aria-label="%s">'%(cls,alt),s,1)
CHK='<svg viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7"/></svg>'
CRZ='<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>'
EXC='<svg viewBox="0 0 24 24"><path d="M12 6v7M12 17.5v.5"/></svg>'
STR='<svg viewBox="0 0 24 24"><path d="M12 4l2.4 5 5.4.6-4 3.7 1.1 5.4L12 16l-4.9 2.7L8.2 13.3l-4-3.7 5.4-.6z"/></svg>'
PAL=[('Verde','#0F4F4E','verde','Marca. Cabecera, botones, horas, color de club por defecto.'),
('Hueso','#F7F1E8','hueso','Fondo de pantallas y zonas de apoyo.'),
('Amarillo','#F6F24A','amarillo','Acento único: resaltador, chip Partido, tile del auto.'),
('Naranja','#FC5603','naranja','Lo que pasa ahora: en vivo, contador, chips de cambio.'),
('Lila','#BD8DFF','lila','Entrenamientos e información secundaria.'),
('Dorado','#BD984A','dorado','Logros: medalla, copa, podio y eventos especiales.'),
('Éxito','#00AB05','exito','Ícono de confirmado, activo, listo.')]
def sw(n,h,v,t): return '<div class="sw"><i style="background:var(--%s)"></i><b>%s</b><code>%s</code><span>%s</span></div>'%(v,n,h,t)
AV='#E7EDED'
pares=[('Texto tinta sobre hueso','#10201F','#F7F1E8'),('Texto secundario sobre hueso','#4B5E5C','#F7F1E8'),('Verde sobre hueso (horas, títulos)','#0F4F4E','#F7F1E8'),('Blanco sobre verde (botones)','#FFFFFF','#0F4F4E'),('Verde sobre amarillo (resaltador)','#0F4F4E','#F6F24A'),
('Tinta sobre naranja','#10201F','#FC5603'),('Blanco sobre naranja','#FFFFFF','#FC5603'),('Naranja-txt sobre hueso','#B93C00','#F7F1E8'),('Tinta sobre lila','#10201F','#BD8DFF'),('Lila-txt sobre hueso','#6A3FC4','#F7F1E8'),
('Tinta sobre dorado','#10201F','#BD984A'),('Dorado-txt sobre hueso','#7A5A1A','#F7F1E8'),('Tinta sobre éxito','#10201F','#00AB05'),
('Ícono éxito-txt sobre aviso (verde claro)','#006B03',AV),('Ícono rojo sobre aviso','#B9341A',AV),('Ícono dorado-txt sobre aviso','#7A5A1A',AV),('Ícono tinta sobre amarillo (círculo del aviso)','#10201F','#F6F24A'),
('Naranja puro sobre hueso (texto o ícono)','#FC5603','#F7F1E8'),('Dorado puro sobre hueso (texto o ícono)','#BD984A','#F7F1E8'),('Lila puro sobre hueso (texto o ícono)','#BD8DFF','#F7F1E8'),('Éxito puro sobre hueso (texto o ícono)','#00AB05','#F7F1E8')]
def fila(n,a,b):
    r=cr(a,b);ok='ok' if r>=4.5 else ('gr' if r>=3 else 'no')
    txt={'ok':'AA texto','gr':'solo texto grande o relleno','no':'no usar'}[ok]
    return '<tr><td><span class="mu" style="background:%s;color:%s">Aa</span> %s</td><td class="n">%.2f:1</td><td class="%s">%s</td></tr>'%(b,a,n,r,ok,txt)
filas=''.join(fila(*p) for p in pares)
def tarjeta(nuevo):
    if nuevo:
        b='background:var(--naranja);color:var(--tinta)';tag_ent='background:var(--lila-t);color:var(--tinta)'
        logo='<span class="mk2">%s</span>'%svgfile('logo-favicon','','u330')
        aviso1='<div class="aviso"><span class="ia am">%s</span>Cambió el lugar del partido · hoy 10:12</div>'%EXC
        aviso2='<div class="aviso"><span class="ia ok">%s</span>Asistencia guardada</div>'%CHK
    else:
        b='background:#B9341A;color:#fff';tag_ent='background:#D9E2EA;color:#215E5E'
        logo='<span style="width:28px;height:28px;border-radius:50%;background:#215E5E;display:grid;place-items:center"><svg viewBox="205 453 326 404" style="width:14px;fill:#F6F24A"><path d="M305 458 L322 459 C328 459 330 465 331 476 C340 560 400 620 480 684 C508 702 527 722 527 745 C527 800 480 850 430 850 C421 850 417 844 415 832 C405 760 385 700 325 652 C285 626 212 590 210 548 C215 500 260 458 305 458 Z"/><path d="M385 458 L415 457 C490 458 527 510 527 560 L527 636 C527 644 520 647 510 646 C440 622 372 555 369 488 C369 465 375 458 385 458 Z"/><path d="M226 657 C218 657 209 660 209 678 L209 712 C209 790 255 851 315 851 L352 852 C366 852 371 846 370 826 C362 750 320 690 245 660 C238 658 232 657 226 657 Z"/></svg></span>'
        aviso1='<div class="aviso" style="background:#E6ECE9">&#9888; Cambió el lugar del partido · hoy 10:12</div>'
        aviso2='<div class="aviso" style="background:#E6ECE9">&#10003; Asistencia guardada</div>'
    return '''<div class="band"><span class="chipc">%s FCJ Orée de l’Autize</span><span class="bell">&#128276;<em style="%s">3</em></span></div>
 <div class="hoja"><h4>Agenda</h4>
  <div class="tc"><div class="tags"><span class="tg" style="background:var(--r);color:#10201F">Partido</span><span class="tg m">U12 · Lucas</span></div>
   <h5>U12 vs Niort</h5>%s
   <div class="hs"><div><small>Encuentro</small><b><span>12:30</span></b></div><div><small>En cancha</small><b>13:15</b></div></div>
   <div class="voto"><span class="on">&#10003; Voy</span><span>? Duda</span><span>&#10005; No voy</span></div></div>
  <div class="tc"><div class="tags"><span class="tg" style="%s">Entrenamiento</span><span class="tg m">U12 · Lucas</span></div><h5>Entrenamiento U12</h5>%s</div>
 </div>'''%(logo,b,aviso1,tag_ent,aviso2)
def grupo(titulo,items,carpeta,clase=''):
    return '<p class="ig">%s</p><div class="iconos">%s</div>'%(titulo,''.join('<div class="icn %s">%s<span>%s</span></div>'%(clase,icono(carpeta,n),t) for n,t in items))
html='''<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>u330 · guía de color</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@700;800&family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap">
<style>
'''+TOK+CSS+'''
</style></head><body><div class="w">
<p class="sub">u330 · pruebas en u330.richgt.com · definitivo en u330.app</p>
<h1>Guía de color</h1>
<p>Siete colores de marca y reglas cortas para aplicarlos a lo que ya tenemos. Los acentos (amarillo y compañía) siguen igual; cambia la base, el verde. La identidad es <b>bicolor</b>: verde y hueso mandan; el resto son detalles.</p>

<h2>Paleta</h2>
<div class="sws">'''+''.join(sw(*p) for p in PAL)+'''</div>
<div class="nota"><b>Ojo:</b> naranja, lila, dorado y éxito son <b>rellenos</b> (chips, un círculo, un fondo con texto oscuro). Como texto o ícono chico sobre claro no llegan al contraste mínimo: para eso hay una versión oscura de cada uno (<code>-txt</code>).</div>

<h2>Qué hace cada color</h2>
<div class="usos">
<div class="uso"><h3><i style="background:var(--verde)"></i>Verde · marca</h3><ul><li>Cabecera, botón principal, píldora activa, horas grandes, títulos con énfasis.</li><li>Es el <code>--club</code> por defecto; cada club puede elegir otro oscuro (≥ 4.5:1 con blanco).</li></ul></div>
<div class="uso"><h3><i style="background:var(--hueso)"></i>Hueso · fondo</h3><ul><li>Fondo de pantalla; las tarjetas siguen blancas.</li><li>Mensajes del sistema: el verde claro de siempre (<code>--aviso</code>).</li></ul></div>
<div class="uso"><h3><i style="background:var(--amarillo)"></i>Amarillo · acento único</h3><ul><li>Resaltador bajo la hora, chip «Partido», tile del auto, ícono sobre verde (check del «Voy»), círculo de atención en los avisos.</li><li>Un solo uso por tarjeta; nunca pegado a otro amarillo.</li></ul></div>
<div class="uso"><h3><i style="background:var(--naranja)"></i>Naranja · ahora</h3><ul><li>«En vivo», «Modificada hoy» (chip), contador de la campana, «Sin leer».</li><li>Relleno con texto tinta, o texto <code>--naranja-txt</code> sobre fondo suave.</li></ul><ul class="no"><li>Blanco sobre naranja solo en texto grande.</li></ul></div>
<div class="uso"><h3><i style="background:var(--lila)"></i>Lila · entrenamiento e info</h3><ul><li>Chip «Entrenamiento», etiquetas informativas, estados neutros (reemplaza el azul grisáceo).</li></ul></div>
<div class="uso"><h3><i style="background:var(--dorado)"></i>Dorado · logros</h3><ul><li>Medalla, copa y podio; resultados destacados, campeones, «Evento especial».</li><li>Solo noticias que se celebran: si todo es dorado, nada lo es.</li></ul></div>
<div class="uso"><h3><i style="background:var(--exito)"></i>Éxito · confirmado</h3><ul><li>Ícono de «guardado», «Activo», «Listo» (en su versión <code>--exito-txt</code>). Nunca fondo de aviso.</li><li>El <b>Voy</b> de asistencia sigue en verde de marca.</li></ul></div>
<div class="uso"><h3><i style="background:var(--error)"></i>Rojo · error</h3><ul><li>Solo errores y acciones que borran (Cerrar sesión, Cancelar convocatoria, Eliminar cuenta).</li><li>Es el rojo de antes (<code>#B9341A</code>); sale del contador de la campana, que pasa a naranja.</li></ul></div>
</div>

<h2>Mensajes del sistema</h2>
<p>Siempre el <b>verde claro de siempre</b> de fondo, sin cambiar de color por tipo. Lo único que cambia es el ícono que va encima, y así se mantiene la identidad bicolor.</p>
<div class="msgs">
<div class="aviso"><span class="ia ok">'''+CHK+'''</span><div>Cambios guardados<small>Ícono verde · éxito</small></div></div>
<div class="aviso"><span class="ia err">'''+CRZ+'''</span><div>No se pudo guardar<small>Ícono rojo · error</small></div></div>
<div class="aviso"><span class="ia am">'''+EXC+'''</span><div>Cambió el lugar del partido<small>Ícono en círculo amarillo · atención o cambio</small></div></div>
<div class="aviso"><span class="ia dor">'''+STR+'''</span><div>¡U12 campeón del torneo!<small>Ícono dorado · logro</small></div></div>
</div>
<div class="nota"><b>Regla de oro:</b> el estado nunca va solo en color. Siempre ícono y texto (✓ Voy · ? Duda · ✕ No voy · reloj Sin respuesta); el color solo refuerza.</div>

<h2>Contraste</h2>
<div class="card"><table><thead><tr><th>Combinación</th><th>Razón</th><th>Uso</th></tr></thead><tbody>'''+filas+'''</tbody></table></div>
<p style="margin-top:10px;color:var(--tinta-2);font-size:13px">Mínimo AA: 4.5:1 para texto normal, 3:1 para texto grande (24 px, o 19 px en negrita) e íconos grandes.</p>

<h2>Qué cambia respecto a lo que ya tenemos</h2>
<div class="card"><table class="cambios"><thead><tr><th>Antes (Sportiva)</th><th></th><th>Ahora (u330)</th><th>Dónde</th></tr></thead><tbody>
<tr><td><i style="background:#215E5E"></i>#215E5E teal</td><td>→</td><td><i style="background:var(--verde)"></i>#0F4F4E verde</td><td>marca, cabecera, botones, horas, club por defecto</td></tr>
<tr><td><i style="background:#F2F0EA"></i>#F2F0EA hueso</td><td>→</td><td><i style="background:var(--hueso)"></i>#F7F1E8</td><td>fondo</td></tr>
<tr><td><i style="background:#F6F24A"></i>#F6F24A limón</td><td>=</td><td><i style="background:var(--amarillo)"></i>#F6F24A amarillo</td><td>igual (solo cambia el nombre)</td></tr>
<tr><td><i style="background:#D9E2EA"></i>#D9E2EA azul grisáceo</td><td>→</td><td><i style="background:var(--lila-t)"></i>lila suave</td><td>chip Entrenamiento</td></tr>
<tr><td><i style="background:#B9341A"></i>#B9341A rojo (contador)</td><td>→</td><td><i style="background:var(--naranja)"></i>#FC5603 naranja</td><td>contador de la campana, «Sin leer»</td></tr>
<tr><td><i style="background:#B9341A"></i>#B9341A rojo (errores)</td><td>=</td><td><i style="background:var(--error)"></i>#B9341A rojo</td><td>solo errores y acciones que borran</td></tr>
<tr><td><i style="background:#E6ECE9"></i>verde claro de los avisos</td><td>=</td><td><i style="background:var(--aviso)"></i>verde claro (10 % de verde)</td><td>todos los mensajes del sistema; solo cambia el ícono</td></tr>
<tr><td>—</td><td>+</td><td><i style="background:var(--dorado)"></i>dorado · <i style="background:var(--exito)"></i>éxito (ícono)</td><td>logros y confirmaciones (nuevos)</td></tr>
<tr><td><i style="background:#10201F"></i>#10201F tinta</td><td>=</td><td><i style="background:var(--tinta)"></i>#10201F</td><td>texto (sigue)</td></tr>
</tbody></table></div>

<h2>Maqueta: la tarjeta de partido, antes y ahora</h2>
<p>Solo cambian la paleta y el logo del header; layout, tamaños y tipografía quedan como están.</p>
<div class="par">
 <div><p class="cap">Antes</p><div class="tel antes">'''+tarjeta(False)+'''</div></div>
 <div><p class="cap">Ahora</p><div class="tel ahora">'''+tarjeta(True)+'''</div></div>
</div>

<h2>Logos</h2>
<div class="logos">
 <figure class="lbox hueso" style="margin:0">'''+svgfile('logo-principal','w2')+'''<figcaption>Principal · sobre hueso</figcaption></figure>
 <figure class="lbox blanco" style="margin:0">'''+svgfile('logo-largo','w2 l')+'''<figcaption>Largo con .app · sobre blanco</figcaption></figure>
 <figure class="lbox verde" style="margin:0">'''+svgfile('logo-principal','w2')+'''<figcaption>Sobre verde · negativo (propuesta)</figcaption></figure>
 <figure class="lbox hueso" style="margin:0">'''+svgfile('logo-favicon','w3')+'''<figcaption>Favicon · ícono de app</figcaption></figure>
 <figure class="lbox verde" style="margin:0"><span class="chip"><span class="mk2">'''+svgfile('logo-favicon','','u330')+'''</span>FCJ Orée de l’Autize</span><figcaption>Header de la app · U invertida sobre círculo verde</figcaption></figure>
</div>
<div class="nota"><b>Sobre verde</b> las partes verdes del logo (palabra, tapas y fondo de la U) se vuelven hueso; la U dorada y naranja se queda. En el header de la app va solo la U del favicon, invertida, dentro del círculo verde. La versión larga usa lila en la U; la principal y el favicon, naranja.</div>

<h2>Íconos</h2>
<p>Mismo trazo que el resto de la interfaz, en verde. Los de logros van en dorado (<code>--dorado-txt</code> sobre claro, dorado puro sobre verde).</p>
'''+grupo('Deportes (balones)',[('futbol','Fútbol'),('futbol-americano','Fútbol americano'),('rugby','Rugby'),('beisbol','Béisbol'),('voley','Vóley'),('voley-playa','Vóley playa')],'deportes')+grupo('Canchas',[('futbol','Fútbol'),('voley','Vóley'),('handball','Handball'),('basquet','Básquet')],'canchas')+grupo('Equipamiento',[('arco-futbol','Arco'),('arco-red','Arco con red'),('silbato','Silbato'),('tarjetas','Tarjetas')],'equipamiento')+grupo('Logros',[('medalla','Medalla'),('copa','Copa'),('podio','Podio')],'especiales','d')+'''
<div class="nota"><b>Chip «Partido»:</b> el ícono sigue el deporte del club (fútbol: el balón de fútbol; vóley: el de líneas; vóley playa: el de curvas; etc.). <b>Logros:</b> medalla = reconocimiento a una persona · copa = título o torneo ganado · podio = ranking o posición. El arco con red se ve bien desde ~40 px; más chico, usa el arco simple.</div>

<h2>Reglas cortas</h2>
<div class="card"><ol style="margin:0;padding-left:20px">
<li>Fondo hueso, tarjetas blancas, verde como protagonista. El resto de los colores son detalles.</li>
<li>Un solo acento llamativo por tarjeta (el resaltador amarillo); no mezclar dos rellenos fuertes juntos.</li>
<li>Mensajes del sistema: siempre el verde claro; solo cambia el ícono (verde, rojo, amarillo en círculo o dorado).</li>
<li>Naranja, lila y dorado: relleno + texto tinta, o fondo suave + texto <code>-txt</code>. Nunca texto puro de esos colores sobre claro.</li>
<li>Texto sobre verde: hueso o blanco; sobre amarillo: verde o tinta.</li>
<li>El grano sigue siendo muy suave y solo en la cabecera.</li>
<li>Cada club puede cambiar <code>--club</code>, pero entre colores oscuros (≥ 4.5:1 con blanco). Los demás colores no cambian por club.</li>
<li>Estados siempre con ícono y texto; el color nunca va solo.</li>
</ol></div>

<h2>Variables CSS</h2>
<pre>'''+TOK.replace('<','&lt;')+'''</pre>
</div></body></html>'''
open(D+'guia-colores.html','w').write(html)
print(len(html)//1024,'KB')
