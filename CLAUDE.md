# Contexto para Claude

## Quién y cómo
- El usuario es chileno, frontend con 15 años de experiencia, algo de backend y mucho diseño. Trabaja como freelancer.
- **Escribe siempre en español de Chile con «tú».** Nunca voseo («querés», «pensá»).
- **Diseño:** no inventes estilos; si un cambio altera lo que se ve, muestra antes una maqueta y pregunta.
- **Instalaciones:** las hace él a mano en Hostinger. Entrégale zips y pasos claros: dónde va cada archivo y qué apretar en el admin.
- **Después de cada cambio:** zip (si aplica), capturas, commit y push. Envía los archivos con SendUserFile.
- **Secretos:** nunca al repo ni al chat. Las claves se ingresan en el admin (cifradas con AES-256-GCM).
- **No abras PR** salvo que lo pida.

## TypeDock (léelo antes de proponer nada)
- Todo corre sobre **TypeDock**, un CMS PHP para hosting compartido (Hostinger). **Lee `docs/TYPEDOCK.md`**: estructura del servidor, forma de un plugin, API de `PluginContext`, convenciones, trampas conocidas y cómo instalar.
- No preguntes de nuevo cómo viene TypeDock: está documentado ahí.
- Referencia viva de un plugin grande hecho por nosotros: repo `finiquito/portalclientes` (carpeta `portal/`, núcleo simulado en `dev/`, zips con `scripts/zip.sh`).

## Proyecto: Sportiva
Plugin de TypeDock para coordinar **clubes / equipos ↔ entrenadores / jugadores / apoderados**. Inspiración: Cantera, Spond, TeamSnap.
- **Sí:** comunicación, citaciones, calendario con confirmación de asistencia (voto público), lugares con mapa (Google Maps y Waze), compartir por WhatsApp, consultas de los apoderados, «hoja de ruta» personal, eventos especiales con precio informativo, multi-idioma y zona horaria, PWA con push.
- Pruebas en `sportiva.richgt.com`. **Plataforma multi-club**: una instalación con muchos clubes, datos separados por `club_id` y roles por club. Cada club se maneja en el front (`/app`); `/admin/sportiva` es solo para el admin técnico.
- Idiomas: español y francés, ampliables con un archivo por idioma (estilo WordPress).
- El hijo es de solo lectura más un canal de niños moderado. Las consultas son privadas al entrenador; lo público va al Tablero del equipo (reemplaza el grupo de WhatsApp).
- Fotos y videos permitidos, con límites, subida por trozos, cuotas y caducidad, para no colapsar Hostinger.
- **No:** cobros, pagos ni documentos. Nunca.
- Plan completo y decisiones abiertas: `docs/PLAN.md`.
- Wireframes grises v1 (5 pantallas + mapa de navegación): `docs/wireframes/index.html`.
- **Arquitectura de menús** (árbol por rol, reglas): `docs/wireframes/arquitectura.html`. Respétala al agregar pantallas: Más es solo cuenta; lo del equipo vive en el equipo.
- **Dirección visual:** estilo tablero (referencia Panze), teal + limón, Bricolage Grotesque 800 en títulos, Geist en texto y **Geist Mono para fechas y detalles**. Logo: la S limón sobre teal que mandó el usuario (trazada en `docs/diseno/agenda.html`). Reglas del usuario: nada inclinado, grano muy suave y solo en la cabecera, no juntar íconos con insignias amarillas (el amarillo va solo en el logo y en un resaltador por tarjeta), títulos y botones chicos (píldoras con poco padding), respetar los espacios. **Pantallas de diseño: `docs/diseno/pantallas.html` (pestañas Móvil / Escritorio; una fila por rol: Acceso, Apoderado, Entrenador y encargado, Directiva, Jugador; 73 pantallas, cada una autocontenida en un iframe `srcdoc`, escritorio con ancho máximo 1140 px). Se genera con `python3 docs/diseno/src/build.py` (pantallas en `src/screens_*.py`, piezas en `src/lib.py`, CSS en `src/css/`; `python3 build.py --out DIR id…` deja páginas sueltas para probar y `node shots.js` / `audit.js` / `contraste.js` revisan desbordes, objetivos táctiles y contraste). No editar pantallas.html a mano. Sin HTML de explicación. El usuario ajusta a mano: respeta sus valores (tarjetas juntas, botones de voto de 38 px, chip Partido y tile del auto en limón, píldora activa en el color del club, el resaltado de fila nunca debe pasar sobre las líneas punteadas, íconos propios en `docs/diseno/iconos/propios/`).** La skill `hallmark` aplica.
- **Prototipo interactivo** (en español, sin textos explicativos): `docs/wireframes/prototipo.html`. Se abre en el navegador; el panel lateral cambia de rol, estado del partido y deporte. Capturas en `docs/wireframes/png/`.

## Código del plugin (desde la v0.1)
- `sportiva/`: plugin TypeDock (PHP 8.2+, Latte 3.1, Flight). `src/` (namespace `TypeDock\Plugin\Sportiva`), `migrations/` (SQL idempotente, sin índices ni ALTER: MariaDB y SQLite), `templates/app` (front `/app`) y `templates/admin`, `lang/fr.json` + `es.json`, `assets/sportiva.css` (generado: **no editar a mano**; fuente en `assets-src/`, `bash scripts/css.sh`).
- `dev/`: núcleo simulado (`composer install` una vez), `seed.php` (club FCJ Orée de l’Autize de ejemplo), `serve.sh`. `tests/run.php`: pruebas (`php tests/run.php`). `scripts/zip.sh` → `dist/sportiva-plugin-vX.zip` (no arma el zip si las pruebas fallan; copiarlo a `entregas/`).
- Versión en tres lugares: `plugin.json`, `SportivaPlugin::getVersion()` y «Cambios» del `README.md`.
- Cada pantalla nueva: textos en **fr y es** (hay una prueba que falla si falta una clave), CSRF en todo POST, y comprobar permisos por rol y por club (hay pruebas de aislamiento entre clubes). El diseño de referencia es `docs/diseno/pantallas.html`.
- Estado: 0.2 (base multi-club, entrada por código, agenda y asistencia). Siguiente: turnos de traslado, feed y consultas, notificaciones, fotos, chat de niños, panel de la directiva. Sin probar aún en MariaDB (no hay Docker en la sesión web).

