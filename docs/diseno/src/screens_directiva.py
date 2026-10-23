from lib import *

def stat4(*p): return '<div class="stats c4">%s</div>'%''.join('<div class="stat"><b>%s</b><span>%s</span></div>'%x for x in p)
RESUMEN=card(ch('Club Aigles','<span class="est">Niort · Fútbol</span>'),stats(('14','equipos'),('212','familias'),('18','entrenadores')))

# ---- Feed del club ----
publicar=card('<div class="hilo">%s<div><b>Avisos del club</b><p>Lo ven todas las familias y entrenadores.</p>%s</div></div>'%(fi('megaphone'),bt('Publicar aviso','plus',k='s')))
posts=[
 post('SY','Directiva','Mié 21 oct','Sábado 14 a las 10:00 en la sala del club. Habrá café.',titulo='Asamblea general, 14 de noviembre',coment='Comentarios: 0',lim=True),
 post('SY','Directiva','Lun 12 oct','Ya está abierta la lista de familias voluntarias.',titulo='Torneo de fin de año',coment='1 comentario'),
]
S('di-feed','directiva','Feed del club','feed','Feed del club',[publicar,dia('Esta semana')]+posts,eyebrow='Hola, Sylvie',layout='g2',
  dmain=[publicar]+posts,dside=[RESUMEN,card(ch('Próximo'),lista(fila(fi('calendar-blank'),'Asamblea general','Sáb 14 nov · 10:00'),fila(fi('trophy'),'Torneo de fin de año','Lista de voluntarios abierta')))])

# ---- Nuevo aviso ----
S('di-nuevoaviso','directiva','Nuevo aviso','feed','Nuevo aviso',[card(
  select('Para','Todo el club'),campo('Título','Asamblea general',ph=False),campo('Mensaje','Escribe el aviso…',ph=True,ta=True),
  bt('Agregar foto','camera',k='w'),acciones(bt('Publicar',k='s w')))],back='Feed',sub='Aviso del club',layout='uno')

# ---- Club ----
menu=card(lkl(
 lk('users-three','Equipos y grupos','14 equipos · 1 temporada'),
 lk('user-circle','Personas','212 familias · 18 entrenadores'),
 lk('paper-plane-tilt','Invitaciones','1 sin enviar · 5 autorizados'),
 lk('map-pin','Lugares','4 guardados'),
 lk('calendar-blank','Temporada','2026 – 2027'),
 lk('gear','Ajustes del club','Deporte, idioma, zona horaria')),style='padding:8px 20px')
S('di-club','directiva','Club','club','Club',[menu],eyebrow='Hola, Sylvie',layout='g2',
  dside=[RESUMEN,card(ch('Invitaciones'),'<p class="meta">{{i:paper-plane-tilt:bold}}1 sin enviar · 2 solicitudes</p>',bt('Revisar',k='w',style='margin-top:12px'))])

# ---- Equipos ----
EQ=[('U13','8 jugadores'),('U12','10 jugadores'),('U11A','6 jugadores'),('U11B','6 jugadores'),('U11C','6 jugadores'),('U10','6 jugadores')]
eq_list=card(lkl(*[lk(None,n,'%s · Camila, Pedro'%j) for n,j in EQ]),style='padding:8px 20px')
S('di-equipos','directiva','Equipos y grupos','club','Equipos y grupos',[eq_list,bt('Nuevo equipo','plus',k='f w')],back='Club',sub='14 equipos · temporada 2026 – 2027',layout='g2',
  dmain=[eq_list],dside=[card(ch('Nuevo equipo'),campo('Nombre','U14'),select('Categoría','Fútbol'),bt('Crear equipo','plus',k='s w')),card(ch('Resumen'),stats(('14','equipos'),('212','familias'),('18','entrenadores')))])

