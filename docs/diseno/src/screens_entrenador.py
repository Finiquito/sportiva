from lib import *

R='entrenador'
EQS=['U13','U12','U11A','U11B','U11C','U10']
def chk(on=True): return '<span class="chk%s">{{i:check:bold}}</span>'%(' on' if on else '')
def sinleer(): return '<span class="nuevo-b">{{i:chat-circle-dots:bold}}Sin leer</span>'
def resumen_a(v=5,d=2,n=1,s=2):
    return '<div class="resumen-a">%s%s%s%s</div>'%(est('check','Voy %d'%v),est('question','Duda %d'%d),est('x','No %d'%n),est('clock','Sin resp. %d'%s))
def btn_s(t,ic=None,w=True): return bt(t,ic,k='s w' if w else 's')
HORAS_LINEA=lambda e,c,p:'<p class="meta" style="margin-top:6px">{{i:clock:bold}}Encuentro %s · En cancha %s · Partido %s</p>'%(e,c,p) if e else '<p class="meta" style="margin-top:6px">{{i:clock:bold}}En cancha %s · Partido %s</p>'%(c,p)

# ---------------- 1 · Agenda ----------------
ag_partido=card(tags(tipo_tag('partido'),chipm('U12'),tag('Modificada hoy','map-pin')),h2('U12 vs Niort'),meta('map-pin','Stade du Centre · Niort'),HORAS_LINEA('12:30','13:15','14:00'),resumen_a(),acciones(bt('Abrir',k='f')))
ag_entreno=card(tags(tipo_tag('entreno'),chipm('U12')),h2('Entrenamiento U12'),meta('clock','18:00 – 19:30'),meta('map-pin','Gimnasio municipal','margin-top:6px'),resumen_a(8,1,0,1),acciones(bt('Abrir',k='f')))
ag_stage=card(tags(tipo_tag('especial'),chipm('U12')),h2('Stage de vacaciones'),meta('clock','09:00 – 17:00'),meta('map-pin','Complexe sportif · Niort','margin-top:6px'),resumen_a(3,1,0,6),acciones(bt('Abrir',k='f')))
ag_chauray=card(tags(tipo_tag('partido'),chipm('U12')),h2('U12 vs Chauray'),meta('map-pin','Stade municipal (Aiffres)'),HORAS_LINEA(None,'14:15','15:00'),resumen_a(0,0,0,10),acciones(bt('Abrir',k='f')))
nueva=bt('Nueva convocatoria','plus',k='s w',style='margin-top:6px')
S('en-agenda',R,'Agenda','agenda','Agenda',
  [dia('Sáb 24 oct'),ag_partido,dia('Mar 27 oct'),ag_entreno,dia('Jue 29 oct'),ag_stage,dia('Sáb 31 oct'),ag_chauray,nueva],
  eyebrow='Hola, Camila',pills=(['Lista','Mes'],0),layout='g2',
  dmain=[dia('Sáb 24 oct'),ag_partido,dia('Mar 27 oct'),ag_entreno,dia('Jue 29 oct'),ag_stage,dia('Sáb 31 oct'),ag_chauray],
  dside=[card(ch('Por responder'),pers('EP','Ethan · U12','Sin responder · Sáb 24',est('clock','2 días')),pers('LU','Lucas · U12','Sin responder · Sáb 24',est('clock','2 días')),bt('Recordar a las familias','paper-plane-tilt',k='w',style='margin-top:12px')),
         card(ch('Turnos de traslado'),'<p class="meta" style="margin-bottom:12px">{{i:car:bold}}Sáb 24: 3 familias cargadas</p><p class="meta">{{i:clock:bold}}Sáb 31: sin cargar</p>',bt('Cargar turnos',k='w',style='margin-top:12px')),
         nueva])

