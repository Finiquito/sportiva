-- Sportiva 0.17.2 · el marcador del en vivo por periodos (sets, cuartos, tiempos) según el deporte
-- Cada periodo cerrado guarda sus propios puntos (en vóley el set; en fútbol y básquet lo anotado en ese tiempo o cuarto).
CREATE TABLE IF NOT EXISTS sportiva_envivo_periodos (
  evento_id VARCHAR(36) NOT NULL,
  numero INT NOT NULL,
  nuestro INT NOT NULL,
  rival INT NOT NULL,
  PRIMARY KEY (evento_id, numero)
);

-- Control del aviso de marcador (para no avisar cada gol seguido).
CREATE TABLE IF NOT EXISTS sportiva_envivo_ctl (
  evento_id VARCHAR(36) PRIMARY KEY,
  aviso_marcador_at VARCHAR(19)
);
