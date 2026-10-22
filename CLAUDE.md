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
- **Dirección visual:** estilo tablero (referencia Panze), teal + limón, Bricolage Grotesque 800 en títulos, Geist en texto y **Geist Mono para fechas y detalles**. Logo: la S limón sobre teal que mandó el usuario (trazada en `docs/diseno/agenda.html`). Reglas del usuario: nada inclinado, grano muy suave y solo en la cabecera, no juntar íconos con insignias amarillas (el amarillo va solo en el logo y en un resaltador por tarjeta), títulos y botones chicos (píldoras con poco padding), respetar los espacios. **Una pantalla a la vez, sin HTML de explicación: ahora solo `docs/diseno/agenda.html` (Agenda móvil), autocontenido.** La skill `hallmark` aplica.
- **Prototipo interactivo** (en español, sin textos explicativos): `docs/wireframes/prototipo.html`. Se abre en el navegador; el panel lateral cambia de rol, estado del partido y deporte. Capturas en `docs/wireframes/png/`.
