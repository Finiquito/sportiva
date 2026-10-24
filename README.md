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
