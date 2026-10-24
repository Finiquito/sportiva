<?php
declare(strict_types=1);

namespace TypeDock\Plugin\Sportiva;

/** Qué comparte cada persona, exportación de sus datos y eliminación de la cuenta (RGPD). */
final class PrivacidadService extends Repo
{
    /** @return array{telefono: int, correo: int, directorio: int} */
    public function get(string $persona): array
    {
        $r = $this->fila('SELECT telefono, correo, directorio FROM sportiva_privacidad WHERE persona_id = ?', [$persona]);
        return $r ? array_map('intval', $r) : ['telefono' => 0, 'correo' => 0, 'directorio' => 0];
    }

    public function guardar(string $persona, bool $telefono, bool $correo, bool $directorio): void
    {
        $this->ejecutar('DELETE FROM sportiva_privacidad WHERE persona_id = ?', [$persona]);
        $this->ejecutar('INSERT INTO sportiva_privacidad (persona_id, telefono, correo, directorio, updated_at) VALUES (?, ?, ?, ?, ?)', [$persona, (int) $telefono, (int) $correo, (int) $directorio, Tiempo::sello()]);
    }

    /**
     * Familias de los equipos de mis hijos que aceptaron aparecer en el directorio (solo lo que compartieron).
     * @return array<int, array<string, mixed>>
     */
    public function familiasDelEquipo(string $persona, string $club): array
    {
        $mios = array_column($this->filas('SELECT DISTINCT j.equipo_id FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id WHERE aj.persona_id = ? AND j.club_id = ?', [$persona, $club]), 'equipo_id');
        if ($mios === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($mios), '?'));
        $por = [];
        foreach ($this->filas(
            "SELECT p.id, p.nombre, p.email, p.telefono, pr.telefono AS ver_tel, pr.correo AS ver_correo, j.nombre AS hijo, e.nombre AS equipo
             FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id JOIN sportiva_personas p ON p.id = aj.persona_id
             JOIN sportiva_privacidad pr ON pr.persona_id = p.id JOIN sportiva_equipos e ON e.id = j.equipo_id
             WHERE pr.directorio = 1 AND p.id <> ? AND j.club_id = ? AND j.equipo_id IN ({$in}) ORDER BY p.nombre, j.nombre",
            array_merge([$persona, $club], $mios)
        ) as $f) {
            $por[$f['id']] ??= ['id' => $f['id'], 'nombre' => $f['nombre'], 'telefono' => $f['ver_tel'] ? $f['telefono'] : null, 'email' => $f['ver_correo'] ? $f['email'] : null, 'hijos' => [], 'equipos' => []];
            $por[$f['id']]['hijos'][] = $f['hijo'];
            $por[$f['id']]['equipos'][$f['equipo']] = $f['equipo'];
        }
        return array_values($por);
    }

    /** Todo lo que u330 guarda de la persona, listo para descargar. @return array<string, mixed> */
    public function exportar(string $persona): array
    {
        $p = (new PersonaService($this->pdo))->buscar($persona) ?? [];
        $clubes = (new PersonaService($this->pdo))->membresias($persona);
        $hijos = [];
        foreach ($this->filas('SELECT j.nombre, j.nacimiento, e.nombre AS equipo, c.nombre AS club, aj.titular FROM sportiva_apoderados_jugadores aj JOIN sportiva_jugadores j ON j.id = aj.jugador_id JOIN sportiva_equipos e ON e.id = j.equipo_id JOIN sportiva_clubes c ON c.id = j.club_id WHERE aj.persona_id = ?', [$persona]) as $h) {
            $hijos[] = $h;
        }
        return [
            'generado' => Tiempo::sello(),
            'persona' => ['nombre' => $p['nombre'] ?? null, 'correo' => $p['email'] ?? null, 'telefono' => $p['telefono'] ?? null, 'idioma' => $p['idioma'] ?? null, 'creada' => $p['created_at'] ?? null],
            'clubes' => array_map(fn($m) => ['club' => $m['club'], 'rol' => $m['rol']], $clubes),
            'hijos' => $hijos,
            'comparte' => $this->get($persona),
            'publicaciones' => $this->filas('SELECT titulo, texto, created_at FROM sportiva_posts WHERE autor_id = ? ORDER BY created_at', [$persona]),
            'comentarios' => $this->filas('SELECT texto, created_at FROM sportiva_comentarios WHERE persona_id = ? ORDER BY created_at', [$persona]),
            'mensajes' => $this->filas('SELECT texto, created_at FROM sportiva_mensajes WHERE persona_id = ? ORDER BY created_at', [$persona]),
            'avisos_preferencias' => $this->filas('SELECT * FROM sportiva_avisos_prefs WHERE persona_id = ?', [$persona]),
        ];
    }

    /** Motivo por el que no se puede eliminar todavía, o null. */
    public function impedimento(string $persona): ?string
    {
        if ($this->fila('SELECT id FROM sportiva_apoderados_jugadores WHERE persona_id = ? AND titular = 1', [$persona]) !== null) {
            return 'perfil.error.titular';
        }
        foreach ($this->filas("SELECT DISTINCT club_id FROM sportiva_membresias WHERE persona_id = ? AND rol = 'directiva'", [$persona]) as $c) {
            if ((int) $this->fila("SELECT COUNT(DISTINCT persona_id) AS n FROM sportiva_membresias WHERE club_id = ? AND rol = 'directiva' AND estado = 'activo'", [$c['club_id']])['n'] <= 1) {
                return 'perfil.error.unica_directiva';
            }
        }
        return null;
    }

    /** Anonimiza la persona (lo que escribió queda sin nombre) y borra su acceso, vínculos, avisos y preferencias. */
    public function eliminar(string $persona): bool
    {
        if ($this->impedimento($persona) !== null) {
            return false;
        }
        foreach (['sportiva_membresias', 'sportiva_apoderados_jugadores', 'sportiva_equipo_staff', 'sportiva_codigos', 'sportiva_intentos', 'sportiva_avisos', 'sportiva_avisos_prefs', 'sportiva_push_subs', 'sportiva_privacidad', 'sportiva_recordatorios'] as $t) {
            $this->ejecutar("DELETE FROM {$t} WHERE persona_id = ?", [$persona]);
        }
        $this->ejecutar('UPDATE sportiva_jugadores SET persona_id = NULL WHERE persona_id = ?', [$persona]);
        $this->ejecutar('UPDATE sportiva_personas SET nombre = ?, email = ?, telefono = NULL, idioma = NULL WHERE id = ?', ['—', 'eliminada-' . $persona . '@u330.invalid', $persona]);
        return true;
    }
}
