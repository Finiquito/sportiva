-- Sportiva 0.3.1 · foto de cada jugador (el archivo vive fuera del web-root; aquí solo la versión para el caché)
CREATE TABLE IF NOT EXISTS sportiva_fotos (
  jugador_id VARCHAR(36) PRIMARY KEY,
  version VARCHAR(19) NOT NULL
);
