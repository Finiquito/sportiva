<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Eventos del club (partido, entrenamiento, evento especial, stage) y su asistencia.
 * Horas: «encuentro» (si hay punto de encuentro), «en cancha» y «partido» (= inicio). Todo en hora local del club.
 */
final class EventoService extends Repo
{
    public const TIPOS = ['partido', 'entreno', 'especial', 'stage'];
    public const RESPUESTAS = ['voy', 'duda', 'no'];

    public function buscar(string $id): ?array
    {
        return $this->fila(
            'SELECT ev.*, eq.nombre AS equipo, l.nombre AS lugar, l.direccion AS lugar_direccion, l.lat AS lugar_lat, l.lon AS lugar_lon, (SELECT COUNT(*) FROM sportiva_lugares_locales xl WHERE xl.lugar_id = ev.lugar_id) AS lugar_local
             FROM sportiva_eventos ev JOIN sportiva_equipos eq ON eq.id = ev.equipo_id LEFT JOIN sportiva_lugares l ON l.id = ev.lugar_id WHERE ev.id = ?',
            [$id]
        );
    }

    /** Une fecha 'Y-m-d' y hora 'H:i' → 'Y-m-d H:i:s' (null si falta alguna). */
    public static function unir(?string $fecha, ?string $hora): ?string
    {
        if (!$fecha || !$hora || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            return null;
        }
        return $fecha . ' ' . $hora . ':00';
    }

    /**
     * Valida y normaliza el formulario. Devuelve [datos, errores(clave de idioma por campo)].
     * @param array<string, mixed> $in
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function normalizar(array $in): array
    {
        $e = [];
        $tipo = in_array($in['tipo'] ?? '', self::TIPOS, true) ? $in['tipo'] : 'partido';
        $fecha = (string) ($in['fecha'] ?? '');
        $inicio = self::unir($fecha, (string) ($in['hora_partido'] ?? ''));
        if ($inicio === null) {
            $e['fecha'] = 'evento.error.fecha';
        }
        $fin = $tipo === 'partido' ? null : self::unir($fecha, (string) ($in['hora_fin'] ?? ''));
        $cancha = $tipo === 'partido' ? self::unir($fecha, (string) ($in['hora_cancha'] ?? '')) : null;
        if ($tipo === 'entreno') {
            $in['hay_encuentro'] = null;
            $in['rival'] = null;
            $in['encuentro_texto'] = null;
        } elseif ($tipo !== 'partido') {
            $in['rival'] = null;
        }
        $encuentro = !empty($in['hay_encuentro']) ? self::unir($fecha, (string) ($in['hora_encuentro'] ?? '')) : null;
        if ($tipo === 'partido' && $cancha === null && $inicio !== null) {
            $min = (int) ($in['cancha_antes'] ?? 45);
            $cancha = (new \DateTimeImmutable($inicio))->modify('-' . max(0, min(180, $min)) . ' minutes')->format('Y-m-d H:i:s');
        }
        if ($inicio !== null && $cancha !== null && $cancha > $inicio) {
            $e['hora_cancha'] = 'evento.error.orden';
        }
        if (!empty($in['hay_encuentro']) && $encuentro === null) {
            $e['hora_encuentro'] = 'evento.error.encuentro';
        } elseif ($encuentro !== null && $cancha !== null && $encuentro > $cancha) {
            $e['hora_encuentro'] = 'evento.error.orden';
        }
        $rival = trim((string) ($in['rival'] ?? ''));
        $titulo = trim((string) ($in['titulo'] ?? ''));
        if ($titulo === '') {
            $e['titulo'] = 'evento.error.titulo';
        }
        if (empty($in['lugar_id'])) {
            $e['lugar_id'] = 'evento.error.lugar';
        }
        return [[
            'tipo' => $tipo, 'titulo' => $titulo, 'rival' => $rival !== '' ? $rival : null, 'inicio' => $inicio, 'fin' => $fin,
            'hora_cancha' => $cancha, 'hora_encuentro' => $encuentro, 'lugar_id' => ($in['lugar_id'] ?? '') ?: null,
            'encuentro_texto' => trim((string) ($in['encuentro_texto'] ?? '')) ?: null, 'nota' => trim((string) ($in['nota'] ?? '')) ?: null,
            'precio_info' => trim((string) ($in['precio_info'] ?? '')) ?: null,
        ], $e];
    }

    /**
     * @param array<string, mixed> $d  salida de normalizar()
     * @param string[] $equiposExtra   otras categorías convocadas (se suman al equipo principal)
     * @param string[]|null $jugadores jugadores convocados; null = todos los de los equipos
     */
    public function crear(string $club, string $equipo, array $d, ?string $por, array $equiposExtra = [], ?array $jugadores = null): string
    {
        $id = $this->id();
        $ahora = Tiempo::sello();
        $this->ejecutar(
            'INSERT INTO sportiva_eventos (id, club_id, equipo_id, tipo, titulo, rival, inicio, fin, hora_cancha, hora_encuentro, lugar_id, encuentro_texto, nota, precio_info, estado, creado_por, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $club, $equipo, $d['tipo'], $d['titulo'], $d['rival'], $d['inicio'], $d['fin'], $d['hora_cancha'], $d['hora_encuentro'], $d['lugar_id'], $d['encuentro_texto'], $d['nota'], $d['precio_info'], 'publicado', $por, $ahora, $ahora]
        );
        $this->convocar($id, $club, array_unique(array_merge([$equipo], $equiposExtra)), $jugadores);
        return $id;
    }

