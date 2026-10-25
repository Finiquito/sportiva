<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/**
 * Plantel y familias. Una familia = el apoderado titular (lo carga el entrenador) + los hijos donde es titular
 * + los otros adultos vinculados a esos hijos. Lo que agrega el titular queda pendiente hasta que un entrenador lo aprueba.
 */
final class PlantelService extends Repo
{
    public const MAX_ADULTOS = 6;
    public const MAX_PENDIENTES = 10;

    private function personas(): PersonaService
    {
        return new PersonaService($this->pdo);
    }

    public static function emailValido(string $e): bool
    {
        return (bool) filter_var($e, FILTER_VALIDATE_EMAIL) && strlen($e) <= 190;
    }

    public static function fechaValida(string $f): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $f);
        return $d !== false && $d->format('Y-m-d') === $f && $f <= date('Y-m-d') && $f >= '1990-01-01';
    }

    /** @return array<string, mixed>|null */
    public function jugador(string $id): ?array
    {
        return $this->fila('SELECT j.*, e.nombre AS equipo, (SELECT f.version FROM sportiva_fotos f WHERE f.jugador_id = j.id) AS foto FROM sportiva_jugadores j JOIN sportiva_equipos e ON e.id = j.equipo_id WHERE j.id = ?', [$id]);
    }

    /** Jugadores de un equipo con la cantidad de adultos. @return array<int, array<string, mixed>> */
    public function delEquipo(string $equipo): array
    {
        return $this->filas(
            'SELECT j.*, (SELECT COUNT(*) FROM sportiva_apoderados_jugadores aj WHERE aj.jugador_id = j.id) AS adultos, (SELECT f.version FROM sportiva_fotos f WHERE f.jugador_id = j.id) AS foto
             FROM sportiva_jugadores j WHERE j.equipo_id = ? ORDER BY j.nombre',
            [$equipo]
        );
    }

    /** Adultos vinculados a un jugador (titular primero). @return array<int, array<string, mixed>> */
    public function adultos(string $jugador): array
    {
        return $this->filas(
            'SELECT p.id, p.nombre, p.email, p.telefono, aj.titular FROM sportiva_apoderados_jugadores aj
             JOIN sportiva_personas p ON p.id = aj.persona_id WHERE aj.jugador_id = ? ORDER BY aj.titular DESC, p.nombre',
            [$jugador]
        );
    }

    public function titularDe(string $jugador): ?string
    {
        $r = $this->fila('SELECT persona_id FROM sportiva_apoderados_jugadores WHERE jugador_id = ? AND titular = 1 ORDER BY id', [$jugador]);
        return $r['persona_id'] ?? null;
    }

    public function esTitular(string $persona, string $jugador): bool
    {
        return $this->fila('SELECT id FROM sportiva_apoderados_jugadores WHERE persona_id = ? AND jugador_id = ? AND titular = 1', [$persona, $jugador]) !== null;
    }

    /** Hijos de una familia (los del titular en el club). @return array<int, array<string, mixed>> */
    public function hijosDeTitular(string $titular, string $club): array
    {
        return $this->filas(
            'SELECT j.*, e.nombre AS equipo, (SELECT f.version FROM sportiva_fotos f WHERE f.jugador_id = j.id) AS foto FROM sportiva_apoderados_jugadores aj
             JOIN sportiva_jugadores j ON j.id = aj.jugador_id JOIN sportiva_equipos e ON e.id = j.equipo_id
             WHERE aj.persona_id = ? AND aj.titular = 1 AND j.club_id = ? ORDER BY j.nombre',
            [$titular, $club]
        );
    }

    /** Otros adultos de la familia del titular. @return array<int, array<string, mixed>> */
    public function adultosDeFamilia(string $titular, string $club): array
    {
        return $this->filas(
            'SELECT DISTINCT p.id, p.nombre, p.email, p.telefono FROM sportiva_apoderados_jugadores aj
             JOIN sportiva_personas p ON p.id = aj.persona_id
             WHERE aj.titular = 0 AND aj.jugador_id IN (
               SELECT a2.jugador_id FROM sportiva_apoderados_jugadores a2 JOIN sportiva_jugadores j ON j.id = a2.jugador_id
               WHERE a2.persona_id = ? AND a2.titular = 1 AND j.club_id = ?) ORDER BY p.nombre',
            [$titular, $club]
        );
    }

    /** Persona por correo (la crea si no existe) con rol de apoderado en el club. @return array{0: string, 1: bool} id y si es nueva */
    private function apoderado(string $club, string $nombre, string $email, ?string $tel): array
    {
        $svc = $this->personas();
        $p = $svc->porCorreo($email);
        $nueva = $p === null;
        $id = $p['id'] ?? $svc->crear($email, $nombre, null, $tel);
        $svc->dar($id, $club, 'apoderado');
        return [$id, $nueva];
    }

    /** Valida y normaliza un adulto o un hijo; devuelve [datos, errores]. @return array{0: array<string, string>, 1: array<string, string>} */
    public function normalizarAdulto(array $in): array
    {
        $d = ['nombre' => trim((string) ($in['nombre'] ?? '')), 'email' => PersonaService::correo((string) ($in['email'] ?? '')), 'telefono' => trim((string) ($in['telefono'] ?? ''))];
        $e = [];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 160) {
            $e['nombre'] = 'plantel.error.nombre';
        }
        if (!self::emailValido($d['email'])) {
            $e['email'] = 'plantel.error.email';
        }
        if (mb_strlen($d['telefono']) > 40) {
            $e['telefono'] = 'plantel.error.telefono';
        }
        return [$d, $e];
    }

    /** @return array{0: array<string, string>, 1: array<string, string>} */
    public function normalizarHijo(array $in, string $club, bool $conEquipo = true): array
    {
        $d = ['nombre' => trim((string) ($in['nombre'] ?? '')), 'nacimiento' => trim((string) ($in['nacimiento'] ?? '')), 'equipo_id' => (string) ($in['equipo_id'] ?? '')];
        $e = [];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 120) {
            $e['nombre'] = 'plantel.error.nombre';
        }
        if ($d['nacimiento'] !== '' && !self::fechaValida($d['nacimiento'])) {
            $e['nacimiento'] = 'plantel.error.nacimiento';
        }
        if ($conEquipo && $this->fila('SELECT id FROM sportiva_equipos WHERE id = ? AND club_id = ? AND archivado = 0', [$d['equipo_id'], $club]) === null) {
            $e['equipo_id'] = 'plantel.error.equipo';
        }
        return [$d, $e];
    }

    /** Familias del club (una por apoderado titular) con los nombres de sus hijos. @return array<int, array{id: string, nombre: string, email: string, hijos: array<int, string>}> */
    public function familias(string $club): array
    {
        $fam = [];
        foreach ($this->filas(
            'SELECT DISTINCT p.id, p.nombre, p.email FROM sportiva_apoderados_jugadores aj
             JOIN sportiva_personas p ON p.id = aj.persona_id JOIN sportiva_jugadores j ON j.id = aj.jugador_id
             WHERE aj.titular = 1 AND j.club_id = ? ORDER BY p.nombre',
            [$club]
        ) as $f) {
            $fam[$f['id']] = $f + ['hijos' => []];
        }
        foreach ($this->filas(
            'SELECT aj.persona_id, j.nombre FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id
             WHERE aj.titular = 1 AND j.club_id = ? ORDER BY j.nombre',
            [$club]
        ) as $h) {
            if (isset($fam[$h['persona_id']])) {
                $fam[$h['persona_id']]['hijos'][] = $h['nombre'];
            }
        }
        return array_values($fam);
    }

    /** Agrega un jugador a una familia que ya existe (sin volver a escribir al titular). */
    public function cargarEnFamilia(string $club, array $hijo, string $titular): ?string
    {
        if ($this->hijosDeTitular($titular, $club) === []) {
            return null;
        }
        return $this->agregarHijo($titular, $club, $hijo);
    }

    /**
     * El entrenador carga un jugador con su apoderado titular. Si el titular ya existe (otro hijo), se reutiliza.
     * @param array<string, string> $hijo @param array<string, string> $adulto
     * @return array{jugador: string, persona: string, nueva: bool}
     */
    public function cargar(string $club, array $hijo, array $adulto): array
    {
        [$pid, $nueva] = $this->apoderado($club, $adulto['nombre'], $adulto['email'], $adulto['telefono'] ?: null);
        $jid = $this->personas()->crearJugador($club, $hijo['equipo_id'], $hijo['nombre'], $hijo['nacimiento'] ?: null);
        $this->personas()->vincularHijo($pid, $jid, true);
        // los otros adultos de esa familia también ven al nuevo hijo
        foreach ($this->adultosDeFamilia($pid, $club) as $a) {
            $this->personas()->vincularHijo($a['id'], $jid, false);
        }
        return ['jugador' => $jid, 'persona' => $pid, 'nueva' => $nueva];
    }

    /**
     * Jugador mayor de edad: tiene cuenta propia (su correo) y es su propio titular, sin apoderado.
     * Queda con rol de apoderado de sí mismo (confirma asistencia, recibe avisos, consulta) y `persona_id` en su ficha.
     * @param array<string, string> $hijo @return array{jugador: string, persona: string, nueva: bool}
     */
    public function cargarAdulto(string $club, array $hijo, string $email, ?string $tel): array
    {
        [$pid, $nueva] = $this->apoderado($club, $hijo['nombre'], $email, $tel);
        $jid = $this->personas()->crearJugador($club, $hijo['equipo_id'], $hijo['nombre'], $hijo['nacimiento'] ?: null, $pid);
        $this->personas()->vincularHijo($pid, $jid, true);
        return ['jugador' => $jid, 'persona' => $pid, 'nueva' => $nueva];
    }

    /** @return array<string, mixed>|null */
    public function equipo(string $id): ?array
    {
        return $this->fila('SELECT id, club_id, nombre, temporada FROM sportiva_equipos WHERE id = ? AND archivado = 0', [$id]);
    }

    /** Agrega un adulto a la familia de un titular (a todos sus hijos del club). @return array{0: string, 1: bool} */
    public function agregarAdulto(string $titular, string $club, array $d): array
    {
        [$pid, $nueva] = $this->apoderado($club, $d['nombre'], $d['email'], $d['telefono'] ?: null);
        foreach ($this->hijosDeTitular($titular, $club) as $h) {
            $this->personas()->vincularHijo($pid, $h['id'], $titular === $pid);
        }
        return [$pid, $nueva];
    }

    public function agregarHijo(string $titular, string $club, array $d): string
    {
        $jid = $this->personas()->crearJugador($club, $d['equipo_id'], $d['nombre'], $d['nacimiento'] ?: null);
        $this->personas()->vincularHijo($titular, $jid, true);
        foreach ($this->adultosDeFamilia($titular, $club) as $a) {
            $this->personas()->vincularHijo($a['id'], $jid, false);
        }
        return $jid;
    }

    public function actualizarJugador(string $id, array $d, bool $cambiaEquipo): void
    {
        if ($cambiaEquipo) {
            $this->ejecutar('UPDATE sportiva_jugadores SET nombre = ?, nacimiento = ?, equipo_id = ? WHERE id = ?', [$d['nombre'], $d['nacimiento'] ?: null, $d['equipo_id'], $id]);
        } else {
            $this->ejecutar('UPDATE sportiva_jugadores SET nombre = ?, nacimiento = ? WHERE id = ?', [$d['nombre'], $d['nacimiento'] ?: null, $id]);
        }
    }

    /** Quita a un adulto (no titular) de la familia de ese jugador. */
    public function quitarAdulto(string $titular, string $club, string $persona): bool
    {
        if ($persona === $titular) {
            return false;
        }
        foreach ($this->hijosDeTitular($titular, $club) as $h) {
            $this->ejecutar('DELETE FROM sportiva_apoderados_jugadores WHERE persona_id = ? AND jugador_id = ? AND titular = 0', [$persona, $h['id']]);
        }
        $quedan = $this->fila(
            'SELECT aj.id FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id WHERE aj.persona_id = ? AND j.club_id = ?',
            [$persona, $club]
        );
        if ($quedan === null) {
            $this->ejecutar("DELETE FROM sportiva_membresias WHERE persona_id = ? AND club_id = ? AND rol = 'apoderado'", [$persona, $club]);
        }
        return true;
    }

    // ---- solicitudes (lo que agrega el titular y aprueba el entrenador) ----

    public function pendientesDe(string $titular, string $club): int
    {
        return (int) ($this->fila("SELECT COUNT(*) AS n FROM sportiva_solicitudes WHERE solicitante_id = ? AND club_id = ? AND estado = 'pendiente'", [$titular, $club])['n'] ?? 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function solicitudesDe(string $titular, string $club): array
    {
        return $this->filas("SELECT s.*, e.nombre AS equipo FROM sportiva_solicitudes s LEFT JOIN sportiva_equipos e ON e.id = s.equipo_id WHERE s.solicitante_id = ? AND s.club_id = ? AND s.estado = 'pendiente' ORDER BY s.created_at", [$titular, $club]);
    }

    public function solicitar(string $club, string $tipo, string $titular, array $d): string
    {
        $id = $this->id();
        $this->ejecutar(
            'INSERT INTO sportiva_solicitudes (id, club_id, tipo, solicitante_id, equipo_id, nombre, email, telefono, nacimiento, estado, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $club, $tipo, $titular, $d['equipo_id'] ?? null, $d['nombre'], $d['email'] ?? null, ($d['telefono'] ?? '') ?: null, ($d['nacimiento'] ?? '') ?: null, 'pendiente', Tiempo::sello()]
        );
        return $id;
    }

    public function solicitud(string $id): ?array
    {
        return $this->fila('SELECT s.*, e.nombre AS equipo FROM sportiva_solicitudes s LEFT JOIN sportiva_equipos e ON e.id = s.equipo_id WHERE s.id = ?', [$id]);
    }

    /** Pendientes que puede resolver un entrenador: las de sus equipos o de familias con hijos en ellos. @param array<int, string> $equipos @return array<int, array<string, mixed>> */
    public function pendientes(string $club, array $equipos): array
    {
        if ($equipos === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($equipos), '?'));
        return $this->filas(
            "SELECT s.*, e.nombre AS equipo, p.nombre AS solicitante FROM sportiva_solicitudes s
             JOIN sportiva_personas p ON p.id = s.solicitante_id LEFT JOIN sportiva_equipos e ON e.id = s.equipo_id
             WHERE s.club_id = ? AND s.estado = 'pendiente' AND (s.equipo_id IN ({$in}) OR s.solicitante_id IN (
               SELECT aj.persona_id FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id
               WHERE aj.titular = 1 AND j.equipo_id IN ({$in}))) ORDER BY s.created_at",
            array_merge([$club], $equipos, $equipos)
        );
    }

    public function puedeResolver(array $s, array $equipos): bool
    {
        foreach ($this->pendientes((string) $s['club_id'], $equipos) as $p) {
            if ($p['id'] === $s['id']) {
                return true;
            }
        }
        return false;
    }

    /** Aprueba: crea el adulto o el hijo. @return array{persona: ?string, nueva: bool}|null null si ya estaba resuelta */
    public function aprobar(string $id, string $por): ?array
    {
        $s = $this->solicitud($id);
        if ($s === null || $s['estado'] !== 'pendiente') {
            return null;
        }
        $res = ['persona' => null, 'nueva' => false];
        if ($s['tipo'] === 'adulto') {
            [$res['persona'], $res['nueva']] = $this->agregarAdulto($s['solicitante_id'], $s['club_id'], ['nombre' => $s['nombre'], 'email' => (string) $s['email'], 'telefono' => (string) $s['telefono']]);
        } else {
            $this->agregarHijo($s['solicitante_id'], $s['club_id'], ['nombre' => $s['nombre'], 'nacimiento' => (string) $s['nacimiento'], 'equipo_id' => (string) $s['equipo_id']]);
        }
        $this->ejecutar("UPDATE sportiva_solicitudes SET estado = 'aprobada', resuelto_por = ?, resuelto_en = ? WHERE id = ?", [$por, Tiempo::sello(), $id]);
        return $res;
    }

    public function rechazar(string $id, string $por): void
    {
        $this->ejecutar("UPDATE sportiva_solicitudes SET estado = 'rechazada', resuelto_por = ?, resuelto_en = ? WHERE id = ? AND estado = 'pendiente'", [$por, Tiempo::sello(), $id]);
    }

    public function cancelar(string $id, string $titular): void
    {
        $this->ejecutar("UPDATE sportiva_solicitudes SET estado = 'cancelada', resuelto_en = ? WHERE id = ? AND solicitante_id = ? AND estado = 'pendiente'", [Tiempo::sello(), $id, $titular]);
    }

    /** Correos de quienes deben aprobar para un equipo: staff del equipo + encargados del club. @return array<int, array<string, mixed>> */
    public function aprobadores(string $club, ?string $equipo): array
    {
        return $this->filas(
            "SELECT DISTINCT p.id, p.email, p.nombre, p.idioma FROM sportiva_personas p WHERE p.id IN (
               SELECT persona_id FROM sportiva_membresias WHERE club_id = ? AND rol = 'encargado' AND estado = 'activo'
               UNION SELECT persona_id FROM sportiva_equipo_staff WHERE equipo_id = ?)",
            [$club, $equipo ?? '']
        );
    }
}
