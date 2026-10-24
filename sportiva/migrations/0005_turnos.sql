-- Sportiva 0.4 · turnos de traslado (sin cupos): qué familias llevan en cada evento
CREATE TABLE IF NOT EXISTS sportiva_turnos (
  id VARCHAR(36) PRIMARY KEY,
  evento_id VARCHAR(36) NOT NULL,
  familia_id VARCHAR(36) NOT NULL,
  estado VARCHAR(10) NOT NULL DEFAULT 'confirmado',
  creado_por VARCHAR(36),
  created_at VARCHAR(19) NOT NULL,
  UNIQUE (evento_id, familia_id)
);