    /** @param array<string, mixed> $d */
    public function actualizar(string $id, array $d): void
    {
        $this->ejecutar(
            'UPDATE sportiva_eventos SET tipo = ?, titulo = ?, rival = ?, inicio = ?, fin = ?, hora_cancha = ?, hora_encuentro = ?, lugar_id = ?, encuentro_texto = ?, nota = ?, precio_info = ?, updated_at = ? WHERE id = ?',
            [$d['tipo'], $d['titulo'], $d['rival'], $d['inicio'], $d['fin'], $d['hora_cancha'], $d['hora_encuentro'], $d['lugar_id'], $d['encuentro_texto'], $d['nota'], $d['precio_info'], Tiempo::sello(), $id]
        );
    }

    public function cancelar(string $id): void
    {
        $this->ejecutar("UPDATE sportiva_eventos SET estado = 'cancelado', updated_at = ? WHERE id = ?", [Tiempo::sello(), $id]);
    }

    public function reactivar(string $id): void
    {
        $this->ejecutar("UPDATE sportiva_eventos SET estado = 'publicado', updated_at = ? WHERE id = ?", [Tiempo::sello(), $id]);
    }

    /** @return array<int, string> */
    public function convocadosIds(string $evento): array
    {
        return array_column($this->filas('SELECT jugador_id FROM sportiva_convocados WHERE evento_id = ?', [$evento]), 'jugador_id');
    }

