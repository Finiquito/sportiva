# Sportiva: plan de producto y arquitectura

Nombre clave: **Sportiva**. Pruebas en `sportiva.richgt.com`. Borrador vivo; nada está construido ni diseñado visualmente todavía.

## 0. Decisiones tomadas
- **Una plataforma con muchos clubes** (reemplaza la decisión anterior de un club por instalación): una sola instalación en `sportiva.richgt.com`, con los datos de cada club separados. Cada club se maneja **en el front**, no en el admin de TypeDock. Ver sección 20.
- `/admin/sportiva/...` lo ve solo un **admin técnico**: ajustes, claves, almacenamiento y diagnóstico.
- Idiomas iniciales: **español y francés**, con posibilidad de agregar más (sección 3).
- El hijo/jugador tiene cuenta de **solo lectura** más un canal propio de niños (sección 5).
- Las consultas son **privadas al entrenador**. Lo público va al **Tablero del equipo**, que reemplaza el grupo de WhatsApp (sección 4).
- Se permiten **fotos y videos**, con límites y almacenamiento pensado para no colapsar Hostinger (sección 7).
- Sin cobros ni documentos.

## 1. Roles y estructura

```
Club (directiva / coordinación)
 └─ Equipos (U8 · U10 · U12 · U14 · U16)
     ├─ Entrenador
     ├─ Apoderado (puede tener varios hijos, en equipos distintos)
     └─ Jugador (hijo, ligado a su apoderado)
```

| Rol | Dónde | Puede |
|---|---|---|
| **Admin técnico** | `/admin/sportiva` (TypeDock) | Ajustes, claves, almacenamiento, idiomas, diagnóstico. No ve el día a día. |
| **Directiva / coordinación** | `/app`, módulo Club | Crear equipos, asignar entrenadores, invitar familias, publicar a todo el club. |
| **Entrenador** | `/app` | Su(s) equipo(s): eventos, asistencia, comunicados, tablero, moderar. |
| **Apoderado** | `/app` | Votar por sus hijos, consultar, ver su hoja de ruta, usar el tablero. |
| **Jugador (hijo)** | `/app`, vista simple | Ver actividades y usar el canal de niños. **No vota** ni consulta al entrenador. |

Una sola PWA en `/app` cuyo menú cambia según el rol. Acceso por código al correo (como Portal); el apoderado le entrega al hijo un enlace propio.

## 2. Módulos

| Módulo | Qué hace |
|---|---|
| **Eventos** | Entrenamiento, partido, stage, evento especial; fecha en la zona horaria del club; repetición semanal. |
| **Lugares** | Dirección, coordenadas, indicaciones. Botones a Google Maps, Waze y Apple Maps; compartir por WhatsApp; mapa con OpenStreetMap (gratis, sin clave). |
| **Asistencia con voto público** | *Voy / Duda / No voy*, tablero visible para todos, recordatorio automático antes del evento. |
| **Comunicados** | Del entrenador a todo el equipo o a jugadores específicos (citación a stage). Con fotos y registro de lectura. |
| **Consultas** | Privadas entre el apoderado y el entrenador, dentro de un comunicado o evento. |
| **Tablero del equipo** | Muro público del equipo: avisos, ropa perdida, coordinación entre familias, comentarios. Reemplaza el grupo de WhatsApp. |
| **Hoja de ruta** | Línea de tiempo personal: mis votos, mis consultas y respuestas, comunicados recibidos y eventos. Sale de cruzar datos, sin tabla propia. |
| **Canal de niños** | Chat y fotos/videos de partidos solo para jugadores (sección 5). |
| **Eventos especiales** | Precio solo informativo. Al inscribirse sale un correo al responsable (tesorero); sin cobro. |
| **Notificaciones** | PWA push, correo y botones de WhatsApp (sección 6). |

## 3. Idiomas (estilo WordPress)
- Todo texto de la interfaz pasa por `t('clave')`. Los textos viven en **un archivo por idioma** (`lang/es.json`, `lang/fr.json`).
- **Agregar un idioma = subir un archivo**: aparece solo en el selector. Si falta una clave, cae en español.
- Cada persona elige su idioma. La zona horaria la define el club; si la persona está en otra, se muestra la hora local junto a la del club.
- El contenido que escriben las personas (comunicados, tablero) **no se traduce solo**. Opción futura: botón «Traducir» con IA, usando la clave que ya manejamos en el admin.

