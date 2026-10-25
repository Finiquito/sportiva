<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Avisos: lo que se ve en la campana (siempre) y, según las preferencias de cada persona, por notificación push
 * y por correo. Crear un aviso solo lo deja en cola; los envíos los hace `Entregador` (cron o al terminar la respuesta).
 */
final class AvisoService extends Repo
{
    public const CATEGORIAS = ['agenda', 'feed', 'consultas', 'turnos', 'plantel'];
    /** Correo activado por defecto (lo que ya se mandaba por correo). */
    private const CORREO_DEFECTO = ['consultas' => 1, 'plantel' => 1];

    /** @return array{push: bool, correo: bool} */
    public function pref(string $persona, string $cat): array
    {
        $f = $this->fila('SELECT push, correo FROM sportiva_avisos_prefs WHERE persona_id = ? AND categoria = ?', [$persona, $cat]);
        return $f ? ['push' => (bool) $f['push'], 'correo' => (bool) $f['correo']] : ['push' => true, 'correo' => (bool) (self::CORREO_DEFECTO[$cat] ?? 0)];
    }

    /** @return array<string, array{push: bool, correo: bool}> */
    public function prefs(string $persona): array
    {
        $r = [];
        foreach (self::CATEGORIAS as $c) {
            $r[$c] = $this->pref($persona, $c);
        }
        return $r;
    }

    public function guardarPrefs(string $persona, array $in): void
    {
        foreach (self::CATEGORIAS as $c) {
            $this->ejecutar('DELETE FROM sportiva_avisos_prefs WHERE persona_id = ? AND categoria = ?', [$persona, $c]);
            $this->ejecutar('INSERT INTO sportiva_avisos_prefs (persona_id, categoria, push, correo) VALUES (?, ?, ?, ?)', [$persona, $c, !empty($in['push'][$c]) ? 1 : 0, !empty($in['correo'][$c]) ? 1 : 0]);
        }
    }

    public function tieneSuscripcion(string $persona): bool
    {
        return $this->fila('SELECT id FROM sportiva_push_subs WHERE persona_id = ?', [$persona]) !== null;
    }

    /** Deja un aviso en la cola para cada persona (sin repetir y sin avisarle a quien lo provocó). @param array<int, string> $personas */
    public function crear(array $personas, string $club, string $cat, string $titulo, string $cuerpo, string $url, ?string $excluir = null, ?array $evento = null): int
    {
        $n = 0;
        foreach (array_unique(array_filter($personas)) as $p) {
            if ($p === $excluir) {
                continue;
            }
            $pr = $this->pref($p, $cat);
            $aid = $this->id();
            $this->ejecutar(
                'INSERT INTO sportiva_avisos (id, persona_id, club_id, categoria, titulo, cuerpo, url, created_at, push_pendiente, correo_pendiente) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$aid, $p, $club, $cat, mb_substr($titulo, 0, 190), mb_substr($cuerpo, 0, 255), $url, Tiempo::sello(), $pr['push'] && $this->tieneSuscripcion($p) ? 1 : 0, $pr['correo'] ? 1 : 0]
            );
            if ($evento !== null) {
                $this->ejecutar('INSERT INTO sportiva_avisos_evento (aviso_id, evento_id, motivo) VALUES (?, ?, ?)', [$aid, $evento['id'], mb_substr((string) $evento['motivo'], 0, 12)]);
            }
            $n++;
        }
        return $n;
    }

    /** Grupo de filtro de un aviso: citaciones, turnos u otros. */
    public static function grupo(string $categoria): string
    {
        return $categoria === 'agenda' ? 'citaciones' : ($categoria === 'turnos' ? 'turnos' : 'otros');
    }

