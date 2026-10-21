# Sportiva: plan de producto y arquitectura

Borrador para discutir. Nada de esto está construido ni diseñado visualmente todavía.

## 1. Estructura (la del diagrama)

```
Club (administrador y coordinador)
 └─ Categorías / Equipos (U8 · U10 · U12 · U14 · U16)
     ├─ Entrenador   → gestiona su equipo, actividades, asistencia
     ├─ Jugador      → ve actividades y contenido adecuado a su edad
     └─ Apoderado    → recibe avisos, autoriza actividades, confirma asistencia
```

- Un apoderado puede tener varios hijos en equipos distintos; un entrenador puede llevar más de un equipo.
- Tres entornos, como en Portal: **club** `/admin/sportiva/...` (administrador/coordinador), **entrenador** `/entrenador`, **familia** `/familia` (apoderado y jugador, con vista simplificada para menores). Una sola PWA, el menú cambia según el rol.
- Acceso sin contraseña por código al correo (como Portal). Para menores, el jugador entra con un enlace que le entrega el apoderado.

## 2. Módulos

| Módulo | Qué hace |
|---|---|
| **Eventos** | Entrenamiento, partido, stage, evento especial. Fecha y hora en la zona horaria del club, lugar, equipo(s), nota. Se repiten por semana. |
| **Asistencia con voto público** | Cada convocado vota *Voy / Duda / No voy*. Todos ven el tablero: quién confirmó, quién duda, quién no va y quién no responde. Cierre y recordatorio automático antes del evento. |
| **Lugares** | Nombre, dirección, coordenadas, indicaciones («entrada por el portón azul»). Botones: Google Maps, Waze, Apple Maps y compartir por WhatsApp. Mapa embebido con OpenStreetMap (gratis, sin clave). |
| **Comunicados** | El entrenador escribe un mensaje con audiencia: todo el equipo, varios equipos, o jugadores específicos (citación a stage). Admite fotos (ej. ropa perdida) y adjuntar un evento. Lista de quién lo leyó. |
| **Consultas** | Los apoderados preguntan dentro de cada comunicado o evento. Hilo visible para el entrenador (y opcionalmente para todos o solo privado). |
| **Hoja de ruta** | Línea de tiempo personal del apoderado: sus votos, sus consultas y respuestas, los comunicados que le llegaron y los eventos, ordenados por fecha. Responde «¿dónde hablé / dónde voté?». |
| **Eventos especiales** | Vacaciones, campeonatos. Precio solo como información. Al inscribirse se manda un correo al responsable (tesorero) con los datos; no hay cobro ni pago. |
| **Idiomas y zona horaria** | Cada usuario elige su idioma; el club define su zona horaria. Las horas se muestran en la zona del club, con la del usuario si difiere. |
| **Notificaciones** | Push de la PWA, correo y, si hace falta, WhatsApp (sección 4). |

## 3. Datos (tablas con prefijo `sportiva_`, ids UUIDv7)

`clubes` · `equipos` · `personas` (rol, idioma, correo, teléfono) · `jugadores` · `apoderados_jugadores` · `entrenadores_equipos` · `lugares` · `eventos` · `evento_equipos` · `asistencias` (evento, jugador, estado, quién votó, cuándo) · `comunicados` · `comunicado_destinatarios` (con «leído») · `consultas` (hilo por comunicado o evento) · `inscripciones` · `push_suscripciones` · `notificaciones_cola` · `ajustes`.

La hoja de ruta no necesita tabla: sale de una consulta sobre `asistencias`, `consultas` y `comunicado_destinatarios` de esa persona.

## 4. Notificaciones: opciones y costo

Orden de prioridad propuesto: **PWA push → correo → WhatsApp (solo si hace falta)**.

