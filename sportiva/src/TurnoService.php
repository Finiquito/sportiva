<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Turnos de traslado. No hay cupos: el entrenador anota qué familias llevan en cada evento y los asientos
 * se reparten al llegar al punto de encuentro. Una familia es su apoderado titular. Una familia puede pedir
 * sumarse («Yo también llevo») y el entrenador la acepta.
 */
final class TurnoService extends Repo
{
    /** Turnos de un evento con los hijos convocados de cada familia. @return array<int, array<string, mixed>> */
    public function delEvento(string $evento): array
    {
        $f = $this->filas(
            'SELECT t.id, t.familia_id, t.estado, p.nombre FROM sportiva_turnos t JOIN sportiva_personas p ON p.id = t.familia_id
             WHERE t.evento_id = ? ORDER BY t.estado, p.nombre',
            [$evento]
        );
        foreach ($f as &$x) {
            $x['hijos'] = array_column($this->filas(
                'SELECT j.nombre FROM sportiva_convocados c JOIN sportiva_jugadores j ON j.id = c.jugador_id
                 JOIN sportiva_apoderados_jugadores aj ON aj.jugador_id = j.id AND aj.titular = 1 AND aj.persona_id = ?
                 WHERE c.evento_id = ? ORDER BY j.nombre',
                [$x['familia_id'], $evento]
            ), 'nombre');
        }
        return $f;
    }

    /** Familias (titulares) de la persona que tienen hijos convocados al evento. @return string[] */
    public function familiasDe(string $persona, string $evento): array
    {
        return array_column($this->filas(
            'SELECT DISTINCT t.persona_id FROM sportiva_apoderados_jugadores aj
             JOIN sportiva_convocados c ON c.jugador_id = aj.jugador_id AND c.evento_id = ?
             JOIN sportiva_apoderados_jugadores t ON t.jugador_id = aj.jugador_id AND t.titular = 1
             WHERE aj.persona_id = ?',
            [$evento, $persona]
        ), 'persona_id');
    }

    /** Familias con hijos convocados que todavía no están en el evento. @return array<int, array<string, mixed>> */
    public function elegibles(string $evento): array
    {
        return $this->filas(
            'SELECT DISTINCT p.id, p.nombre FROM sportiva_convocados c
             JOIN sportiva_apoderados_jugadores t ON t.jugador_id = c.jugador_id AND t.titular = 1
             JOIN sportiva_personas p ON p.id = t.persona_id
             WHERE c.evento_id = ? AND p.id NOT IN (SELECT familia_id FROM sportiva_turnos WHERE evento_id = ?) ORDER BY p.nombre',
            [$evento, $evento]
        );
    }

    public function esElegible(string $evento, string $familia): bool
    {
        foreach ($this->elegibles($evento) as $e) {
            if ($e['id'] === $familia) {
                return true;
            }
        }
        return false;
    }

