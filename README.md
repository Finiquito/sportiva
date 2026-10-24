# Sportiva

Plugin de **TypeDock** para coordinar clubes, equipos, entrenadores, jugadores y apoderados: agenda con convocatorias,
asistencia (Voy / Duda / No voy), horas de encuentro / en cancha / partido, lugares con mapa, y más (ver `docs/PLAN.md`).
Plataforma multi-club: una instalación con muchos clubes, datos separados por `club_id` y roles por club.

- Front de los clubes: `/app` · Admin técnico: `/admin/sportiva` (clubes, equipos, personas, correo SMTP).
- Diseño: `docs/diseno/pantallas.html` (73 pantallas); el CSS sale de `docs/diseno/src/css` y se copia a `sportiva/assets-src/`.
- Cómo trabajamos con TypeDock: `docs/TYPEDOCK.md`.

## Instalar (Hostinger)
1. `bash scripts/zip.sh` → `dist/sportiva-plugin-vX.Y.Z.zip` (compila el CSS y **no arma el zip si las pruebas fallan**).
2. Admin de TypeDock → **Plugins → subir zip**. Las migraciones corren solas en la primera visita (nunca borran datos).
3. Entra a `/admin/sportiva`: crea el club, sus equipos y las personas (entrenadores y apoderados con sus hijos).
4. **Correo:** `/admin/sportiva/ajustes` → SMTP propio (la clave se guarda cifrada con AES-256-GCM y no se vuelve a mostrar).
   Sin SMTP se usa el correo del núcleo.
5. Las familias entran en `/app/entrar` con su correo y un código de 6 dígitos.

## Desarrollo
```
cd dev && composer install      # una vez (Flight y Latte)
php dev/seed.php --reset        # club FCJ Orée de l’Autize con datos de ejemplo
bash dev/serve.sh               # http://127.0.0.1:8080  ·  entrar sin código: /dev/entrar?email=daniela@ejemplo.fr
php tests/run.php               # pruebas (SQLite en memoria)
```
Cuentas de ejemplo: `daniela@` (apoderada), `camila@` (encargada), `pedro@` (entrenador), `sylvie@` (directiva), `lucas@` (jugador), todas `@ejemplo.fr`.
Con MariaDB: `SPORTIVA_DB_DSN="mysql:host=127.0.0.1;dbname=sportiva_test;charset=utf8mb4" SPORTIVA_DB_USER=root SPORTIVA_DB_PASS=root php tests/run.php`.

