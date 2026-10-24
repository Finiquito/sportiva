-- Sportiva 0.3.1 · avatar elegido para cada jugador (1 a 5; sin fila = el de siempre según su id)
CREATE TABLE IF NOT EXISTS sportiva_avatares (
  jugador_id VARCHAR(36) PRIMARY KEY,
  avatar SMALLINT NOT NULL
);