    /**
     * Avisos con los datos de su citación (si la tienen) y el grupo; `$filtro` = todos | citaciones | turnos | otros.
     * @return array{avisos: array<int, array<string, mixed>>, conteos: array<string, array{total: int, nuevos: int}>}
     */
    public function listaFiltrada(string $persona, string $club, string $filtro = 'todos', int $limite = 60): array
    {
        $todos = $this->filas(
            'SELECT a.*, ae.evento_id, ae.motivo FROM sportiva_avisos a LEFT JOIN sportiva_avisos_evento ae ON ae.aviso_id = a.id WHERE a.persona_id = ? AND a.club_id = ? ORDER BY a.created_at DESC, a.id DESC LIMIT ' . (int) $limite,
            [$persona, $club]
        );
        $con = ['todos' => ['total' => 0, 'nuevos' => 0], 'citaciones' => ['total' => 0, 'nuevos' => 0], 'turnos' => ['total' => 0, 'nuevos' => 0], 'otros' => ['total' => 0, 'nuevos' => 0]];
        $ids = [];
        foreach ($todos as &$a) {
            $a['grupo'] = self::grupo((string) $a['categoria']);
            foreach (['todos', $a['grupo']] as $g) {
                $con[$g]['total']++;
                $con[$g]['nuevos'] += $a['leido_en'] ? 0 : 1;
            }
            if ($a['evento_id']) {
                $ids[$a['evento_id']] = true;
            }
        }
        unset($a);
        $evs = [];
        if ($ids !== []) {
            $loc = '(SELECT COUNT(*) FROM sportiva_lugares_locales xl WHERE xl.lugar_id = ev.lugar_id) AS lugar_local';
            foreach ($this->filas("SELECT ev.id, ev.tipo, ev.titulo, ev.inicio, ev.fin, ev.hora_cancha, ev.hora_encuentro, ev.estado, eq.nombre AS equipo, l.nombre AS lugar, {$loc} FROM sportiva_eventos ev JOIN sportiva_equipos eq ON eq.id = ev.equipo_id LEFT JOIN sportiva_lugares l ON l.id = ev.lugar_id WHERE ev.id IN (" . implode(',', array_fill(0, count($ids), '?')) . ')', array_keys($ids)) as $e) {
                $evs[$e['id']] = $e;
            }
        }
        $r = [];
        foreach ($todos as $a) {
            $a['evento'] = $a['evento_id'] ? ($evs[$a['evento_id']] ?? null) : null;
            if ($filtro === 'todos' || $a['grupo'] === $filtro) {
                $r[] = $a;
            }
        }
        return ['avisos' => $r, 'conteos' => $con];
    }

