from lib import *

SALIR='<span class="chev">{{i:arrow-right:bold}}</span>'
def cab_perfil(): return '<div class="perfil-cab"><span class="foto">DP</span><div><b>Daniela Pérez</b><small>daniela@ejemplo.fr</small></div></div>'
def en_club(): return '<div class="tags" style="margin:0 0 4px">%s%s</div>'%(tag('Club Aigles','house'),chipm('Apoderada · Lucas y Emma'))

# ---------- Más ----------
mas_menu=card(lkl(
  lk('user-circle','Mi perfil','Daniela Pérez · daniela@ejemplo.fr'),
  lk('users-three','Mi familia','Daniela · Lucas · Emma'),
  lk('house','Mis clubes','Club Aigles · Club Pampa'),
  lk('question','Ayuda','Instalar la app, preguntas')),style='padding:8px 18px')
salir=card(lkl(lk('arrow-right','Cerrar sesión','',right='')),style='padding:8px 18px')
S('ap-mas','apoderado','Más','mas','Más',[mas_menu,salir],eyebrow='Hola, Daniela',layout='g2',
  dmain=[mas_menu,salir],dside=[card(cab_perfil(),'<div style="height:14px"></div>',en_club(),bt('Ver mi perfil',k='w',style='margin-top:12px'))])

# ---------- Mi perfil ----------
perfil_menu=card(lkl(
  lk('user-circle','Mis datos','Nombre, teléfono, correo'),
  lk('translate','Idioma','Français'),
  lk('bell','Preferencias de avisos','Push, correo'),
  lk('share-network','Compartir mis datos','Con las familias de tu equipo'),
  lk('lock','Privacidad','Quién ve qué, descargar o borrar')),style='padding:8px 18px')
S('ap-perfil','apoderado','Mi perfil','mas','Mi perfil',[card(cab_perfil()),perfil_menu],back='Más',sub='Cuenta de Daniela Pérez',layout='g2',
  dmain=[perfil_menu],dside=[card(cab_perfil(),'<div style="height:14px"></div>',en_club(),bt('Cambiar foto','camera',k='w',style='margin-top:12px'))])

# ---------- Mis datos ----------
datos_form=card(campo('Nombre','Daniela Pérez'),campo('Teléfono','06 00 00 00 01'),
  campo('Correo','daniela@ejemplo.fr',hint='Para cambiar el correo te pedimos un código.'),
  mini('Idioma'),pills(['Français','Español'],0),
  acciones(bt('Guardar',k='s'),bt('Cambiar correo')))
S('ap-misdatos','apoderado','Mis datos','mas','Mis datos',[datos_form],back='Mi perfil',sub='Nombre, teléfono, correo',layout='g2',
  dmain=[datos_form],dside=[card(ch('Quién ve estos datos'),'<p class="meta" style="margin-bottom:8px">{{i:shield-check:bold}}Entrenadores y dirigentes del club</p><p class="meta">{{i:users-three:bold}}Otras familias, solo si tú compartes</p>',bt('Ver privacidad',k='w',style='margin-top:14px'))])

# ---------- Idioma ----------
idioma_l=card(lkl(lk('','Français','',right=est('check','Activo')),lk('','Español','',right='')),style='padding:8px 18px')
S('ap-idioma','apoderado','Idioma','mas','Idioma',[idioma_l,'<p class="sis">{{i:translate:bold}}Cambia los textos de la app. Los avisos del club llegan en el idioma que escribió el entrenador.</p>'],back='Mi perfil',sub='Textos de la app',layout='g2',
  dmain=[idioma_l],dside=[card(ch('Más idiomas'),'<p class="meta">{{i:translate:bold}}Se agregan con un archivo por idioma.</p>')])

# ---------- Preferencias de avisos ----------
avisos_c=[card(ch('Avisos en el celular'),tog('Push','Notificaciones de la app',True),tog('Correo','Un resumen de cambios',True)),
  card(ch('Qué quiero recibir'),tog('Cambios de hora de regreso','Si el partido se alarga',True),tog('Recordatorio de mi turno','48 h y 3 h antes',True),tog('Aviso por WhatsApp','Un mensaje con un enlace',False))]
S('ap-avisos','apoderado','Preferencias de avisos','mas','Preferencias de avisos',avisos_c,back='Mi perfil',sub='Push, correo y WhatsApp',layout='g2',
  dmain=avisos_c[:1],dside=avisos_c[1:])

# ---------- Compartir mis datos ----------
comp_c=card(tog('Mi teléfono','06 00 00 00 01',True),tog('Mi correo','daniela@ejemplo.fr',False),tog('Aparecer en el directorio','Solo familias de tu equipo',True))
S('ap-compartir','apoderado','Compartir mis datos','mas','Compartir mis datos',[comp_c,'<p class="sis">{{i:users-three:bold}}Solo lo ven las familias de tu equipo. Los entrenadores siempre ven tus datos.</p>'],back='Mi perfil',sub='Con las familias de tu equipo',layout='g2',
  dmain=[comp_c],dside=[card(ch('Así te ven'),pers('DP','Daniela Pérez','06 00 00 00 01 · sin correo'),'<p class="meta" style="margin-top:8px">{{i:users-three:bold}}Familias de U12</p>')])

