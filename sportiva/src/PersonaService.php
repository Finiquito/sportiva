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
            'SELECT j.*, e.nombre AS equipo, (SELECT a.avatar FROM sportiva_avatares a WHERE a.jugador_id = j.id) AS avatar FROM sportiva_apoderados_jugadores aj
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
}