# ---------------- 2 · Crear ----------------
c_tipo=card(ch('Tipo'),pills(['Partido','Entreno','Stage','Especial'],0))
c_quien=card(ch('Quién'),campo('Equipo que convoca','U12',fin='arrow-right'),pills(EQS,1),'<div class="lk" style="padding:4px 0;grid-template-columns:minmax(0,1fr) auto"><span><b>10 convocados</b><small>Solo del equipo</small></span>%s</div>'%bt('Elegir','arrow-right'))
c_cuando=card(ch('Cuándo'),campo('Fecha','Sáb 24 oct',ic='calendar-blank'),campo('Hora del partido','14:00',ic='timer'),mini('Hora en cancha'),pills(['30 min antes','45 min antes','1 h antes'],1),'<p class="meta" style="margin:-4px 0 14px">{{i:c:bold}}</p>'.replace('{{i:c:bold}}','')+'<p class="meta">{{c:cancha-futbol}}En cancha 13:15</p>',tog('Hay punto de encuentro','Si todos van directo, se muestran En cancha y Partido',True),campo('Hora de encuentro','12:30',ic='users-three'))
c_punto=card(ch('Punto de encuentro'),pills(['El club','El lugar del partido','Otro lugar'],0),campo('Indicaciones','Estacionamiento del club, portón azul',ta=True))
c_lugar=card(ch('Lugar del partido *'),campo('Lugar','Ningún lugar elegido',ph=True,ic='map-pin'),aviso_box('map-pin','Sin ubicación confirmada no se puede publicar.'),bt('Elegir lugar','map-pin',k='w',style='margin-top:12px'))
c_turno=card(ch('Traslado compartido'),'<p class="meta">{{i:car:bold}}Turno de este sábado</p>',fam_ch:='<div class="fam-chips"><span class="tag m">Daniela P.</span><span class="tag m">Camille R.</span><span class="tag m">Julien M.</span></div>','<p class="sis" style="margin:12px 0">{{i:users-three:bold}}Los cupos se reparten al llegar al punto de encuentro.</p>',bt('Editar turnos',k='w'))
c_nota=card(ch('Nota para las familias'),campo('Mensaje (opcional)','',ph=True,ta=True),tog('Avisar a 10 familias','Notificación y correo',True),btn_s('Publicar convocatoria','paper-plane-tilt'))
S('en-crear',R,'Nueva convocatoria','agenda','Nueva convocatoria',[c_tipo,c_quien,c_cuando,c_punto,c_lugar,c_turno,c_nota],back='Agenda',sub='U12 · Partido',layout='g2',
  dmain=[c_tipo,c_quien,c_cuando,c_punto,c_lugar],dside=[c_turno,c_nota])

# ---------------- 3 · Elegir jugadores ----------------
def fila_j(n,ini_,on=True,eq='U12'): return '<div class="pers">%s<div><b>%s</b><small>%s</small></div>%s</div>'%(avatar(ini_),n,eq,chk(on))
jug_card=card(ch('U12 · 10','<span class="est">10 convocados</span>'),''.join(fila_j(n,ini(n)) for n in JUG),bt('Quitar a todo el equipo',k='w',style='margin-top:12px'))
elegir_pills=pills(EQS,1)
S('en-elegir',R,'Elegir jugadores','agenda','Elegir jugadores',[elegir_pills,jug_card,btn_s('Listo · 10 convocados','check')],back='Nueva convocatoria',sub='Puedes sumar jugadores de otras categorías',layout='g2l',
  dleft=[card(ch('Equipos'),lista(*[fila(fi('users-three'),e,'%d jugadores'%n,est('check','%d'%c) if c else '',sel=(e=='U12')) for e,n,c in [('U13',8,0),('U12',10,10),('U11A',6,0),('U11B',6,0),('U11C',6,0),('U10',6,0)]]))],
  dmain=[jug_card,btn_s('Listo · 10 convocados','check',w=False)])

