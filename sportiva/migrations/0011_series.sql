-- Sportiva 0.10 · planificaciones repetidas (p. ej. entrenamientos cada martes y jueves por X meses)
-- La plantilla (JSON) guarda los datos y las horas; cada fecha puede tener su evento o estar omitida con un motivo.
CREATE TABLE IF NOT EXISTS sportiva_series (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  equipo_id VARCHAR(36) NOT NULL,
  plantilla TEXT NOT NULL,
  dias VARCHAR(20) NOT NULL,
  creado_por VARCHAR(36),
  created_at VARCHAR(19) NOT NULL,
  updated_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_serie_fechas (
  id VARCHAR(36) PRIMARY KEY,
  serie_id VARCHAR(36) NOT NULL,
  fecha VARCHAR(10) NOT NULL,
  evento_id VARCHAR(36),
  omitida SMALLINT NOT NULL DEFAULT 0,
  motivo VARCHAR(160),
  UNIQUE (serie_id, fecha)
);