## Cambios
- **0.11.0** **Avisos:** filtros Todos / Citaciones / Turnos / Otros con contador; la citación muestra su fecha en el ícono verde, el horario destacado y la publicación como «hace 2 h» (migración `0012`). **Agenda:** el próximo evento queda fijo arriba, solo se ven los próximos 10 días y máximo 2 entrenamientos («Ver más fechas» suma 10 días); tarjeta compacta (título, horas contenidas, lugar, voto, acciones al pie). **Citación según tipo:** entrenamiento sin rival, encuentro ni hora en cancha; partido sin hora de fin; repetir solo en entrenamientos; contador de citados (total y por categoría). **Lugares locales:** lista «Dónde entrenamos» en el Plantel; un entrenamiento en lugar local no muestra mapa, cómo llegar ni compartir (un partido en casa solo se puede compartir). **Guardado sin recargar:** Voy/Duda/No voy, Guardar y Ver fechas se envían en segundo plano, no saltan al inicio y avisan con un mensaje corto. Globito de avisos sobre el ícono de la app.
- **0.10.0** **Planificación repetida:** al crear una convocatoria se puede repetir por días de la semana y X meses, ver todas las fechas, saltar algunas con su motivo y publicar. Después se edita en *Ver planificación* (saltar, recuperar o extender); cada cambio manda un solo aviso a las familias. **Citados:** se elige a qué jugadores se cita (y se puede cambiar después, avisando a quienes entran o salen); las familias ven la lista de citados y la de quienes confirmaron. **Mapas y compartir:** la ficha del evento destaca fecha, horas y lugar; siempre hay Google Maps y Waze (por coordenadas o buscando por nombre y dirección), se pueden sacar las coordenadas pegando un enlace de Google Maps, y hay Compartir (WhatsApp o menú del teléfono) en la ficha y en la agenda. Migración `0011_series`.
- **0.9.0** **Panel de la directiva** (Club en el menú de la directiva): equipos y cuerpo técnico, personas con roles, invitaciones (individual, reenviar y lista pegada), lugares con mapa, temporada, ajustes del club y directorio de contactos (llamar, WhatsApp, correo; también para entrenadores y encargados, solo de sus equipos). **Más: perfil y privacidad**: Mi perfil (nombre y teléfono, idioma, avisos), Compartir mis datos (todo apagado por defecto) y Familias del equipo, Privacidad (quién ve qué, descargar mis datos, pedir que quiten fotos de mis hijos, eliminar mi cuenta) y Ayuda. En escritorio el menú lateral flota fuera del contenedor. Migración `0010_privacidad`.
- **0.8.1** La hoja de estilos y los íconos de la app se sirven por PHP desde `/app/estilos` y `/app/recurso/…` (sin depender de la copia pública de assets del núcleo, que puede quedar vieja al actualizar). En el pie de cada pantalla se ve la versión del plugin y la de la hoja de estilos cargada, y avisa en rojo si no coinciden.
- **0.8.0** Sin videos (decisión final): se quitaron del plugin; quedan solo fotos comprimidas. Foto opcional en mensajes de consulta, publicaciones del feed y comentarios (el navegador la reduce y el servidor la vuelve a comprimir, sin EXIF; solo la ve quien puede leer ese mensaje). La versión de la hoja de estilos (`?v=`) ahora es el hash del archivo, para que ningún navegador use una vieja.
- **0.7.1** Arregla que el navegador siguiera usando una hoja de estilos vieja (el `?v=` de la CSS ahora cambia solo con cada versión): campana bajo el chip, degradado arriba, bordes del panel y espacios en escritorio. App instalable a pantalla completa en iPhone (barra de estado translúcida con la cabecera verde por debajo) y tarjeta «Instala u330 en tu teléfono» en Más cuando se abre desde el navegador.
- **0.7.0** Fotos y videos de cada evento (los videos se quitaron en la 0.8.0): galería y visor; las familias de los convocados y los entrenadores suben fotos (se reducen a 1600 px, sin EXIF, con miniatura) y videos de hasta 60 s y 50 MB por trozos de 2 MB con reanudación; cuota por equipo, los videos caducan a los 90 días (aviso 7 días antes) y el cron los borra; se sirven solo a quien corresponde, con soporte de rangos; «Pedir que lo quiten» avisa al entrenador; aviso de fotos nuevas y novedades en el feed; admin «Fotos y videos» con límites y espacio usado.
- **0.6.0** Avisos: campana con contador, página de avisos y preferencias por categoría (agenda, feed, consultas, turnos, familia) con notificación push y correo; app instalable (manifest + service worker) y Web Push propio (VAPID + cifrado aes128gcm, sin librerías); recordatorio de asistencia 24 horas antes; página de admin «Avisos y cron» con el comando del cron.
- **0.5.1** Botón «Asignar» en cada partido ya creado (panel Turnos y página del evento): completa el turno con las familias que menos han llevado.
- **0.5.0** Feed del equipo (avisos del entrenador, encargado y directiva por equipo o para todo el club, fijados, convocatorias publicadas, comentarios de las familias) y consultas privadas entre la familia y el cuerpo técnico (Mi actividad / Consultas, con no leídas y aviso por correo sin el texto del mensaje).
- **0.4.0** Turnos de traslado sin cupos: el entrenador anota las familias que llevan en cada partido (o los reparte entre los próximos partidos, parejo), la familia ve «Te toca llevar» y puede pedir «Yo también llevo» para que el entrenador la acepte.
- **0.3.1** Foto de cada niño (la sube el entrenador o el apoderado titular; se recorta, se le quitan los datos EXIF y solo la ve gente del club). Al agregar un jugador se puede elegir una familia que ya existe (hermanos) sin volver a escribir al apoderado. Esquinas de la hoja arregladas en móvil y escritorio, barra inferior más baja y color de la barra de estado en iPhone.
- **0.3.0** Plantel y familias: el entrenador carga jugadores con su apoderado titular (recibe un correo para entrar); el titular ve «Mi familia» (Más) y pide agregar adultos e hijos, que el entrenador aprueba o rechaza (aviso por correo y contador en Plantel). Solo el titular o el entrenador editan la familia. Ficha del jugador, edición, quitar adultos. Arregla la flecha de «Volver».
- **0.2.4** Logos sobre verde actualizados; favicon que cambia solo según la pestaña (claro/oscuro) y ícono de iOS; arregla las esquinas redondeadas del panel en escritorio.
- **0.2.3** Arregla el error 500 de `/app` en el TypeDock real: usaba `renderView()`, que no existe en el núcleo (solo `view()`).
- **0.2.2** Diagnóstico: los errores de `/app` se anotan en `storage/sportiva_uploads/errores.log` y se ven en el admin (u330 → Diagnóstico).
- **0.2.1** Marca u330: paleta nueva (verde, hueso, amarillo, naranja, lila, dorado), logo del header (favicon sobre verde) y logo principal sobre verde, favicon, avisos en verde claro con ícono (check blanco sobre círculo verde), contador de la campana en naranja con número blanco, íconos de balón y cancha según el deporte del club, íconos nuevos (medalla, copa, podio, arco con red). El nombre visible pasa a «u330»; el slug, tablas y rutas siguen como `sportiva`.
- **0.2.0** Agenda y asistencia: convocatorias (partido, entrenamiento, evento especial, stage) con horas Encuentro / En cancha / Partido,
  otras categorías convocadas, lugares con mapa y enlaces a Google Maps y Waze; Voy / Duda / No voy por hijo; vista de entrenadores
  (resumen, crear, editar, cancelar) y del jugador (solo lectura).
- **0.1.0** Base: clubes, equipos, personas con rol por club, entrada con código por correo, español y francés (idiomas añadibles en
  `storage/sportiva_lang/`), Más (idioma, cambiar de club), admin técnico y SMTP cifrado.
