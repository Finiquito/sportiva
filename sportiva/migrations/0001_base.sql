-- Sportiva 0.1 · base multi-club (idempotente: se corre en cada carga)
CREATE TABLE IF NOT EXISTS sportiva_clubes (
  id VARCHAR(36) PRIMARY KEY,
  nombre VARCHAR(160) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  ciudad VARCHAR(120),
  deporte VARCHAR(16) NOT NULL DEFAULT 'futbol',
  idioma VARCHAR(5) NOT NULL DEFAULT 'fr',
  zona VARCHAR(64) NOT NULL DEFAULT 'Europe/Paris',
  telefono VARCHAR(40),
  direccion VARCHAR(255),
  descripcion TEXT,
  lat VARCHAR(24),
  lon VARCHAR(24),
  publico SMALLINT NOT NULL DEFAULT 1,
  exigir_app SMALLINT NOT NULL DEFAULT 1,
  created_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_equipos (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  temporada VARCHAR(16),
  archivado SMALLINT NOT NULL DEFAULT 0,
  created_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_personas (
  id VARCHAR(36) PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  nombre VARCHAR(160) NOT NULL,
  telefono VARCHAR(40),
  idioma VARCHAR(5),
  created_at VARCHAR(19) NOT NULL
);

-- Una persona puede tener un rol distinto en cada club (y varios en el mismo).
CREATE TABLE IF NOT EXISTS sportiva_membresias (
  id VARCHAR(36) PRIMARY KEY,
  persona_id VARCHAR(36) NOT NULL,
  club_id VARCHAR(36) NOT NULL,
  rol VARCHAR(16) NOT NULL,
  estado VARCHAR(16) NOT NULL DEFAULT 'activo',
  created_at VARCHAR(19) NOT NULL,
  UNIQUE (persona_id, club_id, rol)
);

CREATE TABLE IF NOT EXISTS sportiva_jugadores (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  equipo_id VARCHAR(36) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  nacimiento VARCHAR(10),
  persona_id VARCHAR(36),
  created_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_apoderados_jugadores (
  id VARCHAR(36) PRIMARY KEY,
  persona_id VARCHAR(36) NOT NULL,
  jugador_id VARCHAR(36) NOT NULL,
  titular SMALLINT NOT NULL DEFAULT 1,
  UNIQUE (persona_id, jugador_id)
);

CREATE TABLE IF NOT EXISTS sportiva_equipo_staff (
  id VARCHAR(36) PRIMARY KEY,
  equipo_id VARCHAR(36) NOT NULL,
  persona_id VARCHAR(36) NOT NULL,
  rol VARCHAR(16) NOT NULL DEFAULT 'entrenador',
  UNIQUE (equipo_id, persona_id)
);

CREATE TABLE IF NOT EXISTS sportiva_codigos (
  id VARCHAR(36) PRIMARY KEY,
  persona_id VARCHAR(36) NOT NULL,
  codigo VARCHAR(8) NOT NULL,
  expira_en VARCHAR(19) NOT NULL,
  usado_en VARCHAR(19),
  created_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_intentos (
  id VARCHAR(36) PRIMARY KEY,
  persona_id VARCHAR(36) NOT NULL,
  created_at VARCHAR(19) NOT NULL
);

-- Ajustes: club_id vacío = globales (SMTP, etc.). Los secretos se guardan cifrados (AES-256-GCM).
CREATE TABLE IF NOT EXISTS sportiva_ajustes (
  club_id VARCHAR(36) NOT NULL DEFAULT '',
  clave VARCHAR(64) NOT NULL,
  valor TEXT,
  PRIMARY KEY (club_id, clave)
);
