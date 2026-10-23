from lib import *

CH='<span class="chev">{{i:arrow-right:bold}}</span>'
def clubrow(esc,nombre,sub,k='',right=CH):
    return '<div class="clubrow">%s<div><b>%s</b><small>%s</small></div>%s</div>'%(escudo(esc,k),nombre,sub,right)
def cta(t,ic=None): return bt(t,ic,k='s w')
def sec(t,ic=None): return bt(t,ic,k='w')
def btns(*b): return '<div class="ac-btns">%s</div>'%''.join(b)
def lead(t): return '<p class="lead">%s</p>'%t

# 1 · lista pública de clubes
A('ac-landing','Lista de clubes','Clubes en Sportiva',[
  stats(('4','clubes'),('757','familias'),('49','equipos')),
  '<div style="height:14px"></div>',
  card(clubrow('CA','Club Aigles','Niort · Fútbol · 14 equipos · 212 familias')
      +clubrow('CP','Club Pampa','Poitiers · Vóley · 9 equipos · 148 familias','b')
      +clubrow('SR','Sporting Rochelais','La Rochelle · Fútbol · 18 equipos · 301 familias','g')
      +clubrow('AB','AS Bocage','Bressuire · Fútbol · 8 equipos · 96 familias','g')),
  btns(cta('Entrar','arrow-right'))],wide=True)

# 2 · club público
A('ac-clubpublico','Club público','Club Aigles',[
  '<div class="foto-ph h">{{i:images:bold}}</div>',
  meta('map-pin','Niort · Fútbol'),
  '<div style="height:14px"></div>',
  '<div class="stats dos"><div class="stat"><b>14</b><span>equipos</span></div><div class="stat"><b>212</b><span>familias</span></div></div>',
  card(mini('Categorías'),tags(*[chipm(c) for c in ['U8','U10','U12','U14','U16']]),hr(),mini('Contacto'),meta('paper-plane-tilt','contacto@aigles.fr'),meta('map-pin','Niort','margin-top:6px'),style='margin-top:14px'),
  btns(cta('Pedir invitación'),sec('Ya tengo invitación'))],back=True)

# 3 · correo de invitación
A('ac-invitacion','Invitación (correo)','Correo',[
  card('<div class="mail-l"><b>De</b><span>Club Aigles</span></div><div class="mail-l"><b>Para</b><span>daniela@ejemplo.fr</span></div><hr class="sep-h">',
       '<div class="mail-h">Daniela, el Club Aigles te invita a Sportiva</div>',
       '<p class="mail-p">Ahí verás la agenda, los avisos y los partidos de Lucas y Emma, y avisarás si van o no.</p>',
       bt('Aceptar invitación',k='s'),
       '<p class="centro" style="text-align:left">Si no esperabas este correo, ignóralo.</p>')])

# 4 · instalar (iPhone / Android)
def paso(n,t): return '<div class="instr"><span class="paso-n">%d</span><div><b>%s</b><div class="cap-ph">captura iPhone</div></div></div>'%(n,t)
A('ac-instalar1','Instalar la app','Instala Sportiva',[
  lead('Para recibir los avisos en tu celular.'),
  pills(['iPhone','Android'],0),
  card(paso(1,'Toca el botón Compartir')+paso(2,'Elige «Agregar a pantalla de inicio»')+paso(3,'Toca Agregar')),
  btns(cta('Ya la instalé'),sec('Continuar sin la app'))])

# 5 · abrir desde el inicio
def app(ic,t,sp=False):
    return '<div><i%s>%s</i><span%s>%s</span></div>'%(' class="sp"' if sp else '','{{LOGO}}' if sp else '{{i:%s:bold}}'%ic,' class="sp-l"' if sp else '',t)
A('ac-instalar2','Abrir desde inicio','Abre Sportiva desde tu pantalla de inicio',[
  card('<div class="apps">%s</div>'%''.join([app('paper-plane-tilt','Correo'),app('map-pin','Mapas'),app('images','Fotos'),app('clock','Reloj'),app('','Sportiva',True),app('push-pin','Notas'),app('heart','Música'),app('gear','Ajustes')]),
       aviso_box('shield-check','Entra desde el ícono, no desde el navegador','En iPhone la app instalada tiene su propia sesión: la primera vez vuelve a entrar con tu correo.')),
  btns(cta('Abrir la app'))])

# 6 · correo
A('ac-login1','Entrar con correo','Entra con tu correo',[
  lead('Te enviamos un código. No necesitas contraseña.'),
  campo('Correo','daniela@ejemplo.fr',ic='paper-plane-tilt'),
  btns(cta('Enviarme un código'))])

# 7 · código
A('ac-login2','Código','Revisa tu correo',[
  lead('Enviamos un código a <b>da•••@ejemplo.fr</b>'),
  '<div class="cod" role="img" aria-label="Código de 6 dígitos"><span class="v">4</span><span class="v">8</span><span class="v">1</span><span></span><span></span><span></span></div>',
  btns(cta('Entrar'),sec('Reenviar código (0:45)'),sec('Usar otro correo'))],back=True)

# 8 · elegir club
A('ac-clubes','Elegir club','¿A qué club entras?',[
  lead('Tu correo está en más de un club.'),
  card(clubrow('CA','Club Aigles','Niort · Apoderada de Lucas y Emma')+clubrow('CP','Club Pampa','Poitiers · Apoderada de Camila (U9)','b'))],back=True)

# 9-12 · onboarding
A('ac-ob1','Bienvenida','Bienvenida',[
  pasos(1,4),
  lead('El Club Aigles ya cargó a tus hijos. Revisa tus datos.'),
  campo('Nombre','Daniela Pérez'),campo('Teléfono','06 00 00 00 01'),
  mini('Idioma'),pills(['Français','Español'],0),
  btns(cta('Continuar'))])

def hijo(n,eq,edad): return pers(ini(n),n,'%s · %s años'%(eq,edad),est('check','Confirmado'))
A('ac-ob2','Tus hijos','Tus hijos',[
  pasos(2,4),
  lead('Confirma quiénes son tus hijos en el club.'),
  card(hijo('Lucas','U12',12)+hijo('Emma','U10',10)),
  '<div style="height:12px"></div>',bt('Agregar otro hijo','plus',k='w'),
  btns(cta('Continuar'))],back=True)

A('ac-ob3','Datos con otras familias','Tus datos con otras familias',[
  pasos(3,4),
  lead('Opcional. Solo lo verán las familias de tu equipo.'),
  card(tog('Mi teléfono','',False)+tog('Mi correo','',False)+tog('Aparecer en el directorio','',False)),
  btns(cta('Continuar'))],back=True)

A('ac-ob4','Activar avisos','Activa los avisos',[
  pasos(4,4),
  card(tog('Cambios de hora de regreso','',True)+tog('Recordatorio de tu turno de traslado','48 h y 3 h antes',True)+tog('Respuestas del entrenador','',True)),
  btns(cta('Activar avisos','bell'),sec('Entrar a la agenda'))],back=True)