# ---------------- 4 · Asistencia ----------------
def grupo(titulo,ic,nombres,sub='U12'): return card(ch(titulo),''.join(pers(ini(n),n,sub,est(ic[0],ic[1])) for n in nombres))
res_card=card(ch('Asistencia','<span class="est">5 de 10</span>'),donut(5,10))
cambios=card(ch('Cambios recientes'),'<div class="mov-fila"><div><b>Adam</b><p class="meta" style="margin:2px 0 0">cambió a No voy · hace 12 min</p></div>%s</div>'%tag('Gripe'),'<div class="mov-fila"><div><b>Paul</b><p class="meta" style="margin:2px 0 0">cambió a Duda · hace 1 h</p></div>%s</div>'%est('question','Duda'))
acc_a=card(btn_s('Recordar a quienes no respondieron','paper-plane-tilt'),acciones(bt('Reporte en vivo','timer'),bt('Editar convocatoria','arrow-right')))
quien=[grupo('Voy · 5',('check','Voy'),['Tomás','Mateo','Gabriel','Louis','Arthur']),grupo('Duda · 2',('question','Duda'),['Raphaël','Paul']),grupo('No voy · 1',('x','No voy'),['Adam']),grupo('Sin responder · 2',('clock','Sin respuesta'),['Ethan','Lucas'])]
S('en-asistencia',R,'Asistencia del partido','agenda','U12 vs Niort',[res_card,cambios,acc_a]+quien[:2],back='Agenda',sub='Sáb 24 oct · 14:00 · en cancha 13:15',layout='g2',
  dmain=[res_card]+[card(*(list(c.replace('<section class="card">','').replace('</section>','') for c in [q]))) for q in quien][:0]+quien,dside=[cambios,acc_a])

# ---------------- 5 · Lista (quién va) ----------------
S('en-lista',R,'Quién va','agenda','Quién va',quien,back='Asistencia',sub='U12 vs Niort · Sáb 24 oct',layout='cols2')

# ---------------- 6 · En vivo ----------------
ev_marc=card(ch('Marcador','<span class="est">{{i:check:bold}}Reportando</span>'),marcador(2,1),acciones(bt('Gol U12','plus'),bt('Gol Niort','plus'),bt('Cambiar periodo')))
ev_ret=card(ch('Regreso estimado al club'),'<div class="horas" style="border:0;margin:0;padding:0;grid-template-columns:1fr"><div><span class="hora"><span class="hl">18:50</span></span></div></div>','<p class="meta" style="margin:10px 0 14px">{{i:clock:bold}}Antes 18:30 · lo cambió Marta hace 4 min</p>',bt('Cambiar hora',k='w'))
ev_quien=card(ch('Quién reporta'),pers('CA','Camila (tú)','Entrenadora · reportando',est('check','Activa')),pers('MG','Marta G.','Familia · reportando',est('check','Activa')),bt('Pedir a otra familia','plus',k='w',style='margin-top:12px'))
ev_line=card(ch('Actualizaciones'),campo('Escribe una actualización','',ph=True),acciones(bt('Foto','camera'),btn_s('Publicar',None,w=False)),hr(),evt('14:42','Marta','Cambió el regreso a 18:50. Se alargó el torneo.'),evt('14:05','Tú','Comenzó el partido.'),evt('13:40','Sofía','Llegamos. Hay estacionamiento frente a la cancha.'),evt('12:35','Luis','Salimos del club. 3 autos.'))
S('en-envivo',R,'Reporte en vivo','agenda','U12 vs Niort',[ev_marc,ev_ret,ev_quien,ev_line],back='Asistencia',sub='En partido · 14:00 · Stade du Centre',layout='g2',
  dmain=[ev_marc,ev_line],dside=[ev_ret,ev_quien])

# ---------------- 7 · Turnos (nuevo modelo) ----------------
def turno_card(d,m,partido,fams,extra=''):
    return card('<div class="turno-f"><span class="fecha"><b>%s</b>%s</span><div><b style="font:700 15px/1.3 var(--font-body)">%s</b><div class="fam-chips">%s</div></div></div>'%(d,m,partido,''.join('<span class="tag m">%s</span>'%f for f in fams)),extra,acciones(bt('Editar familias','plus',k='pill-sm')))
