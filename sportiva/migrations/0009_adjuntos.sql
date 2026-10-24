-- Sportiva 0.8 · una foto opcional en mensajes de consulta, publicaciones del feed y comentarios
CREATE TABLE IF NOT EXISTS sportiva_adjuntos (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  contexto VARCHAR(12) NOT NULL,
  ref_id VARCHAR(36) NOT NULL,
  subido_por VARCHAR(36) NOT NULL,
  ancho INT,
  alto INT,
  bytes BIGINT NOT NULL DEFAULT 0,
  created_at VARCHAR(19) NOT NULL
);