    public function agregar(string $evento, string $familia, ?string $por, string $estado = 'confirmado'): void
    {
        if ($this->fila('SELECT id FROM sportiva_turnos WHERE evento_id = ? AND familia_id = ?', [$evento, $familia]) === null) {
            $this->ejecutar('INSERT INTO sportiva_turnos (id, evento_id, familia_id, estado, creado_por, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$this->id(), $evento, $familia, $estado, $por, Tiempo::sello()]);
        }
    }

    public function quitar(string $evento, string $familia): void
    {
        $this->ejecutar('DELETE FROM sportiva_turnos WHERE evento_id = ? AND familia_id = ?', [$evento, $familia]);
    }

    public function aceptar(string $evento, string $familia): void
    {
        $this->ejecutar("UPDATE sportiva_turnos SET estado = 'confirmado' WHERE evento_id = ? AND familia_id = ? AND estado = 'pendiente'", [$evento, $familia]);
    }

    /** Ids de los eventos futuros donde alguna familia de la persona lleva (confirmado). @return array<string, true> */
    public function eventosDeTurno(string $persona, string $club, string $desde): array
    {
        $r = [];
        foreach ($this->filas(
            "SELECT DISTINCT t.evento_id FROM sportiva_turnos t JOIN sportiva_eventos ev ON ev.id = t.evento_id
             JOIN sportiva_apoderados_jugadores aj ON aj.persona_id = ? JOIN sportiva_apoderados_jugadores tt ON tt.jugador_id = aj.jugador_id AND tt.titular = 1 AND tt.persona_id = t.familia_id
             WHERE ev.club_id = ? AND ev.inicio >= ? AND t.estado = 'confirmado'",
            [$persona, $club, $desde]
        ) as $x) {
            $r[$x['evento_id']] = true;
        }
        return $r;
    }

    /**
     * Completa el turno de un evento hasta `$hasta` familias, tomando entre las que tienen hijos convocados
     * las que menos han llevado en ese equipo. @return int familias agregadas
     */
    public function asignar(string $evento, int $hasta, ?string $por, ?callable $alAsignar = null): int
    {
        $hasta = max(1, min(6, $hasta));
        $faltan = $hasta - count($this->delEvento($evento));
        if ($faltan <= 0) {
            return 0;
        }
        $e = $this->fila('SELECT equipo_id FROM sportiva_eventos WHERE id = ?', [$evento]);
        $cand = [];
        foreach ($this->elegibles($evento) as $f) {
            $cand[] = $f + ['n' => (int) ($this->fila(
                "SELECT COUNT(*) AS n FROM sportiva_turnos t JOIN sportiva_eventos ev ON ev.id = t.evento_id
                 WHERE t.familia_id = ? AND ev.equipo_id = ? AND t.estado = 'confirmado'",
                [$f['id'], $e['equipo_id'] ?? '']
            )['n'] ?? 0)];
        }
        usort($cand, fn($a, $b) => [$a['n'], $a['nombre']] <=> [$b['n'], $b['nombre']]);
        $n = 0;
        foreach (array_slice($cand, 0, $faltan) as $f) {
            $this->agregar($evento, $f['id'], $por);
            $alAsignar && $alAsignar($evento, $f['id']);
            $n++;
        }
        return $n;
    }

    /**
     * Reparte turnos entre los próximos partidos de un equipo que aún no tienen ninguno: a cada partido van las
     * `$porPartido` familias con menos turnos hasta ese momento. @return int partidos completados
     */
    public function repartir(string $club, string $equipo, int $porPartido, string $desde, ?string $por, ?callable $alAsignar = null): int
    {
        $porPartido = max(1, min(6, $porPartido));
        $familias = $this->filas(
            'SELECT DISTINCT p.id, p.nombre FROM sportiva_jugadores j JOIN sportiva_apoderados_jugadores t ON t.jugador_id = j.id AND t.titular = 1
             JOIN sportiva_personas p ON p.id = t.persona_id WHERE j.equipo_id = ? AND j.club_id = ? ORDER BY p.nombre',
            [$equipo, $club]
        );
        if ($familias === []) {
            return 0;
        }
        $cuenta = [];
        foreach ($familias as $f) {
            $cuenta[$f['id']] = (int) ($this->fila(
                "SELECT COUNT(*) AS n FROM sportiva_turnos t JOIN sportiva_eventos ev ON ev.id = t.evento_id
                 WHERE t.familia_id = ? AND ev.equipo_id = ? AND t.estado = 'confirmado'",
                [$f['id'], $equipo]
            )['n'] ?? 0);
        }
        $hechos = 0;
        foreach ($this->filas(
            "SELECT ev.id FROM sportiva_eventos ev WHERE ev.club_id = ? AND ev.equipo_id = ? AND ev.tipo = 'partido' AND ev.estado = 'publicado' AND ev.inicio >= ?
             AND NOT EXISTS (SELECT 1 FROM sportiva_turnos t WHERE t.evento_id = ev.id) ORDER BY ev.inicio",
            [$club, $equipo, $desde]
        ) as $ev) {
            $orden = $familias;
            usort($orden, fn($a, $b) => [$cuenta[$a['id']], $a['nombre']] <=> [$cuenta[$b['id']], $b['nombre']]);
            foreach (array_slice($orden, 0, $porPartido) as $f) {
                $this->agregar($ev['id'], $f['id'], $por);
                $alAsignar && $alAsignar($ev['id'], $f['id']);
                $cuenta[$f['id']]++;
            }
            $hechos++;
        }
        return $hechos;
    }

    /** Próximos partidos del equipo con sus familias (vista del entrenador). @return array<int, array<string, mixed>> */
    public function proximos(string $club, array $equipos, string $desde): array
    {
        if ($equipos === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($equipos), '?'));
        $ev = $this->filas(
            "SELECT ev.id, ev.titulo, ev.inicio, ev.equipo_id, eq.nombre AS equipo FROM sportiva_eventos ev JOIN sportiva_equipos eq ON eq.id = ev.equipo_id
             WHERE ev.club_id = ? AND ev.tipo = 'partido' AND ev.estado = 'publicado' AND ev.inicio >= ? AND ev.equipo_id IN ({$in}) ORDER BY ev.inicio",
            array_merge([$club, $desde], $equipos)
        );
        foreach ($ev as &$e) {
            $e['turnos'] = $this->delEvento($e['id']);
        }
        return $ev;
    }
}
