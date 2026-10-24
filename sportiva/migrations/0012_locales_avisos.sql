-- Sportiva 0.11 · lugares donde el club es local y datos de la cita dentro de cada aviso
CREATE TABLE IF NOT EXISTS sportiva_lugares_locales (
  lugar_id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL
);

-- Los lugares habituales de cada club pasan a ser «locales» la primera vez (no se repite si ya están).
INSERT INTO sportiva_lugares_locales (lugar_id, club_id)
SELECT l.id, l.club_id FROM sportiva_lugares l WHERE l.predeterminado = 1 AND l.id NOT IN (SELECT lugar_id FROM sportiva_lugares_locales);

-- Un aviso que viene de una citación: a qué evento y por qué (nueva, hora, lugar, cancelado, citado, descitado).
CREATE TABLE IF NOT EXISTS sportiva_avisos_evento (
  aviso_id VARCHAR(36) PRIMARY KEY,
  evento_id VARCHAR(36) NOT NULL,
  motivo VARCHAR(12) NOT NULL
);