# ---------- Privacidad ----------
quien=card(ch('Quién ve tus datos'),
  '<div class="nivel">'+fi('shield-check')+'<div><b>Entrenadores y dirigentes del club</b><p>Tu nombre, teléfono y correo, y los datos de tus hijos.</p></div></div>'
  +'<div class="nivel">'+fi('users-three')+'<div><b>Otras familias de tu equipo</b><p>Solo lo que tú elijas compartir.</p></div></div>'
  +'<div class="nivel">'+fi('chats-circle')+'<div><b>Compañeros de tus hijos</b><p>Solo su nombre de pila, en el chat del equipo.</p></div></div>',
  bt('Elegir qué comparto',k='w',style='margin-top:12px'))
derechos=card(ch('Tus derechos'),lkl(lk('download-simple','Descargar mis datos'),lk('camera','Pedir que se quiten fotos de mis hijos'),lk('x','Eliminar mi cuenta','Acción definitiva')),style='padding:20px 18px 8px')
S('ap-privacidad','apoderado','Privacidad','mas','Privacidad',[quien,derechos],back='Mi perfil',sub='Quién ve qué, descargar o borrar',layout='g2',
  dmain=[quien],dside=[derechos])

# ---------- Mi familia ----------
adultos=card(ch('Adultos'),pers('DP','Daniela Pérez','Titular · daniela@ejemplo.fr',est('check','Tú'),True),bt('Agregar a otro papá o mamá','plus',k='w',style='margin-top:12px'))
def hijo(n,eq,edad):
    return '<div class="hijo-fila"><div class="top">%s<div><b>%s</b><small>%s · %s</small></div>%s</div><div class="sub-acc">%s%s</div></div>'%(avatar(n[0]),n,eq,edad+' · solo puede ver',tag('Perfil hijo'),bt('Chat del equipo','chats-circle',style='height:34px;padding:0 12px'),bt('Enlace de acceso para %s'%n,'share-network',style='height:34px;padding:0 12px'))
hijos=card(ch('Hijos'),hijo('Lucas','U12','12 años'),hijo('Emma','U10','10 años'),bt('Agregar hijo','plus',k='w',style='margin-top:6px'))
S('ap-familia','apoderado','Mi familia','mas','Mi familia',[adultos,hijos],back='Más',sub='Adultos e hijos',layout='g2',
  dmain=[hijos],dside=[adultos])

# ---------- Mis clubes ----------
def club_row(esc,k,n,sub,sub2,act=False):
    return '<li><button type="button" class="lk" style="grid-template-columns:auto minmax(0,1fr) auto">%s<span><b>%s</b><small>%s</small><small>%s</small></span>%s</button></li>'%(escudo(esc,k),n,sub,sub2,est('check','Activo') if act else '<span class="chev">{{i:arrow-right:bold}}</span>')
clubes=card('<ul class="lkl">%s%s</ul>'%(club_row('CA','','Club Aigles','Niort · Fútbol','U12 · Lucas · U10 · Emma',True),club_row('CP','b','Club Pampa','Poitiers · Vóley','U14 Vóley · Lucas')),style='padding:8px 18px')
S('ap-misclubes','apoderado','Mis clubes','mas','Mis clubes',[clubes,'<p class="sis">{{i:house:bold}}Tu correo está en más de un club. Cambias de club desde el nombre arriba.</p>'],back='Más',sub='2 clubes',layout='g2',
  dmain=[clubes],dside=[card(ch('Club activo'),'<div class="tags">%s</div>'%tag('Club Aigles','house'),'<p class="meta">{{i:map-pin:bold}}Niort · Fútbol</p>',bt('Entrar al club',k='w',style='margin-top:12px'))])

# ---------- Club ----------
eq_u12=lk('c:balon','U12','Lucas · Camila, Pedro',k='lim'); eq_u10=lk('c:balon','U10','Emma · Léa, Marc',k='lim')
mis_eq=card(ch('Mis equipos'),lkl(eq_u12,eq_u10),style='padding:20px 18px 8px')
sobre=card(ch('Sobre el club'),'<p class="meta">{{i:paper-plane-tilt:bold}}contacto@aigles.fr</p>','<p class="meta" style="margin-top:6px">{{i:map-pin:bold}}Niort</p>',bt('Salir de este club',k='danger w',style='margin-top:16px'))
S('ap-club','apoderado','Club Aigles','mas','Club Aigles',[card(tags(tag('Club activo','check'),chipm('Niort · Fútbol'))),mis_eq,sobre],back='Mis clubes',sub='Niort · Fútbol',layout='g2',
  dmain=[mis_eq],dside=[card(tags(tag('Club activo','check'),chipm('Niort · Fútbol'))),sobre])