    public function sinLeer(string $persona, string $club): int
    {
        return (int) ($this->fila('SELECT COUNT(*) AS n FROM sportiva_avisos WHERE persona_id = ? AND club_id = ? AND leido_en IS NULL', [$persona, $club])['n'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function lista(string $persona, string $club, int $limite = 50): array
    {
        return $this->filas('SELECT * FROM sportiva_avisos WHERE persona_id = ? AND club_id = ? ORDER BY created_at DESC, id DESC LIMIT ' . (int) $limite, [$persona, $club]);
    }

    /** Marca como leído y devuelve la URL del aviso (solo si es de esa persona). */
    public function abrir(string $id, string $persona): ?string
    {
        $a = $this->fila('SELECT * FROM sportiva_avisos WHERE id = ? AND persona_id = ?', [$id, $persona]);
        if ($a === null) {
            return null;
        }
        $this->ejecutar('UPDATE sportiva_avisos SET leido_en = ? WHERE id = ? AND leido_en IS NULL', [Tiempo::sello(), $id]);
        return (string) ($a['url'] ?: '/app/avisos');
    }

    public function leerTodo(string $persona, string $club): void
    {
        $this->ejecutar('UPDATE sportiva_avisos SET leido_en = ? WHERE persona_id = ? AND club_id = ? AND leido_en IS NULL', [Tiempo::sello(), $persona, $club]);
    }

    public function suscribir(string $persona, string $endpoint, string $p256dh, string $auth): bool
    {
        if (!str_starts_with($endpoint, 'https://') || strlen($endpoint) > 1000 || $p256dh === '' || strlen($p256dh) > 190 || $auth === '' || strlen($auth) > 64) {
            return false;
        }
        $h = hash('sha256', $endpoint);
        $this->ejecutar('DELETE FROM sportiva_push_subs WHERE endpoint_hash = ?', [$h]);
        $this->ejecutar('INSERT INTO sportiva_push_subs (id, persona_id, endpoint_hash, endpoint, p256dh, auth, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$this->id(), $persona, $h, $endpoint, $p256dh, $auth, Tiempo::sello()]);
        return true;
    }

    public function baja(string $persona, string $endpoint): void
    {
        $this->ejecutar('DELETE FROM sportiva_push_subs WHERE endpoint_hash = ? AND persona_id = ?', [hash('sha256', $endpoint), $persona]);
    }

    /** @return array<int, array<string, mixed>> */
    public function suscripciones(string $persona): array
    {
        return $this->filas('SELECT endpoint, p256dh, auth FROM sportiva_push_subs WHERE persona_id = ?', [$persona]);
    }

    public function quitarSuscripcion(string $endpoint): void
    {
        $this->ejecutar('DELETE FROM sportiva_push_subs WHERE endpoint_hash = ?', [hash('sha256', $endpoint)]);
    }

    /** Avisos en cola de envío. @return array<int, array<string, mixed>> */
    public function enCola(int $limite = 40): array
    {
        return $this->filas(
            'SELECT a.*, p.email, p.nombre AS persona_nombre, p.idioma FROM sportiva_avisos a JOIN sportiva_personas p ON p.id = a.persona_id
             WHERE a.push_pendiente = 1 OR a.correo_pendiente = 1 ORDER BY a.created_at LIMIT ' . (int) $limite
        );
    }

    public function entregado(string $id): void
    {
        $this->ejecutar('UPDATE sportiva_avisos SET push_pendiente = 0, correo_pendiente = 0 WHERE id = ?', [$id]);
    }

    public function cantidadEnCola(): int
    {
        return (int) ($this->fila('SELECT COUNT(*) AS n FROM sportiva_avisos WHERE push_pendiente = 1 OR correo_pendiente = 1')['n'] ?? 0);
    }

    // ---- a quién avisar ----------------------------------------------------------------------

    /** Adultos de los jugadores convocados a un evento. @return array<int, string> */
    public function adultosDelEvento(string $evento): array
    {
        return array_column($this->filas('SELECT DISTINCT aj.persona_id FROM sportiva_convocados c JOIN sportiva_apoderados_jugadores aj ON aj.jugador_id = c.jugador_id WHERE c.evento_id = ?', [$evento]), 'persona_id');
    }

    /** Adultos de un equipo (con jugadores en él). @return array<int, string> */
    public function adultosDelEquipo(string $equipo): array
    {
        return array_column($this->filas('SELECT DISTINCT aj.persona_id FROM sportiva_jugadores j JOIN sportiva_apoderados_jugadores aj ON aj.jugador_id = j.id WHERE j.equipo_id = ?', [$equipo]), 'persona_id');
    }

    /** Entrenadores del equipo, encargados del club. @return array<int, string> */
    public function staffDelEquipo(string $club, ?string $equipo): array
    {
        return array_column($this->filas(
            "SELECT persona_id FROM sportiva_membresias WHERE club_id = ? AND rol = 'encargado' AND estado = 'activo' UNION SELECT persona_id FROM sportiva_equipo_staff WHERE equipo_id = ?",
            [$club, $equipo ?? '']
        ), 'persona_id');
    }

    /** Todas las personas con algún rol en el club. @return array<int, string> */
    public function miembrosDelClub(string $club): array
    {
        return array_column($this->filas("SELECT DISTINCT persona_id FROM sportiva_membresias WHERE club_id = ? AND estado = 'activo'", [$club]), 'persona_id');
    }

    /** Titular de la familia + sus adultos (los que reciben el aviso del turno). @return array<int, string> */
    public function familia(string $titular, string $club): array
    {
        return array_merge([$titular], array_column((new PlantelService($this->pdo))->adultosDeFamilia($titular, $club), 'id'));
    }

    // ---- recordatorios ---------------------------------------------------------------------

    /**
     * Eventos que empiezan dentro de `$horas` y adultos de convocados que aún no responden (un aviso por persona y evento).
     * @return array<int, array{evento: array<string, mixed>, personas: array<int, string>}>
     */
    public function recordatoriosPendientes(string $club, string $desde, string $hasta): array
    {
        $r = [];
        foreach ($this->filas("SELECT id, titulo, inicio FROM sportiva_eventos WHERE club_id = ? AND estado = 'publicado' AND tipo <> 'especial' AND inicio >= ? AND inicio <= ? AND inicio NOT LIKE '%23:59:59'", [$club, $desde, $hasta]) as $e) {
            $p = array_column($this->filas(
                'SELECT DISTINCT aj.persona_id FROM sportiva_convocados c JOIN sportiva_apoderados_jugadores aj ON aj.jugador_id = c.jugador_id
                 LEFT JOIN sportiva_asistencias a ON a.evento_id = c.evento_id AND a.jugador_id = c.jugador_id
                 WHERE c.evento_id = ? AND a.id IS NULL AND aj.persona_id NOT IN (SELECT persona_id FROM sportiva_recordatorios WHERE evento_id = ?)',
                [$e['id'], $e['id']]
            ), 'persona_id');
            if ($p !== []) {
                $r[] = ['evento' => $e, 'personas' => $p];
            }
        }
        return $r;
    }

    public function recordado(string $evento, string $persona): void
    {
        $this->ejecutar('DELETE FROM sportiva_recordatorios WHERE evento_id = ? AND persona_id = ?', [$evento, $persona]);
        $this->ejecutar('INSERT INTO sportiva_recordatorios (evento_id, persona_id, created_at) VALUES (?, ?, ?)', [$evento, $persona, Tiempo::sello()]);
    }
}
