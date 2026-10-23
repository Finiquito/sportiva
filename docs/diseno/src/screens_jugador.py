from lib import *

ro_nota=lambda t='Solo lectura: tus entrenadores y tu familia confirman la asistencia.':'<p class="ro" style="margin-top:14px">{{i:lock:bold}}%s</p>'%t
partido=card(tags(tipo_tag('partido'),tag('Modificada hoy','clock','m')),h2('U12 vs Niort'),meta('map-pin','Estadio · Coulonges-sur-l’Autize'),HORAS_ENC(),ro_nota())
entreno=card(tags(tipo_tag('entreno')),h2('Entrenamiento U12'),meta('clock','18:00 – 19:30'),meta('map-pin','Gimnasio municipal','margin-top:6px'))
especial=card(tags(tipo_tag('especial')),h2('Stage de vacaciones'),meta('clock','09:00 – 17:00'),meta('map-pin','Complexe sportif · Coulonges-sur-l’Autize','margin-top:6px'),bt('Abrir',k='w',style='margin-top:16px'))
chauray=card(tags(tipo_tag('partido')),h2('U12 vs Chauray'),meta('map-pin','Stade municipal · Aiffres'),HORAS_DIR('14:15','15:00'))
resumen=card(tags(tipo_tag('partido')),h2('U12 vs Niort','font-size:26px'),meta('calendar-blank','Sábado 24 de octubre'),meta('map-pin','Estadio · Coulonges-sur-l’Autize','margin-top:6px'),mapa(),mapas_btns(),HORAS_ENC())
S('ju-agenda','jugador','Agenda','agenda','Agenda',[dia('Sáb 24 oct'),partido,dia('Mar 27 oct'),entreno,dia('Jue 29 oct'),especial,dia('Sáb 31 oct'),chauray],eyebrow='Hola, Lucas',pills=(['Lista','Mes'],0),layout='g2',
  dmain=[dia('Sáb 24 oct'),partido,dia('Mar 27 oct'),entreno,dia('Jue 29 oct'),especial,dia('Sáb 31 oct'),chauray],dside=[resumen])

cambio=card('<div class="aviso">%s<div><b>Cambió el lugar del partido</b><p>Hoy 10:12 · Camila</p></div></div><div class="antes-ahora"><div><small>Antes</small><b>Terreno anexo · Coulonges-sur-l’Autize</b></div><div><small>Ahora</small><b>Estadio · Coulonges-sur-l’Autize</b></div></div>%s'%(fi('map-pin'),bt('Entendido',k='w')))
cabeza=card(tags(tipo_tag('partido'),chipm('U12')),h2('U12 vs Niort'),meta('calendar-blank','Sábado 24 de octubre'),HORAS_ENC())
punto=card(mini('Punto de encuentro'),'<p class="meta" style="color:var(--tinta);font-weight:600">{{i:users-three:bold}}Club · Estacionamiento, portón azul</p>',mapa(),mapas_btns())
lugar=card(mini('Lugar del partido'),'<p class="meta" style="color:var(--tinta);font-weight:600">{{i:map-pin:bold}}Estadio · Coulonges-sur-l’Autize</p><p class="meta" style="margin-top:6px">Sede del club · 6 Place du Château, 79160 Coulonges-sur-l’Autize</p>',mapa(),mapas_btns())
conv=card(ch('Convocatoria','<span class="est">5 de 10</span>'),donut(5,10),'<p class="ro" style="margin-top:14px">{{i:lock:bold}}Solo lectura</p>')
fotos_c=card(ch('Fotos del partido','<span class="est">0</span>'),vacio('images','Todavía no hay fotos','Las suben los entrenadores y las familias.'))
S('ju-convo','jugador','Convocatoria · partido','agenda','U12 vs Niort',[cambio,cabeza,punto,lugar,conv,fotos_c],back='Agenda',sub='Convocatoria · Sáb 24 oct',layout='g2',
  dmain=[cambio,cabeza,punto,lugar],dside=[conv,fotos_c])

fposts=[
 card('<div class="hilo">%s<div><span class="ey">Convocatoria publicada</span><b>U12 vs Niort</b><p>Sáb 24 oct · 14:00 · Estadio · Coulonges-sur-l’Autize</p>%s</div></div>'%(fi('c:balon','lim'),bt('Ver'))),
 post('CA','Camila · entrenadora','Mié 21 oct','Quedaron una sudadera gris y dos botellas en el vestuario. Están en la caja del club.',titulo='Ropa perdida del sábado',foto=True,extra='<p class="ro">{{i:chat-circle-dots:bold}}1 comentario</p>'),
 '<p class="sis">{{i:users-three:bold}}Nolan pasa de U11B a U11A desde el 15 nov</p>',
 card(ch('Fotos','<span class="est">12 nuevas</span>'),fotos(3),bt('Ver fotos',k='w')),
 card('<div class="hilo">%s<div><span class="ey">Resultado</span><b>U12 3 – 1 Rochelais</b><p>Dom 18 oct</p></div></div>'%fi('trophy'))]
S('ju-feed','jugador','Feed','feed','Feed',fposts+[ro_nota('Solo lectura: tú no comentas aquí.')],eyebrow='Hola, Lucas',layout='g2',
  dmain=fposts,dside=[card(ch('Próximo'),'<p class="meta" style="color:var(--tinta);font-weight:600">{{i:calendar-blank:bold}}U12 vs Niort</p><p class="meta" style="margin-top:6px">Sáb 24 · 14:00</p>',HORAS_ENC().replace('class="horas"','class="horas s"')),ro_nota('Solo lectura: tú no comentas aquí.')])

msgs=[('TO','Tomás','¿Quién va el sábado?',0),('MA','Mateo','¡Vamos!',0),('GA','Gabriel','',1),('AR','Arthur','Yo voy',0),('LO','Louis','Bravo',0)]
chat=card('<div class="burbujas">%s</div>'%''.join(burbuja(a,b,c,o) for a,b,c,o in msgs))
stk=['¡Gol!','Bravo','Ánimo','Gracias','Jaja','Ok','¡Vamos!','GG','Buen partido','Hasta mañana','¿Voy?','Uf']
compos=card(mini('Stickers'),stickers(*stk),'<div class="chat-in">%s%s</div>'%(campo('Mensaje','Escribe un mensaje…',ph=True),bt('Enviar','paper-plane-tilt',k='s',style='margin-top:26px')),'<p class="ro" style="margin-top:12px">{{i:lock:bold}}Sin fotos ni enlaces. Lo moderan Camila y Pedro.</p>')
S('ju-chat','jugador','Chat del equipo','chat','Chat de U12',[chat,compos],sub='10 jugadores · lo moderan Camila y Pedro',layout='g2',
  dmain=[chat],dside=[compos])

mas=card(lkl(lk('user-circle','Mi perfil','Datos, idioma, avisos'),lk('house','Mis clubes','FCJ Orée de l’Autize · 2 clubes'),lk('chat-circle-dots','Ayuda','Instalar la app'),lk('x','Cerrar sesión','',right='')),style='padding:8px 20px')
S('ju-mas','jugador','Más','mas','Más',[mas],eyebrow='Hola, Lucas',layout='uno')
