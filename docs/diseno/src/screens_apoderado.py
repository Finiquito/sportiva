from lib import *

# ---------- piezas reutilizables del apoderado ----------
def traslado_resumen():
    return card('<div class="traslado"><span class="ico">{{i:car:bold}}</span><div><span class="ey">Te toca llevar</span><b>Sáb 24 · 12:30</b><span class="s">Estacionamiento del club</span></div><button type="button" class="btn">Turnos</button></div>')
def quien_va(filas=5):
    rows=[pers('LP','Lucas P.','Daniela · mamá',est('check','Voy')),pers('TR','Tom R.','Camille R.',est('check','Voy')),pers('LM','Léo M.','Julien M.',est('question','Duda')),pers('HB','Hugo B.','Sophie B.',est('clock','Sin respuesta')),pers('ND','Nolan D.','Karim D.',est('x','No voy'))][:filas]
    return card(ch('Quién va','<span class="est">5 de 10</span>'),''.join(rows),bt('Ver los 10',k='w',style='margin-top:12px'))
def en_partido():
    return card('<div class="hilo">%s<div><span class="ey">En partido</span><b style="font:600 24px/1.1 var(--font-mono);letter-spacing:-.03em">14:00</b><p>El reporte en vivo aparece aquí cuando empiece.</p></div></div>'%fi('timer'))
def preguntar():
    return card('<div class="hilo">%s<div><b>¿Algo que preguntar?</b><p>Tu consulta es privada: solo la ve el entrenador.</p>%s</div></div>'%(fi('chat-circle-dots'),bt('Preguntar al entrenador')))
def lista_semana():
    return card(ch('Esta semana',bt('','plus',style='width:40px;padding:0;border-radius:50%')),lista(
        fila(fi('c:silbato','pol'),'Entrenamiento U10','Hoy · 18:00 · Emma',est('check','Voy')),
        fila(fi('c:balon','lim'),'U12 vs Niort','Sáb 24 · 14:00 · Lucas',est('check','Voy'),sel=True),
        fila(fi('c:silbato','pol'),'Entrenamiento U12','Mar 27 · 18:00 · Lucas',est('question','Duda')),
        fila(fi('c:balon','lim'),'U10 vs Chauray','Sáb 31 · 11:00 · Emma',est('clock','Sin responder'))))

# ---------- 1 · Agenda ----------
partido=card(tags(tipo_tag('partido'),chipm('U12 · Lucas')),h2('U12 vs Niort'),meta('map-pin','Stade du Centre · Niort'),HORAS_ENC(),asistencia(0))
entreno=card(tags(tipo_tag('entreno'),chipm('U12 · Lucas')),h2('Entrenamiento U12'),meta('clock','18:00 – 19:30'),meta('map-pin','Gimnasio municipal','margin-top:6px'),'<div style="height:18px"></div>',asistencia(1))
especial=card(tags(tipo_tag('especial'),chipm('U12 · Lucas')),h2('Stage de vacaciones'),meta('clock','09:00 – 17:00'),meta('map-pin','Complexe sportif · Niort','margin-top:6px'),'<div style="height:18px"></div>',bt('Abrir',k='w'))
chauray=card(tags(tipo_tag('partido'),chipm('U10 · Emma')),h2('U10 vs Chauray'),meta('map-pin','Complexe Sportif · Chauray'),HORAS_DIR('10:15','11:00'),asistencia())
S('ap-agenda','apoderado','Agenda','agenda','Agenda',
  [dia('Sáb 24 oct'),partido,traslado_resumen(),dia('Mar 27 oct'),entreno,dia('Jue 29 oct'),especial,dia('Sáb 31 oct'),chauray],
  eyebrow='Hola, Daniela',pills=(['Hoy','Semana','Mes'],1),layout='g3',
  dleft=[lista_semana()],
  dmain=[card(tags(tipo_tag('partido'),chipm('U12 · Lucas')),h2('U12 vs Niort','font-size:28px'),meta('calendar-blank','Sábado 24 de octubre'),meta('map-pin','Stade du Centre · Niort','margin-top:6px'),mapa(),mapas_btns(),HORAS_ENC(),asistencia(0))],
  dside=[quien_va(),traslado_resumen()])

# ---------- 2 · Convocatoria ----------
cambio=card('<div class="aviso">%s<div><b>Cambió el lugar del partido</b><p>Hoy 10:12 · Camila</p></div></div><div class="antes-ahora"><div><small>Antes</small><b>Stade municipal · Niort</b></div><div><small>Ahora</small><b>Stade du Centre · Niort</b></div></div>%s'%(fi('map-pin'),bt('Entendido',k='w')))
convo_main=[cambio,
  card(tags(tipo_tag('partido'),chipm('U12 · Lucas')),h2('U12 vs Niort'),meta('calendar-blank','Sábado 24 de octubre'),meta('map-pin','Stade du Centre · Niort','margin-top:6px'),'<p class="meta" style="margin-top:6px">{{i:users-three:bold}}Club local: Niort FC · Rue Ejemplo 12</p>',mapa(),mapas_btns()),
  card(HORAS_ENC(),'<div class="punto"><span class="fi">{{i:users-three:bold}}</span><div><span class="ey">Punto de encuentro</span><b>Estacionamiento del club, portón azul</b></div></div>',mapa().replace('height:120px',''),mapas_btns(),'<div style="height:16px"></div><span class="mini-t">Tu asistencia · Lucas</span>',asistencia(0))]
