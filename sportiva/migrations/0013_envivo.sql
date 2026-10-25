-- Sportiva 0.17 · reporte en vivo del partido
-- Una sola versión vigente por partido (marcador, estado, hora de regreso); cada publicación queda en el historial.
CREATE TABLE IF NOT EXISTS sportiva_envivo (
  evento_id VARCHAR(36) PRIMARY KEY,
  estado VARCHAR(12) NOT NULL,
  periodo INT NOT NULL,
  nuestro INT NOT NULL,
  rival INT NOT NULL,
  regreso VARCHAR(19),
  regreso_por VARCHAR(36),
  regreso_at VARCHAR(19),
  confirmado INT NOT NULL,
  confirmado_por VARCHAR(36),
  updated_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_envivo_notas (
  id VARCHAR(36) PRIMARY KEY,
  evento_id VARCHAR(36) NOT NULL,
  persona_id VARCHAR(36) NOT NULL,
  tipo VARCHAR(12) NOT NULL,
  texto VARCHAR(255),
  nuestro INT,
  rival INT,
  regreso VARCHAR(19),
  created_at VARCHAR(19) NOT NULL
);

-- Quién marcó «Estoy en la cancha» (puede publicar novedades).
CREATE TABLE IF NOT EXISTS sportiva_envivo_presentes (
  id VARCHAR(36) PRIMARY KEY,
  evento_id VARCHAR(36) NOT NULL,
  persona_id VARCHAR(36) NOT NULL,
  created_at VARCHAR(19) NOT NULL
);

-- A quién ya se le pidió «¿Puedes actualizar el marcador?» (una vez por partido).
CREATE TABLE IF NOT EXISTS sportiva_envivo_avisados (
  evento_id VARCHAR(36) NOT NULL,
  persona_id VARCHAR(36) NOT NULL,
  PRIMARY KEY (evento_id, persona_id)
);
