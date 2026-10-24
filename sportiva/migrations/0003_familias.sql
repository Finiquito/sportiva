-- Sportiva 0.3 · familias: solicitudes que hace el titular y que el entrenador aprueba
CREATE TABLE IF NOT EXISTS sportiva_solicitudes (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  tipo VARCHAR(8) NOT NULL,
  solicitante_id VARCHAR(36) NOT NULL,
  equipo_id VARCHAR(36),
  nombre VARCHAR(160) NOT NULL,
  email VARCHAR(190),
  telefono VARCHAR(40),
  nacimiento VARCHAR(10),
  estado VARCHAR(10) NOT NULL DEFAULT 'pendiente',
  resuelto_por VARCHAR(36),
  created_at VARCHAR(19) NOT NULL,
  resuelto_en VARCHAR(19)
);