# ---- Personas ----
PE=[pers('CA','Camila','Entrenadora · encargada','<span class="chev">{{i:arrow-right:bold}}</span>'),pers('PE','Pedro','Entrenador','<span class="chev">{{i:arrow-right:bold}}</span>'),
    pers('DP','Daniela Pérez','Familia · Lucas, Emma','<span class="chev">{{i:arrow-right:bold}}</span>'),pers('MG','Marta Garcia','Familia · Tomás','<span class="chev">{{i:arrow-right:bold}}</span>'),
    pers('JM','Julien Moreau','Familia · Hugo','<span class="chev">{{i:arrow-right:bold}}</span>'),pers('SY','Sylvie','Directiva · presidenta','<span class="chev">{{i:arrow-right:bold}}</span>',True)]
personas=card(campo('Buscar','Nombre o correo',ph=True,ic='magnifying-glass'),''.join(PE))
S('di-personas','directiva','Personas','club','Personas',[pills(['Todos','Entrenadores','Familias','Directiva'],0),personas,bt('Invitar a alguien','plus',k='f w')],back='Club',sub='212 familias · 18 entrenadores',layout='g2',
  dmain=[pills(['Todos','Entrenadores','Familias','Directiva'],0),personas],
  dside=[card(ch('Resumen'),stats(('212','familias'),('18','entrenadores'),('4','directiva'))),card(ch('Invitar'),'<p class="meta" style="margin-bottom:12px">Agrega a alguien con su correo.</p>',bt('Invitar a alguien','plus',k='s w'))])

# ---- Invitaciones ----
ST=stat4(('5','autorizados'),('2','activos'),('1','sin entrar'),('1','rebotados'))
solic=card(ch('Solicitudes','<span class="est">2</span>'),
  '<div class="sol"><div class="pers" style="padding:0">%s<div><b>sophie.martin@ejemplo.fr</b><small>Jade · U10</small></div></div><div class="acc">%s%s</div></div>'%(avatar('SM'),bt('Aprobar','check',k='s'),bt('Rechazar','x')),
  '<div class="sol"><div class="pers" style="padding:0">%s<div><b>karim.b@ejemplo.fr</b><small>Inés · U12</small></div></div><div class="acc">%s%s</div></div>'%(avatar('KB'),bt('Aprobar','check',k='s'),bt('Rechazar','x')))
def inv(ini,mail,sub,right): return pers(ini,mail,sub,right)
lst=card(ch('Correos autorizados'),
  inv('DP','daniela@ejemplo.fr','Familia · U12 · U10',est('check','Activo')),
  inv('PE','pedro@aigles.fr','Entrenador · U13',est('check','Activo')),
  inv('MG','marta.garcia@ejemplo.fr','Familia · U12',bt('Reenviar','paper-plane-tilt',style='height:34px;padding:0 10px')),
  inv('JM','julien.moreau@ejemplo.fr','Familia · U11A',est('shield-check','Autorizado')),
  inv('CR','claire.roux@ejemplo.fr','Familia · U12',est('x','Rebotó')))
cargar=acciones(bt('Cargar lista de correos','plus',k='f'),bt('Enviar invitaciones · 1','paper-plane-tilt',k='s'))
S('di-invitaciones','directiva','Invitaciones','club','Invitaciones',[card(ST),cargar,solic,lst],back='Club',sub='Lista autorizada de correos',layout='g2',
  dmain=[lst],dside=[card(ST,cargar),solic])

# ---- Lugares ----
LU=[('Club (predeterminado)','Stade del club, Niort'),('Gimnasio municipal','Rue du Stade 4, Niort'),('Stade du Centre · Niort FC','Rue Ejemplo 12, 79000 Niort'),('Complexe sportif · Rochelais','Avenue Ejemplo 20, La Rochelle')]
lug=card(lkl(*[lk('map-pin',a,b) for a,b in LU]),style='padding:8px 20px')
S('di-lugares','directiva','Lugares','club','Lugares',[lug,bt('Nuevo lugar','plus',k='f w')],back='Club',sub='4 guardados',layout='g2',
  dmain=[lug],dside=[card(ch('Stade del club'),mapa(),mapas_btns()),bt('Nuevo lugar','plus',k='f w')])