### 4.1 PWA con push (costo cero)
- Hay que servir `manifest.json`, un service worker y las suscripciones con claves VAPID. El envío necesita cifrado de mensajes (RFC 8291); en PHP lo resuelve la librería `minishlink/web-push`. TypeDock shared no trae Composer, así que **el plugin lleva su propio `vendor/` dentro del zip** (necesita `openssl` y `curl` en el PHP de Hostinger, que normalmente están).
- **Android/Chrome:** funciona bien, con ícono e insignia.
- **iPhone:** el push solo funciona si la persona **instala la app en la pantalla de inicio** (iOS 16.4 o superior). Hay que guiarla con un aviso de «Agregar a inicio». Es la parte más frágil.
- **Trampa de TypeDock:** el service worker debe servirse desde la raíz del dominio (`/sw.js`) para controlar toda la app, y la guía dice que el núcleo no sirve `.js` desde `/plugins/<slug>/assets/`. Hay que servirlo por una ruta PHP (`Flight::route('GET /sportiva-sw.js')`) con `Service-Worker-Allowed: /`. **Por verificar en el servidor real.**
- Sin cron, los recordatorios no salen a tiempo: el cron de 15 minutos del hPanel es necesario.

### 4.2 Correo (costo casi cero)
El SMTP del dominio, como Portal. Con cola y horario hábil por zona. Funciona como respaldo universal.

### 4.3 WhatsApp
- **API oficial (WhatsApp Cloud API de Meta):** es la única vía estable. Para escribirle a alguien que no te ha escrito, hay que usar **plantillas aprobadas** («Tienes nueva información en Sportiva: {{enlace}}») y Meta cobra **por mensaje** según la categoría (utilidad, marketing). Si la persona responde, se abre una ventana de 24 horas con mensajes libres. Requiere cuenta Meta Business, número dedicado y verificación. **Los precios por país y los cambios recientes los tengo que verificar antes de darte números.**
- **No recomiendo** librerías no oficiales (Baileys, whatsapp-web.js): son gratis, pero violan los términos y el número se bloquea, y mezclar eso con menores de edad es mal negocio.
- **Alternativa gratuita y sin riesgo:** botones **«Compartir por WhatsApp»** con `wa.me/?text=...`. El entrenador o el apoderado los aprieta y manda el mensaje desde su propio WhatsApp (por ejemplo al grupo existente del equipo). Cero costo, cero verificación; no es automático.
- **Otra alternativa:** un bot de Telegram (gratis, API simple, sin plantillas). Solo sirve si las familias lo usan.

### Recomendación
1. Partir con **PWA + correo + botones de compartir por WhatsApp**.
2. Agregar WhatsApp oficial **opcional por club** (cada club pone su propio token en el admin, cifrado), solo para el aviso de «tienes novedades», con costo a cargo del club.
3. Medir cuántos apoderados de iPhone instalan la PWA antes de decidir si vale la pena el paso 2.

## 5. Privacidad (menores de edad)
- Datos mínimos: nombre, categoría, contacto del apoderado. Sin documentos, sin cobros.
- Fotos solo en comunicados (ropa perdida, etc.); definir si se permiten fotos de personas.
- La ley chilena de protección de datos personales (Ley 21.719) trata de forma especial los datos de menores. **Hay que revisar qué exige y desde cuándo** antes de lanzar.
- Cada club solo ve sus datos; archivos fuera del web-root; CSRF y roles en todo.

## 6. Hosting
- Un plugin `sportiva/` en una instalación de TypeDock por dominio, igual que Portal. Una instalación puede tener varios clubes (multi-club) con datos separados por `club_id`.
- Zips con `scripts/zip.sh` (sube la versión en `plugin.json`, `getVersion()` y README). Núcleo simulado en `dev/` y pruebas con SQLite y MariaDB, como en portalclientes.

## 7. Fases sugeridas
1. **Base:** clubes, equipos, personas y roles, acceso por código, idioma y zona horaria.
2. **Eventos y lugares:** calendario, mapa, Google Maps/Waze, compartir por WhatsApp.
3. **Asistencia con voto público** y recordatorios (cron).
4. **Comunicados y consultas**, con audiencias y lectura.
5. **Hoja de ruta.**
6. **PWA y push**, luego correo programado.
7. **Eventos especiales** con inscripción y aviso al tesorero.
8. **WhatsApp oficial** opcional.

## 8. Decisiones abiertas
- Nombre definitivo (¿Sportiva?) y dominio.
- Idiomas iniciales (¿es-CL, inglés, portugués?).
- ¿Los jugadores menores tienen cuenta propia o todo pasa por el apoderado?
- ¿Las consultas son públicas para el equipo o privadas con el entrenador?
- ¿Un club o varios por instalación?
- ¿Fotos de personas en comunicados, sí o no?
- Maqueta del look antes de construir cualquier pantalla.
