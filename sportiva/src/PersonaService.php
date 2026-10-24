<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

final class PersonaService extends Repo
{
    public const ROLES = ['apoderado', 'entrenador', 'encargado', 'directiva', 'jugador'];

    public static function correo(string $e): string
    {
        return strtolower(trim($e));
    }

    public function porCorreo(string $email): ?array
    {
        return $this->fila('SELECT * FROM sportiva_personas WHERE email = ?', [self::correo($email)]);
    }

    public function buscar(string $id): ?array
    {
        return $this->fila('SELECT * FROM sportiva_personas WHERE id = ?', [$id]);
    }

    public function crear(string $email, string $nombre, ?string $idioma = null, ?string $tel = null): string
    {
        $id = $this->id();
        $this->ejecutar('INSERT INTO sportiva_personas (id, email, nombre, telefono, idioma, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$id, self::correo($email), trim($nombre), $tel, $idioma, Tiempo::sello()]);
        return $id;
    }

    public function cambiarIdioma(string $id, string $idioma): void
    {
        $this->ejecutar('UPDATE sportiva_personas SET idioma = ? WHERE id = ?', [$idioma, $id]);
    }

    public function dar(string $persona, string $club, string $rol): void
    {
        if (!in_array($rol, self::ROLES, true)) {
            throw new \InvalidArgumentException('Rol desconocido: ' . $rol);
        }
        if ($this->fila('SELECT id FROM sportiva_membresias WHERE persona_id = ? AND club_id = ? AND rol = ?', [$persona, $club, $rol]) === null) {
            $this->ejecutar('INSERT INTO sportiva_membresias (id, persona_id, club_id, rol, estado, created_at) VALUES (?, ?, ?, ?, ?, ?)', [$this->id(), $persona, $club, $rol, 'activo', Tiempo::sello()]);
        }
    }

    /** Membresías activas con los datos del club, ordenadas por club. @return array<int, array<string, mixed>> */
    public function membresias(string $persona): array
    {
        return $this->filas(
            "SELECT m.id, m.club_id, m.rol, c.nombre AS club, c.slug, c.idioma AS club_idioma, c.zona
             FROM sportiva_membresias m JOIN sportiva_clubes c ON c.id = m.club_id
             WHERE m.persona_id = ? AND m.estado = 'activo' ORDER BY c.nombre, m.rol",
            [$persona]
        );
    }

    /** Hijos de un apoderado en un club. @return array<int, array<string, mixed>> */
    public function hijos(string $persona, string $club): array
    {
        return $this->filas(
            'SELECT j.*, e.nombre AS equipo, (SELECT f.version FROM sportiva_fotos f WHERE f.jugador_id = j.id) AS foto FROM sportiva_apoderados_jugadores aj
             JOIN sportiva_jugadores j ON j.id = aj.jugador_id JOIN sportiva_equipos e ON e.id = j.equipo_id
             WHERE aj.persona_id = ? AND j.club_id = ? ORDER BY j.nombre',
            [$persona, $club]
        );
    }

    public function jugadorDe(string $persona, string $club): ?array
    {
        return $this->fila('SELECT j.*, e.nombre AS equipo FROM sportiva_jugadores j JOIN sportiva_equipos e ON e.id = j.equipo_id WHERE j.persona_id = ? AND j.club_id = ?', [$persona, $club]);
    }

    /** Equipos que entrena una persona en un club. @return array<int, array<string, mixed>> */
    public function equiposDeStaff(string $persona, string $club): array
    {
        return $this->filas(
            'SELECT e.*, s.rol AS rol_staff FROM sportiva_equipo_staff s JOIN sportiva_equipos e ON e.id = s.equipo_id
             WHERE s.persona_id = ? AND e.club_id = ? AND e.archivado = 0 ORDER BY e.nombre',
            [$persona, $club]
        );
    }

    public function crearJugador(string $club, string $equipo, string $nombre, ?string $nacimiento = null, ?string $persona = null): string
    {
        $id = $this->id();
        $this->ejecutar('INSERT INTO sportiva_jugadores (id, club_id, equipo_id, nombre, nacimiento, persona_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)', [$id, $club, $equipo, trim($nombre), $nacimiento, $persona, Tiempo::sello()]);
        return $id;
    }

    public function vincularHijo(string $persona, string $jugador, bool $titular = true): void
    {
        if ($this->fila('SELECT id FROM sportiva_apoderados_jugadores WHERE persona_id = ? AND jugador_id = ?', [$persona, $jugador]) === null) {
            $this->ejecutar('INSERT INTO sportiva_apoderados_jugadores (id, persona_id, jugador_id, titular) VALUES (?, ?, ?, ?)', [$this->id(), $persona, $jugador, (int) $titular]);
        }
    }

    public function asignarStaff(string $equipo, string $persona, string $rol = 'entrenador'): void
    {
        if ($this->fila('SELECT id FROM sportiva_equipo_staff WHERE equipo_id = ? AND persona_id = ?', [$equipo, $persona]) === null) {
            $this->ejecutar('INSERT INTO sportiva_equipo_staff (id, equipo_id, persona_id, rol) VALUES (?, ?, ?, ?)', [$this->id(), $equipo, $persona, $rol]);
        }
    }

    // ---- panel de la directiva ----------------------------------------------------------------

    /**
     * Personas con algún rol en el club, con sus roles, sus hijos y si ya entraron alguna vez.
     * @param string $grupo todos | entrenadores | familias | directiva @return array<int, array<string, mixed>>
     */
    public function delClub(string $club, string $grupo = 'todos', string $q = ''): array
    {
        $roles = ['entrenadores' => ['entrenador', 'encargado'], 'familias' => ['apoderado'], 'directiva' => ['directiva']][$grupo] ?? self::ROLES;
        $in = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT p.id, p.nombre, p.email, p.telefono, m.rol FROM sportiva_membresias m JOIN sportiva_personas p ON p.id = m.persona_id
                WHERE m.club_id = ? AND m.estado = 'activo' AND m.rol IN ({$in})";
        $par = array_merge([$club], $roles);
        if (trim($q) !== '') {
            $sql .= ' AND (LOWER(p.nombre) LIKE ? OR LOWER(p.email) LIKE ?)';
            $like = '%' . mb_strtolower(trim($q)) . '%';
            $par[] = $like;
            $par[] = $like;
        }
        $por = [];
        foreach ($this->filas($sql . ' ORDER BY p.nombre', $par) as $f) {
            $por[$f['id']] ??= ['id' => $f['id'], 'nombre' => $f['nombre'], 'email' => $f['email'], 'telefono' => $f['telefono'], 'roles' => []];
            $por[$f['id']]['roles'][] = $f['rol'];
        }
        foreach ($por as &$p) {
            $p['hijos'] = in_array('apoderado', $p['roles'], true) ? array_column($this->hijos($p['id'], $club), 'nombre') : [];
            $p['activo'] = $this->haEntrado($p['id']);
        }
        return array_values($por);
    }

    /** ¿Alguna vez usó un código para entrar? */
    public function haEntrado(string $persona): bool
    {
        return $this->fila('SELECT id FROM sportiva_codigos WHERE persona_id = ? AND usado_en IS NOT NULL', [$persona]) !== null;
    }

    /** @return array<int, string> */
    public function rolesEn(string $persona, string $club): array
    {
        return array_column($this->filas("SELECT rol FROM sportiva_membresias WHERE persona_id = ? AND club_id = ? AND estado = 'activo' ORDER BY rol", [$persona, $club]), 'rol');
    }

    public function quitarRol(string $persona, string $club, string $rol): void
    {
        $this->ejecutar('DELETE FROM sportiva_membresias WHERE persona_id = ? AND club_id = ? AND rol = ?', [$persona, $club, $rol]);
        if (in_array($rol, ['entrenador', 'encargado'], true) && !array_intersect(['entrenador', 'encargado'], $this->rolesEn($persona, $club))) {
            $this->ejecutar('DELETE FROM sportiva_equipo_staff WHERE persona_id = ? AND equipo_id IN (SELECT id FROM sportiva_equipos WHERE club_id = ?)', [$persona, $club]);
        }
    }

    /** Equipos que entrena (o apoya) una persona en el club. @return array<int, array<string, mixed>> */
    public function equiposStaff(string $persona, string $club): array
    {
        return $this->filas('SELECT e.id, e.nombre, s.rol FROM sportiva_equipo_staff s JOIN sportiva_equipos e ON e.id = s.equipo_id WHERE s.persona_id = ? AND e.club_id = ? ORDER BY e.nombre', [$persona, $club]);
    }

    public function quitarStaff(string $equipo, string $persona): void
    {
        $this->ejecutar('DELETE FROM sportiva_equipo_staff WHERE equipo_id = ? AND persona_id = ?', [$equipo, $persona]);
    }

    public function actualizarDatos(string $persona, string $nombre, ?string $telefono): void
    {
        $this->ejecutar('UPDATE sportiva_personas SET nombre = ?, telefono = ? WHERE id = ?', [trim($nombre), $telefono !== null && trim($telefono) !== '' ? trim($telefono) : null, $persona]);
    }

    /**
     * Contactos para el directorio: familias (por hijo) y cuerpo técnico de los equipos pedidos.
     * @param array<int, string> $equipos @return array{familias: array<int, array<string, mixed>>, staff: array<int, array<string, mixed>>}
     */
    public function directorio(string $club, array $equipos, string $q = ''): array
    {
        if ($equipos === []) {
            return ['familias' => [], 'staff' => []];
        }
        $in = implode(',', array_fill(0, count($equipos), '?'));
        $like = '%' . mb_strtolower(trim($q)) . '%';
        $filtro = trim($q) !== '' ? ' AND (LOWER(p.nombre) LIKE ? OR LOWER(p.email) LIKE ? OR LOWER(j.nombre) LIKE ?)' : '';
        $par = array_merge([$club], $equipos, trim($q) !== '' ? [$like, $like, $like] : []);
        $fam = [];
        foreach ($this->filas("SELECT p.id, p.nombre, p.email, p.telefono, j.nombre AS hijo, e.nombre AS equipo FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id JOIN sportiva_personas p ON p.id = aj.persona_id JOIN sportiva_equipos e ON e.id = j.equipo_id WHERE j.club_id = ? AND j.equipo_id IN ({$in}){$filtro} ORDER BY p.nombre, j.nombre", $par) as $f) {
            $fam[$f['id']] ??= ['id' => $f['id'], 'nombre' => $f['nombre'], 'email' => $f['email'], 'telefono' => $f['telefono'], 'hijos' => [], 'equipos' => []];
            $fam[$f['id']]['hijos'][] = $f['hijo'];
            $fam[$f['id']]['equipos'][$f['equipo']] = $f['equipo'];
        }
        $filtroS = trim($q) !== '' ? ' AND (LOWER(p.nombre) LIKE ? OR LOWER(p.email) LIKE ?)' : '';
        $parS = array_merge($equipos, trim($q) !== '' ? [$like, $like] : []);
        $staff = [];
        foreach ($this->filas("SELECT p.id, p.nombre, p.email, p.telefono, s.rol, e.nombre AS equipo FROM sportiva_equipo_staff s JOIN sportiva_personas p ON p.id = s.persona_id JOIN sportiva_equipos e ON e.id = s.equipo_id WHERE s.equipo_id IN ({$in}){$filtroS} ORDER BY p.nombre", $parS) as $f) {
            $staff[$f['id']] ??= ['id' => $f['id'], 'nombre' => $f['nombre'], 'email' => $f['email'], 'telefono' => $f['telefono'], 'rol' => $f['rol'], 'equipos' => []];
            $staff[$f['id']]['equipos'][$f['equipo']] = $f['equipo'];
        }
        return ['familias' => array_values($fam), 'staff' => array_values($staff)];
    }

    /** Solo dígitos y «+», para tel: y WhatsApp. */
    public static function telLimpio(?string $t): string
    {
        return preg_replace('/[^0-9+]/', '', (string) $t) ?? '';
    }
}
