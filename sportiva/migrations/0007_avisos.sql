-- Sportiva 0.6 · avisos (campana), preferencias y suscripciones de notificaciones push
CREATE TABLE IF NOT EXISTS sportiva_avisos (
  id VARCHAR(36) PRIMARY KEY,
  persona_id VARCHAR(36) NOT NULL,
  club_id VARCHAR(36) NOT NULL,
  categoria VARCHAR(12) NOT NULL,
  titulo VARCHAR(190) NOT NULL,
  cuerpo VARCHAR(255),
  url VARCHAR(255),
  created_at VARCHAR(19) NOT NULL,
  leido_en VARCHAR(19),
  push_pendiente SMALLINT NOT NULL DEFAULT 0,
  correo_pendiente SMALLINT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS sportiva_avisos_prefs (
  persona_id VARCHAR(36) NOT NULL,
  categoria VARCHAR(12) NOT NULL,
  push SMALLINT NOT NULL DEFAULT 1,
  correo SMALLINT NOT NULL DEFAULT 0,
  PRIMARY KEY (persona_id, categoria)
);

CREATE TABLE IF NOT EXISTS sportiva_push_subs (
  id VARCHAR(36) PRIMARY KEY,
  persona_id VARCHAR(36) NOT NULL,
  endpoint_hash VARCHAR(64) NOT NULL UNIQUE,
  endpoint TEXT NOT NULL,
  p256dh VARCHAR(190) NOT NULL,
  auth VARCHAR(64) NOT NULL,
  created_at VARCHAR(19) NOT NULL
);

-- Un recordatorio de asistencia por (evento, persona)
CREATE TABLE IF NOT EXISTS sportiva_recordatorios (
  evento_id VARCHAR(36) NOT NULL,
  persona_id VARCHAR(36) NOT NULL,
  created_at VARCHAR(19) NOT NULL,
  PRIMARY KEY (evento_id, persona_id)
);