t_cards=[turno_card('24','OCT','U12 vs Niort',['Daniela P.','Camille R.','Julien M.']),
         turno_card('31','OCT','U12 vs Chauray',['Sophie B.','Karim D.']),
         turno_card('07','NOV','Partido por confirmar',['Familia Bernard','Familia Dubois']),
         turno_card('14','NOV','Torneo U12',['Léa T.','Nina F.'])]
t_sol=card(ch('Solicitudes','<span class="est">2</span>'),
  '<div class="sol"><div class="pers" style="padding:0">%s<div><b>Sophie B.</b><small>Yo también llevo · Sáb 24</small></div></div><div class="botones">%s%s</div></div>'%(avatar('SB'),bt('Aceptar','check',k='s pill-sm'),bt('Rechazar',k='pill-sm')),
  '<div class="sol"><div class="pers" style="padding:0">%s<div><b>Karim D.</b><small>Cambio con Julien M. · Sáb 7 nov</small></div></div><div class="botones">%s%s</div></div>'%(avatar('KD'),bt('Confirmar','check',k='s pill-sm'),bt('Rechazar',k='pill-sm')))
t_info=aviso_box('car','Calcula de más','Los cupos no se asignan: se reparten al llegar al punto de encuentro. Las familias hablan entre ellas para cambiar.')
t_cargar=card(btn_s('Cargar turnos del mes','plus'),'<p class="sis" style="margin-top:12px">{{i:calendar-blank:bold}}Se cargan junto con los partidos de la temporada.</p>')
S('en-turnos',R,'Turnos de traslado','agenda','Turnos',[t_info,t_sol]+t_cards+[t_cargar],back='Agenda',sub='U12 · Temporada 2026 – 2027',layout='g2',
  dmain=t_cards,dside=[t_sol,t_info,t_cargar])

# ---------------- 8 · Feed ----------------
f_items=[
 card('<div class="hilo">%s<div><span class="ey">Convocatoria publicada</span><b>U12 vs Niort</b><p>Sáb 24 oct · 14:00 · Stade du Centre · Niort</p>%s</div></div>'%(fi('c:balon','lim'),bt('Ver asistencia'))),
 post('CA','Camila · entrenadora','Mié 21 oct','Quedaron una sudadera gris y dos botellas en el vestuario. Están en la caja del club.',titulo='Ropa perdida del sábado',extra='<div class="foto-ph" style="margin-bottom:14px">{{i:images:bold}}</div>',coment='1 comentario'),
 '<p class="sis">{{i:users-three:bold}}Nolan pasa de U11B a U11A desde el 15 nov</p>',
 card(ch('Fotos','<span class="est">12 nuevas</span>'),fotos(3),bt('Ver fotos',k='w')),
 card('<div class="hilo">%s<div><span class="ey">Resultado</span><b>U12 3 – 1 Rochelais</b><p>Dom 18 oct</p></div></div>'%fi('trophy'))]
pub=bt('Publicar aviso','megaphone',k='s w',style='margin-bottom:12px')
S('en-feed',R,'Feed','feed','Feed',[pub,dia('Esta semana')]+f_items,eyebrow='Hola, Camila',layout='g3',
  dleft=[card(ch('Equipos'),lista(fila(fi('c:balon','lim'),'U12','10 jugadores','',sel=True),fila(fi('c:silbato','pol'),'U13','8 jugadores'),fila(fi('house'),'Club Aigles','Avisos del club')))],
  dmain=[pub]+f_items,dside=[card(ch('Próximo partido'),tags(tipo_tag('partido'),chipm('U12')),h2('U12 vs Niort','font-size:20px'),'<p class="meta">{{i:calendar-blank:bold}}Sáb 24 · 14:00</p>',resumen_a(),bt('Ver asistencia',k='w',style='margin-top:12px'))])

