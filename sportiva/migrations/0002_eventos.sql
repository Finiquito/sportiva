-- Sportiva 0.2 · lugares, eventos y asistencia
CREATE TABLE IF NOT EXISTS sportiva_lugares (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  nombre VARCHAR(160) NOT NULL,
  direccion VARCHAR(255),
  lat VARCHAR(24),
  lon VARCHAR(24),
  club_local VARCHAR(160),
  predeterminado SMALLINT NOT NULL DEFAULT 0,
  created_at VARCHAR(19) NOT NULL
);

-- Fechas y horas en hora local del club ('Y-m-d H:i:s'). hora_partido = inicio del evento.
CREATE TABLE IF NOT EXISTS sportiva_eventos (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  equipo_id VARCHAR(36) NOT NULL,
  tipo VARCHAR(16) NOT NULL DEFAULT 'partido',
  titulo VARCHAR(200) NOT NULL,
  rival VARCHAR(120),
  inicio VARCHAR(19) NOT NULL,
  fin VARCHAR(19),
  hora_cancha VARCHAR(19),
  hora_encuentro VARCHAR(19),
  lugar_id VARCHAR(36),
  encuentro_lugar_id VARCHAR(36),
  encuentro_texto VARCHAR(255),
  nota TEXT,
  precio_info VARCHAR(40),
  estado VARCHAR(12) NOT NULL DEFAULT 'publicado',
  creado_por VARCHAR(36),
  created_at VARCHAR(19) NOT NULL,
  updated_at VARCHAR(19) NOT NULL
);

-- Convocados de un evento (el entrenador puede convocar jugadores de otras categorías).
CREATE TABLE IF NOT EXISTS sportiva_convocados (
  id VARCHAR(36) PRIMARY KEY,
  evento_id VARCHAR(36) NOT NULL,
  jugador_id VARCHAR(36) NOT NULL,
  UNIQUE (evento_id, jugador_id)
);

CREATE TABLE IF NOT EXISTS sportiva_asistencias (
  id VARCHAR(36) PRIMARY KEY,
  evento_id VARCHAR(36) NOT NULL,
  jugador_id VARCHAR(36) NOT NULL,
  respuesta VARCHAR(8) NOT NULL,
  por_persona_id VARCHAR(36),
  updated_at VARCHAR(19) NOT NULL,
  UNIQUE (evento_id, jugador_id)
);
