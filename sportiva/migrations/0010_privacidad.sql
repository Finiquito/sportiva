-- Sportiva 0.9 · qué datos de contacto comparte cada persona con las familias de su equipo (todo apagado por defecto)
CREATE TABLE IF NOT EXISTS sportiva_privacidad (
  persona_id VARCHAR(36) PRIMARY KEY,
  telefono SMALLINT NOT NULL DEFAULT 0,
  correo SMALLINT NOT NULL DEFAULT 0,
  directorio SMALLINT NOT NULL DEFAULT 0,
  updated_at VARCHAR(19) NOT NULL
);
