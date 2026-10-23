from lib import *

ANT='<span style="display:inline-flex;transform:scaleX(-1)">{{i:arrow-right:bold}}</span>'
SIG='{{i:arrow-right:bold}}'
def mapa_lugar(n1,n2):
    return meta('map-pin',n1)+'<p class="sub" style="margin:4px 0 0 27px">%s</p>'%n2+mapa()+mapas_btns()
def campo_enviar(ph,btxt='Enviar'):
    return campo('Mensaje',ph,ph=True,ta=True)+bt(btxt,'paper-plane-tilt',k='s w')
def regreso(h,sub,cambio=None):
    return card('<span class="ey">Regreso estimado al club</span><span class="num-g">%s</span><p class="meta" style="margin-top:8px">{{i:clock:bold}}%s</p>'%(h,sub),(('<div class="aviso" style="margin-top:12px">%s<div><b>%s</b><p>%s</p></div></div>'%(fi('timer'),cambio[0],cambio[1])) if cambio else ''))

# ---------- Evento especial ----------
ev_info=card(tags(tipo_tag('especial'),chipm('U12 · Lucas')),h2('Stage de vacaciones'),meta('calendar-blank','Jue 29 oct · 09:00 – 17:00'),meta('map-pin','Complexe sportif · Coulonges-sur-l’Autize','margin-top:6px'))
ev_insc=card('<span class="ey">Inscripción</span><span class="num-g">45 €</span>','<p class="meta" style="margin:8px 0 14px">Precio informativo. No se cobra en la app.</p>',aviso_box('mail' if False else 'paper-plane-tilt','Al inscribirte se avisa a tesorería','tesoreria@ejemplo.fr'),'<div style="height:14px"></div>',bt('Inscribir a Lucas','check',k='s w'))
ev_lugar=card(ch('Lugar'),mapa_lugar('Complexe sportif · Coulonges-sur-l’Autize','6 Place du Château, 79160 Coulonges-sur-l’Autize'))
ev_preg=card(ch('Preguntar al entrenador'),campo_enviar('Escribe tu pregunta…'),'<p class="meta" style="margin-top:10px">{{i:lock:bold}}Privado: lo ven los entrenadores del equipo.</p>')
S('ap-evento','apoderado','Convocatoria · evento especial','agenda','Stage de vacaciones',[ev_info,ev_insc,ev_lugar,ev_preg],back='Agenda',sub='Evento especial · Jue 29 oct',layout='g2',
  dmain=[ev_info,ev_lugar],dside=[ev_insc,ev_preg])

# ---------- En partido: antes ----------
pa_hora=card('<div class="hilo">%s<div><span class="ey">En partido</span><b style="font:600 24px/1.1 var(--font-mono);letter-spacing:-.03em">14:00</b><p>Hora del partido. Aquí aparecerá el reporte en vivo.</p></div></div>'%fi('timer'))
pa_vacio=card(vacio('clock','Todavía no empieza','El reporte en vivo se activa 2 horas antes del partido.'))
S('ap-envivo-antes','apoderado','En partido · antes de empezar','agenda','En partido',[pa_hora,pa_vacio,regreso('18:30','Hora tentativa del entrenador')],back='Convocatoria',sub='U12 vs Niort · Sáb 24 oct',layout='g2',
  dmain=[pa_vacio,pa_hora],dside=[regreso('18:30','Hora tentativa del entrenador')])

# ---------- En vivo ----------
vivo_marc=card(marcador('2','1','U12','Niort','En vivo · 2.º tiempo'))
vivo_line=card(ch('Línea de tiempo','<span class="est">{{i:bell-ringing:bold}}En vivo</span>'),
  evt('14:42','Marta','Cambió el regreso a 18:50. Se alargó el torneo.'),evt('14:05','Tú','Comenzó el partido.'),
  evt('13:40','Sofía','Llegamos. Hay estacionamiento frente a la cancha.')+'<div style="margin:8px 0 4px 54px;max-width:260px">'+img(0,'ancho',alt='Foto del grupo llegando')+'</div>',
  evt('12:35','Luis','Salimos del club. 3 autos.'))
vivo_reg=regreso('18:50','Cambió hace 4 min · antes 18:30',('Cambió la hora de regreso','Marta · hace 4 min'))
vivo_rep=card(ch('Quién reporta'),pers('MG','Marta Garcia','Reportera en vivo · papás de Tomás',est('check','Activa')),'<p class="meta" style="margin-top:8px">{{i:users-three:bold}}Cualquier familia o el entrenador puede reportar.</p>')
S('ap-envivo','apoderado','En vivo','agenda','En vivo',[vivo_marc,vivo_reg,vivo_line,vivo_rep],back='Convocatoria',sub='U12 vs Niort · Sáb 24 oct',layout='g2',
  dmain=[vivo_marc,vivo_line],dside=[vivo_reg,vivo_rep])

# ---------- Fotos ----------
def galeria(n,k='g4'): return masonry(n)
fo_main=card(ch('Fotos del partido','<span class="est">%d fotos</span>'%NFOTOS+''),galeria(NFOTOS,'g3'),'<div style="height:6px"></div>',bt('Agregar foto','camera',k='s w'))
fo_reg=card(aviso_box('camera','Quién sube fotos','Papás y entrenadores. Los niños no suben fotos.'),'<p class="meta" style="margin-top:12px">{{i:lock:bold}}¿No quieres que salga tu hijo? Pídelo en Privacidad.</p>')
S('ap-fotos','apoderado','Fotos del partido','agenda','Fotos',[fo_main,fo_reg],back='Convocatoria',sub='U12 vs Niort · Sáb 24 oct',layout='g2',
  dmain=[card(ch('Fotos del partido','<span class="est">%d fotos</span>'%NFOTOS+''),galeria(NFOTOS,'g3'),'<div style="height:6px"></div>',bt('Agregar foto','camera',k='s w'))],dside=[fo_reg])

