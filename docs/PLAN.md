# Sportiva: plan de producto y arquitectura

Nombre clave: **Sportiva**. Pruebas en `sportiva.richgt.com`. Borrador vivo; nada está construido ni diseñado visualmente todavía.

## 0. Decisiones tomadas
- Un club por instalación. El club se maneja **en el front**, no en el admin de TypeDock.
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
- **Tablero:** visible para todo el equipo. Lo publican entrenador y directiva. Preguntas de diseño:
  - ¿Los apoderados **publican** o solo **comentan y reaccionan**? Si debe reemplazar de verdad a WhatsApp, deberían poder publicar. Propuesta: pueden publicar, el entrenador y la directiva moderan (ocultar y borrar), y cada equipo puede cerrar la publicación libre.
  - Publicaciones fijadas arriba (reglas del grupo, horarios).

## 5. Hijos y canal de niños
Cuenta de **solo lectura** más un canal propio. Como son menores, lo importante es la seguridad antes que las funciones:
- **Sin mensajes privados 1 a 1.** Solo canal grupal por equipo. Los apoderados pueden verlo y el entrenador modera.
- **Nombre y foto de perfil acotados** (nombre de pila, sin datos de contacto).
- **Sin enlaces externos** y sin números de teléfono en los mensajes.
- **Botón «Reportar»** en cada mensaje, y el entrenador puede ocultar o suspender a alguien.
- **Stickers y reacciones propios** (set deportivo en SVG, sin licencias ajenas) más emojis. **GIFs de buscadores externos (Giphy y similares), no los recomiendo**: traen contenido no filtrado, rastreo de terceros y dependencia de una API. Lo cambiaría por una **biblioteca de GIFs curada** por el club.
- **Tiempo real en hosting compartido:** no hay websockets. El chat consulta novedades cada 10 a 20 segundos mientras la pantalla está abierta. Basta para niños y no sobrecarga el servidor.
- **Fotos y videos de partidos:** suben al canal con moderación. Por definir: ¿se publican de inmediato y se reportan después, o el entrenador aprueba antes? Para menores recomiendo aprobación previa de lo que sube cada niño.
- **Chile (Ley 21.719):** imágenes de menores son datos personales sensibles. Habrá que pedir **autorización del apoderado** (casilla al inscribir) para que el niño use el canal y para publicar su imagen. Esto lo reviso contra el texto de la ley antes de lanzar.

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

## 10. Pendiente por decidir
- ¿Los apoderados publican en el Tablero o solo comentan?
- ¿Las fotos y videos de niños se aprueban antes o se reportan después?
- ¿Biblioteca de GIFs curada por el club, o solo stickers y reacciones?
- Edad mínima para el canal de niños (¿8 años en adelante?).
- Qué dice exactamente la Ley 21.719 sobre autorización de imagen y datos de menores.
- Maqueta del look antes de construir cualquier pantalla.