S('ap-convo','apoderado','Convocatoria · partido','agenda','U12 vs Niort',convo_main+[en_partido(),quien_va(),traslado_resumen(),preguntar()],back='Agenda',sub='Convocatoria · Sáb 24 oct',layout='g2',
  dmain=convo_main,dside=[en_partido(),quien_va(),traslado_resumen(),preguntar()])

# ---------- 3 · Turnos ----------
toca=card('<div class="traslado" style="grid-template-columns:auto minmax(0,1fr)"><span class="ico">{{i:car:bold}}</span><div><span class="ey">Te toca llevar</span><b>Sáb 24 · 12:30</b><span class="s">Estacionamiento del club</span></div></div>','<p class="sis" style="margin:14px 0 16px">{{i:users-three:bold}}Los cupos se reparten al llegar al punto de encuentro.</p>',bt('Cambiar con otra familia',k='w'))
hablar=bt('Hablar','chat-circle-dots',style='height:34px;padding:0 12px')
familias=card(ch('Familias de turno','<span class="est">Sáb 24 oct</span>'),pers('DP','Daniela P.','Tú · Lucas',est('car','Te toca'),True),pers('CR','Camille R.','Tom',hablar),pers('JM','Julien M.','Léo',hablar),pers('SB','Sophie B.','Quiere sumarse',est('clock','Esperando al profe')),bt('Yo también llevo','plus',k='w',style='margin-top:12px'))
def turno(d,m,p,f,mio=False): return fila('<span class="fecha"><b>%s</b>%s</span>'%(d,m),p,f,est('car','Te toca') if mio else '')
proximos=card(ch('Próximos turnos','<span class="est">Oct y nov</span>'),lista(turno('24','OCT','U12 vs Niort','Daniela P. · Camille R. · Julien M.',True),turno('07','NOV','U12 vs Chauray','Sophie B. · Karim D.'),turno('14','NOV','Torneo U12','Léa T. · Nina F.'),turno('21','NOV','U12 vs Aiffres','Daniela P. · Hugo')))
S('ap-turnos','apoderado','Turnos de traslado','agenda','Turnos',[toca,familias,proximos],back='Agenda',sub='U12 vs Niort · Sáb 24 oct',layout='g2',
  dmain=[proximos],dside=[toca,familias])

# ---------- 4 · Feed ----------
feed_posts=[
 card(tags(tag('Fijado','push-pin')),h2('Reglas del equipo','font-size:20px;margin-bottom:8px'),'<p class="tx" style="margin:0 0 14px;font:400 14.5px/1.5 var(--font-body)">Llegar 15 minutos antes. Avisar si no vas hasta el jueves a las 21:00.</p>',bt('Comentar','chat-circle-dots')),
 card('<div class="hilo">%s<div><span class="ey">Convocatoria publicada</span><b>U12 vs Niort</b><p>Sáb 24 oct · 14:00 · Stade du Centre · Niort</p>%s</div></div>'%(fi('c:balon','lim'),bt('Responder'))),
 post('CA','Camila · entrenadora','Mié 21 oct','Quedaron una sudadera gris y dos botellas en el vestuario. Están en la caja del club.',titulo='Ropa perdida del sábado',foto=True,coment='1 comentario'),
 '<p class="sis">{{i:users-three:bold}}Nolan pasa de U11B a U11A desde el 15 nov</p>',
 card(ch('Fotos','<span class="est">12 nuevas</span>'),fotos(3),'<p class="meta" style="margin-bottom:12px">12 fotos nuevas del partido en La Rochelle</p>',bt('Ver fotos',k='w')),
 card('<div class="hilo">%s<div><span class="ey">Resultado</span><b>U12 3 – 1 Rochelais</b><p>Dom 18 oct</p></div></div>'%fi('trophy'))]
S('ap-feed','apoderado','Feed','feed','Feed',[dia('Esta semana')]+feed_posts,eyebrow='Hola, Daniela',pills=(['Equipo U12','Club','Fotos'],0),layout='g3',
  dleft=[card(ch('Equipos'),lista(fila(fi('c:balon','lim'),'U12','Lucas','',sel=True),fila(fi('c:silbato','pol'),'U10','Emma'),fila(fi('house'),'Club Aigles','Avisos del club')))],
  dmain=feed_posts,dside=[partido,traslado_resumen()])

# ---------- 5 · Mi actividad ----------
consultas=[card('<div class="hilo">%s<div><span class="ey">Consulta privada</span><b>¿Hay que llevar botella?</b><p>Camila: «Sí, y camiseta blanca.»</p><div class="chips">%s<span class="tag m">Vie 16 oct</span></div></div></div>'%(fi('chat-circle-dots'),est('check','Respondida'))),
 bt('Nueva consulta','plus',k='f w',style='margin-top:6px')]
asistencias=card(lista(
  fila(fi('c:silbato','pol'),'Entrenamiento U10','Hoy · 18:00 · Emma',est('check','Voy')),
  fila(fi('c:balon','lim'),'U12 vs Niort','Sáb 24 · 14:00 · Lucas',est('clock','Sin responder')),
  fila(fi('c:silbato','pol'),'Entrenamiento U12','Mar 27 · 18:00 · Lucas',est('question','Duda'))),style='padding:8px 20px')
S('ap-actividad','apoderado','Mi actividad','actividad','Mi actividad',[dia('Consultas')]+consultas+[dia('Mis asistencias'),asistencias],eyebrow='Hola, Daniela',pills=(['Todo','Asistencias','Consultas'],0),layout='g2',
  dmain=[dia('Consultas')]+consultas,dside=[dia('Mis asistencias'),asistencias])
