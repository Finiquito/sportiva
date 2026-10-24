-- Sportiva 0.5 · Feed (tablero del equipo) y consultas privadas
CREATE TABLE IF NOT EXISTS sportiva_posts (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  equipo_id VARCHAR(36),
  autor_id VARCHAR(36) NOT NULL,
  titulo VARCHAR(160),
  texto TEXT NOT NULL,
  fijado SMALLINT NOT NULL DEFAULT 0,
  created_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_comentarios (
  id VARCHAR(36) PRIMARY KEY,
  post_id VARCHAR(36) NOT NULL,
  persona_id VARCHAR(36) NOT NULL,
  texto TEXT NOT NULL,
  created_at VARCHAR(19) NOT NULL
);

-- Consulta privada: una familia con el cuerpo técnico, sobre un hijo (y opcionalmente un evento).
CREATE TABLE IF NOT EXISTS sportiva_consultas (
  id VARCHAR(36) PRIMARY KEY,
  club_id VARCHAR(36) NOT NULL,
  jugador_id VARCHAR(36) NOT NULL,
  evento_id VARCHAR(36),
  creada_por VARCHAR(36) NOT NULL,
  asunto VARCHAR(160) NOT NULL,
  ultimo_de VARCHAR(8) NOT NULL DEFAULT 'familia',
  nueva_staff SMALLINT NOT NULL DEFAULT 1,
  nueva_familia SMALLINT NOT NULL DEFAULT 0,
  created_at VARCHAR(19) NOT NULL,
  updated_at VARCHAR(19) NOT NULL
);

CREATE TABLE IF NOT EXISTS sportiva_mensajes (
  id VARCHAR(36) PRIMARY KEY,
  consulta_id VARCHAR(36) NOT NULL,
  persona_id VARCHAR(36) NOT NULL,
  lado VARCHAR(8) NOT NULL,
  texto TEXT NOT NULL,
  created_at VARCHAR(19) NOT NULL
);