# ---------------- 9 · Nuevo aviso ----------------
na_form=card(ch('Nuevo aviso'),mini('Para'),pills(['Todo el equipo U12','U12 y U13','Jugadores específicos…'],0),campo('Título','Ropa perdida del sábado'),campo('Mensaje','Quedaron una sudadera gris y dos botellas en el vestuario. Están en la caja del club.',ta=True),acciones(bt('Agregar foto','camera'),btn_s('Publicar',None,w=False)))
na_prev=card(ch('Así lo verán las familias'),post('CA','Camila · entrenadora','Ahora','Quedaron una sudadera gris y dos botellas en el vestuario. Están en la caja del club.',titulo='Ropa perdida del sábado').replace('<article class="card post">','<article class="post">').replace('</article>','</article>'),'<p class="sis" style="margin-top:8px">{{i:users-three:bold}}Se avisará a 10 familias.</p>')
S('en-nuevoaviso',R,'Nuevo aviso','feed','Nuevo aviso',[na_form],back='Feed',sub='Para las familias del equipo',layout='g2',dside=[na_prev])

# ---------------- 10 · Consultas ----------------
def cons(nom,ctx,txt,nuevo=True,sel=False):
    return fila(avatar(ini(nom.replace('Familia ',''))),nom,'%s · %s'%(ctx,txt),sinleer() if nuevo else '',sel=sel)
c_list=card(lista(cons('Familia Pérez','Lucas · U12 vs Niort','¿Hasta qué hora estará el bus?',True,True),cons('Familia Garcia','Tomás · Stage de vacaciones','¿El stage incluye almuerzo?'),cons('Familia Moreau','Hugo (U11A) · Convocado a U13','Perfecto.',False)),style='padding:8px 12px')
c_hilo=card(ch('Familia Pérez','<span class="est">Privada</span>'),'<span class="mini-t">Lucas · U12 vs Niort</span>','<div class="burbujas">%s%s</div>'%(burbuja('DP','Daniela','¿Hasta qué hora estará el bus?'),burbuja('CA','Tú','Hasta las 18:30. Si cambia, te avisamos.')),hr(),campo('Responder','',ph=True),btn_s('Enviar','paper-plane-tilt'))
S('en-consultas',R,'Consultas','consultas','Consultas',[pills(['Familias · 2','Encargado · 1'],0),c_list],eyebrow='Hola, Camila',layout='g2l',
  dleft=[pills(['Familias · 2','Encargado · 1'],0),c_list],dmain=[c_hilo])

# ---------------- 11 · Hilo ----------------
h_conv=card('<span class="mini-t">Lucas · U12 vs Niort</span>','<div class="burbujas">%s%s</div>'%(burbuja('DP','Daniela','¿Hasta qué hora estará el bus?'),burbuja('CA','Tú','Hasta las 18:30. Si cambia, te avisamos.')),hr(),campo('Responder','',ph=True),btn_s('Enviar','paper-plane-tilt'))
h_fam=card(ch('Familia Pérez'),pers('DP','Daniela Pérez','Madre · 06 00 00 00 01'),acciones(bt('Llamar','navigation-arrow'),bt('WhatsApp','chat-circle-dots')),bt('Ver ficha de Lucas',k='w',style='margin-top:8px'))
S('en-hilo',R,'Hilo de consulta','consultas','Familia Pérez',[h_conv,aviso_box('lock','Consulta privada','La ven los entrenadores del equipo.')],back='Consultas',sub='Lucas · U12 vs Niort',layout='g2',dmain=[h_conv],dside=[h_fam,aviso_box('lock','Consulta privada','La ven los entrenadores del equipo.')])

# ---------------- 12 · Plantel ----------------
PCT=[70,77,84,91,70,77,84,91,70,77]
def j_fila(n,p): return fila(avatar(ini(n)),n,'Asistencia %d%%'%p,'<span class="chev">{{i:arrow-right:bold}}</span>')
pl_list=card(ch('U12 · 10 jugadores'),lista(*[j_fila(n,p) for n,p in zip(JUG,PCT)]))
pl_pills=pills(EQS,1)
S('en-plantel',R,'Plantel','plantel','Plantel',[pl_pills,pl_list],eyebrow='Hola, Camila',layout='g2',
  dmain=[pl_pills,pl_list],dside=[card(ch('Resumen U12'),'<span class="num-g">79%</span><p class="meta" style="margin:6px 0 0">Asistencia media de la temporada</p>',barra(79)),card(ch('Categoría'),bt('Reorganizar U11','share-network',k='w'),bt('Directorio de contactos','user-circle',k='w',style='margin-top:8px'))])