# ---------- Equipo ----------
entrenadores=card(ch('Entrenadores'),pers('CA','Camila','Entrenadora · encargada',bt('Preguntar',style='height:34px;padding:0 12px')),pers('PE','Pedro','Entrenador',bt('Preguntar',style='height:34px;padding:0 12px')))
fijado=card(tags(tag('Fijado','push-pin')),'<div class="regla"><b>Reglas del equipo</b><p>Llegar 15 minutos antes. Avisar si no vas hasta el jueves a las 21:00.</p></div>')
en_este=card(ch('En este equipo'),lkl(lk('car','Turnos de traslado','Quién lleva y cuándo'),lk('users-three','Familias del equipo','Directorio opcional'),lk('chats-circle','Chat de niños','Solo lectura')),style='padding:20px 18px 8px')
jug=card(ch('Jugadores','<span class="est">10</span>'),'<div class="chips">%s</div>'%''.join(tag(j) for j in JUG))
S('ap-equipo','apoderado','Equipo U12','mas','U12',[fijado,entrenadores,en_este,jug],back='Club Aigles',sub='Temporada 2026 – 2027 · 10 jugadores',layout='g2',
  dmain=[fijado,en_este],dside=[entrenadores,jug])

# ---------- Familias del equipo ----------
DATOS={'Lucas':None,'Tomás':'06 00 00 00 02 · marta.garcia@ejemplo.fr','Mateo':'06 00 00 00 20 · paul.fournier@ejemplo.fr','Gabriel':None,'Louis':'06 00 00 00 22 · karim.dubois@ejemplo.fr','Raphaël':'06 00 00 00 23 · marta.roux@ejemplo.fr','Adam':None,'Arthur':'06 00 00 00 25 · sofia.bernard@ejemplo.fr','Ethan':'06 00 00 00 26 · julien.lefevre@ejemplo.fr','Paul':None}
def fam_row(j,p):
    d=DATOS[j]
    return pers(ini(p),p,'· %s'%j+('' if d is None else ' · '+d) if False else ('%s · %s'%(j,d) if d else '%s · No comparte sus datos'%j),'<span class="chev">{{i:arrow-right:bold}}</span>' if d else est('lock','Privado'),lim=(j=='Lucas'))
fams=card(ch('Directorio','<span class="est">10</span>'),''.join(fam_row(j,p) for j,p in PPFAM))
S('ap-familias','apoderado','Familias del equipo','mas','Familias de U12',[fams],back='U12',sub='Solo ves lo que cada familia comparte',layout='g2',
  dmain=[fams],dside=[card(ch('Tu privacidad'),'<p class="meta">{{i:lock:bold}}Tus datos aparecen solo si activas «Aparecer en el directorio».</p>',bt('Compartir mis datos',k='w',style='margin-top:12px'))])

# ---------- Chat de niños (solo lectura) ----------
chat=card(ch('Mensajes','<span class="est">{{i:lock:bold}}Solo lectura</span>'),'<p class="meta" style="margin-bottom:14px">{{i:users-three:bold}}10 jugadores · lo moderan Camila y Pedro</p>',
  '<div class="burbujas">'+burbuja('TO','Tomás','¿Quién va el sábado?')+burbuja('MA','Mateo','¡Vamos!')+burbuja('GA','Gabriel','',oculto=True)+burbuja('GA','Gabriel','Yo voy')+burbuja('AR','Arthur','Bravo')+'</div>',
  '<div class="chat-pie">{{i:lock:bold}}<span>Solo lectura. Los niños escriben desde su perfil.</span></div>')
S('ap-chat','apoderado','Chat de niños','mas','Chat de U12',[chat],back='U12',sub='Lo ves, no escribes',layout='g2',
  dmain=[chat],dside=[card(ch('Cómo funciona'),'<p class="meta" style="margin-bottom:8px">{{i:chats-circle:bold}}Solo texto y stickers.</p><p class="meta" style="margin-bottom:8px">{{i:shield-check:bold}}Los entrenadores pueden ocultar mensajes.</p><p class="meta">{{i:lock:bold}}Los apoderados solo leen.</p>')])

# ---------- Ayuda ----------
ayuda=card(lkl(lk('download-simple','Instalar la app','Para recibir avisos'),lk('question','Preguntas frecuentes'),lk('chat-circle-dots','Escribir al soporte de Sportiva')),style='padding:8px 18px')
S('ap-ayuda','apoderado','Ayuda','mas','Ayuda',[ayuda],back='Más',sub='Instalar, preguntas y soporte',layout='g2',
  dmain=[ayuda],dside=[card(ch('Antes de escribir'),'<p class="meta">{{i:hand-waving:bold}}Si es del club (horarios, turnos), pregúntale al entrenador desde su equipo.</p>')])