## 4. Consultas y Tablero
- **Consulta:** solo la ven el apoderado y el entrenador. Aparece en la hoja de ruta del apoderado.
- **Tablero:** visible para todo el equipo. Lo publican **entrenador y directiva**. Los **apoderados solo comentan y reaccionan**, no publican. Publicaciones fijadas arriba (reglas, horarios).

## 5. Hijos y canal de niños
Cuenta de **solo lectura** más un canal propio. Como son menores, lo importante es la seguridad antes que las funciones:
- **Sin mensajes privados 1 a 1.** Solo canal grupal por equipo. Los apoderados pueden verlo y el entrenador modera.
- **Nombre y foto de perfil acotados** (nombre de pila, sin datos de contacto).
- **Sin enlaces externos** y sin números de teléfono en los mensajes.
- **«Reportar» escondido en el menú del mensaje**, no a la vista. El entrenador puede ocultar o suspender.
- **Stickers y reacciones propios** (set deportivo en SVG, sin licencias ajenas) más emojis. **GIFs de buscadores externos (Giphy y similares), no los recomiendo**: traen contenido no filtrado, rastreo de terceros y dependencia de una API. Lo cambiaría por una **biblioteca de GIFs curada** por el club.
- **Tiempo real en hosting compartido:** no hay websockets. El chat consulta novedades cada 10 a 20 segundos mientras la pantalla está abierta. Basta para niños y no sobrecarga el servidor.
- **Los niños no suben fotos ni videos.** Solo suben apoderados y entrenadores.
- **Edad:** el canal se habilita desde los **8 años, según fecha de nacimiento** (dato obligatorio del jugador). La mayoría de los niños de esa edad no tiene teléfono propio (desde los 11 es lo común), así que entran con el dispositivo del apoderado.
- **Francia (RGPD y CNIL):** el club y las familias están en Francia, así que rige el RGPD y no la ley chilena. Hay que pedir **autorización del apoderado** (casilla al inscribir) para el uso de la app por el niño y para publicar su imagen (*droit à l'image*). En Francia el consentimiento digital propio parte a los 15 años, por lo que todos nuestros niños pasan por el apoderado. **Por verificar con la fuente oficial antes de lanzar.**

## 6. Notificaciones: opciones y costo
Orden propuesto: **PWA push → correo → WhatsApp solo si hace falta**.
- **PWA push (gratis):** manifest, service worker y claves VAPID. En PHP, `minishlink/web-push`; TypeDock shared no trae Composer, así que el plugin lleva su `vendor/` dentro del zip. Android funciona bien. **iPhone solo funciona con la app instalada en la pantalla de inicio** (iOS 16.4+), y habrá que guiar a la gente. El service worker se sirve con una ruta PHP en la raíz (`Service-Worker-Allowed: /`), porque el núcleo no sirve `.js` desde `/plugins/` (por verificar en el servidor).
- **Correo:** SMTP del dominio con cola y horario hábil, como Portal. Es el respaldo universal.
- **WhatsApp:**
  - API oficial de Meta con plantillas aprobadas («Tienes nueva información en Sportiva: enlace»), que cobra por mensaje. Los precios actuales los verifico antes de cotizar. Sería opcional por club.
  - Librerías no oficiales: no, por riesgo de bloqueo.
  - Botón «Compartir por WhatsApp» (`wa.me`): gratis y sin riesgo, pero lo manda la persona.
- **Cron de 15 minutos** necesario para recordatorios y colas.

## 7. Fotos y videos sin colapsar el servidor
El riesgo en hosting compartido es: **disco**, **límites de subida** (`upload_max_filesize`, `post_max_size`, `max_execution_time`), **CPU** al procesar y **procesos PHP** ocupados sirviendo archivos pesados. Hostinger normalmente no trae `ffmpeg` y no hay que depender de eso.

**Fotos**
- Se reducen **en el teléfono** antes de subir (a ~1600 px, WebP o JPEG calidad 80) con `canvas`. El servidor solo valida el tipo real, quita los datos EXIF (incluye ubicación) y crea la miniatura con GD.

**Videos**
- **Sin transcodificar en el servidor.** Límites duros: **máximo 60 s y 50 MB** (ajustable en admin técnico).
- **Subida por trozos** (chunks de ~2 MB) desde el navegador: evita los límites de subida y tiempos de espera, y reanuda si se corta la conexión.
- **Compresión en el teléfono** cuando el navegador lo permite (WebCodecs). Es la parte menos segura, sobre todo en iPhone, y la pruebo antes de comprometerme.
- **Compatibilidad:** los iPhone graban en HEVC, que no se reproduce en todos los navegadores de Android. Hay que probar con equipos reales; la solución es compresión en el cliente o, si no alcanza, almacenamiento externo con transcodificación.
- **Miniatura y duración** se sacan en el cliente, no en el servidor.

**Control del espacio**
- Cuota por equipo (ej. 2 GB) y contador visible para el admin técnico.
- **Los videos caducan** (ej. a los 90 días; se avisa antes). Una tarea del cron purga lo vencido.
- Aviso al 80 % y bloqueo de nuevas subidas al 100 %.

**Cómo servirlos**
- Privados: por PHP con soporte `Range` (para adelantar el video) y permiso por rol. Es lo correcto para menores, pero cada reproducción ocupa un proceso PHP. Con 20 familias es aceptable; lo medimos.
- Archivos fuera del web-root (`typedock/storage/sportiva_uploads/`).

**Plan B si crece:** almacenamiento externo opcional (Cloudflare R2, Bunny u otro compatible con S3), configurado por el admin técnico. El navegador sube directo con una URL firmada, así el video nunca pasa por Hostinger. Se deja el almacenamiento detrás de una interfaz desde el inicio para poder cambiarlo sin tocar el resto.

## 8. Datos (prefijo `sportiva_`, ids UUIDv7)
`clubes` (uno) · `equipos` · `personas` (rol, idioma, correo) · `jugadores` · `apoderados_jugadores` · `entrenadores_equipos` · `autorizaciones` (apoderado → hijo: canal, imagen) · `lugares` · `eventos` · `evento_equipos` · `asistencias` · `comunicados` · `comunicado_destinatarios` (leído) · `consultas` · `tablero_posts` · `tablero_comentarios` · `canal_mensajes` · `medios` (foto/video, tamaño, caduca, estado de moderación) · `inscripciones` · `push_suscripciones` · `notificaciones_cola` · `ajustes`.

## 9. Fases
1. **Base:** persona y roles, acceso por código, idiomas es/fr, zona horaria, estructura de club y equipos desde el front.
2. **Eventos y lugares:** calendario, mapa, Maps/Waze, compartir.
3. **Asistencia con voto público** y recordatorios.
4. **Comunicados, consultas y Tablero del equipo.**
5. **Hoja de ruta.**
6. **PWA y push**, luego correo programado.
7. **Medios:** fotos, luego videos con subida por trozos y cuotas.
8. **Canal de niños** (chat, stickers, moderación).
9. **Eventos especiales** con aviso al tesorero.
10. **WhatsApp oficial** opcional y almacenamiento externo si hace falta.

## 10. Lo que duele hoy (requisitos del día a día)
Salió de la conversación con familias reales; manda sobre el resto del plan.

1. **Traslado compartido (co-voiturage).** Hoy es un PDF pegado en el refrigerador. Pasa a ser un **calendario de turnos**: cada convocatoria tiene su bloque «Traslado» con quién lleva, cupos y hora de salida. El turno se **asigna a un usuario** (familia), que recibe recordatorio 48 h y 3 h antes y ve «Te toca llevar» en su Agenda.
2. **Reporte en vivo.** La mayoría de las familias no va a los partidos y no sabe cómo les fue ni a qué hora vuelven. Quien marca «Estoy en la cancha» puede publicar novedades (llegamos, comenzó, resultado, salimos de vuelta, fotos). Lo central es la **hora estimada de regreso**, que cambia casi siempre (10 a 20 min, o más si se acorta el torneo): si cambia, avisa a todas las familias convocadas.
3. **Dos direcciones por convocatoria, claras y obligatorias:**
   - **Punto de encuentro** (salida): por defecto el club, editable por evento, con hora.
   - **Lugar del partido**: nombre del recinto, **club local**, dirección y **coordenadas obligatorias**. El entrenador confirma el pin en el mapa; sin eso no puede publicar. Así se acaba el «el estadio de Niort… ¿cuál de los tres?».
4. **Cambio de confirmación avisa a los entrenadores.** Si un jugador pasa de «Voy» a «No voy» (o «Duda»), se avisa a **todos los entrenadores del equipo** (puede haber más de uno), con motivo opcional.
5. **Fotos del partido.** Cada convocatoria tiene su galería, que suben apoderados y entrenadores. Los niños la ven desde su vista de solo lectura, pero no suben.
6. **Acceso:** código de 6 dígitos al correo, sin contraseña.

## 11. Pendiente por decidir
- Qué pasa con el canal de niños: solo lectura (propuesto) y que vean las fotos del canal de los apoderados.
- Video propio en AWS: solo si el piloto lo pide (fase 4 del orden acordado: texto, fotos livianas, videos como enlace, video propio).
- ¿Quién puede publicar en vivo? Propuesta: quien marca «Estoy en la cancha» (apoderados y entrenadores).
- ¿La hora de regreso la puede cambiar cualquiera de los presentes o solo uno? Propuesta: cualquiera, con historial visible.
- Verificar qué exigen el RGPD y la CNIL sobre datos e imagen de menores (reemplaza la mención anterior a la ley chilena).
- Pantallas siguientes: asistencia del entrenador, Tablero, Mi ruta y calendario de turnos.

## 12. Ronda 3: cambios y decisiones
- **Idioma por defecto: francés**, español opcional. Fechas y horas en formato francés (`sam. 24 oct.`, 24 h), zona horaria del club (Europe/Paris). Todo el texto nace en francés y se traduce al español.
- **Convocar entre categorías y grupos.** Un partido de U13 puede convocar jugadores de U12 o U11, y un equipo puede tener grupos (U11A, U11B, U11C). La convocatoria es una **lista de jugadores**, no un equipo. Cualquier entrenador del club puede convocar a cualquier jugador, aunque no sea de su grupo.
  - Al crear, se ven los jugadores de todos los equipos, con búsqueda y «marcar todo el equipo».
  - Se avisa a los entrenadores del equipo base de cada jugador convocado, y la familia ve «convocado con U13».
  - Se avisa si el jugador ya tiene otro evento a la misma hora.
  - El entrenador de otro grupo ve el nombre del jugador y su voto, pero no los datos de contacto de la familia.
- **Equipos móviles.** El jugador pertenece a un equipo **por temporada y por fechas** (desde, hasta), con historial. Se puede reorganizar una categoría a mitad de temporada: mover jugadores entre U11A, U11B y U11C, con vista previa de los cambios y aviso a las familias y entrenadores con un mensaje editable («Desde el 15 de noviembre, Lucas pasa de U11B a U11A»). Los eventos futuros del equipo anterior piden decisión (mantener o quitar). Se hace desde el celular, en un toque por jugador, y en escritorio arrastrando.
- **Escritorio.** Es la misma app con diseño adaptable, no otra. En pantallas anchas la navegación pasa a una barra lateral y se ve el detalle al lado de la lista. Uso principal: entrenadores y directiva (asistencia en tabla, crear convocatoria, reorganizar equipos). Los wireframes de escritorio vienen en la siguiente ronda.

## 13. Roles, menús y nombres (decidido en la ronda 4)

### Roles
| Rol | Qué ve y hace |
|---|---|
| **Admin técnico** | Solo `/admin/sportiva` en TypeDock: claves, idiomas, deportes, almacenamiento, diagnóstico. |
| **Directiva (dirigentes)** | Parte administrativa: personas, invitaciones, estructura de equipos, lugares, temporada, avisos generales del club y directorio. **No ve temas deportivos** (votos, consultas, alineaciones, resultados). |
| **Encargado** | Es uno de los entrenadores, con permiso extra: el jefe deportivo. Ve todo lo deportivo, revisa y contesta las dudas de los entrenadores y revisa las reorganizaciones. |
| **Entrenador** | Sus equipos y las convocatorias que arma; puede convocar jugadores de cualquier equipo. Ve el directorio completo de contactos. |
| **Apoderado** | Sus hijos, votos, consultas, feed y, si la familia lo acepta, el directorio de familias de su equipo. |
| **Jugador (hijo)** | Solo lectura: agenda y fotos. |

### Pestañas inferiores (máximo 5)
| Rol | Pestañas |
|---|---|
| Apoderado | Agenda · Feed · Mi actividad · Más |
| Entrenador | Agenda · Feed · Consultas · Plantel · Más |
| Encargado | igual que entrenador; su bandeja de Consultas incluye las de los entrenadores |
| Directiva | Feed del club · Club · Directorio · Más (¿Agenda sin datos deportivos? Por confirmar) |
| Jugador | Agenda · Fotos · Más |

### Nombres
| Sección | Español | Francés |
|---|---|---|
| Calendario | Agenda | Agenda |
| Muro del equipo | **Feed** | **Le fil** |
| Línea de tiempo personal | Mi actividad | Mon activité |
| Bandeja del entrenador | Consultas | Questions |
| Jugadores | Plantel | Effectif |
| Gestión del club | Club | Club |
| Menú extra | Más | Plus |

Términos del francés: convocation · entraînement · match · point de rendez-vous · lieu du match · covoiturage · en direct · présent / peut-être / absent / sans réponse. **Por revisar con un hablante nativo.**

### El Feed
No es un muro social: es la **hoja de ruta del equipo**. De arriba hacia abajo van pasando los momentos del equipo: avisos de entrenador y directiva, convocatorias publicadas, resultados, fotos del partido, cambios de equipo. Los apoderados comentan pero no publican.

## 14. Directorio y privacidad entre familias
- **Directorio de entrenadores y dirigentes:** por equipo, todos los contactos (apoderados) de cada jugador. Se llama, se escribe por WhatsApp o por correo con un toque. Lo ve todo el cuerpo técnico, el encargado y la directiva (contactos, no lo deportivo). Cualquier entrenador ve el contacto de un jugador al que convoca.
- **Sin exportar:** no hay descarga de listas (CSV, PDF). Se consulta dentro de la app, para evitar fugas.
- **Directorio de familias de cada equipo**, aparte. Es **opcional por familia**: cada una elige qué comparte (teléfono, correo) con las otras familias de su equipo. Lo normal en un club es que todos se conozcan, pero el RGPD pide que compartir contactos entre apoderados sea voluntario. Se pregunta una sola vez al ingresar, con un lenguaje cercano.
- El aviso de privacidad cuenta qué ve el cuerpo técnico y por qué.

## 15. Eventos, consultas y reorganización (aclaraciones)
- **Los eventos futuros pertenecen al equipo.** Si un jugador cambia de equipo, sus eventos futuros se reemplazan automáticamente por los del equipo nuevo. Las convocatorias individuales a otros equipos, armadas a mano, se mantienen.
- **Consultas de un jugador convocado desde otro equipo:** las ven el entrenador que convoca y el de su equipo base. Se asume que entre entrenadores hay acuerdo.
- **Reorganizar equipos:** pueden hacerlo los entrenadores; el encargado revisa. La directiva ve la estructura, no los temas deportivos. Por confirmar con el usuario.

## 16. En vivo cuando nadie reporta
La tarjeta del partido tiene tres estados, y la app **no dice «en vivo» si nadie está reportando**:
1. **Con reporteros:** como en el wireframe 4.
2. **Sin reporteros:** se muestra «Sin novedades en vivo» y la hora de regreso tentativa del entrenador con su antigüedad («actualizada hace 3 h»). El botón «Estoy en la cancha» sigue visible para quien quiera reportar.
3. **Terminado:** el marcador final lo pone el entrenador o un reportero. Aparece en el Feed.
- **Reporteros:** cualquier apoderado que marque «Estoy en la cancha» puede publicar novedades. Además, cada equipo puede tener 2 o 3 **reporteros habituales** (padres activos) que actualizan el marcador. El turno de traslado puede traer «reporta en vivo» por defecto.
- Al empezar el partido se envía una notificación a los reporteros: «¿Puedes actualizar el marcador?».
- El marcador tiene **una sola versión vigente** con historial visible. El entrenador la puede confirmar («Resultado confirmado»).

## 17. Perfiles de deporte
Cada deporte se define en un archivo (`sports/futbol.json`, `volley.json`, `basquet.json`…), igual que los idiomas:
- **Tipo de marcador:** por periodos (fútbol: goles, dos tiempos; básquet: cuartos) o por sets (vóley: mejor de 3 o 5, con puntos por set).
- **Vocabulario** (gol, punto, set, tiempo, cuarto) como claves de traducción.
- **Botones rápidos** de novedades del en vivo (en fútbol «Entretiempo»; en vóley «Fin del set»).
- **Duración típica** del evento.
- El **deporte se define en el club** y cada equipo puede sobrescribirlo (por si el club tiene varios deportes).

## 18. Idiomas: cómo funcionan por dentro
- **Un archivo por idioma** (`fr.json` es la base; `es.json`, etc.). Claves ordenadas por sección (`agenda.voto.si`). Variables con `{nombre}`.
- **Los idiomas que agregues viven fuera del plugin** (`typedock/storage/sportiva_lang/`), para que actualizar el zip del plugin no los pise.
- **Pantalla «Idiomas» en el admin técnico:** lista de idiomas con el porcentaje traducido, descargar plantilla, subir archivo y editar textos en línea. Un idioma nuevo aparece solo en el selector.
- **Respaldo en cadena:** idioma de la persona → francés → la clave.
- **Plurales** con las reglas de cada idioma (en francés, 0 y 1 van en singular). Se usa `ext-intl` si el hosting la tiene; si no, un respaldo propio.
- **Fechas, horas y números** con `IntlDateFormatter` y `NumberFormatter`, en la zona horaria del club.
- **Notificaciones en el idioma del destinatario:** la cola guarda **la clave y sus datos**, no el texto ya armado, y se traduce al enviar. Así el mismo aviso sale en francés a una familia y en español a otra.
- **Plantillas Latte:** una función de traducción registrada en el motor (por confirmar contra el núcleo real de TypeDock).
- **Revisión automática:** un script que detecta claves sin traducir o sin uso, y las pruebas fallan si falta algo en francés o español.
- **El contenido que escriben las personas no se traduce solo.** Más adelante, un botón «Traducir» con IA.
- **Género en francés** («présent/présente»): los textos evitan el acuerdo y nombran al jugador («Lucas : présent»).

## 19. Pendiente por confirmar
- ¿La directiva ve el calendario de eventos (qué, cuándo, dónde), sin votos ni consultas ni resultados?
- Reorganización: ¿puede hacerla solo el entrenador del equipo, o cualquier entrenador? ¿El encargado la revisa antes o después?
- ¿Un solo deporte por club, o puede haber varios?
- Wireframes: se mantienen en español hasta que lo pidas; después, en francés.

## 20. Plataforma multi-club (ronda 5)
- **Una sola instalación aloja a todos los clubes.** Toda tabla lleva `club_id`; no hace falta un sitio por club.
- **Identidad global, pertenencia por club.** La persona entra con su correo (una sola vez) y elige club si tiene más de uno (por ejemplo un entrenador en dos clubes, o una familia con hijos en clubes distintos). El rol es **por club**.
- **Separación de datos:** todas las consultas pasan por una sola capa que fuerza el `club_id`, con pruebas automáticas de que un club no puede ver a otro. Con datos de menores, un cruce entre clubes sería el error más grave.
- **Configuración por club:** deporte, idioma por defecto, zona horaria, logo, remitente de correos y aparición en la lista pública.
- **Dirección pública:** `sportiva.richgt.com/c/<club>` (por ruta; los subdominios comodín no están garantizados en el hosting compartido).
- **Página pública de la plataforma:** lista de clubes y cifras (clubes, familias, equipos). Es **opt-in por club** y solo muestra cifras agregadas, nunca personas.
- **Admin técnico (`/admin/sportiva`):** crea clubes y nombra a su primera directiva. Es el único lugar fuera del front.
- **RGPD:** con varios clubes, cada club es el responsable del tratamiento de sus datos y la plataforma actúa como encargada. Cada club acepta un contrato de tratamiento de datos al darse de alta. **Por revisar con asesoría legal.**
- **Crecimiento:** en hosting compartido funciona bien para el piloto y varias decenas de clubes. Si crece, el modelo de datos no cambia: se mueve a un VPS y los medios a almacenamiento externo.
- **Respaldos:** por plataforma, no por club. Restaurar un solo club exige una herramienta de exportar e importar por club (pendiente).