# ---------- Visor ----------
vi_main=card(img(3,'visor',alt='Foto %d de %d'%(min(3,NFOTOS),NFOTOS),L=True),'<p class="meta" style="margin:14px 0 0">{{i:camera:bold}}Subida por Sofía B. · 13:40</p><p class="sub" style="margin:4px 0 0 27px">U12 vs Niort · Sáb 24 oct</p>',acciones(bt('Anterior',k='',style='flex:1').replace('<button type="button" class="bt" style="flex:1">','<button type="button" class="bt" style="flex:1">'+ANT),bt('Siguiente',k='',style='flex:1')[:-9]+' '+SIG+'</button>'))
S('ap-visor','apoderado','Visor de foto','agenda','Foto %d de %d'%(min(3,NFOTOS),NFOTOS),[vi_main],back='Fotos',sub='U12 vs Niort',layout='uno')

# ---------- Aviso con comentarios ----------
av_post=post('CA','Camila · entrenadora','Mié 21 oct','Quedaron una sudadera gris y dos botellas en el vestuario. Están en la caja del club.',titulo='Ropa perdida del sábado',extra='<div class="foto-ph" style="margin:0 0 14px" role="img" aria-label="Foto">{{i:images:bold}}</div>')
av_com=card(ch('Comentarios','<span class="est">1</span>'),'<div class="burbujas">%s</div>'%burbuja('MG','Marta Garcia','Creo que la sudadera es de Tomás.'),'<div style="height:14px"></div>',campo('Tu comentario','Escribe un comentario…',ph=True),bt('Enviar','paper-plane-tilt',k='s w'))
av_lado=card(pers('CA','Camila','Entrenadora · encargada'),'<p class="meta" style="margin:10px 0 12px">Solo comentas; para algo privado usa una consulta.</p>',bt('Preguntar en privado','chat-circle-dots',k='w'))
S('ap-aviso','apoderado','Aviso con comentarios','feed','Aviso',[av_post,av_com],back='Feed',sub='Equipo U12',layout='g2',
  dmain=[av_post,av_com],dside=[av_lado])

# ---------- Hilo ----------
hi_main=card('<div class="burbujas">%s%s</div>'%(burbuja('DP','Tú','¿Hay que llevar botella?'),burbuja('CA','Camila','Sí, y camiseta blanca.')),'<div style="height:16px"></div>',campo('Responder','Escribe un mensaje…',ph=True),bt('Enviar','paper-plane-tilt',k='s w'))
S('ap-hilo','apoderado','Consulta · hilo','actividad','¿Hay que llevar botella?',[hi_main,card(est('lock','Privada'),'<p class="meta" style="margin-top:8px">Solo la ven los entrenadores del equipo.</p>')],back='Mi actividad',sub='U12 vs Rochelais · Consulta privada',layout='g2',
  dmain=[hi_main],dside=[card(est('lock','Privada'),'<p class="meta" style="margin-top:8px">Solo la ven los entrenadores del equipo.</p>'),card(pers('CA','Camila','Entrenadora · respondió el 16 oct'))])

# ---------- Nueva consulta ----------
nc_main=card(mini('Para'),pills(['Camila','Pedro','Camila y Pedro'],2),campo('Tema','Ej.: horario, lesión, ropa…',ph=True),campo('Mensaje','Escribe tu consulta…',ph=True,ta=True),aviso_box('lock','Es privada','Solo la ven los entrenadores.'),'<div style="height:14px"></div>',bt('Enviar','paper-plane-tilt',k='s w'))
S('ap-nueva-consulta','apoderado','Nueva consulta','actividad','Nueva consulta',[nc_main],back='Mi actividad',sub='Consulta privada al entrenador',layout='uno')

# ---------- Notificaciones ----------
NUEVO='<span class="est">{{i:bell-ringing:bold}}Nuevo</span>'
def nt(ic,t,sub,nuevo=False,k=''): return lk(ic,t,sub,right=('<span style="display:inline-flex;align-items:center;gap:6px">'+NUEVO+'<span class="chev">{{i:arrow-right:bold}}</span></span>') if nuevo else None,k=k)
nb_hoy=card(lkl(nt('timer','Cambió la hora de regreso: 18:50 (antes 18:30)','Hora · U12 vs Niort · 14:42',True),nt('map-pin','Cambió el lugar del partido','Lugar · Sáb 24 oct · 10:12',True),nt('car','Te toca llevar este sábado','Turno · Encuentro 12:30 · 09:00',True)),style='padding:8px 18px')
nb_ayer=card(lkl(nt('chat-circle-dots','Camila respondió tu consulta','Respuesta · ¿Hay que llevar botella? · 20:10'),nt('c:balon','Nueva convocatoria: U12 vs Chauray','Convocatoria · Sáb 31 oct · 18:30',k='lim')),style='padding:8px 18px')
S('ap-bandeja','apoderado','Notificaciones','agenda','Notificaciones',[pills(['Todas','Sin leer · 3'],0),dia('Hoy'),nb_hoy,dia('Ayer'),nb_ayer,bt('Marcar todo como leído','check',k='w')],back='Agenda',sub='Hoy y ayer',layout='uno')