# ---- Nuevo lugar ----
form_l=card(campo('Recinto','Stade du Centre'),campo('Club local','Niort FC'),campo('Dirección','Rue Ejemplo 12, 79000 Niort',ic='map-pin'),campo('Enlace','o pega un enlace de Google Maps o Waze',ph=True,ic='share-network'))
mapa_l=card(mini('Ubicación'),mapa(),'<p class="ro" style="margin-top:12px">{{i:map-pin:bold}}Arrastra el pin para ajustar.</p>')
S('di-lugar-nuevo','directiva','Nuevo lugar','club','Nuevo lugar',[form_l,mapa_l,bt('Usar este lugar',k='s w')],back='Lugares',sub='Recinto de partidos',layout='g2',
  dmain=[form_l,bt('Usar este lugar',k='s w')],dside=[mapa_l])

# ---- Temporada ----
temp=card(mini('Temporada actual'),'<div class="num-g">2026 – 2027</div><p class="meta" style="margin-top:8px">{{i:calendar-blank:bold}}1 sept. 2026 – 31 ago. 2027</p>')
cerrar=card(aviso_box('calendar-blank','Cerrar la temporada','Se archivan los equipos y se crea la siguiente. Los jugadores quedan para reorganizar.'),bt('Cerrar y crear la siguiente',k='f w',style='margin-top:14px'))
S('di-temporada','directiva','Temporada','club','Temporada',[temp,cerrar],back='Club',sub='Club Aigles',layout='g2',dmain=[temp],dside=[cerrar])

# ---- Ajustes ----
ajustes=[card(mini('Deporte'),pills(['Fútbol','Vóley','Básquet'],0),mini('Idioma por defecto'),pills(['Français','Español'],0),select('Zona horaria','Europe/Paris')),
  card(tog('Aparecer en la lista pública de clubes','Solo se muestran cifras',True),tog('Exigir instalar la app','Para recibir avisos en el celular',True)),
  card(mini('Logo'),'<div class="logo-fila">%s<span class="meta" style="margin:0">Club Aigles</span>%s</div>'%(escudo('CA'),bt('Cambiar','camera')),),
  bt('Guardar',k='s w')]
S('di-ajustes','directiva','Ajustes del club','club','Ajustes del club',ajustes,back='Club',sub='Deporte, idioma, zona horaria',layout='g2',
  dmain=ajustes[:1]+ajustes[1:2],dside=[ajustes[2],ajustes[3]])

# ---- Directorio ----
eqp=['U13','U12','U11A','U11B','U11C','U10']
dir_rows=[('Lucas','Daniela Pérez'),('Tomás','Marta Garcia'),('Mateo','Paul Fournier'),('Gabriel','Inés Garcia'),('Louis','Karim Dubois · Pierre Dubois'),('Raphaël','Marta Roux'),('Adam','Luis Girard'),('Arthur','Sofía Bernard · Nadia Bernard'),('Ethan','Julien Lefèvre'),('Paul','Ana Morel')]
dlist=card(campo('Buscar','Jugador o familia',ph=True,ic='magnifying-glass'),''.join(pers(ini(a),a,b,'<span class="chev">{{i:arrow-right:bold}}</span>') for a,b in dir_rows))
ficha=card(ch('Daniela Pérez','<span class="est">Lucas · U12</span>'),'<p class="meta">{{i:users-three:bold}}Madre</p><p class="meta" style="margin-top:6px">{{i:chat-circle-dots:bold}}06 00 00 00 01</p><p class="meta" style="margin-top:6px">{{i:paper-plane-tilt:bold}}daniela@ejemplo.fr</p>',acciones(bt('Llamar'),bt('WhatsApp'),bt('Correo')))
S('di-directorio','directiva','Directorio','directorio','Directorio',[pills(eqp,1,'grupo-pills'),dlist],eyebrow='Hola, Sylvie',layout='g2',
  dmain=[pills(eqp,1),dlist],dside=[ficha,aviso_box('lock','Solo contactos','El directorio no muestra asistencia ni temas deportivos.')])

# ---- Más ----
mas=card(lkl(lk('user-circle','Mi perfil','Datos, idioma, avisos'),lk('house','Mis clubes','Club Aigles · 2 clubes'),lk('chat-circle-dots','Ayuda','Instalar la app'),lk('x','Cerrar sesión','',right='')),style='padding:8px 20px')
S('di-mas','directiva','Más','mas','Más',[mas],eyebrow='Hola, Sylvie',layout='uno')