# ---------------- 13 · Jugador ----------------
j_cont=card(ch('Contactos'),pers('DP','Daniela Pérez','Madre · 06 00 00 00 01 · daniela@ejemplo.fr'),acciones(bt('Llamar','navigation-arrow'),bt('WhatsApp','chat-circle-dots'),bt('Correo','paper-plane-tilt')))
j_hist=card(ch('Historial de equipos'),pers('12','U12','desde sept. 2026',est('check','Actual')),pers('11','U11B','sept. 2025 – ago. 2026'),bt('Mover de equipo','share-network',k='w',style='margin-top:12px'))
j_asis=card(ch('Asistencia'),'<span class="num-g">70%</span><p class="meta" style="margin:6px 0 0">Temporada 2026 – 2027</p>',barra(70))
S('en-jugador',R,'Ficha de jugador','plantel','Lucas',[j_cont,j_asis,j_hist],back='Plantel',sub='U12 · 12 años · nació 12 mar 2014',layout='g2',dmain=[j_cont,j_hist],dside=[j_asis])

# ---------------- 14 · Reorganizar ----------------
def col(eq,nombres): return card(ch('%s · %d'%(eq,len(nombres))),''.join('<div class="mov-fila"><b>%s</b>%s</div>'%(n,bt('','share-network',k='pill-sm',style='width:38px;padding:0')) for n in nombres))
r_cols=[col('U11A',['Nolan','Théo','Maxime','Ilan','Sacha','Yanis']),col('U11B',['Camille','Léon','Rayan','Oscar','Axel','Malo']),col('U11C',['Eliott','Gaspard','Timéo','Victor','Basile','Joseph'])]
r_acc=card(aviso_box('users-three','Se avisará a las familias','Solo a las de los jugadores que cambien de equipo.'),'<div style="height:12px"></div>',bt('Sin cambios',k='w',style='opacity:.55'),cls='x-wide')
S('en-reorganizar',R,'Reorganizar U11','plantel','Reorganizar U11',r_cols+[r_acc],back='Plantel',sub='Toca un jugador para moverlo',layout='cols3')

# ---------------- 15 · Directorio ----------------
d_rows=''.join(pers(ini(h),h,f,'<span class="chev">{{i:arrow-right:bold}}</span>') for h,f in PPFAM)
d_list=card(campo('Buscar','Nombre del jugador o familia',ph=True,ic='magnifying-glass'),pills(EQS,1),d_rows)
d_ficha=card(ch('Daniela Pérez'),'<span class="mini-t">Familia de Lucas · U12</span>',pers('DP','Daniela Pérez','06 00 00 00 01 · daniela@ejemplo.fr'),acciones(bt('Llamar','navigation-arrow'),bt('WhatsApp','chat-circle-dots'),bt('Correo','paper-plane-tilt')))
S('en-directorio',R,'Directorio','plantel','Directorio',[d_list],back='Plantel',sub='Familias de todos los equipos',layout='g2',dmain=[d_list],dside=[d_ficha])

# ---------------- 16 · Más ----------------
S('en-mas',R,'Más','mas','Más',[card(lkl(lk('user-circle','Mi perfil','Datos, idioma, avisos'),lk('address-book' if False else 'users-three','Directorio de contactos','Familias de todos los equipos'),lk('house','Mis clubes','Club Aigles · 2 clubes'),lk('question','Ayuda','Instalar la app')),style='padding:8px 20px'),bt('Cerrar sesión','x',k='danger w')],eyebrow='Hola, Camila',layout='uno')