    /** Solo los jugadores de este club. @param array<int, string> $ids @return array<int, string> */
    public function jugadoresDelClub(string $club, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        return array_column($this->filas('SELECT id FROM sportiva_jugadores WHERE club_id = ? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$club], array_values($ids))), 'id');
    }

    /** Adultos de estos jugadores. @param array<int, string> $jugadores @return array<int, string> */
    public function adultosDe(array $jugadores): array
    {
        if ($jugadores === []) {
            return [];
        }
        return array_column($this->filas('SELECT DISTINCT persona_id FROM sportiva_apoderados_jugadores WHERE jugador_id IN (' . implode(',', array_fill(0, count($jugadores), '?')) . ')', array_values($jugadores)), 'persona_id');
    }

    /** @param string[] $equipos @param string[]|null $jugadores */
    public function convocar(string $evento, string $club, array $equipos, ?array $jugadores): void
    {
        $ids = [];
        if ($jugadores !== null) {
            $ids = $jugadores;
        } else {
            foreach ($equipos as $eq) {
                foreach ($this->filas('SELECT id FROM sportiva_jugadores WHERE equipo_id = ? AND club_id = ?', [$eq, $club]) as $j) {
                    $ids[] = $j['id'];
                }
            }
        }
        $this->ejecutar('DELETE FROM sportiva_convocados WHERE evento_id = ?', [$evento]);
        foreach (array_unique($ids) as $j) {
            $this->ejecutar('INSERT INTO sportiva_convocados (id, evento_id, jugador_id) VALUES (?, ?, ?)', [$this->id(), $evento, $j]);
        }
    }

    /** Convocados con su respuesta. @return array<int, array<string, mixed>> */
    public function convocados(string $evento): array
    {
        return $this->filas(
            'SELECT j.id, j.nombre, j.nacimiento, eq.nombre AS equipo, a.respuesta, a.updated_at AS respondio, (SELECT f.version FROM sportiva_fotos f WHERE f.jugador_id = j.id) AS foto
             FROM sportiva_convocados c JOIN sportiva_jugadores j ON j.id = c.jugador_id JOIN sportiva_equipos eq ON eq.id = j.equipo_id
             LEFT JOIN sportiva_asistencias a ON a.evento_id = c.evento_id AND a.jugador_id = j.id
             WHERE c.evento_id = ? ORDER BY j.nombre',
            [$evento]
        );
    }

    /** @return array{voy:int,duda:int,no:int,sin:int,total:int} */
    public function resumen(string $evento): array
    {
        $r = ['voy' => 0, 'duda' => 0, 'no' => 0, 'sin' => 0, 'total' => 0];
        foreach ($this->convocados($evento) as $c) {
            $k = in_array($c['respuesta'], self::RESPUESTAS, true) ? $c['respuesta'] : 'sin';
            $r[$k]++;
            $r['total']++;
        }
        return $r;
    }

    /** @return array<int, array<string, mixed>> */
    public function delClubDesde(string $club, string $desde, ?array $equipos = null, bool $borradores = false): array
    {
        $sql = 'SELECT ev.*, eq.nombre AS equipo, l.nombre AS lugar, l.direccion AS lugar_direccion, l.lat AS lugar_lat, l.lon AS lugar_lon, (SELECT COUNT(*) FROM sportiva_lugares_locales xl WHERE xl.lugar_id = ev.lugar_id) AS lugar_local FROM sportiva_eventos ev JOIN sportiva_equipos eq ON eq.id = ev.equipo_id LEFT JOIN sportiva_lugares l ON l.id = ev.lugar_id
                WHERE ev.club_id = ? AND ev.inicio >= ? AND ev.estado <> ?';
        $p = [$club, $desde, 'cancelado'];
        if ($equipos !== null) {
            if ($equipos === []) {
                return [];
            }
            $sql .= ' AND ev.equipo_id IN (' . implode(',', array_fill(0, count($equipos), '?')) . ')';
            $p = array_merge($p, $equipos);
        }
        return $this->filas($sql . ' ORDER BY ev.inicio', $p);
    }

    /**
     * Eventos para las familias: uno por (evento, hijo convocado), con su respuesta.
     * @return array<int, array<string, mixed>>
     */
    public function paraApoderado(string $persona, string $club, string $desde): array
    {
        return $this->filas(
            "SELECT ev.*, eq.nombre AS equipo, l.nombre AS lugar, l.direccion AS lugar_direccion, l.lat AS lugar_lat, l.lon AS lugar_lon, (SELECT COUNT(*) FROM sportiva_lugares_locales xl WHERE xl.lugar_id = ev.lugar_id) AS lugar_local, j.id AS jugador_id, j.nombre AS jugador, a.respuesta
             FROM sportiva_apoderados_jugadores aj
             JOIN sportiva_jugadores j ON j.id = aj.jugador_id
             JOIN sportiva_convocados c ON c.jugador_id = j.id
             JOIN sportiva_eventos ev ON ev.id = c.evento_id
             JOIN sportiva_equipos eq ON eq.id = ev.equipo_id
             LEFT JOIN sportiva_lugares l ON l.id = ev.lugar_id
             LEFT JOIN sportiva_asistencias a ON a.evento_id = ev.id AND a.jugador_id = j.id
             WHERE aj.persona_id = ? AND ev.club_id = ? AND ev.inicio >= ? AND ev.estado = 'publicado'
             ORDER BY ev.inicio, j.nombre",
            [$persona, $club, $desde]
        );
    }

    /** Eventos del propio jugador (hijo), solo lectura. @return array<int, array<string, mixed>> */
    public function paraJugador(string $persona, string $club, string $desde): array
    {
        return $this->filas(
            "SELECT ev.*, eq.nombre AS equipo, l.nombre AS lugar, l.direccion AS lugar_direccion, l.lat AS lugar_lat, l.lon AS lugar_lon, (SELECT COUNT(*) FROM sportiva_lugares_locales xl WHERE xl.lugar_id = ev.lugar_id) AS lugar_local, j.id AS jugador_id, j.nombre AS jugador, a.respuesta
             FROM sportiva_jugadores j
             JOIN sportiva_convocados c ON c.jugador_id = j.id
             JOIN sportiva_eventos ev ON ev.id = c.evento_id
             JOIN sportiva_equipos eq ON eq.id = ev.equipo_id
             LEFT JOIN sportiva_lugares l ON l.id = ev.lugar_id
             LEFT JOIN sportiva_asistencias a ON a.evento_id = ev.id AND a.jugador_id = j.id
             WHERE j.persona_id = ? AND ev.club_id = ? AND ev.inicio >= ? AND ev.estado = 'publicado'
             ORDER BY ev.inicio",
            [$persona, $club, $desde]
        );
    }

    public function esApoderadoDe(string $persona, string $jugador): bool
    {
        return $this->fila('SELECT id FROM sportiva_apoderados_jugadores WHERE persona_id = ? AND jugador_id = ?', [$persona, $jugador]) !== null;
    }

    public function estaConvocado(string $evento, string $jugador): bool
    {
        return $this->fila('SELECT id FROM sportiva_convocados WHERE evento_id = ? AND jugador_id = ?', [$evento, $jugador]) !== null;
    }

    /** Guarda la asistencia (la última respuesta manda). false si no corresponde. */
    public function responder(string $evento, string $jugador, string $respuesta, string $persona): bool
    {
        if (!in_array($respuesta, self::RESPUESTAS, true) || !$this->estaConvocado($evento, $jugador)) {
            return false;
        }
        $this->ejecutar('DELETE FROM sportiva_asistencias WHERE evento_id = ? AND jugador_id = ?', [$evento, $jugador]);
        $this->ejecutar('INSERT INTO sportiva_asistencias (id, evento_id, jugador_id, respuesta, por_persona_id, updated_at) VALUES (?, ?, ?, ?, ?, ?)', [$this->id(), $evento, $jugador, $respuesta, $persona, Tiempo::sello()]);
        return true;
    }

    /** Jugadores de un equipo. @return array<int, array<string, mixed>> */
    public function jugadoresDe(string $equipo): array
    {
        return $this->filas('SELECT * FROM sportiva_jugadores WHERE equipo_id = ? ORDER BY nombre', [$equipo]);
    }
}
