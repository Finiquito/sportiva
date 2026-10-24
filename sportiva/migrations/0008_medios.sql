-- Sportiva 0.7 · fotos y videos de los eventos (los archivos viven fuera del web-root)
CREATE TABLE IF NOT EXISTS sportiva_medios (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  equipo_id VARCHAR(36) NOT NULL,
  evento_id VARCHAR(36) NOT NULL,
  tipo VARCHAR(6) NOT NULL,
  mime VARCHAR(40) NOT NULL,
  bytes BIGINT NOT NULL DEFAULT 0,
  ancho INT,
  alto INT,
  duracion INT,
  tiene_poster SMALLINT NOT NULL DEFAULT 0,
  subido_por VARCHAR(36) NOT NULL,
  created_at VARCHAR(19) NOT NULL,
  expira_en VARCHAR(19),
  caducidad_avisada SMALLINT NOT NULL DEFAULT 0
);
